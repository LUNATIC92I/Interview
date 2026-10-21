<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database;
use App\Services\Settings;
use Tests\Support\Context;
use Tests\Support\HttpClient;
use Tests\Support\TestCase;

/** End to end paid access with the sandbox payment driver: trial, checkout, confirmation, resources, admin. */
final class PaymentFlowTest extends TestCase
{
    private HttpClient $c;
    private string $email;
    private int $nurse;
    private int $cloud;

    public function setUpClass(): void
    {
        Settings::set('paywall_enabled', '1');
        $this->c = Context::http();
        $this->email = 'payflow-' . uniqid() . '@test.local';
        $this->c->register($this->email);
        $this->nurse = (int) Database::fetchColumn("SELECT id FROM professions WHERE slug = 'nurse'");
        $this->cloud = (int) Database::fetchColumn("SELECT id FROM professions WHERE slug = 'cloud-engineer'");
    }

    public function __destruct()
    {
        Settings::set('paywall_enabled', '0');
    }

    private function create(int $profession, int $count = 5): array
    {
        $this->c->get('/interview/new');
        return $this->c->post('/interview', ['profession_id' => $profession, 'experience_level' => 2, 'interview_type' => 'hr', 'locale' => 'fr',
            'difficulty' => 2, 'duration_minutes' => 20, 'question_count' => $count, 'mode' => 'training']);
    }

    private function userId(): int
    {
        return (int) Database::fetchColumn('SELECT id FROM users WHERE email = :e', ['e' => $this->email]);
    }

    public function testPricingAndLockedProfession(): void
    {
        $pricing = $this->c->get('/pricing');
        $this->assertSame(200, $pricing['status']);
        $this->assertContains("6\u{202F}550\u{00A0}FCFA", $pricing['body'], 'price displayed');
        $page = $this->c->get('/professions/nurse')['body'];
        $this->assertContains('/checkout/nurse', $page, 'unlock call to action');
        $this->assertSame(302, $this->c->get('/my-resources/nurse')['status'], 'resources locked before payment');
    }

    public function testOneShortTrialThenPaywall(): void
    {
        $trial = $this->create($this->nurse, 8);
        $this->assertSame(302, $trial['status']);
        $this->assertTrue(str_contains((string) $trial['location'], '/interview/'), 'trial simulation starts');
        $session = Database::fetch('SELECT * FROM interview_sessions WHERE user_id = :u ORDER BY id DESC LIMIT 1', ['u' => $this->userId()]);
        $this->assertSame(1, (int) $session['is_trial']);
        $this->assertSame(3, (int) $session['question_count'], 'trial limited to 3 questions');

        $blocked = $this->create($this->cloud);
        $this->assertSame(302, $blocked['status']);
        $this->assertTrue(str_ends_with((string) $blocked['location'], '/checkout/cloud-engineer'), 'second simulation redirected to checkout');
        $this->assertSame(1, (int) Database::fetchColumn('SELECT COUNT(*) FROM interview_sessions WHERE user_id = :u', ['u' => $this->userId()]));
        $setup = $this->c->get('/interview/new?profession=cloud-engineer')['body'];
        $this->assertContains('data-access-state="locked"', $setup);
    }

    public function testFailedPaymentGrantsNothing(): void
    {
        $page = $this->c->get('/checkout/cloud-engineer');
        $this->assertSame(200, $page['status']);
        $start = $this->c->post('/checkout/cloud-engineer');
        $this->assertSame(303, $start['status']);
        $this->assertTrue(preg_match('#/payments/([a-f0-9]{32})/checkout$#', (string) $start['location'], $m) === 1, 'same-origin hand-off');
        $handoff = $this->c->get('/payments/' . $m[1] . '/checkout');
        $this->assertContains('/payments/sandbox/' . $m[1], $handoff['body'], 'link to the provider checkout');
        $this->assertContains('data-auto-redirect', $handoff['body']);
        $this->c->get('/payments/sandbox/' . $m[1]);
        $this->c->post('/payments/sandbox/' . $m[1], ['outcome' => 'failed']);
        $show = $this->c->get('/payments/' . $m[1]);
        $this->assertContains('data-payment-status="failed"', $show['body']);
        $this->assertSame(302, $this->create($this->cloud)['status']);
        $this->assertSame(0, (int) Database::fetchColumn('SELECT COUNT(*) FROM profession_access WHERE user_id = :u', ['u' => $this->userId()]));
    }

    public function testSuccessfulPaymentUnlocksProfession(): void
    {
        $start = $this->c->post('/checkout/cloud-engineer');
        preg_match('#/payments/([a-f0-9]{32})/checkout$#', (string) $start['location'], $m);
        $payment = Database::fetch('SELECT * FROM payments WHERE public_id = :p', ['p' => $m[1]]);
        $this->assertSame(6550, (int) $payment['amount'], 'amount from the settings');
        $this->assertSame('XOF', $payment['currency']);
        $this->assertSame('pending', $payment['status']);
        $again = $this->c->post('/checkout/cloud-engineer');
        $this->assertSame($start['location'], $again['location'], 'open checkout reused');

        $other = Context::http();
        $other->register('payflow-other-' . uniqid() . '@test.local');
        $this->assertSame(404, $other->get('/payments/' . $m[1])['status'], 'payment visible to its owner only');
        $this->assertSame(404, $other->get('/payments/sandbox/' . $m[1])['status']);

        $this->assertContains('data-payment-status="pending"', $this->c->get('/payments/' . $m[1])['body']);
        $this->c->get('/payments/sandbox/' . $m[1]);
        $settle = $this->c->post('/payments/sandbox/' . $m[1], ['outcome' => 'completed']);
        $this->assertSame(302, $settle['status']);
        $done = $this->c->get('/payments/' . $m[1]);
        $this->assertContains('data-payment-status="completed"', $done['body']);
        $this->assertSame(1, (int) Database::fetchColumn('SELECT COUNT(*) FROM profession_access WHERE user_id = :u AND profession_id = :p AND revoked_at IS NULL', ['u' => $this->userId(), 'p' => $this->cloud]));
        $this->c->get('/payments/' . $m[1]);
        $this->assertSame(1, (int) Database::fetchColumn('SELECT COUNT(*) FROM profession_access WHERE user_id = :u', ['u' => $this->userId()]), 'confirmation is idempotent');

        $run = $this->create($this->cloud, 5);
        $this->assertTrue(str_contains((string) $run['location'], '/interview/'), 'unlocked profession can be practised');
        $session = Database::fetch('SELECT * FROM interview_sessions WHERE user_id = :u ORDER BY id DESC LIMIT 1', ['u' => $this->userId()]);
        $this->assertSame(0, (int) $session['is_trial']);
        $this->assertSame(5, (int) $session['question_count'], 'full simulation');
        $this->assertSame(302, $this->create($this->nurse)['status'], 'other professions stay locked');
        $this->assertTrue(str_ends_with((string) $this->create($this->nurse)['location'], '/checkout/nurse'));

        $res = $this->c->get('/my-resources/cloud-engineer');
        $this->assertSame(200, $res['status']);
        foreach (['Bibliothèque de questions', 'Réponse indicative', 'Éléments attendus', 'Erreurs fréquentes'] as $needle) {
            $this->assertContains($needle, $res['body']);
        }
        $this->assertNotContains('[my_resources.', $res['body']);
        $this->assertContains('/my-resources/cloud-engineer', $this->c->get('/my-resources')['body']);
        $this->assertSame(302, $this->c->get('/checkout/cloud-engineer')['status'], 'no second payment for an unlocked profession');
        $this->assertContains('Mes paiements', $this->c->get('/account')['body']);
    }

    public function testCheckoutRequiresCsrfAndAuthentication(): void
    {
        $this->assertSame(419, $this->c->post('/checkout/nurse', ['_csrf' => 'bad'])['status']);
        $guest = Context::http();
        $this->assertSame(302, $guest->get('/checkout/nurse')['status'], 'login required');
        $this->assertSame(404, $guest->request('POST', '/webhooks/geniuspay', ['x' => 1])['status'], 'webhook inactive with the sandbox driver');
        $this->assertSame(404, $this->c->get('/checkout/unknown-profession')['status']);
    }

    public function testAdminPaymentsAndManualAccess(): void
    {
        $admin = Context::http();
        $admin->login(Context::$adminEmail, Context::$adminPassword);
        $list = $admin->get('/admin/payments?status=completed');
        $this->assertSame(200, $list['status']);
        $this->assertContains($this->email, $list['body']);
        $uid = $this->userId();
        $admin->get('/admin/users/' . $uid);
        $admin->post('/admin/users/' . $uid . '/access', ['profession_id' => $this->nurse]);
        $this->assertSame('admin', Database::fetchColumn('SELECT source FROM profession_access WHERE user_id = :u AND profession_id = :p AND revoked_at IS NULL', ['u' => $uid, 'p' => $this->nurse]));
        $this->assertTrue(str_contains((string) $this->create($this->nurse)['location'], '/interview/'), 'granted profession usable');
        $admin->get('/admin/users/' . $uid);
        $admin->post('/admin/users/' . $uid . '/access/' . $this->nurse . '/revoke');
        $this->assertTrue(str_ends_with((string) $this->create($this->nurse)['location'], '/checkout/nurse'), 'revoked access');
        $this->assertTrue((bool) Database::fetchColumn("SELECT 1 FROM audit_logs WHERE action = 'access.revoked'"), 'audited');
        $this->assertSame(200, $admin->get('/admin/settings')['status']);
        $candidate = $this->c->get('/admin/payments');
        $this->assertSame(403, $candidate['status'], 'candidates cannot see payments');
    }

    public function testAccountDeletionKeepsAnonymousPaymentRecord(): void
    {
        $uid = $this->userId();
        $count = (int) Database::fetchColumn('SELECT COUNT(*) FROM payments WHERE user_id = :u', ['u' => $uid]);
        $this->assertTrue($count >= 2);
        \App\Services\UserService::deleteAccount($uid);
        $this->assertSame(0, (int) Database::fetchColumn('SELECT COUNT(*) FROM payments WHERE user_id = :u', ['u' => $uid]));
        $this->assertTrue((int) Database::fetchColumn('SELECT COUNT(*) FROM payments WHERE user_id IS NULL') >= $count, 'records kept without the user');
        $this->assertSame(0, (int) Database::fetchColumn('SELECT COUNT(*) FROM profession_access WHERE user_id = :u', ['u' => $uid]));
    }
}
