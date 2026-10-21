<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Domain;

/**
 * Validation and persistence of questions with their translations, skills, examples and follow ups.
 * Used by the back office form and by the CSV import so both apply exactly the same rules.
 */
final class QuestionService
{
    /** Parse "label | kw1, kw2" lines into expected elements. */
    public static function parseElements(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$label, $kw] = array_pad(explode('|', $line, 2), 2, '');
            $out[] = ['label' => mb_substr(trim($label), 0, 300), 'keywords' => array_values(array_filter(array_map('trim', explode(',', $kw))))];
        }
        return $out;
    }

    public static function elementsToText(array $elements): string
    {
        return implode("\n", array_map(static fn ($e) => $e['label'] . ($e['keywords'] ? ' | ' . implode(', ', $e['keywords']) : ''), $elements));
    }

    public static function parseLines(string $text): array
    {
        return array_values(array_filter(array_map(static fn ($l) => mb_substr(trim($l), 0, 500), preg_split('/\R/u', $text) ?: [])));
    }

    /** @return array<string, string> field => message */
    public static function validate(array $d, ?int $id = null): array
    {
        $e = [];
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $d['code']) || mb_strlen((string) $d['code']) > 100) {
            $e['code'] = t('validation.slug');
        } elseif (Database::fetchColumn('SELECT id FROM questions WHERE code = :c AND id <> :id', ['c' => $d['code'], 'id' => $id ?? 0])) {
            $e['code'] = t('validation.unique');
        }
        if (!in_array($d['question_type'], Domain::QUESTION_TYPES, true)) {
            $e['question_type'] = t('validation.in');
        }
        foreach (['level_min', 'level_max', 'difficulty'] as $f) {
            if ((int) $d[$f] < 1 || (int) $d[$f] > 5) {
                $e[$f] = t('validation.between', ['min' => 1, 'max' => 5]);
            }
        }
        if ((int) $d['level_min'] > (int) $d['level_max']) {
            $e['level_max'] = t('admin.questions.level_order');
        }
        if ((int) $d['recommended_seconds'] < 30 || (int) $d['recommended_seconds'] > 1800) {
            $e['recommended_seconds'] = t('validation.between', ['min' => 30, 'max' => 1800]);
        }
        if (!Database::fetchColumn('SELECT 1 FROM evaluation_rubrics WHERE id = :id', ['id' => (int) $d['rubric_id']])) {
            $e['rubric_id'] = t('validation.in');
        }
        if ($d['profession_id'] !== null && !Database::fetchColumn('SELECT 1 FROM professions WHERE id = :id', ['id' => (int) $d['profession_id']])) {
            $e['profession_id'] = t('validation.in');
        }
        if ($d['category_id'] !== null && !Database::fetchColumn('SELECT 1 FROM profession_categories WHERE id = :id', ['id' => (int) $d['category_id']])) {
            $e['category_id'] = t('validation.in');
        }
        $hasBody = false;
        foreach (['fr', 'en'] as $loc) {
            $t = $d['translations'][$loc] ?? [];
            if (trim((string) ($t['body'] ?? '')) !== '') {
                $hasBody = true;
                if (mb_strlen((string) $t['body']) > 2000) {
                    $e['body_' . $loc] = t('validation.max', ['max' => 2000]);
                }
            }
        }
        if (!$hasBody) {
            $e['body_fr'] = t('admin.questions.body_required');
        }
        foreach ($d['followups'] ?? [] as $f) {
            if (!in_array($f['trigger_type'], Domain::FOLLOWUP_TRIGGERS, true)) {
                $e['followups'] = t('validation.in');
            }
        }
        return $e;
    }

    public static function save(?int $id, array $d): int
    {
        return Database::transaction(static function () use ($id, $d) {
            $row = [
                'code' => $d['code'],
                'profession_id' => $d['profession_id'],
                'category_id' => $d['category_id'],
                'question_type' => $d['question_type'],
                'level_min' => (int) $d['level_min'],
                'level_max' => (int) $d['level_max'],
                'difficulty' => (int) $d['difficulty'],
                'rubric_id' => (int) $d['rubric_id'],
                'recommended_seconds' => (int) $d['recommended_seconds'],
                'is_active' => $d['is_active'] ? 1 : 0,
                'updated_at' => now(),
            ];
            if ($id === null) {
                $id = Database::insert('questions', $row + ['created_at' => now()]);
            } else {
                Database::update('questions', $row, ['id' => $id]);
            }
            foreach (['fr', 'en'] as $loc) {
                $t = $d['translations'][$loc] ?? [];
                if (trim((string) ($t['body'] ?? '')) === '') {
                    Database::delete('question_translations', ['question_id' => $id, 'locale' => $loc]);
                    Database::delete('answer_examples', ['question_id' => $id, 'locale' => $loc]);
                    continue;
                }
                Database::query(
                    'INSERT INTO question_translations (question_id, locale, body, guidance, evaluation_criteria, expected_elements, common_mistakes, keywords)
                     VALUES (:q, :l, :b, :g, :c, :e, :m, :k)
                     ON DUPLICATE KEY UPDATE body = VALUES(body), guidance = VALUES(guidance), evaluation_criteria = VALUES(evaluation_criteria),
                        expected_elements = VALUES(expected_elements), common_mistakes = VALUES(common_mistakes), keywords = VALUES(keywords)',
                    [
                        'q' => $id, 'l' => $loc, 'b' => trim((string) $t['body']),
                        'g' => trim((string) ($t['guidance'] ?? '')) ?: null,
                        'c' => trim((string) ($t['evaluation_criteria'] ?? '')) ?: null,
                        'e' => json_encode($t['expected_elements'] ?? [], JSON_UNESCAPED_UNICODE),
                        'm' => json_encode($t['common_mistakes'] ?? [], JSON_UNESCAPED_UNICODE),
                        'k' => mb_substr(trim((string) ($t['keywords'] ?? '')), 0, 500) ?: null,
                    ]
                );
                $example = trim((string) ($t['example_answer'] ?? ''));
                if ($example === '') {
                    Database::delete('answer_examples', ['question_id' => $id, 'locale' => $loc]);
                } else {
                    Database::query(
                        'INSERT INTO answer_examples (question_id, locale, body, created_at) VALUES (:q, :l, :b, :d)
                         ON DUPLICATE KEY UPDATE body = VALUES(body)',
                        ['q' => $id, 'l' => $loc, 'b' => $example, 'd' => now()]
                    );
                }
            }
            Database::delete('question_skills', ['question_id' => $id]);
            foreach (array_unique(array_map('intval', $d['skill_ids'] ?? [])) as $sid) {
                if (Database::fetchColumn('SELECT 1 FROM skills WHERE id = :id', ['id' => $sid])) {
                    Database::insert('question_skills', ['question_id' => $id, 'skill_id' => $sid]);
                }
            }
            if (array_key_exists('followups', $d) && $d['followups'] !== null) {
                self::saveFollowups($id, $d['followups']);
            }
            return $id;
        });
    }

    /** Follow ups already used in sessions are deactivated rather than deleted, to keep history intact. */
    private static function saveFollowups(int $questionId, array $followups): void
    {
        foreach (Database::fetchAll('SELECT id FROM question_followups WHERE question_id = :q', ['q' => $questionId]) as $old) {
            $used = Database::fetchColumn('SELECT 1 FROM interview_answers WHERE followup_id = :f LIMIT 1', ['f' => $old['id']]);
            if ($used) {
                Database::update('question_followups', ['is_active' => 0], ['id' => $old['id']]);
            } else {
                Database::delete('question_followups', ['id' => $old['id']]);
            }
        }
        foreach (array_values($followups) as $i => $f) {
            if (trim((string) $f['fr']) === '' && trim((string) $f['en']) === '') {
                continue;
            }
            $fid = Database::insert('question_followups', [
                'question_id' => $questionId, 'trigger_type' => $f['trigger_type'],
                'min_words' => max(0, (int) $f['min_words']), 'sort_order' => $i, 'is_active' => 1,
            ]);
            foreach (['fr', 'en'] as $loc) {
                if (trim((string) $f[$loc]) !== '') {
                    Database::insert('question_followup_translations', [
                        'followup_id' => $fid, 'locale' => $loc, 'body' => trim((string) $f[$loc]),
                        'trigger_keywords' => mb_substr(trim((string) ($f['kw_' . $loc] ?? '')), 0, 500) ?: null,
                    ]);
                }
            }
        }
    }

    /** Delete when never used, otherwise deactivate. Returns 'deleted' or 'deactivated'. */
    public static function delete(int $id): string
    {
        $used = (int) Database::fetchColumn('SELECT COUNT(*) FROM interview_answers WHERE question_id = :id', ['id' => $id])
            + (int) Database::fetchColumn('SELECT COUNT(*) FROM scenario_questions WHERE question_id = :id', ['id' => $id])
            + (int) Database::fetchColumn('SELECT COUNT(*) FROM content_reports WHERE question_id = :id', ['id' => $id]);
        if ($used > 0) {
            Database::update('questions', ['is_active' => 0, 'updated_at' => now()], ['id' => $id]);
            return 'deactivated';
        }
        Database::transaction(static function () use ($id) {
            Database::delete('question_followups', ['question_id' => $id]);
            Database::delete('questions', ['id' => $id]);
        });
        return 'deleted';
    }
}
