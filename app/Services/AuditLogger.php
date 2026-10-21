<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\View;

/**
 * Records sensitive and administrative operations. Never pass secrets or private answers in $details.
 */
final class AuditLogger
{
    public static function log(string $action, ?string $entityType = null, int|string|null $entityId = null, array $details = [], ?int $userId = null): void
    {
        $request = View::shared('request');
        try {
            Database::insert('audit_logs', [
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId === null ? null : (string) $entityId,
                'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip_masked' => $request ? mask_ip($request->ip()) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Audit log failure', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
