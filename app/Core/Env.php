<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader. Values already present in the real environment take precedence,
 * which lets the test runner and the web server override the file safely.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }
            if (preg_match('/^(["\'])(.*)\1$/s', $value, $m)) {
                $value = $m[2];
            } elseif (($hash = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $hash));
            }
            if (getenv($key) === false && !isset($_ENV[$key]) && !isset($_SERVER[$key])) {
                self::$values[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? self::$values[$key] ?? null;
        }
        if ($value === null || $value === '') {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
        putenv($key . '=' . $value);
    }
}
