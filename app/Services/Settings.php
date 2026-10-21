<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Platform settings stored in app_settings and editable by administrators.
 */
final class Settings
{
    /** Default values and types, also used to seed the table. */
    public const DEFAULTS = [
        'registration_open' => ['bool', '1'],
        'require_email_verification' => ['bool', '0'],
        'ai_evaluation_enabled' => ['bool', '1'],
        'max_sessions_per_day' => ['int', '30'],
        'contact_email' => ['string', 'contact@example.com'],
        'maintenance_message' => ['string', ''],
        'default_question_count' => ['int', '6'],
        'paywall_enabled' => ['bool', '1'],
        'access_price' => ['int', '6550'],
        'access_currency' => ['string', 'XOF'],
        'trial_simulations' => ['int', '1'],
        'trial_question_limit' => ['int', '3'],
    ];

    private static ?array $cache = null;

    private static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::fetchAll('SELECT setting_key, setting_value FROM app_settings') as $row) {
                    self::$cache[$row['setting_key']] = (string) $row['setting_value'];
                }
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default ?? (self::DEFAULTS[$key][1] ?? null);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function set(string $key, string $value): void
    {
        $type = self::DEFAULTS[$key][0] ?? 'string';
        Database::query(
            'INSERT INTO app_settings (setting_key, setting_value, value_type, updated_at) VALUES (:k, :v, :t, :u)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
            ['k' => $key, 'v' => $value, 't' => $type, 'u' => now()]
        );
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
