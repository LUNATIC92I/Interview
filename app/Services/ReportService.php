<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Domain;
use App\Models\Question;
use App\Models\Rubric;
use App\Models\Skill;

/**
 * Turns stored evaluations into readable feedback and session reports in the active interface language.
 */
final class ReportService
{
    /** Resolve a stored feedback item: translation key with params, or literal text from a language model. */
    public static function text(array $item): string
    {
        if (isset($item['text'])) {
            return (string) $item['text'];
        }
        return isset($item['key']) ? t((string) $item['key'], (array) ($item['params'] ?? [])) : '';
    }

    public static function evaluatorLabel(array $evaluation): string
    {
        if ($evaluation['evaluator'] === 'llm') {
            return t('eval.engine.llm', ['model' => (string) $evaluation['model_name']]);
        }
        return str_contains((string) $evaluation['model_name'], 'fallback') ? t('eval.engine.rules_fallback') : t('eval.engine.rules');
    }

    /** Feedback for one answer, ready for display. */
    public static function answerFeedback(int $answerId, ?string $locale = null, ?array $criteriaByRubric = null): ?array
    {
        $ev = Database::fetch('SELECT e.*, a.question_id, a.status FROM answer_evaluations e JOIN interview_answers a ON a.id = e.answer_id WHERE e.answer_id = :id', ['id' => $answerId]);
        if (!$ev) {
            return null;
        }
        $q = Question::find((int) $ev['question_id'], locale());
        $criteriaByRubric ??= Rubric::criteriaByRubric();
        $rubricCriteria = $criteriaByRubric[(int) $ev['rubric_id']] ?? [];
        $criteria = [];
        foreach (json_list($ev['criteria_scores']) as $c) {
            $row = $rubricCriteria[$c['code']] ?? null;
            $criteria[] = [
                'code' => $c['code'],
                'evaluator' => $c['evaluator'],
                'name' => $row ? loc($row) : t('criteria.' . $c['evaluator']),
                'description' => $row ? loc($row, 'description') : '',
                'weight' => (int) $c['weight'],
                'points' => (float) $c['points'],
                'ratio' => (float) $c['ratio'],
            ];
        }
        $expected = $q['expected_elements'] ?? [];
        $missing = [];
        foreach (json_list($ev['missing_elements']) as $i) {
            if (isset($expected[$i])) {
                $missing[] = $expected[$i]['label'];
            }
        }
        $skills = Skill::byIds(json_list($ev['skills_demonstrated']));
        $score = (float) $ev['score'];
        return [
            'score' => $score,
            'score_label' => score_label($score),
            'score_class' => score_class($score),
            'criteria' => $criteria,
            'strengths' => array_map([self::class, 'text'], json_list($ev['strengths'])),
            'gaps' => array_map([self::class, 'text'], json_list($ev['gaps'])),
            'recommendations' => array_map([self::class, 'text'], json_list($ev['recommendations'])),
            'next_tip' => self::text(json_list($ev['next_tip'])),
            'missing_elements' => $missing,
            'expected_elements' => array_map(static fn ($e) => $e['label'], $expected),
            'skills' => array_map(static fn ($s) => loc($s), array_values($skills)),
            'example_answer' => (string) ($q['example_answer'] ?? ''),
            'common_mistakes' => $q['common_mistakes'] ?? [],
            'evaluation_criteria' => (string) ($q['evaluation_criteria'] ?? ''),
            'evaluator' => $ev['evaluator'],
            'evaluator_label' => self::evaluatorLabel($ev),
            'confidence' => $ev['confidence'],
            'confidence_label' => t('eval.confidence.' . $ev['confidence']),
            'summary' => (string) ($ev['llm_feedback'] ?? ''),
            'status' => $ev['status'],
        ];
    }

    /** Complete post interview report. */
    public static function build(array $session): array
    {
        $profession = \App\Models\Profession::find((int) $session['profession_id']);
        $scenario = $session['scenario_id'] ? \App\Models\Scenario::find((int) $session['scenario_id']) : null;
        $criteriaByRubric = Rubric::criteriaByRubric();
        $answers = [];
        $criteriaAgg = [];
        $strengthCount = $gapCount = $recoCount = [];
        $totalTime = 0;
        foreach (InterviewEngine::answers((int) $session['id']) as $a) {
            $q = Question::find((int) $a['question_id'], locale());
            $body = $q['body'] ?? '';
            if ($a['followup_id']) {
                $f = Question::followup((int) $a['followup_id'], locale());
                $body = $f['body'] ?? $body;
            }
            $feedback = self::answerFeedback((int) $a['id'], null, $criteriaByRubric);
            $totalTime += (int) $a['time_spent_seconds'];
            if ($feedback) {
                foreach ($feedback['criteria'] as $c) {
                    $criteriaAgg[$c['evaluator']][] = $c['ratio'] * 100;
                }
                foreach ($feedback['strengths'] as $s) {
                    $strengthCount[$s] = ($strengthCount[$s] ?? 0) + 1;
                }
                foreach ($feedback['gaps'] as $s) {
                    $gapCount[$s] = ($gapCount[$s] ?? 0) + 1;
                }
                foreach ($feedback['recommendations'] as $s) {
                    $recoCount[$s] = ($recoCount[$s] ?? 0) + 1;
                }
            }
            $answers[] = [
                'position' => (int) $a['position'],
                'is_followup' => $a['followup_id'] !== null,
                'type' => $q['question_type'] ?? '',
                'question' => $body,
                'is_fallback' => (bool) ($q['is_fallback'] ?? false),
                'answer' => (string) ($a['answer_text'] ?? ''),
                'status' => $a['status'],
                'time_spent' => (int) $a['time_spent_seconds'],
                'word_count' => (int) $a['word_count'],
                'feedback' => $feedback,
            ];
        }
        $criteria = [];
        foreach ($criteriaAgg as $evaluator => $values) {
            $criteria[] = ['evaluator' => $evaluator, 'name' => t('criteria.' . $evaluator), 'score' => round(array_sum($values) / count($values))];
        }
        usort($criteria, static fn ($a, $b) => $b['score'] <=> $a['score']);

        $skills = array_map(static fn ($r) => ['name' => loc($r), 'score' => round((float) $r['score'])], Database::fetchAll(
            'SELECT s.name_fr, s.name_en, AVG(e.score) AS score FROM interview_answers a
             JOIN answer_evaluations e ON e.answer_id = a.id
             JOIN question_skills qs ON qs.question_id = a.question_id
             JOIN skills s ON s.id = qs.skill_id
             WHERE a.session_id = :s GROUP BY s.id, s.name_fr, s.name_en ORDER BY score DESC',
            ['s' => $session['id']]
        ));
        arsort($strengthCount);
        arsort($gapCount);
        arsort($recoCount);
        $evaluators = array_unique(array_filter(array_map(static fn ($a) => $a['feedback']['evaluator'] ?? null, $answers)));

        return [
            'session' => $session,
            'profession' => $profession,
            'scenario' => $scenario,
            'score' => $session['overall_score'] !== null ? (float) $session['overall_score'] : null,
            'answers' => $answers,
            'criteria' => $criteria,
            'skills' => $skills,
            'strengths' => array_slice(array_keys($strengthCount), 0, 5),
            'improvements' => array_slice(array_keys($gapCount), 0, 5),
            'priorities' => array_slice(array_keys($recoCount), 0, 3),
            'answer_time' => $totalTime,
            'elapsed' => InterviewEngine::elapsedSeconds($session),
            'answered' => count(array_filter($answers, static fn ($a) => in_array($a['status'], ['answered', 'timed_out'], true))),
            'skipped' => count(array_filter($answers, static fn ($a) => $a['status'] === 'skipped')),
            'uses_llm' => in_array('llm', $evaluators, true),
            'meta' => [
                'date' => format_date($session['completed_at'] ?? $session['started_at'], true),
                'profession' => $profession['name'] ?? '',
                'type' => t('interview_types.' . $session['interview_type']),
                'mode' => t('modes.' . $session['mode'] . '.name'),
                'level' => Domain::levelLabel((int) $session['experience_level']),
                'language' => t('languages.' . $session['locale']),
                'difficulty' => \App\Models\Difficulty::label((int) $session['difficulty']),
            ],
        ];
    }
}
