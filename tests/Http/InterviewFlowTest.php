<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database;
use Tests\Support\Context;
use Tests\Support\HttpClient;
use Tests\Support\TestCase;

final class InterviewFlowTest extends TestCase
{
    private HttpClient $c;
    private string $id = '';

    public function setUpClass(): void
    {
        $this->c = Context::http();
        $this->c->register('flow-' . uniqid() . '@test.local');
    }

    private function start(array $extra = []): string
    {
        $this->c->get('/interview/new');
        $prof = (int) Database::fetchColumn("SELECT id FROM professions WHERE slug = 'php-backend-developer'");
        $r = $this->c->post('/interview', $extra + ['profession_id' => $prof, 'experience_level' => 3, 'interview_type' => 'technical', 'locale' => 'fr',
            'difficulty' => 3, 'duration_minutes' => 20, 'question_count' => 5, 'mode' => 'training']);
        $this->assertSame(302, $r['status']);
        $this->assertTrue(preg_match('#/interview/([a-f0-9]{32})$#', (string) $r['location'], $m) === 1, 'redirect to the runner');
        $this->c->get('/interview/' . $m[1]);
        return $m[1];
    }

    public function testCreateSaveResumeSubmitFinishReport(): void
    {
        $id = $this->start();
        $page = $this->c->get('/interview/' . $id);
        $this->assertSame(200, $page['status']);
        $this->assertContains('id="interview-state"', $page['body']);

        $state = $this->c->getJson("/api/interview/$id/state")['json']['state'];
        $this->assertSame(5, $state['progress']['total']);
        $this->assertSame(1, $state['question']['position']);

        // Draft autosave survives a "reload" (new state request) and a language switch.
        $draft = $this->c->json("/api/interview/$id/draft", ['position' => 1, 'text' => 'Brouillon en cours']);
        $this->assertTrue($draft['json']['ok']);
        $this->c->get('/lang/en?redirect=/interview/' . $id);
        $resumed = $this->c->getJson("/api/interview/$id/state")['json']['state'];
        $this->assertSame('Brouillon en cours', $resumed['question']['text'], 'draft kept after reload and language switch');
        $this->assertSame('fr', $resumed['session']['locale'], 'interview language unchanged');
        $this->assertContains('Submit answer', $this->c->get('/interview/' . $id)['body'], 'interface now in English');

        $answer = "D'abord je valide les entrées côté serveur avec filter_var, ensuite j'utilise des requêtes préparées PDO avec bindValue. "
            . "Enfin j'échappe les sorties avec htmlspecialchars et j'ajoute une Content-Security-Policy parce que la défense en profondeur limite le risque.";
        for ($pos = 1; $pos <= 20; $pos++) {
            $st = $this->c->getJson("/api/interview/$id/state")['json']['state'];
            if ($st['progress']['all_done']) {
                break;
            }
            $q = $st['question'];
            $res = $this->c->json("/api/interview/$id/submit", ['position' => $q['position'], 'text' => $answer]);
            $this->assertTrue($res['json']['ok'], 'submit ok: ' . ($res['json']['error'] ?? ''));
        }
        // Training mode: instant feedback is available when going back to an answered question.
        $back = $this->c->json("/api/interview/$id/goto", ['position' => 1]);
        $fb = $back['json']['state']['question']['feedback'];
        $this->assertTrue($fb['score'] > 0 && $fb['score'] <= 100);
        $this->assertTrue(count($fb['criteria']) >= 4, 'criteria detail');
        $this->assertTrue($fb['example_answer'] !== '', 'indicative answer provided');

        $finish = $this->c->json("/api/interview/$id/finish");
        $this->assertTrue($finish['json']['ok']);
        $session = Database::fetch('SELECT * FROM interview_sessions WHERE public_id = :p', ['p' => $id]);
        $this->assertSame('completed', $session['status']);
        $this->assertTrue((float) $session['overall_score'] > 0);
        $times = Database::fetchAll('SELECT time_spent_seconds, word_count FROM interview_answers WHERE session_id = :s', ['s' => $session['id']]);
        foreach ($times as $t) {
            $this->assertTrue((int) $t['word_count'] > 0, 'answer length recorded');
        }

        $report = $this->c->get('/reports/' . $id);
        $this->assertSame(200, $report['status']);
        foreach (['/100', 'Scores by criterion', 'Strengths', 'Export to PDF', 'Indicative automatic evaluation'] as $needle) {
            $this->assertContains($needle, $report['body']);
        }
        $this->assertNotContains('[report.', $report['body']);
        $this->c->get('/lang/fr?redirect=/reports/' . $id);
        $this->assertContains('Scores par critère', $this->c->get('/reports/' . $id)['body'], 'report available in French');

        $pdf = $this->c->get('/reports/' . $id . '/pdf');
        $this->assertSame(200, $pdf['status']);
        $this->assertSame('application/pdf', $pdf['headers']['content-type']);
        $this->assertContains('attachment; filename="interviewpro-report-', $pdf['headers']['content-disposition']);
        $this->assertTrue(str_starts_with($pdf['body'], '%PDF-1.4') && str_contains($pdf['body'], '%%EOF'));

        $this->assertSame(302, $this->c->get('/interview/' . $id)['status'], 'completed simulation redirects to its report');
        $this->id = $id;
    }

    public function testCompareAttemptsAndDashboard(): void
    {
        $second = $this->start();
        $this->c->json("/api/interview/$second/finish");
        $r = $this->c->get("/reports/compare?a={$this->id}&b=$second");
        $this->assertSame(200, $r['status']);
        $this->assertContains('Tentative A', $r['body']);
        $dash = $this->c->get('/dashboard')['body'];
        $this->assertContains('<svg class="chart chart-line"', $dash, 'performance chart');
        $this->assertContains('Compétences', $dash);
        $hist = $this->c->get('/history?status=completed')['body'];
        $this->assertContains('data-compare-pick', $hist);
    }

    public function testRealisticModeRules(): void
    {
        $id = $this->start(['mode' => 'realistic']);
        $state = $this->c->getJson("/api/interview/$id/state")['json']['state'];
        $this->assertFalse($state['session']['allow_back']);
        $this->assertFalse($state['session']['instant_feedback']);
        $this->c->json("/api/interview/$id/submit", ['position' => 1, 'text' => 'Une réponse suffisante pour avancer dans la simulation.']);
        $back = $this->c->json("/api/interview/$id/goto", ['position' => 1]);
        $this->assertSame(422, $back['status'], 'going back refused');
        $skip = $this->c->json("/api/interview/$id/submit", ['position' => 2, 'text' => '', 'skip' => 1]);
        $this->assertTrue($skip['json']['ok'], 'skipping allowed');
        $this->assertFalse(isset($skip['json']['state']['question']['feedback']), 'no instant feedback');
        $pause = $this->c->json("/api/interview/$id/pause");
        $this->assertSame('paused', $pause['json']['state']['session']['status']);
        $this->assertSame(422, $this->c->json("/api/interview/$id/submit", ['position' => 3, 'text' => 'x'])['status'], 'no answer while paused');
        $this->assertSame('in_progress', $this->c->json("/api/interview/$id/resume")['json']['state']['session']['status']);
        $saved = $this->c->get('/dashboard')['body'];
        $this->assertContains('/interview/' . $id, $saved, 'unfinished simulation listed for resumption');
    }

    public function testContentReportAndGoals(): void
    {
        $id = $this->start();
        $q = $this->c->getJson("/api/interview/$id/state")['json']['state']['question'];
        $r = $this->c->json('/api/questions/' . $q['question_id'] . '/report', ['reason' => 'unclear', 'message' => 'Ambigu']);
        $this->assertTrue($r['json']['ok']);
        $this->assertSame(422, $this->c->json('/api/questions/' . $q['question_id'] . '/report', ['reason' => 'bogus'])['status']);
        $this->c->get('/dashboard');
        $this->c->post('/goals', ['goal_type' => 'average_score', 'target_value' => 80]);
        $this->assertContains('Atteindre 80 de moyenne', $this->c->get('/dashboard')['body']);
    }
}
