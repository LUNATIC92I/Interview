<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\QuestionCsv;
use Tests\Support\TestCase;

final class CsvImportTest extends TestCase
{
    private function csv(array $rows, ?array $header = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        $fh = fopen($path, 'w');
        fputcsv($fh, $header ?? QuestionCsv::COLUMNS, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($fh, $r, ',', '"', '');
        }
        fclose($fh);
        return $path;
    }

    private function row(string $code, array $overrides = []): array
    {
        $base = array_combine(QuestionCsv::COLUMNS, array_fill(0, count(QuestionCsv::COLUMNS), ''));
        return array_values(array_merge($base, [
            'code' => $code, 'question_type' => 'technical', 'profession_slug' => 'dba', 'level_min' => '1', 'level_max' => '5',
            'difficulty' => '3', 'rubric_code' => 'technical', 'recommended_seconds' => '180', 'is_active' => '1', 'skills' => 'technical_expertise ;; problem_solving',
            'body_fr' => 'Comment indexez-vous une table volumineuse ?', 'expected_fr' => 'Analyse|explain ;; Index composite|index,composite',
            'body_en' => 'How do you index a large table?', 'expected_en' => 'Analysis|explain ;; Composite index|index,composite',
            'mistakes_en' => 'Indexing everything ;; Not measuring',
        ], $overrides));
    }

    public function testExportHasHeaderAndAllQuestions(): void
    {
        $csv = QuestionCsv::export();
        $lines = array_filter(explode("\n", $csv), static fn ($l) => preg_match('/^[a-z0-9-]+,/', $l));
        $this->assertContains(implode(',', QuestionCsv::COLUMNS), $csv);
        $this->assertGreaterThan(190, count($lines));
    }

    public function testDryRunThenImportAndUpdate(): void
    {
        $file = $this->csv([$this->row('csv-test-index')]);
        $dry = QuestionCsv::import($file, true);
        $this->assertSame([], $dry['errors']);
        $this->assertFalse((bool) Database::fetchColumn("SELECT 1 FROM questions WHERE code = 'csv-test-index'"), 'dry run writes nothing');

        $res = QuestionCsv::import($file);
        $this->assertSame(1, $res['created']);
        $id = (int) Database::fetchColumn("SELECT id FROM questions WHERE code = 'csv-test-index'");
        $this->assertSame(2, (int) Database::fetchColumn('SELECT COUNT(*) FROM question_skills WHERE question_id = :id', ['id' => $id]));
        $el = json_decode((string) Database::fetchColumn("SELECT expected_elements FROM question_translations WHERE question_id = :id AND locale = 'en'", ['id' => $id]), true);
        $this->assertSame(['index', 'composite'], $el[1]['keywords']);

        $res2 = QuestionCsv::import($this->csv([$this->row('csv-test-index', ['difficulty' => '4'])]));
        $this->assertSame(1, $res2['updated']);
        $this->assertSame(4, (int) Database::fetchColumn('SELECT difficulty FROM questions WHERE id = :id', ['id' => $id]));
    }

    public function testInvalidFilesAreRejectedAtomically(): void
    {
        $before = (int) Database::fetchColumn('SELECT COUNT(*) FROM questions');
        $bad = QuestionCsv::import($this->csv([
            $this->row('csv-ok-row'),
            $this->row('csv-bad-row', ['profession_slug' => 'unknown-job', 'difficulty' => '9', 'skills' => 'nope']),
            $this->row('Bad Code!'),
        ]));
        $this->assertSame(2, count($bad['errors']), 'two invalid rows reported');
        $this->assertContains('unknown-job', $bad['errors'][0]);
        $this->assertSame($before, (int) Database::fetchColumn('SELECT COUNT(*) FROM questions'), 'nothing imported when a row fails');

        $header = QuestionCsv::import($this->csv([], ['code', 'body']));
        $this->assertSame(1, count($header['errors']));

        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode(',', QuestionCsv::COLUMNS) . "\n\xFF\xFE broken");
        $this->assertSame(1, count(QuestionCsv::import($path)['errors']), 'non UTF-8 rejected');
    }

    public function testUploadValidation(): void
    {
        $this->assertFalse(QuestionCsv::validateUpload(null)['ok']);
        $php = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($php, '<?php echo 1;');
        $this->assertFalse(QuestionCsv::validateUpload(['name' => 'evil.php', 'tmp_name' => $php, 'size' => 13, 'error' => UPLOAD_ERR_OK])['ok'], 'extension checked');
        $this->assertFalse(QuestionCsv::validateUpload(['name' => 'big.csv', 'tmp_name' => $php, 'size' => 50 * 1024 * 1024, 'error' => UPLOAD_ERR_OK])['ok'], 'size checked');
        $png = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        $this->assertFalse(QuestionCsv::validateUpload(['name' => 'image.csv', 'tmp_name' => $png, 'size' => 70, 'error' => UPLOAD_ERR_OK])['ok'], 'content type checked');
        $ok = $this->csv([$this->row('x')]);
        $this->assertTrue(QuestionCsv::validateUpload(['name' => 'q.csv', 'tmp_name' => $ok, 'size' => filesize($ok), 'error' => UPLOAD_ERR_OK])['ok']);
    }

    public function testFormulaInjectionIsNeutralised(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", QuestionCsv::safeCell('=HYPERLINK("x")'));
        $this->assertSame("'+1", QuestionCsv::safeCell('+1'));
        $this->assertSame('Normal text', QuestionCsv::safeCell('Normal text'));
    }
}
