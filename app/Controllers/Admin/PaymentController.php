<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Paginator;
use App\Models\Profession;
use App\Services\AccessService;
use App\Services\Auth;
use App\Services\AuditLogger;
use App\Services\PaymentService;

/** Payments follow-up and manual access management (for example after an offline payment). */
final class PaymentController extends AdminController
{
    public const STATUSES = ['created', 'pending', 'processing', 'completed', 'failed', 'cancelled', 'expired', 'refunded'];

    public function index(Request $request): Response
    {
        $q = mb_substr(trim((string) ($request->query['q'] ?? '')), 0, 100);
        $status = (string) ($request->query['status'] ?? '');
        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.email LIKE :q1 OR pay.provider_reference LIKE :q2)';
            $params += ['q1' => Database::like($q), 'q2' => Database::like($q)];
        }
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'pay.status = :st';
            $params['st'] = $status;
        }
        $sqlWhere = implode(' AND ', $where);
        $page = Paginator::page($request->query);
        $per = 25;
        $total = (int) Database::fetchColumn("SELECT COUNT(*) FROM payments pay LEFT JOIN users u ON u.id = pay.user_id WHERE $sqlWhere", $params);
        $items = Database::fetchAll(
            "SELECT pay.*, u.email, u.display_name, COALESCE(t1.name, t2.name) AS profession_name
             FROM payments pay
             LEFT JOIN users u ON u.id = pay.user_id
             JOIN professions p ON p.id = pay.profession_id
             LEFT JOIN profession_translations t1 ON t1.profession_id = p.id AND t1.locale = :l
             LEFT JOIN profession_translations t2 ON t2.profession_id = p.id AND t2.locale = :o
             WHERE $sqlWhere ORDER BY pay.id DESC LIMIT :lim OFFSET :off",
            $params + ['l' => locale(), 'o' => locale() === 'fr' ? 'en' : 'fr', 'lim' => $per, 'off' => ($page - 1) * $per]
        );
        $totals = Database::fetchAll(
            "SELECT currency, COUNT(*) AS n, SUM(amount) AS total FROM payments WHERE status = 'completed' GROUP BY currency"
        );
        return $this->view('admin/payments/index', [
            'title' => t('admin.payments.title'), 'items' => $items, 'q' => $q, 'status' => $status, 'totals' => $totals,
            'pager' => new Paginator($total, $page, $per, '/admin/payments', ['q' => $q, 'status' => $status]),
        ]);
    }

    public function refresh(Request $request, string $id): Response
    {
        $payment = Database::fetch('SELECT * FROM payments WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $updated = PaymentService::refresh($payment);
        AuditLogger::log('admin.payment_refreshed', 'payment', (int) $payment['id'], ['status' => $updated['status']]);
        $this->flash('success', t('admin.payments.refreshed', ['status' => t('payment.status.' . $updated['status'])]));
        return $this->redirect('/admin/payments?q=' . rawurlencode((string) $payment['provider_reference']));
    }

    public function grant(Request $request, string $id): Response
    {
        $user = Database::fetch('SELECT id FROM users WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $profession = Profession::find($request->int('profession_id'));
        if (!$profession) {
            $this->flash('error', t('validation.required'));
            return $this->redirect('/admin/users/' . $user['id']);
        }
        AccessService::grant((int) $user['id'], (int) $profession['id'], 'admin', null, Auth::id());
        $this->flash('success', t('admin.access.granted', ['profession' => $profession['name']]));
        return $this->redirect('/admin/users/' . $user['id']);
    }

    public function revoke(Request $request, string $id, string $professionId): Response
    {
        $user = Database::fetch('SELECT id FROM users WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        AccessService::revoke((int) $user['id'], (int) $professionId, Auth::id());
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/users/' . $user['id']);
    }
}
