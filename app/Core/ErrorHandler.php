<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Converts PHP errors to exceptions and makes sure technical details are never shown in production.
 */
final class ErrorHandler
{
    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;
        error_reporting(E_ALL);
        ini_set('display_errors', $debug && PHP_SAPI !== 'cli' ? '1' : (PHP_SAPI === 'cli' ? '1' : '0'));
        ini_set('log_errors', '1');
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    public static function isDebug(): bool
    {
        return self::$debug;
    }

    public static function report(\Throwable $e): void
    {
        Logger::error($e->getMessage(), [
            'type' => $e::class,
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 12),
        ]);
    }
}
