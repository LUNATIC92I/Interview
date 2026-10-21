<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database;
use Tests\Support\Context;
use Tests\Support\TestCase;

final class SecurityTest extends TestCase
{
    public function testCsrfIsRequired(): void
    {
        $c = Context::http();
        $c->get('/login');
        $this->assertSame(419, $c->post('/login', ['email' => 'a@b.c', 'password' => 'x'], null, false)['status'], 'missing token');
        $this->assertSame(419, $c->post('/login', ['email' => 'a@b.c', 'password' => 'x', '_csrf' => str_repeat('a', 64)])['status'], 'forged token');
        $email = 'csrf-' . uniqid() . '@test.local';
        $c->register($email);
        $res = $c->request('POST', '/api/interview/' . str_repeat('a', 32) . '/draft', ['position' => 1, 'text' => 'x'], ['X-CSRF-Token: bad'], true);
        $this->assertSame(419, $res['status'], 'API calls require the header token');
    }

    public function testSecurityHeadersAndCookies(): void
    {
        $r = Context::http()->get('/');
        foreach (['content-security-policy', 'x-frame-options', 'x-content-type-options', 'referrer-policy'] as $h) {
            $this->assertTrue(isset($r['headers'][$h]), "header $h");
        }
        $this->assertContains("frame-ancestors 'none'", $r['headers']['content-security-policy']);
        $c = Context::http();
        $raw = $c->request('GET', '/login');
        $jarCookie = $raw['headers']['set-cookie'] ?? '';
        $this->assertContains('HttpOnly', $jarCookie);
        $this->assertContains('SameSite=Lax', $jarCookie);
    }

    public function testSqlInjectionAttemptsAreHarmless(): void
    {
        $count = (int) Database::fetchColumn('SELECT COUNT(*) FROM users');
        $c = Context::http();
        foreach (["' OR '1'='1", "x%' UNION SELECT password_hash FROM users -- ", "1; DROP TABLE users; --"] as $payload) {
            $r = $c->get('/professions?q=' . urlencode($payload) . '&category=' . urlencode($payload));
            $this->assertSame(200, $r['status']);
            $this->assertNotContains('$2y$', $r['body'], 'no hash leak');
            $this->assertNotContains('SQLSTATE', $r['body']);
        }
        $login = $c->login("admin@test.local' -- ", "' OR '1'='1");
        $this->assertContains('/login', (string) $login['location'], 'injection does not authenticate');
        $this->assertSame($count, (int) Database::fetchColumn('SELECT COUNT(*) FROM users'));
    }

    public function testOutputIsEscaped(): void
    {
        $c = Context::http();
        $c->register('xss-' . uniqid() . '@test.local', 'Password123', '<script>alert(1)</script>');
        $body = $c->get('/dashboard')['body'];
        $this->assertNotContains('<script>alert(1)</script>', $body);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function testUsersCannotAccessOtherUsersData(): void
    {
        $a = Context::http();
        $a->register('owner-' . uniqid() . '@test.local');
        $a->get('/interview/new');
        $created = $a->post('/interview', ['profession_id' => 1, 'experience_level' => 2, 'interview_type' => 'hr', 'locale' => 'fr', 'difficulty' => 2, 'duration_minutes' => 15, 'question_count' => 3, 'mode' => 'realistic']);
        preg_match('#/interview/([a-f0-9]{32})#', (string) $created['location'], $m);
        $id = $m[1];
        $a->get('/interview/' . $id);
        $a->json("/api/interview/$id/finish");

        $b = Context::http();
        $b->register('intruder-' . uniqid() . '@test.local');
        $b->get('/dashboard');
        foreach (["/interview/$id", "/reports/$id", "/reports/$id/pdf", "/api/interview/$id/state", "/reports/compare?a=$id&b=$id"] as $path) {
            $this->assertSame(404, $b->get($path)['status'], "foreign resource hidden: $path");
        }
        $this->assertSame(404, $b->json("/api/interview/$id/draft", ['position' => 1, 'text' => 'hack'])['status']);
        $this->assertSame(200, $a->get("/reports/$id")['status'], 'owner still has access');
    }

    public function testAdminAreaPermissions(): void
    {
        $this->assertSame(302, Context::http()->get('/admin')['status'], 'guest redirected to login');
        $candidate = Context::http();
        $candidate->register('cand-' . uniqid() . '@test.local');
        foreach (['/admin', '/admin/users', '/admin/questions', '/admin/questions/export', '/admin/settings'] as $path) {
            $this->assertSame(403, $candidate->get($path)['status'], "candidate forbidden on $path");
        }
        $this->assertSame(403, $candidate->post('/admin/settings', ['max_sessions_per_day' => 1], '/dashboard')['status']);
        $admin = Context::http();
        $admin->login(Context::$adminEmail, Context::$adminPassword);
        $this->assertSame(200, $admin->get('/admin')['status']);
    }

    public function testSensitiveFilesAreNotServed(): void
    {
        $c = Context::http();
        foreach (['/.env', '/../.env', '/storage/logs/mail.log', '/app/bootstrap.php', '/config/database.php', '/composer.json'] as $path) {
            $r = $c->get($path);
            $this->assertNotContains('DB_PASSWORD', $r['body']);
            $this->assertNotContains('<?php', $r['body']);
            $this->assertTrue(in_array($r['status'], [404, 400], true), "$path not served (got {$r['status']})");
        }
    }

    public function testOpenRedirectIsBlockedOnLanguageSwitch(): void
    {
        $r = Context::http()->get('/lang/en?redirect=' . urlencode('//evil.example.com/x'));
        $this->assertSame(302, $r['status']);
        $this->assertNotContains('evil.example.com', (string) $r['location']);
    }
}
