<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Translator;
use App\Models\Question;
use Tests\Support\TestCase;

final class TranslationTest extends TestCase
{
    public function testFrenchAndEnglishHaveTheSameKeys(): void
    {
        $fr = array_keys(Translator::flatten(require BASE_PATH . '/lang/fr.php'));
        $en = array_keys(Translator::flatten(require BASE_PATH . '/lang/en.php'));
        $this->assertSame([], array_values(array_diff($fr, $en)), 'keys only in FR');
        $this->assertSame([], array_values(array_diff($en, $fr)), 'keys only in EN');
        $this->assertGreaterThan(500, count($fr));
    }

    public function testEveryKeyUsedInCodeExists(): void
    {
        $fr = Translator::flatten(require BASE_PATH . '/lang/fr.php');
        $missing = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . '/app'));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/\\bt\\('([a-z0-9_]+(?:\\.[a-z0-9_]+)+)'(?!\\s*\\.)/", (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $key) {
                if (!isset($fr[$key])) {
                    $missing[] = $key;
                }
            }
        }
        $this->assertSame([], array_values(array_unique($missing)));
    }

    public function testFallbacks(): void
    {
        Translator::setLocale('en');
        $this->assertSame('Log in', t('nav.login'));
        $this->assertSame('[does.not.exist]', t('does.not.exist'), 'explicit marker instead of empty text');
        $this->assertSame('Bonjour Ada', t('dashboard.hello', ['name' => 'Ada'], 'fr'));
    }

    public function testQuestionContentFallsBackToOtherLanguage(): void
    {
        $id = (int) Database::fetchColumn("SELECT id FROM questions WHERE code = 'gen-hr-introduce'");
        $en = Database::fetch("SELECT * FROM question_translations WHERE question_id = :id AND locale = 'en'", ['id' => $id]);
        Database::delete('question_translations', ['question_id' => $id, 'locale' => 'en']);
        try {
            $q = Question::find($id, 'en');
            $this->assertSame(1, (int) $q['is_fallback']);
            $this->assertContains('Présentez-vous', $q['body']);
        } finally {
            Database::insert('question_translations', $en);
        }
        $this->assertSame(0, (int) Question::find($id, 'en')['is_fallback']);
    }

    public function testSeedContentIsBilingual(): void
    {
        $this->assertSame(0, (int) Database::fetchColumn('SELECT COUNT(*) FROM questions q WHERE (SELECT COUNT(*) FROM question_translations t WHERE t.question_id = q.id) <> 2'));
        $this->assertGreaterThan(190, (int) Database::fetchColumn('SELECT COUNT(*) FROM questions'));
        $this->assertGreaterThan(50, (int) Database::fetchColumn('SELECT COUNT(*) FROM professions'));
        $this->assertSame(0, (int) Database::fetchColumn('SELECT COUNT(*) FROM professions p WHERE NOT EXISTS (SELECT 1 FROM questions q WHERE q.profession_id = p.id)'), 'every profession has specific questions');
        $this->assertSame(0, (int) Database::fetchColumn('SELECT COUNT(*) FROM (SELECT rubric_id, SUM(weight) s FROM rubric_criteria GROUP BY rubric_id HAVING s <> 100) x'), 'rubric weights total 100');
    }
}
