<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database;
use Tests\Support\Context;
use Tests\Support\HttpClient;
use Tests\Support\TestCase;

final class AdminTest extends TestCase
{
    private HttpClient $a;

    public function setUpClass(): void
    {
        $this->a = Context::http();
        $this->a->login(Context::$adminEmail, Context::$adminPassword);
    }

    public function testAllAdminPagesRender(): void
    {
        foreach (['/admin', '/admin/users', '/admin/categories', '/admin/categories/new', '/admin/professions', '/admin/professions/new',
                  '/admin/questions', '/admin/questions/new', '/admin/questions/1/edit', '/admin/questions/import', '/admin/scenarios',
                  '/admin/scenarios/new', '/admin/scenarios/1/edit', '/admin/rubrics', '/admin/rubrics/1/edit', '/admin/difficulties',
                  '/admin/learning', '/admin/learning/new', '/admin/translations', '/admin/reports', '/admin/settings', '/admin/audit',
                  '/admin/questions?q=SQL&type=technical', '/admin/translations?missing=1'] as $path) {
            $r = $this->a->get($path);
            $this->assertSame(200, $r['status'], $path);
            $this->assertNotContains('[admin.', $r['body'], "untranslated key on $path");
        }
    }

    public function testCreateProfessionWithoutCode(): void
    {
        $this->a->get('/admin/professions/new');
        $r = $this->a->post('/admin/professions', ['slug' => 'data-protection-officer', 'category_id' => 1, 'icon' => 'shield', 'level_min' => 2, 'level_max' => 5,
            'sort_order' => 99, 'is_active' => 1, 'name_fr' => 'Délégué à la protection des données', 'name_en' => 'Data protection officer',
            'summary_fr' => 'Veille à la conformité RGPD.', 'summary_en' => 'Ensures GDPR compliance.', 'responsibilities_fr' => "Tenir le registre\nConseiller", 'responsibilities_en' => "Keep the register\nAdvise", 'skills' => [1, 2]]);
        $this->assertSame(302, $r['status']);
        $public = Context::http()->get('/professions/data-protection-officer');
        $this->assertSame(200, $public['status']);
        $this->assertContains('Délégué à la protection des données', $public['body']);
        $this->a->get('/admin/professions/new');
        $dup = $this->a->post('/admin/professions', ['slug' => 'data-protection-officer', 'category_id' => 1, 'icon' => 'x', 'level_min' => 1, 'level_max' => 5, 'sort_order' => 1, 'name_fr' => 'X', 'name_en' => 'X']);
        $this->assertContains('/admin/professions/new', (string) $dup['location'], 'duplicate slug rejected');
    }

    public function testQuestionCrudWithFollowup(): void
    {
        $this->a->get('/admin/questions/new');
        $data = ['code' => 'admin-created-q', 'question_type' => 'behavioral', 'profession_id' => '', 'category_id' => '', 'level_min' => 1, 'level_max' => 5,
            'difficulty' => 2, 'rubric_id' => 1, 'recommended_seconds' => 150, 'is_active' => 1, 'skills' => [1],
            'body_fr' => 'Racontez une réussite.', 'expected_fr' => "Contexte | contexte, situation\nRésultat | resultat", 'body_en' => 'Tell me about a success.',
            'expected_en' => "Context | context\nResult | result", 'followups' => [['trigger_type' => 'missing_results', 'min_words' => 0, 'fr' => 'Quel résultat ?', 'en' => 'What result?', 'kw_fr' => '', 'kw_en' => '']]];
        $r = $this->a->post('/admin/questions', $data);
        $this->assertSame(302, $r['status']);
        $id = (int) Database::fetchColumn("SELECT id FROM questions WHERE code = 'admin-created-q'");
        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, (int) Database::fetchColumn('SELECT COUNT(*) FROM question_followups WHERE question_id = :id', ['id' => $id]));
        $this->a->get("/admin/questions/$id/edit");
        $this->a->post("/admin/questions/$id", array_merge($data, ['difficulty' => 4]));
        $this->assertSame(4, (int) Database::fetchColumn('SELECT difficulty FROM questions WHERE id = :id', ['id' => $id]));
        $this->a->get('/admin/questions');
        $this->a->post("/admin/questions/$id/delete");
        $this->assertSame(0, (int) Database::fetchColumn('SELECT COUNT(*) FROM questions WHERE id = :id', ['id' => $id]), 'unused question deleted');
        $used = (int) Database::fetchColumn('SELECT question_id FROM interview_answers LIMIT 1');
        $this->a->get('/admin/questions');
        $this->a->post("/admin/questions/$used/delete");
        $this->assertSame(0, (int) Database::fetchColumn('SELECT is_active FROM questions WHERE id = :id', ['id' => $used]), 'used question only deactivated');
        Database::update('questions', ['is_active' => 1], ['id' => $used]);
    }

    public function testCsvExportAndImportOverHttp(): void
    {
        $export = $this->a->get('/admin/questions/export');
        $this->assertSame(200, $export['status']);
        $this->assertContains('text/csv', $export['headers']['content-type']);
        $this->assertContains('code,question_type', $export['body']);
        $lines = explode("\n", trim($export['body']));
        $file = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
        file_put_contents($file, $lines[0] . "\n" . $lines[1] . "\n");
        $this->a->get('/admin/questions/import');
        $res = $this->a->upload('/admin/questions/import', ['dry_run' => '1'], ['field' => 'file', 'path' => $file, 'name' => 'questions.csv']);
        $this->assertSame(200, $res['status']);
        $this->assertContains('alert-success', $res['body']);
        $bad = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
        file_put_contents($bad, "foo,bar\n1,2\n");
        $res2 = $this->a->upload('/admin/questions/import', [], ['field' => 'file', 'path' => $bad, 'name' => 'bad.csv']);
        $this->assertContains('alert-error', $res2['body']);
    }

    public function testScenarioRubricSettingsTranslation(): void
    {
        $this->a->get('/admin/scenarios/new');
        $q = array_map('intval', array_column(Database::fetchAll('SELECT id FROM questions LIMIT 3'), 'id'));
        $this->a->post('/admin/scenarios', ['slug' => 'admin-scenario', 'interview_type' => 'hr', 'profession_id' => '', 'category_id' => '', 'experience_level' => 2,
            'difficulty' => 2, 'is_active' => 1, 'title_fr' => 'Scénario admin', 'title_en' => 'Admin scenario', 'questions' => $q]);
        $sid = (int) Database::fetchColumn("SELECT id FROM scenarios WHERE slug = 'admin-scenario'");
        $this->assertSame($q, array_map('intval', array_column(Database::fetchAll('SELECT question_id FROM scenario_questions WHERE scenario_id = :s ORDER BY position', ['s' => $sid]), 'question_id')));

        $criteria = Database::fetchAll('SELECT * FROM rubric_criteria WHERE rubric_id = 1');
        $post = ['name_fr' => 'Grille', 'name_en' => 'Grid', 'is_active' => 1, 'criteria' => []];
        foreach ($criteria as $c) {
            $post['criteria'][$c['id']] = ['name_fr' => $c['name_fr'], 'name_en' => $c['name_en'], 'evaluator' => $c['evaluator'], 'weight' => 10];
        }
        $this->a->get('/admin/rubrics/1/edit');
        $this->a->post('/admin/rubrics/1', $post);
        $this->assertSame(100, (int) Database::fetchColumn('SELECT SUM(weight) FROM rubric_criteria WHERE rubric_id = 1'), 'weights not totalling 100 rejected');

        $this->a->get('/admin/settings');
        $this->a->post('/admin/settings', ['registration_open' => '', 'ai_evaluation_enabled' => '1', 'max_sessions_per_day' => 30, 'default_question_count' => 6, 'contact_email' => 'c@test.local', 'maintenance_message' => '', 'access_price' => 6550, 'access_currency' => 'XOF', 'trial_simulations' => 1, 'trial_question_limit' => 3]);
        $this->assertContains('temporairement fermées', Context::http()->get('/register')['body']);
        $this->a->get('/admin/settings');
        $this->a->post('/admin/settings', ['registration_open' => '1', 'ai_evaluation_enabled' => '1', 'max_sessions_per_day' => 30, 'default_question_count' => 6, 'contact_email' => 'c@test.local', 'maintenance_message' => '', 'access_price' => 6550, 'access_currency' => 'XOF', 'trial_simulations' => 1, 'trial_question_limit' => 3]);

        $this->a->get('/admin/translations');
        $this->a->post('/admin/translations', ['key' => 'nav.resources', 'value_fr' => 'Conseils pratiques', 'value_en' => 'Advice']);
        $this->assertContains('Conseils pratiques', Context::http()->get('/')['body'], 'override applied');
        $this->a->get('/admin/translations');
        $this->a->post('/admin/translations', ['key' => 'nav.resources', 'value_fr' => 'Conseils', 'value_en' => 'Advice']);
        $this->assertSame(0, (int) Database::fetchColumn("SELECT COUNT(*) FROM translation_overrides WHERE translation_key = 'nav.resources'"));

        $this->assertGreaterThan(3, (int) Database::fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'admin.%'"), 'admin operations audited');
    }

    public function testUserManagementAndAggregatedStatsOnly(): void
    {
        $email = 'managed-' . uniqid() . '@test.local';
        Context::http()->register($email);
        $uid = (int) Database::fetchColumn('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        $this->a->get("/admin/users/$uid");
        $this->a->post("/admin/users/$uid/status", ['status' => 'suspended']);
        $this->assertContains('/login', (string) Context::http()->login($email)['location'], 'suspended user cannot log in');
        $this->a->get("/admin/users/$uid");
        $this->a->post("/admin/users/$uid/status", ['status' => 'active']);
        $dash = $this->a->get('/admin')['body'];
        $this->assertNotContains('$2y$', $dash);
        $answer = (string) Database::fetchColumn('SELECT answer_text FROM interview_answers WHERE answer_text IS NOT NULL LIMIT 1');
        if ($answer !== '') {
            $this->assertNotContains(e(mb_substr($answer, 0, 40)), $dash . $this->a->get("/admin/users/$uid")['body'], 'answers never shown');
        }
    }
}
