<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

/**
 * Session based authentication with role checks.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            if ($id) {
                $user = Database::fetch('SELECT * FROM users WHERE id = :id AND status = :s', ['id' => (int) $id, 's' => 'active']);
                if ($user) {
                    $user['roles'] = array_column(Database::fetchAll(
                        'SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id',
                        ['id' => (int) $user['id']]
                    ), 'code');
                    self::$user = $user;
                } else {
                    Session::forget('user_id');
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && in_array('admin', $u['roles'], true);
    }

    public static function hasRole(string $role): bool
    {
        $u = self::user();
        return $u !== null && in_array($role, $u['roles'], true);
    }

    /** Validate credentials; returns the user row on success. */
    public static function attempt(string $email, string $password): ?array
    {
        $user = Database::fetch('SELECT * FROM users WHERE email = :e', ['e' => mb_strtolower(trim($email))]);
        // Always run password_verify to keep timing similar for unknown accounts.
        $hash = $user['password_hash'] ?? password_hash(random_bytes(8), PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash);
        if (!$user || !$valid || $user['status'] !== 'active') {
            return null;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user['id']]);
        }
        return $user;
    }

    public static function login(array $user, Request $request): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('locale', $user['locale']);
        Database::update('users', ['last_login_at' => now()], ['id' => $user['id']]);
        Database::insert('login_history', [
            'user_id' => $user['id'],
            'event' => 'login',
            'ip_masked' => mask_ip($request->ip()),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        $locale = Session::get('locale');
        Session::destroy();
        if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
            session_start();
        }
        Session::regenerate();
        if ($locale) {
            Session::set('locale', $locale);
        }
        self::$user = null;
        self::$loaded = true;
    }

    /** Forget cached state (after profile updates or in tests). */
    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }
}
