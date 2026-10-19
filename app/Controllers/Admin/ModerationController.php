<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Paginator;
use App\Services\AuditLogger;

/** Content reports sent by candidates about questions. */
final class ModerationController extends AdminController
{
    public function index(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? 'open');
        if (!in_array($status, ['open', 'resolved', 'dismissed', 'all'], true)) {
            $status = 'open';
        }
        $where = $status === 'all' ? '1 = 1' : 'r.status = :st';
        $params = $status === 'all' ? [] : ['st' => $status];
        $page = Paginator::page($request->query);
        $total = (int) Database::fetchColumn("SELECT COUNT(*) FROM content_reports r WHERE $where", $params);
        $items = Database::fetchAll(
            "SELECT r.*, q.code, COALESCE(t1.body, t2.body) AS body FROM content_reports r
             JOIN questions q ON q.id = r.question_id
             LEFT JOIN question_translations t1 ON t1.question_id = q.id AND t1.locale = :l
             LEFT JOIN question_translations t2 ON t2.question_id = q.id AND t2.locale = :o
             WHERE $where ORDER BY r.created_at DESC LIMIT :lim OFFSET :off",
            $params + ['l' => locale(), 'o' => locale() === 'fr' ? 'en' : 'fr', 'lim' => 20, 'off' => ($page - 1) * 20]
        );
        return $this->view('admin/reports/index', ['title' => t('admin.moderation.title'), 'items' => $items, 'status' => $status,
            'pager' => new Paginator($total, $page, 20, '/admin/reports', ['status' => $status])]);
    }

    public function update(Request $request, string $id): Response
    {
        $report = Database::fetch('SELECT * FROM content_reports WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $status = $request->str('status');
        if (!in_array($status, ['open', 'resolved', 'dismissed'], true)) {
            throw new HttpException(404);
        }
        Database::update('content_reports', [
            'status' => $status, 'admin_note' => mb_substr($request->str('admin_note'), 0, 1000) ?: null,
            'resolved_by' => $status === 'open' ? null : \App\Services\Auth::id(), 'resolved_at' => $status === 'open' ? null : now(),
        ], ['id' => $report['id']]);
        AuditLogger::log('admin.report_' . $status, 'content_report', (int) $report['id']);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/reports');
    }
}
