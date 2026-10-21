<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * CSV import and export of the question bank (UTF-8, comma separated, header row required).
 * Lists inside a cell (skills, expected elements, mistakes) are separated by " ;; ".
 * Expected elements use "label|kw1,kw2". The import is all or nothing: one invalid row rejects the file.
 */
final class QuestionCsv
{
    public const COLUMNS = [
        'code', 'question_type', 'profession_slug', 'category_slug', 'level_min', 'level_max', 'difficulty',
        'rubric_code', 'recommended_seconds', 'is_active', 'skills',
        'body_fr', 'guidance_fr', 'criteria_fr', 'expected_fr', 'mistakes_fr', 'example_fr', 'keywords_fr',
        'body_en', 'guidance_en', 'criteria_en', 'expected_en', 'mistakes_en', 'example_en', 'keywords_en',
    ];
    private const SEP = ' ;; ';

    /** Neutralise spreadsheet formulas in exported cells (CSV injection). */
    public static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    public static function export(): string
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, self::COLUMNS, ',', '"', '');
        $rows = Database::fetchAll(
            'SELECT q.*, p.slug AS profession_slug, c.slug AS category_slug, r.code AS rubric_code
             FROM questions q LEFT JOIN professions p ON p.id = q.profession_id
             LEFT JOIN profession_categories c ON c.id = q.category_id JOIN evaluation_rubrics r ON r.id = q.rubric_id ORDER BY q.id'
        );
        foreach ($rows as $q) {
            $tr = \App\Models\Question::translations((int) $q['id']);
            $skills = array_column(Database::fetchAll('SELECT s.code FROM question_skills qs JOIN skills s ON s.id = qs.skill_id WHERE qs.question_id = :id', ['id' => $q['id']]), 'code');
            $line = [
                $q['code'], $q['question_type'], $q['profession_slug'] ?? '', $q['category_slug'] ?? '', $q['level_min'], $q['level_max'],
                $q['difficulty'], $q['rubric_code'], $q['recommended_seconds'], $q['is_active'], implode(self::SEP, $skills),
            ];
            foreach (['fr', 'en'] as $loc) {
                $t = $tr[$loc] ?? [];
                $line[] = $t['body'] ?? '';
                $line[] = $t['guidance'] ?? '';
                $line[] = $t['evaluation_criteria'] ?? '';
                $line[] = implode(self::SEP, array_map(static fn ($e) => $e['label'] . '|' . implode(',', $e['keywords']), $t['expected_elements'] ?? []));
                $line[] = implode(self::SEP, $t['common_mistakes'] ?? []);
                $line[] = $t['example_answer'] ?? '';
                $line[] = $t['keywords'] ?? '';
            }
            fputcsv($fh, array_map(static fn ($v) => self::safeCell((string) $v), $line), ',', '"', '');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);
        return $csv;
    }

    /**
     * Validate an uploaded file array from $_FILES.
     * @return array{ok: bool, errors: list<string>, path?: string}
     */
    public static function validateUpload(?array $file): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'errors' => [t('import.no_file')]];
        }
        if ((int) $file['size'] > (int) Config::get('security.max_import_bytes', 2097152)) {
            return ['ok' => false, 'errors' => [t('import.too_large')]];
        }
        if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            return ['ok' => false, 'errors' => [t('import.bad_extension')]];
        }
        $mime = function_exists('mime_content_type') ? (string) @mime_content_type($file['tmp_name']) : 'text/plain';
        if (!in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'], true)) {
            return ['ok' => false, 'errors' => [t('import.bad_type')]];
        }
        if (!is_uploaded_file($file['tmp_name']) && !defined('TESTING_UPLOADS')) {
            return ['ok' => false, 'errors' => [t('import.no_file')]];
        }
        return ['ok' => true, 'errors' => [], 'path' => $file['tmp_name']];
    }

    /**
     * Parse and import. With $dryRun the file is only validated.
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public static function import(string $path, bool $dryRun = false): array
    {
        $content = (string) file_get_contents($path);
        if (!mb_check_encoding($content, 'UTF-8')) {
            return ['created' => 0, 'updated' => 0, 'errors' => [t('import.encoding')]];
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, (string) $content);
        rewind($fh);
        $header = fgetcsv($fh, 0, ',', '"', '');
        if ($header === false || array_map('trim', $header) !== self::COLUMNS) {
            return ['created' => 0, 'updated' => 0, 'errors' => [t('import.bad_header', ['columns' => implode(',', self::COLUMNS)])]];
        }
        $rubrics = array_column(Database::fetchAll('SELECT id, code FROM evaluation_rubrics'), 'id', 'code');
        $professions = array_column(Database::fetchAll('SELECT id, slug FROM professions'), 'id', 'slug');
        $categories = array_column(Database::fetchAll('SELECT id, slug FROM profession_categories'), 'id', 'slug');
        $skills = array_column(Database::fetchAll('SELECT id, code FROM skills'), 'id', 'code');
        $max = (int) Config::get('security.max_import_rows', 2000);

        $rows = [];
        $errors = [];
        $line = 1;
        $codes = [];
        while (($cells = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $line++;
            if ($cells === [null] || $cells === ['']) {
                continue;
            }
            if (count($rows) >= $max) {
                $errors[] = t('import.too_many_rows', ['max' => $max]);
                break;
            }
            if (count($cells) !== count(self::COLUMNS)) {
                $errors[] = t('import.line', ['line' => $line]) . ' ' . t('import.bad_columns');
                continue;
            }
            $r = array_combine(self::COLUMNS, array_map(static fn ($c) => trim((string) preg_replace('/^\'(?=[=+\-@])/', '', (string) $c)), $cells));
            $rowErrors = [];
            if ($r['profession_slug'] !== '' && !isset($professions[$r['profession_slug']])) {
                $rowErrors[] = t('import.unknown', ['field' => 'profession_slug', 'value' => $r['profession_slug']]);
            }
            if ($r['category_slug'] !== '' && !isset($categories[$r['category_slug']])) {
                $rowErrors[] = t('import.unknown', ['field' => 'category_slug', 'value' => $r['category_slug']]);
            }
            if (!isset($rubrics[$r['rubric_code']])) {
                $rowErrors[] = t('import.unknown', ['field' => 'rubric_code', 'value' => $r['rubric_code']]);
            }
            $skillIds = [];
            foreach (array_filter(array_map('trim', explode(trim(self::SEP), $r['skills']))) as $code) {
                isset($skills[$code]) ? $skillIds[] = (int) $skills[$code] : $rowErrors[] = t('import.unknown', ['field' => 'skills', 'value' => $code]);
            }
            foreach (['level_min', 'level_max', 'difficulty', 'recommended_seconds', 'is_active'] as $f) {
                if (!preg_match('/^\d+$/', $r[$f])) {
                    $rowErrors[] = t('import.not_integer', ['field' => $f]);
                }
            }
            if (isset($codes[$r['code']])) {
                $rowErrors[] = t('import.duplicate_code', ['code' => $r['code']]);
            }
            $codes[$r['code']] = true;
            $existingId = Database::fetchColumn('SELECT id FROM questions WHERE code = :c', ['c' => $r['code']]);
            $data = [
                'code' => $r['code'],
                'question_type' => $r['question_type'],
                'profession_id' => $r['profession_slug'] !== '' ? (int) ($professions[$r['profession_slug']] ?? 0) : null,
                'category_id' => $r['category_slug'] !== '' ? (int) ($categories[$r['category_slug']] ?? 0) : null,
                'level_min' => (int) $r['level_min'],
                'level_max' => (int) $r['level_max'],
                'difficulty' => (int) $r['difficulty'],
                'rubric_id' => (int) ($rubrics[$r['rubric_code']] ?? 0),
                'recommended_seconds' => (int) $r['recommended_seconds'],
                'is_active' => $r['is_active'] === '1',
                'skill_ids' => $skillIds,
                'followups' => null,
                'translations' => [],
            ];
            foreach (['fr', 'en'] as $loc) {
                $data['translations'][$loc] = [
                    'body' => $r['body_' . $loc],
                    'guidance' => $r['guidance_' . $loc],
                    'evaluation_criteria' => $r['criteria_' . $loc],
                    'expected_elements' => QuestionService::parseElements(str_replace(trim(self::SEP), "\n", $r['expected_' . $loc])),
                    'common_mistakes' => QuestionService::parseLines(str_replace(trim(self::SEP), "\n", $r['mistakes_' . $loc])),
                    'example_answer' => $r['example_' . $loc],
                    'keywords' => $r['keywords_' . $loc],
                ];
            }
            if ($rowErrors === []) {
                $rowErrors = array_values(QuestionService::validate($data, $existingId ? (int) $existingId : null));
            }
            if ($rowErrors) {
                $errors[] = t('import.line', ['line' => $line]) . ' ' . implode(' ; ', $rowErrors);
                continue;
            }
            $rows[] = [$existingId ? (int) $existingId : null, $data];
        }
        fclose($fh);
        if ($errors || $dryRun) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }
        $created = $updated = 0;
        Database::transaction(static function () use ($rows, &$created, &$updated) {
            foreach ($rows as [$id, $data]) {
                QuestionService::save($id, $data);
                $id === null ? $created++ : $updated++;
            }
        });
        return ['created' => $created, 'updated' => $updated, 'errors' => []];
    }
}
