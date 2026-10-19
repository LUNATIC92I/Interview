<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Models\Domain;
use App\Models\Question;
use App\Models\Rubric;
use App\Services\Evaluation\EvaluationInput;
use App\Services\Evaluation\EvaluationResult;
use App\Services\Evaluation\EvaluationUnavailableException;
use App\Services\Evaluation\EvaluatorInterface;
use App\Services\Evaluation\LlmEvaluator;
use App\Services\Evaluation\RuleBasedEvaluator;

/**
 * Chooses the evaluator, builds its input from the database and stores the result.
 * The optional language model is used only when configured, enabled in settings and within the
 * per user daily quota; otherwise, or on any failure, the rule based engine is used and the
 * report states which engine produced the evaluation.
 */
final class EvaluationService
{
    private static ?EvaluatorInterface $llmOverride = null;

    /** Inject a language model evaluator (tests). */
    public static function setLlmEvaluator(?EvaluatorInterface $evaluator): void
    {
        self::$llmOverride = $evaluator;
    }

    public static function llmAvailable(): bool
    {
        if (self::$llmOverride !== null) {
            return true;
        }
        $provider = (string) Config::get('ai.provider', '');
        return in_array($provider, ['anthropic', 'openai_compatible'], true)
            && (string) Config::get('ai.api_key', '') !== ''
            && Settings::bool('ai_evaluation_enabled');
    }

    private static function llm(): ?EvaluatorInterface
    {
        if (self::$llmOverride !== null) {
            return self::$llmOverride;
        }
        if (!self::llmAvailable()) {
            return null;
        }
        return new LlmEvaluator(
            (string) Config::get('ai.provider'),
            (string) Config::get('ai.api_key'),
            (string) Config::get('ai.model'),
            (int) Config::get('ai.timeout', 45),
            (string) Config::get('ai.base_url', ''),
            (string) Config::get('ai.effort', 'medium'),
        );
    }

    public static function buildInput(array $answer, array $session): EvaluationInput
    {
        $locale = $session['locale'];
        $q = Question::find((int) $answer['question_id'], $locale);
        if (!$q) {
            throw new \RuntimeException('Question not found');
        }
        $criteria = array_map(static fn ($c) => [
            'code' => $c['code'],
            'evaluator' => $c['evaluator'],
            'weight' => (int) $c['weight'],
            'name' => $locale === 'fr' ? $c['name_fr'] : $c['name_en'],
        ], Rubric::criteria((int) $q['rubric_id']));
        $questionText = $q['body'];
        $expected = $q['expected_elements'];
        if ($answer['followup_id']) {
            $f = Question::followup((int) $answer['followup_id'], $locale);
            $questionText = ($f['body'] ?? '') . "\n(" . $q['body'] . ')';
        }
        $profession = Database::fetch(
            'SELECT COALESCE(t1.name, t2.name) AS name FROM professions p
             LEFT JOIN profession_translations t1 ON t1.profession_id = p.id AND t1.locale = :l
             LEFT JOIN profession_translations t2 ON t2.profession_id = p.id AND t2.locale = :o WHERE p.id = :id',
            ['id' => $session['profession_id'], 'l' => $locale, 'o' => $locale === 'fr' ? 'en' : 'fr']
        );
        return new EvaluationInput(
            answer: (string) ($answer['answer_text'] ?? ''),
            locale: $locale,
            questionType: $q['question_type'],
            questionText: (string) $questionText,
            expectedElements: $expected,
            keywords: $q['keyword_list'],
            criteria: $criteria,
            skillIds: array_map(static fn ($s) => (int) $s['id'], Question::skills((int) $q['id'])),
            recommendedSeconds: (int) $q['recommended_seconds'],
            professionName: (string) ($profession['name'] ?? ''),
            levelLabel: Domain::levelCode((int) $session['experience_level']),
        );
    }

    public static function evaluateAnswer(int $answerId): EvaluationResult
    {
        $answer = Database::fetch('SELECT * FROM interview_answers WHERE id = :id', ['id' => $answerId]);
        $session = Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $answer['session_id']]);
        $input = self::buildInput($answer, $session);
        $rubricId = (int) Database::fetchColumn('SELECT rubric_id FROM questions WHERE id = :id', ['id' => $answer['question_id']]);

        $result = null;
        $fallback = false;
        $llm = trim($input->answer) !== '' ? self::llm() : null;
        if ($llm !== null) {
            $quota = (int) Config::get('ai.max_calls_per_user_per_day', 40);
            if (RateLimiter::attempt('ai:' . $session['user_id'] . ':' . gmdate('Ymd'), $quota, 86400)) {
                try {
                    $result = $llm->evaluate($input);
                } catch (EvaluationUnavailableException $e) {
                    Logger::info('LLM evaluation unavailable, rule engine used', ['reason' => $e->getMessage()]);
                    $fallback = true;
                }
            } else {
                $fallback = true;
            }
        }
        $result ??= (new RuleBasedEvaluator())->evaluate($input);

        Database::transaction(static function () use ($answerId, $rubricId, $result, $fallback) {
            Database::delete('answer_evaluations', ['answer_id' => $answerId]);
            Database::insert('answer_evaluations', [
                'answer_id' => $answerId,
                'rubric_id' => $rubricId,
                'score' => $result->score,
                'criteria_scores' => json_encode($result->criteria, JSON_UNESCAPED_UNICODE),
                'strengths' => json_encode($result->strengths, JSON_UNESCAPED_UNICODE),
                'gaps' => json_encode($result->gaps, JSON_UNESCAPED_UNICODE),
                'skills_demonstrated' => json_encode($result->skillsDemonstrated),
                'missing_elements' => json_encode($result->missingElements),
                'recommendations' => json_encode($result->recommendations, JSON_UNESCAPED_UNICODE),
                'next_tip' => json_encode($result->nextTip, JSON_UNESCAPED_UNICODE),
                'evaluator' => $result->evaluator,
                'confidence' => $result->confidence,
                'model_name' => $fallback ? RuleBasedEvaluator::VERSION . '+fallback' : $result->modelName,
                'llm_feedback' => $result->llmFeedback,
                'created_at' => now(),
            ]);
        });
        return $result;
    }
}
