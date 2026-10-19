<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Settings;

/**
 * Loads reference data and the bilingual content bank. Idempotent: existing rows (matched on their
 * stable code or slug) are left untouched, so re-running never overwrites administrator edits.
 */
final class DatabaseSeeder
{
    /** @var callable(string): void */
    private $out;
    private array $rubricIds = [];
    private array $skillIds = [];
    private array $categoryIds = [];
    private array $professionIds = [];
    private array $questionIds = [];
    private array $stats = ['questions' => 0, 'professions' => 0, 'scenarios' => 0];

    public function __construct(callable $out)
    {
        $this->out = $out;
    }

    public function run(): void
    {
        Database::transaction(function () {
            $this->reference(require __DIR__ . '/data/reference.php');
            $this->catalog(require __DIR__ . '/data/catalog.php');
            $typeRubrics = (require __DIR__ . '/data/reference.php')['type_rubrics'];
            foreach (['questions_generic', 'questions_it', 'questions_finance_management', 'questions_hr_public', 'questions_other'] as $file) {
                foreach (require __DIR__ . '/data/' . $file . '.php' as $q) {
                    $this->question($q, $typeRubrics);
                }
            }
            $this->scenarios(require __DIR__ . '/data/scenarios.php');
            $this->learning(require __DIR__ . '/data/learning.php');
        });
        ($this->out)(sprintf(
            'Seed complete: %d professions, %d questions, %d scenarios added (existing rows kept).',
            $this->stats['professions'], $this->stats['questions'], $this->stats['scenarios']
        ));
    }

    private function reference(array $ref): void
    {
        foreach ($ref['roles'] as [$code, $fr, $en]) {
            Database::query('INSERT IGNORE INTO roles (code, name_fr, name_en) VALUES (:c, :f, :e)', ['c' => $code, 'f' => $fr, 'e' => $en]);
        }
        foreach ($ref['difficulty_levels'] as [$id, $code, $fr, $en, $dfr, $den]) {
            Database::query(
                'INSERT IGNORE INTO difficulty_levels (id, code, name_fr, name_en, description_fr, description_en) VALUES (:i, :c, :f, :e, :df, :de)',
                ['i' => $id, 'c' => $code, 'f' => $fr, 'e' => $en, 'df' => $dfr, 'de' => $den]
            );
        }
        foreach ($ref['skill_categories'] as [$code, $fr, $en, $order]) {
            Database::query('INSERT IGNORE INTO skill_categories (code, name_fr, name_en, sort_order) VALUES (:c, :f, :e, :o)', ['c' => $code, 'f' => $fr, 'e' => $en, 'o' => $order]);
        }
        $cats = array_column(Database::fetchAll('SELECT id, code FROM skill_categories'), 'id', 'code');
        foreach ($ref['skills'] as $code => [$cat, $fr, $en]) {
            Database::query('INSERT IGNORE INTO skills (skill_category_id, code, name_fr, name_en) VALUES (:s, :c, :f, :e)', ['s' => $cats[$cat], 'c' => $code, 'f' => $fr, 'e' => $en]);
        }
        $this->skillIds = array_map('intval', array_column(Database::fetchAll('SELECT id, code FROM skills'), 'id', 'code'));

        foreach ($ref['rubrics'] as $code => [$fr, $en, $dfr, $den, $criteria]) {
            $id = Database::fetchColumn('SELECT id FROM evaluation_rubrics WHERE code = :c', ['c' => $code]);
            if (!$id) {
                $id = Database::insert('evaluation_rubrics', ['code' => $code, 'name_fr' => $fr, 'name_en' => $en, 'description_fr' => $dfr, 'description_en' => $den, 'is_active' => 1, 'updated_at' => now()]);
                foreach ($criteria as $i => [$cCode, $evaluator, $weight, $cfr, $cen, $cdfr, $cden]) {
                    Database::insert('rubric_criteria', [
                        'rubric_id' => $id, 'code' => $cCode, 'evaluator' => $evaluator, 'weight' => $weight,
                        'name_fr' => $cfr, 'name_en' => $cen, 'description_fr' => $cdfr, 'description_en' => $cden, 'sort_order' => $i,
                    ]);
                }
            }
            $this->rubricIds[$code] = (int) $id;
        }
        foreach (Settings::DEFAULTS as $key => [$type, $value]) {
            Database::query(
                'INSERT IGNORE INTO app_settings (setting_key, setting_value, value_type, updated_at) VALUES (:k, :v, :t, :u)',
                ['k' => $key, 'v' => $value, 't' => $type, 'u' => now()]
            );
        }
    }

    private function catalog(array $catalog): void
    {
        foreach ($catalog['categories'] as $slug => [$icon, $order, $fr, $en]) {
            $id = Database::fetchColumn('SELECT id FROM profession_categories WHERE slug = :s', ['s' => $slug]);
            if (!$id) {
                $id = Database::insert('profession_categories', ['slug' => $slug, 'icon' => $icon, 'sort_order' => $order, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
                foreach (['fr' => $fr, 'en' => $en] as $loc => [$name, $desc]) {
                    Database::insert('profession_category_translations', ['category_id' => $id, 'locale' => $loc, 'name' => $name, 'description' => $desc]);
                }
            }
            $this->categoryIds[$slug] = (int) $id;
        }
        $order = 0;
        foreach ($catalog['professions'] as $slug => [$cat, $icon, $min, $max, $skills, $fr, $en]) {
            $order++;
            $id = Database::fetchColumn('SELECT id FROM professions WHERE slug = :s', ['s' => $slug]);
            if (!$id) {
                $id = Database::insert('professions', [
                    'category_id' => $this->categoryIds[$cat], 'slug' => $slug, 'icon' => $icon,
                    'level_min' => $min, 'level_max' => $max, 'sort_order' => $order, 'is_active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach (['fr' => $fr, 'en' => $en] as $loc => [$name, $summary, $resp]) {
                    Database::insert('profession_translations', [
                        'profession_id' => $id, 'locale' => $loc, 'name' => $name, 'summary' => $summary,
                        'responsibilities' => str_replace('|', "\n", $resp),
                    ]);
                }
                foreach ($skills as $skill) {
                    if (!isset($this->skillIds[$skill])) {
                        throw new RuntimeException("Unknown skill $skill for $slug");
                    }
                    Database::insert('profession_skills', ['profession_id' => $id, 'skill_id' => $this->skillIds[$skill]]);
                }
                $this->stats['professions']++;
            }
            $this->professionIds[$slug] = (int) $id;
        }
    }

    /** "label|kw1,kw2" => ['label' => ..., 'keywords' => [...]] */
    private static function elements(array $items): array
    {
        return array_map(static function (string $item) {
            [$label, $kw] = array_pad(explode('|', $item, 2), 2, '');
            return ['label' => trim($label), 'keywords' => array_values(array_filter(array_map('trim', explode(',', $kw))))];
        }, $items);
    }

    private function question(array $q, array $typeRubrics): void
    {
        $existing = Database::fetchColumn('SELECT id FROM questions WHERE code = :c', ['c' => $q['code']]);
        if ($existing) {
            $this->questionIds[$q['code']] = (int) $existing;
            return;
        }
        $rubricCode = $q['rubric'] ?? $typeRubrics[$q['type']];
        $professionId = isset($q['prof']) ? ($this->professionIds[$q['prof']] ?? throw new RuntimeException('Unknown profession ' . $q['prof'])) : null;
        $id = Database::insert('questions', [
            'code' => $q['code'],
            'profession_id' => $professionId,
            'category_id' => isset($q['cat']) ? $this->categoryIds[$q['cat']] : null,
            'question_type' => $q['type'],
            'level_min' => $q['lvl'][0],
            'level_max' => $q['lvl'][1],
            'difficulty' => $q['diff'],
            'rubric_id' => $this->rubricIds[$rubricCode],
            'recommended_seconds' => $q['sec'],
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rubric = Database::fetch('SELECT name_fr, name_en FROM evaluation_rubrics WHERE id = :id', ['id' => $this->rubricIds[$rubricCode]]);
        foreach (['fr', 'en'] as $loc) {
            [$body, $guidance, $expected, $mistakes, $example, $keywords] = $q[$loc];
            $elements = self::elements($expected);
            $criteria = $q['criteria_' . $loc] ?? ($loc === 'fr'
                ? 'Évaluée avec la grille « ' . $rubric['name_fr'] . ' ». Éléments attendus : ' . implode(' ; ', array_column($elements, 'label')) . '.'
                : 'Assessed with the "' . $rubric['name_en'] . '" grid. Expected elements: ' . implode('; ', array_column($elements, 'label')) . '.');
            Database::insert('question_translations', [
                'question_id' => $id,
                'locale' => $loc,
                'body' => $body,
                'guidance' => $guidance,
                'evaluation_criteria' => $criteria,
                'expected_elements' => json_encode($elements, JSON_UNESCAPED_UNICODE),
                'common_mistakes' => json_encode($mistakes, JSON_UNESCAPED_UNICODE),
                'keywords' => mb_substr($keywords, 0, 500),
            ]);
            Database::insert('answer_examples', ['question_id' => $id, 'locale' => $loc, 'body' => $example, 'created_at' => now()]);
        }
        foreach ($q['skills'] as $skill) {
            if (!isset($this->skillIds[$skill])) {
                throw new RuntimeException("Unknown skill $skill in {$q['code']}");
            }
            Database::insert('question_skills', ['question_id' => $id, 'skill_id' => $this->skillIds[$skill]]);
        }
        foreach ($q['fu'] ?? [] as $i => [$trigger, $minWords, $fr, $en, $kwFr, $kwEn]) {
            $fid = Database::insert('question_followups', ['question_id' => $id, 'trigger_type' => $trigger, 'min_words' => $minWords, 'sort_order' => $i, 'is_active' => 1]);
            Database::insert('question_followup_translations', ['followup_id' => $fid, 'locale' => 'fr', 'body' => $fr, 'trigger_keywords' => $kwFr]);
            Database::insert('question_followup_translations', ['followup_id' => $fid, 'locale' => 'en', 'body' => $en, 'trigger_keywords' => $kwEn]);
        }
        $this->questionIds[$q['code']] = (int) $id;
        $this->stats['questions']++;
    }

    private function scenarios(array $scenarios): void
    {
        foreach ($scenarios as $slug => $s) {
            if (Database::fetchColumn('SELECT id FROM scenarios WHERE slug = :s', ['s' => $slug])) {
                continue;
            }
            $id = Database::insert('scenarios', [
                'slug' => $slug,
                'profession_id' => isset($s['prof']) ? $this->professionIds[$s['prof']] : null,
                'category_id' => isset($s['cat']) ? $this->categoryIds[$s['cat']] : null,
                'interview_type' => $s['type'],
                'experience_level' => $s['level'],
                'difficulty' => $s['diff'],
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (['fr', 'en'] as $loc) {
                Database::insert('scenario_translations', ['scenario_id' => $id, 'locale' => $loc, 'title' => $s[$loc][0], 'description' => $s[$loc][1]]);
            }
            foreach ($s['questions'] as $pos => $code) {
                $qid = $this->questionIds[$code] ?? throw new RuntimeException("Unknown question $code in scenario $slug");
                Database::insert('scenario_questions', ['scenario_id' => $id, 'question_id' => $qid, 'position' => $pos + 1]);
            }
            $this->stats['scenarios']++;
        }
    }

    private function learning(array $items): void
    {
        foreach ($items as $slug => $item) {
            [$type, $qtype, $order] = [$item[0], $item[1], $item[2]];
            [$fr, $en] = [$item['fr'], $item['en']];
            if (Database::fetchColumn('SELECT id FROM learning_contents WHERE slug = :s', ['s' => $slug])) {
                continue;
            }
            $id = Database::insert('learning_contents', ['slug' => $slug, 'content_type' => $type, 'question_type' => $qtype, 'is_published' => 1, 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
            foreach (['fr' => $fr, 'en' => $en] as $loc => [$title, $summary, $body]) {
                Database::insert('learning_content_translations', ['content_id' => $id, 'locale' => $loc, 'title' => $title, 'summary' => $summary, 'body' => $body]);
            }
        }
    }
}
