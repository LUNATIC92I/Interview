<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Account lifecycle: registration, e-mail confirmation, password reset, profile and deletion.
 * Tokens are random 256 bit values; only their SHA-256 hash is stored.
 */
final class UserService
{
    /** @return list<string> error messages */
    public static function validatePassword(string $password): array
    {
        $errors = [];
        if (mb_strlen($password) < (int) Config::get('security.password_min_length', 10)) {
            $errors[] = t('validation.min', ['min' => Config::get('security.password_min_length', 10)]);
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = t('validation.password');
        }
        if (mb_strlen($password) > 200) {
            $errors[] = t('validation.max', ['max' => 200]);
        }
        return $errors;
    }

    public static function emailExists(string $email): bool
    {
        return (bool) Database::fetchColumn('SELECT 1 FROM users WHERE email = :e', ['e' => mb_strtolower(trim($email))]);
    }

    public static function create(string $email, string $name, string $password, string $locale, array $roles = ['candidate']): int
    {
        return Database::transaction(static function () use ($email, $name, $password, $locale, $roles) {
            $now = now();
            $id = Database::insert('users', [
                'email' => mb_strtolower(trim($email)),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => mb_substr(trim($name), 0, 120),
                'locale' => $locale,
                'status' => 'active',
                'email_verified_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($roles as $role) {
                $roleId = Database::fetchColumn('SELECT id FROM roles WHERE code = :c', ['c' => $role]);
                if ($roleId) {
                    Database::insert('user_roles', ['user_id' => $id, 'role_id' => $roleId]);
                }
            }
            Database::insert('profiles', ['user_id' => $id, 'updated_at' => $now]);
            Database::insert('user_preferences', ['user_id' => $id, 'locale' => $locale, 'updated_at' => $now]);
            return $id;
        });
    }

    public static function createAdmin(string $email, string $name, string $password): int
    {
        $existing = Database::fetch('SELECT id FROM users WHERE email = :e', ['e' => mb_strtolower(trim($email))]);
        if ($existing) {
            $roleId = Database::fetchColumn("SELECT id FROM roles WHERE code = 'admin'");
            Database::query('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)', ['u' => $existing['id'], 'r' => $roleId]);
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => now()], ['id' => $existing['id']]);
            AuditLogger::log('admin.promoted', 'user', (int) $existing['id'], [], (int) $existing['id']);
            return (int) $existing['id'];
        }
        $id = self::create($email, $name, $password, (string) Config::get('app.locale', 'fr'), ['candidate', 'admin']);
        Database::update('users', ['email_verified_at' => now()], ['id' => $id]);
        AuditLogger::log('admin.created', 'user', $id, [], $id);
        return $id;
    }

    public static function adminExists(): bool
    {
        return (bool) Database::fetchColumn("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.code = 'admin' LIMIT 1");
    }

    private static function newToken(string $table, int $userId, int $ttlSeconds): string
    {
        $token = bin2hex(random_bytes(32));
        Database::query("UPDATE $table SET used_at = :n WHERE user_id = :u AND used_at IS NULL", ['n' => now(), 'u' => $userId]);
        Database::insert($table, [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttlSeconds),
            'created_at' => now(),
        ]);
        return $token;
    }

    /** Consume a token; returns the user id or null when invalid, used or expired. */
    private static function consumeToken(string $table, string $token): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = Database::fetch("SELECT * FROM $table WHERE token_hash = :h", ['h' => hash('sha256', $token)]);
        if (!$row || $row['used_at'] !== null || strtotime($row['expires_at'] . ' UTC') < time()) {
            return null;
        }
        Database::update($table, ['used_at' => now()], ['id' => $row['id']]);
        return (int) $row['user_id'];
    }

    public static function sendVerification(array $user): bool
    {
        if (!Mailer::isConfigured()) {
            return false;
        }
        $token = self::newToken('email_verification_tokens', (int) $user['id'], (int) Config::get('security.verify_token_hours', 48) * 3600);
        $link = absolute_url('/email/verify', ['token' => $token]);
        $loc = $user['locale'];
        return Mailer::send(
            $user['email'],
            t('emails.verify.subject', [], $loc),
            t('emails.verify.body', ['name' => $user['display_name'], 'link' => $link, 'hours' => Config::get('security.verify_token_hours', 48)], $loc)
        );
    }

    public static function verifyEmail(string $token): ?int
    {
        $userId = self::consumeToken('email_verification_tokens', $token);
        if ($userId) {
            Database::update('users', ['email_verified_at' => now(), 'updated_at' => now()], ['id' => $userId]);
            AuditLogger::log('user.email_verified', 'user', $userId, [], $userId);
        }
        return $userId;
    }

    /** Always behaves the same whether or not the address exists (no account enumeration). */
    public static function requestPasswordReset(string $email): void
    {
        $user = Database::fetch("SELECT * FROM users WHERE email = :e AND status = 'active'", ['e' => mb_strtolower(trim($email))]);
        if (!$user || !Mailer::isConfigured()) {
            return;
        }
        $minutes = (int) Config::get('security.reset_token_minutes', 60);
        $token = self::newToken('password_reset_tokens', (int) $user['id'], $minutes * 60);
        $link = absolute_url('/password/reset', ['token' => $token]);
        Mailer::send(
            $user['email'],
            t('emails.reset.subject', [], $user['locale']),
            t('emails.reset.body', ['name' => $user['display_name'], 'link' => $link, 'minutes' => $minutes], $user['locale'])
        );
        AuditLogger::log('user.password_reset_requested', 'user', (int) $user['id'], [], (int) $user['id']);
    }

    public static function tokenIsValid(string $table, string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }
        $row = Database::fetch("SELECT used_at, expires_at FROM $table WHERE token_hash = :h", ['h' => hash('sha256', $token)]);
        return $row && $row['used_at'] === null && strtotime($row['expires_at'] . ' UTC') >= time();
    }

    public static function resetPassword(string $token, string $password): bool
    {
        $userId = self::consumeToken('password_reset_tokens', $token);
        if (!$userId) {
            return false;
        }
        Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => now()], ['id' => $userId]);
        RateLimiter::clear('login:' . $userId);
        AuditLogger::log('user.password_reset', 'user', $userId, [], $userId);
        return true;
    }

    /**
     * Permanently delete an account and the personal data attached to it, explicitly and in dependency order
     * (the schema deliberately avoids cascading deletes on history tables).
     */
    public static function deleteAccount(int $userId): void
    {
        Database::transaction(static function () use ($userId) {
            $sessionIds = array_column(Database::fetchAll('SELECT id FROM interview_sessions WHERE user_id = :u', ['u' => $userId]), 'id');
            if ($sessionIds) {
                $p = [];
                $in = Database::in($sessionIds, $p, 's');
                Database::query("DELETE e FROM answer_evaluations e JOIN interview_answers a ON a.id = e.answer_id WHERE a.session_id IN $in", $p);
                Database::query("DELETE FROM interview_answers WHERE session_id IN $in", $p);
                Database::query("DELETE FROM interview_sessions WHERE id IN $in", $p);
            }
            foreach (['user_skill_progress', 'user_goals', 'login_history', 'password_reset_tokens', 'email_verification_tokens', 'user_preferences', 'profiles', 'user_roles'] as $table) {
                Database::query("DELETE FROM $table WHERE user_id = :u", ['u' => $userId]);
            }
            Database::query('UPDATE content_reports SET user_id = NULL, message = NULL WHERE user_id = :u', ['u' => $userId]);
            Database::query('DELETE FROM rate_limits WHERE rl_key LIKE :k', ['k' => 'ai:' . $userId . ':%']);
            Database::delete('users', ['id' => $userId]);
        });
        AuditLogger::log('user.deleted', 'user', $userId, [], null);
    }
}
