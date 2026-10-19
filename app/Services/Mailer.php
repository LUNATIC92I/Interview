<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;

/**
 * Outgoing e-mail. Drivers: none (disabled), log (development file), mail (PHP mail()), smtp (built-in client).
 */
final class Mailer
{
    public static function isConfigured(): bool
    {
        $driver = (string) Config::get('mail.driver', 'none');
        if ($driver === 'smtp') {
            return (string) Config::get('mail.host', '') !== '';
        }
        return in_array($driver, ['log', 'mail'], true);
    }

    public static function send(string $to, string $subject, string $textBody): bool
    {
        $driver = (string) Config::get('mail.driver', 'none');
        $from = (string) Config::get('mail.from');
        $fromName = (string) Config::get('mail.from_name', 'InterviewPro');
        $to = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        try {
            return match ($driver) {
                'log' => self::toLog($to, $subject, $textBody),
                'mail' => mail(
                    $to,
                    '=?UTF-8?B?' . base64_encode($subject) . '?=',
                    $textBody,
                    "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0"
                ),
                'smtp' => (new SmtpClient(
                    (string) Config::get('mail.host'),
                    (int) Config::get('mail.port', 587),
                    (string) Config::get('mail.encryption', 'tls'),
                    (string) Config::get('mail.username'),
                    (string) Config::get('mail.password'),
                ))->send($from, $fromName, $to, $subject, $textBody),
                default => false,
            };
        } catch (\Throwable $e) {
            Logger::error('Mail delivery failed', ['driver' => $driver, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private static function toLog(string $to, string $subject, string $body): bool
    {
        $file = BASE_PATH . '/storage/logs/mail.log';
        $entry = sprintf("==== %s\nTo: %s\nSubject: %s\n\n%s\n\n", gmdate('c'), $to, $subject, $body);
        return file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
