<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File logger writing JSON lines into storage/logs (outside the public web root).
 */
final class Logger
{
    public static function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $line = json_encode([
            'time' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        @file_put_contents($dir . '/' . $channel . '-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function security(string $message, array $context = []): void
    {
        self::log('warning', $message, $context, 'security');
    }
}
