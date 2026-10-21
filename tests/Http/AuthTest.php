<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database;
use Tests\Support\Context;
use Tests\Support\TestCase;

final class AuthTest extends TestCase
{
    public function testRegistrationLoginLogout(): void
    {
        $email = 'auth-' . uniqid() . '@test.local';
        $c = Context::http();
        $r = $c->register($email, 'Password123', 'Ada Lovelace');
        $this->assertSame(302, $r['status']);
        $this->assertContains('/dashboard', (string) $r['location']);
        $this->assertContains('Ada Lovelace', $c->get('/dashboard')['body']);
        $hash = (string) Database::fetchColumn('SELECT password_hash FROM users WHERE email = :e', ['e' => $email]);
        $this->assertTrue(password_verify('Password123', $hash) && !str_contains($hash, 'Password123'), 'password stored hashed');

        $out = $c->post('/logout', [], '/dashboard');
        $this->assertSame(302, $out['status']);
        $this->assertSame(302, $c->get('/dashboard')['status'], 'logged out user is redirected');

        $c2 = Context::http();
        $c2->get('/login');
        $before = $this->sessionCookie($c2);
        $this->assertSame(302, $c2->login($email)['status']);
        $this->assertSame(200, $c2->get('/dashboard')['status']);
        $this->assertTrue($before !== $this->sessionCookie($c2), 'session id regenerated after login');
        // Registration signs the user in, then the explicit login: two entries, plus one logout.
        $this->assertSame(2, (int) Database::fetchColumn("SELECT COUNT(*) FROM login_history h JOIN users u ON u.id = h.user_id WHERE u.email = :e AND h.event = 'login'", ['e' => $email]));
        $this->assertSame(1, (int) Database::fetchColumn("SELECT COUNT(*) FROM login_history h JOIN users u ON u.id = h.user_id WHERE u.email = :e AND h.event = 'logout'", ['e' => $email]));
    }

    private function sessionCookie(\Tests\Support\HttpClient $c): string
    {
        $r = new \ReflectionProperty($c, 'jar');
        $jar = (string) @file_get_contents($r->getValue($c));
        preg_match('/ipsess\s+(\S+)/', $jar, $m);
        return $m[1] ?? '';
    }

    public function testRegistrationValidation(): void
    {
        $c = Context::http();
        $c->get('/register');
        $r = $c->post('/register', ['display_name' => '', 'email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'other']);
        $this->assertSame(302, $r['status']);
        $page = $c->get('/register')['body'];
        $this->assertContains('aria-invalid="true"', $page);
        $this->assertContains('field-error', $page);
        $this->assertSame(0, (int) Database::fetchColumn("SELECT COUNT(*) FROM users WHERE email = 'not-an-email'"));
        $dup = Context::http();
        $dup->register(Context::$adminEmail);
        $this->assertSame(1, (int) Database::fetchColumn('SELECT COUNT(*) FROM users WHERE email = :e', ['e' => Context::$adminEmail]), 'no duplicate account');
    }

    public function testWrongPasswordAndThrottling(): void
    {
        $email = 'throttle-' . uniqid() . '@test.local';
        Context::http()->register($email);
        $c = Context::http();
        for ($i = 0; $i < 5; $i++) {
            $r = $c->login($email, 'WrongPassword1');
            $this->assertContains('/login', (string) $r['location']);
        }
        $c->get('/login');
        $blocked = $c->post('/login', ['email' => $email, 'password' => 'Password123']);
        $this->assertContains('/login', (string) $blocked['location'], 'correct password refused while throttled');
        $this->assertContains('Trop de tentatives', $c->get('/login')['body'], 'throttling message shown');
        Database::query('DELETE FROM rate_limits');
        $this->assertContains('/dashboard', (string) Context::http()->login($email)['location']);
    }

    public function testPasswordResetFlow(): void
    {
        $email = 'reset-' . uniqid() . '@test.local';
        Context::http()->register($email);
        $c = Context::http();
        $c->get('/password/forgot');
        $r = $c->post('/password/forgot', ['email' => $email]);
        $this->assertSame(302, $r['status']);
        $mail = (string) Context::lastMailTo($email);
        $this->assertTrue(preg_match('#/password/reset\?token=([a-f0-9]{64})#', $mail, $m) === 1, 'reset link e-mailed');
        $token = $m[1];
        $this->assertSame(0, (int) Database::fetchColumn('SELECT COUNT(*) FROM password_reset_tokens WHERE token_hash = :t', ['t' => $token]), 'raw token never stored');

        $form = $c->get('/password/reset?token=' . $token);
        $this->assertSame(200, $form['status']);
        $done = $c->post('/password/reset', ['token' => $token, 'password' => 'NewPassword456', 'password_confirmation' => 'NewPassword456']);
        $this->assertContains('/login', (string) $done['location']);
        $this->assertContains('/dashboard', (string) Context::http()->login($email, 'NewPassword456')['location']);
        $this->assertSame(302, $c->get('/password/reset?token=' . $token)['status'], 'token single use');

        // Unknown address: same response, no e-mail
        $unknown = Context::http();
        $unknown->get('/password/forgot');
        $this->assertSame(302, $unknown->post('/password/forgot', ['email' => 'nobody-' . uniqid() . '@test.local'])['status']);
    }

    public function testEmailVerification(): void
    {
        $email = 'verify-' . uniqid() . '@test.local';
        Context::http()->register($email);
        $mail = (string) Context::lastMailTo($email);
        $this->assertTrue(preg_match('#/email/verify\?token=([a-f0-9]{64})#', $mail, $m) === 1, 'verification e-mail sent');
        Context::http()->get('/email/verify?token=' . $m[1]);
        $this->assertTrue(Database::fetchColumn('SELECT email_verified_at FROM users WHERE email = :e', ['e' => $email]) !== null);
    }

    public function testAccountDeletionRemovesPersonalData(): void
    {
        $email = 'delete-' . uniqid() . '@test.local';
        $c = Context::http();
        $c->register($email);
        $id = (int) Database::fetchColumn('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        $c->get('/interview/new');
        $c->post('/interview', ['profession_id' => 1, 'experience_level' => 2, 'interview_type' => 'hr', 'locale' => 'fr', 'difficulty' => 2, 'duration_minutes' => 15, 'question_count' => 3, 'mode' => 'realistic']);
        $this->assertSame(1, (int) Database::fetchColumn('SELECT COUNT(*) FROM interview_sessions WHERE user_id = :u', ['u' => $id]));
        $c->get('/account');
        $wrong = $c->post('/account/delete', ['current_password' => 'nope', 'confirm' => 'SUPPRIMER']);
        $this->assertContains('/account', (string) $wrong['location']);
        $this->assertSame(1, (int) Database::fetchColumn('SELECT COUNT(*) FROM users WHERE id = :u', ['u' => $id]));
        $c->get('/account');
        $c->post('/account/delete', ['current_password' => 'Password123', 'confirm' => 'SUPPRIMER']);
        foreach (['users' => 'id', 'interview_sessions' => 'user_id', 'profiles' => 'user_id', 'login_history' => 'user_id'] as $table => $col) {
            $this->assertSame(0, (int) Database::fetchColumn("SELECT COUNT(*) FROM $table WHERE $col = :u", ['u' => $id]), "$table purged");
        }
    }
}
