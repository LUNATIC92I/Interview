<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Translator;

/**
 * Paid access per profession. A candidate can run simulations and open the premium resources of a
 * profession once it is unlocked (payment confirmed or granted by an administrator). Before that,
 * a limited number of short trial simulations is offered. Administrators always have access, and
 * everything is open when the paywall setting is turned off.
 */
final class AccessService
{
    public static function paywallEnabled(): bool
    {
        return Settings::bool('paywall_enabled');
    }

    public static function price(): int
    {
        return max(0, Settings::int('access_price'));
    }

    public static function currency(): string
    {
        return strtoupper((string) Settings::get('access_currency')) ?: 'XOF';
    }

    private static function isAdmin(int $userId): bool
    {
        return (bool) Database::fetchColumn(
            "SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :u AND r.code = 'admin'",
            ['u' => $userId]
        );
    }

    /** True when the profession is unlocked for this user (or when no payment is required). */
    public static function hasAccess(int $userId, int $professionId): bool
    {
        if (!self::paywallEnabled() || self::isAdmin($userId)) {
            return true;
        }
        return (bool) Database::fetchColumn(
            'SELECT 1 FROM profession_access WHERE user_id = :u AND profession_id = :p AND revoked_at IS NULL',
            ['u' => $userId, 'p' => $professionId]
        );
    }

    public static function trialsUsed(int $userId): int
    {
        return (int) Database::fetchColumn('SELECT COUNT(*) FROM interview_sessions WHERE user_id = :u AND is_trial = 1', ['u' => $userId]);
    }

    public static function trialsLeft(int $userId): int
    {
        return max(0, Settings::int('trial_simulations') - self::trialsUsed($userId));
    }

    public static function trialQuestionLimit(): int
    {
        return max(3, min(15, Settings::int('trial_question_limit')));
    }

    /**
     * Whether a new simulation may start for this profession.
     *
     * @return array{allowed: bool, trial: bool}
     */
    public static function decide(int $userId, int $professionId): array
    {
        if (self::hasAccess($userId, $professionId)) {
            return ['allowed' => true, 'trial' => false];
        }
        $trial = self::trialsLeft($userId) > 0;
        return ['allowed' => $trial, 'trial' => $trial];
    }

    /** Professions unlocked by the user, in the active locale. */
    public static function unlocked(int $userId): array
    {
        $loc = Translator::locale();
        return Database::fetchAll(
            'SELECT a.*, p.slug, p.icon, p.category_id, COALESCE(t1.name, t2.name) AS name, COALESCE(t1.summary, t2.summary) AS summary
             FROM profession_access a
             JOIN professions p ON p.id = a.profession_id
             LEFT JOIN profession_translations t1 ON t1.profession_id = p.id AND t1.locale = :l
             LEFT JOIN profession_translations t2 ON t2.profession_id = p.id AND t2.locale = :o
             WHERE a.user_id = :u AND a.revoked_at IS NULL
             ORDER BY a.granted_at DESC',
            ['u' => $userId, 'l' => $loc, 'o' => Translator::other($loc)]
        );
    }

    /** Unlock a profession. Idempotent: an existing grant is kept, a revoked one is restored. */
    public static function grant(int $userId, int $professionId, string $source, ?int $paymentId = null, ?int $grantedBy = null): void
    {
        $existing = Database::fetch(
            'SELECT * FROM profession_access WHERE user_id = :u AND profession_id = :p',
            ['u' => $userId, 'p' => $professionId]
        );
        if ($existing && $existing['revoked_at'] === null) {
            return;
        }
        $data = ['source' => $source, 'payment_id' => $paymentId, 'granted_by' => $grantedBy, 'granted_at' => now(), 'revoked_at' => null];
        if ($existing) {
            Database::update('profession_access', $data, ['id' => $existing['id']]);
        } else {
            Database::insert('profession_access', $data + ['user_id' => $userId, 'profession_id' => $professionId]);
        }
        AuditLogger::log('access.granted', 'profession', $professionId, ['user_id' => $userId, 'source' => $source, 'payment_id' => $paymentId], $grantedBy);
    }

    public static function revoke(int $userId, int $professionId, ?int $by = null, string $reason = 'admin'): void
    {
        $n = Database::query(
            'UPDATE profession_access SET revoked_at = :d WHERE user_id = :u AND profession_id = :p AND revoked_at IS NULL',
            ['d' => now(), 'u' => $userId, 'p' => $professionId]
        )->rowCount();
        if ($n > 0) {
            AuditLogger::log('access.revoked', 'profession', $professionId, ['user_id' => $userId, 'reason' => $reason], $by);
        }
    }
}
