<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Paginator;

final class AuditController extends AdminController
{
    public function index(Request $request): Response
    {
        $action = mb_substr(trim((string) ($request->query['action'] ?? '')), 0, 80);
        $where = '1 = 1';
        $params = [];
        if ($action !== '') {
            $where = 'a.action LIKE :a';
            $params['a'] = Database::like($action);
        }
        $page = Paginator::page($request->query);
        $total = (int) Database::fetchColumn("SELECT COUNT(*) FROM audit_logs a WHERE $where", $params);
        $items = Database::fetchAll(
            "SELECT a.*, u.display_name, u.email FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $where ORDER BY a.id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => 50, 'off' => ($page - 1) * 50]
        );
        return $this->view('admin/audit/index', ['title' => t('admin.audit.title'), 'items' => $items, 'action' => $action,
            'pager' => new Paginator($total, $page, 50, '/admin/audit', ['action' => $action])]);
    }
}
