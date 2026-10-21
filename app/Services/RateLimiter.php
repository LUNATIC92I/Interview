<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Fixed window rate limiter persisted in MySQL so it works across PHP workers.
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $row = Database::fetch('SELECT hits, window_start FROM rate_limits WHERE rl_key = :k', ['k' => self::key($key)]);
        if (!$row) {
            return false;
        }
        if (strtotime($row['window_start'] . ' UTC') + $windowSeconds < time()) {
            return false;
        }
        return (int) $row['hits'] >= $maxAttempts;
    }

    public static function hit(string $key, int $windowSeconds): int
    {
        $k = self::key($key);
        $row = Database::fetch('SELECT hits, window_start FROM rate_limits WHERE rl_key = :k', ['k' => $k]);
        if (!$row || strtotime($row['window_start'] . ' UTC') + $windowSeconds < time()) {
            Database::query(
                'INSERT INTO rate_limits (rl_key, hits, window_start) VALUES (:k, 1, :w)
                 ON DUPLICATE KEY UPDATE hits = 1, window_start = VALUES(window_start)',
                ['k' => $k, 'w' => now()]
            );
            return 1;
        }
        Database::query('UPDATE rate_limits SET hits = hits + 1 WHERE rl_key = :k', ['k' => $k]);
        return (int) $row['hits'] + 1;
    }

    /** Record a hit and report whether the limit is now exceeded. */
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        if (self::tooManyAttempts($key, $maxAttempts, $windowSeconds)) {
            return false;
        }
        self::hit($key, $windowSeconds);
        return true;
    }

    public static function clear(string $key): void
    {
        Database::delete('rate_limits', ['rl_key' => self::key($key)]);
    }

    private static function key(string $key): string
    {
        return strlen($key) > 150 ? substr($key, 0, 20) . hash('sha256', $key) : $key;
    }

    /** Periodic purge of expired windows. */
    public static function purge(int $olderThanSeconds = 86400): void
    {
        Database::query('DELETE FROM rate_limits WHERE window_start < :d', ['d' => gmdate('Y-m-d H:i:s', time() - $olderThanSeconds)]);
    }
}
