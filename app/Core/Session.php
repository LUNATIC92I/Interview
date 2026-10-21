<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened PHP session: strict mode, HttpOnly/SameSite cookies, Secure flag behind HTTPS,
 * idle and absolute expiry, identifier regeneration on privilege change.
 */
final class Session
{
    private static bool $started = false;
    private static bool $expired = false;

    public static function start(Request $request): void
    {
        if (self::$started || PHP_SAPI === 'cli' && !defined('SESSION_FORCE')) {
            self::$started = true;
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }
        $secureSetting = Config::get('security.secure_cookie', 'auto');
        $secure = $secureSetting === 'auto' ? $request->isSecure() : (bool) $secureSetting;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        session_name($secure ? '__Host-ipsess' : 'ipsess');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;

        $now = time();
        $idle = (int) Config::get('security.session_idle_minutes', 30) * 60;
        $absolute = (int) Config::get('security.session_absolute_hours', 12) * 3600;
        $created = (int) ($_SESSION['_created'] ?? $now);
        $last = (int) ($_SESSION['_last'] ?? $now);
        if (isset($_SESSION['user_id']) && ($now - $last > $idle || $now - $created > $absolute)) {
            $locale = $_SESSION['locale'] ?? null;
            self::destroy();
            session_start();
            self::$expired = true;
            if ($locale) {
                $_SESSION['locale'] = $locale;
            }
            $created = $now;
        }
        $_SESSION['_created'] = $created;
        $_SESSION['_last'] = $now;
    }

    public static function wasExpired(): bool
    {
        return self::$expired;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_created'] = time();
        unset($_SESSION['_csrf']);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
                'httponly' => true, 'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function pullFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    /** Keep submitted values and validation errors for the next request. */
    public static function withInput(array $input, array $errors = []): void
    {
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_csrf']);
        $_SESSION['_old'] = $input;
        $_SESSION['_errors'] = $errors;
    }

    public static function pullOld(): array
    {
        $old = ['input' => $_SESSION['_old'] ?? [], 'errors' => $_SESSION['_errors'] ?? []];
        unset($_SESSION['_old'], $_SESSION['_errors']);
        return $old;
    }
}
