<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Translator;

final class Question
{
    /**
     * Load a question with its content in the requested locale. When that translation is missing the
     * other language is returned and flagged with is_fallback = 1 so the interface can say so.
     */
    public static function find(int $id, ?string $locale = null): ?array
    {
        $loc = $locale ?? Translator::locale();
        $row = Database::fetch(
            'SELECT q.*, r.code AS rubric_code,
                COALESCE(t1.body, t2.body) AS body, COALESCE(t1.guidance, t2.guidance) AS guidance,
                COALESCE(t1.evaluation_criteria, t2.evaluation_criteria) AS evaluation_criteria,
                COALESCE(t1.expected_elements, t2.expected_elements) AS expected_elements,
                COALESCE(t1.common_mistakes, t2.common_mistakes) AS common_mistakes,
                COALESCE(t1.keywords, t2.keywords) AS keywords,
                (t1.body IS NULL) AS is_fallback,
                COALESCE(e1.body, e2.body) AS example_answer
             FROM questions q
             JOIN evaluation_rubrics r ON r.id = q.rubric_id
             LEFT JOIN question_translations t1 ON t1.question_id = q.id AND t1.locale = :loc
             LEFT JOIN question_translations t2 ON t2.question_id = q.id AND t2.locale = :other
             LEFT JOIN answer_examples e1 ON e1.question_id = q.id AND e1.locale = :loc2
             LEFT JOIN answer_examples e2 ON e2.question_id = q.id AND e2.locale = :other2
             WHERE q.id = :id',
            ['id' => $id, 'loc' => $loc, 'other' => Translator::other($loc), 'loc2' => $loc, 'other2' => Translator::other($loc)]
        );
        if (!$row) {
            return null;
        }
        $row['expected_elements'] = json_list($row['expected_elements']);
        $row['common_mistakes'] = json_list($row['common_mistakes']);
        $row['keyword_list'] = array_values(array_filter(array_map('trim', explode(',', (string) $row['keywords']))));
        return $row;
    }

    public static function skills(int $questionId): array
    {
        return Database::fetchAll(
            'SELECT s.* FROM question_skills qs JOIN skills s ON s.id = qs.skill_id WHERE qs.question_id = :id ORDER BY s.id',
            ['id' => $questionId]
        );
    }

    public static function translations(int $id): array
    {
        $rows = Database::fetchAll('SELECT * FROM question_translations WHERE question_id = :id', ['id' => $id]);
        $out = [];
        foreach ($rows as $r) {
            $r['expected_elements'] = json_list($r['expected_elements']);
            $r['common_mistakes'] = json_list($r['common_mistakes']);
            $out[$r['locale']] = $r;
        }
        foreach (Database::fetchAll('SELECT locale, body FROM answer_examples WHERE question_id = :id', ['id' => $id]) as $ex) {
            $out[$ex['locale']]['example_answer'] = $ex['body'];
        }
        return $out;
    }

    /** Active follow ups of a question with their text in the given locale. */
    public static function followups(int $questionId, string $locale): array
    {
        return Database::fetchAll(
            'SELECT f.*, COALESCE(t1.body, t2.body) AS body, COALESCE(t1.trigger_keywords, t2.trigger_keywords) AS trigger_keywords
             FROM question_followups f
             LEFT JOIN question_followup_translations t1 ON t1.followup_id = f.id AND t1.locale = :loc
             LEFT JOIN question_followup_translations t2 ON t2.followup_id = f.id AND t2.locale = :other
             WHERE f.question_id = :id AND f.is_active = 1 ORDER BY f.sort_order, f.id',
            ['id' => $questionId, 'loc' => $locale, 'other' => Translator::other($locale)]
        );
    }

    public static function followup(int $id, string $locale): ?array
    {
        return Database::fetch(
            'SELECT f.*, COALESCE(t1.body, t2.body) AS body, (t1.body IS NULL) AS is_fallback
             FROM question_followups f
             LEFT JOIN question_followup_translations t1 ON t1.followup_id = f.id AND t1.locale = :loc
             LEFT JOIN question_followup_translations t2 ON t2.followup_id = f.id AND t2.locale = :other
             WHERE f.id = :id',
            ['id' => $id, 'loc' => $locale, 'other' => Translator::other($locale)]
        );
    }

    /**
     * Administrative search with filters and server side pagination.
     * @return array{items: list<array>, total: int}
     */
    public static function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $loc = Translator::locale();
        $params = ['loc' => $loc, 'other' => Translator::other($loc)];
        $where = ['1 = 1'];
        if (!empty($filters['q'])) {
            $where[] = '(t1.body LIKE :q1 OR t2.body LIKE :q2 OR q.code LIKE :q3)';
            $like = Database::like((string) $filters['q']);
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        foreach (['question_type' => 'type', 'difficulty' => 'difficulty', 'profession_id' => 'profession', 'category_id' => 'category', 'is_active' => 'active'] as $col => $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $where[] = "q.$col = :f_$key";
                $params["f_$key"] = $filters[$key];
            }
        }
        if (($filters['scope'] ?? '') === 'generic') {
            $where[] = 'q.profession_id IS NULL AND q.category_id IS NULL';
        }
        if (($filters['missing'] ?? '') !== '') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM question_translations mt WHERE mt.question_id = q.id AND mt.locale = :missing)';
            $params['missing'] = $filters['missing'];
        }
        $from = ' FROM questions q
            LEFT JOIN question_translations t1 ON t1.question_id = q.id AND t1.locale = :loc
            LEFT JOIN question_translations t2 ON t2.question_id = q.id AND t2.locale = :other
            WHERE ' . implode(' AND ', $where);
        $total = (int) Database::fetchColumn('SELECT COUNT(*)' . $from, $params);
        $items = Database::fetchAll(
            'SELECT q.*, COALESCE(t1.body, t2.body) AS body, (t1.body IS NULL) AS is_fallback,
                (SELECT GROUP_CONCAT(locale ORDER BY locale) FROM question_translations x WHERE x.question_id = q.id) AS locales'
            . $from . ' ORDER BY q.id DESC LIMIT :lim OFFSET :off',
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['items' => $items, 'total' => $total];
    }

    public static function usageCount(int $id): int
    {
        return (int) Database::fetchColumn('SELECT COUNT(*) FROM interview_answers WHERE question_id = :id', ['id' => $id])
            + (int) Database::fetchColumn('SELECT COUNT(*) FROM scenario_questions WHERE question_id = :id', ['id' => $id]);
    }
}
