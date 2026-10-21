<?php

declare(strict_types=1);

namespace Tests\Support;

/** Shared test context filled by tests/run.php. */
final class Context
{
    public static string $base = '';
    public static string $adminEmail = 'admin@test.local';
    public static string $adminPassword = 'AdminPass123';
    public static int $port = 0;

    public static function http(): HttpClient
    {
        return new HttpClient(self::$base);
    }

    /** Last e-mail written by the "log" mail driver to the given recipient. */
    public static function lastMailTo(string $email): ?string
    {
        $log = (string) @file_get_contents(BASE_PATH . '/storage/logs/mail.log');
        $parts = explode('==== ', $log);
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            if (str_contains($parts[$i], 'To: ' . $email)) {
                return $parts[$i];
            }
        }
        return null;
    }
}
