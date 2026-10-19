<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Paginator;
use App\Services\AuditLogger;
use App\Services\Auth;

final class UserController extends AdminController
{
    public function index(Request $request): Response
    {
        $q = mb_substr(trim((string) ($request->query['q'] ?? '')), 0, 100);
        $status = (string) ($request->query['status'] ?? '');
        $role = (string) ($request->query['role'] ?? '');
        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.email LIKE :q1 OR u.display_name LIKE :q2)';
            $params += ['q1' => \App\Core\Database::like($q), 'q2' => \App\Core\Database::like($q)];
        }
        if (in_array($status, ['active', 'suspended'], true)) {
            $where[] = 'u.status = :st';
            $params['st'] = $status;
        }
        if (in_array($role, ['admin', 'candidate'], true)) {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code = :role)';
            $params['role'] = $role;
        }
        $page = Paginator::page($request->query);
        $per = 25;
        $sqlWhere = implode(' AND ', $where);
        $total = (int) Database::fetchColumn("SELECT COUNT(*) FROM users u WHERE $sqlWhere", $params);
        $items = Database::fetchAll(
            "SELECT u.id, u.email, u.display_name, u.status, u.locale, u.created_at, u.last_login_at, u.email_verified_at,
                (SELECT GROUP_CONCAT(r.code) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS roles,
                (SELECT COUNT(*) FROM interview_sessions s WHERE s.user_id = u.id AND s.status = 'completed') AS completed
             FROM users u WHERE $sqlWhere ORDER BY u.created_at DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $per, 'off' => ($page - 1) * $per]
        );
        return $this->view('admin/users/index', [
            'title' => t('admin.users.title'), 'items' => $items, 'q' => $q, 'status' => $status, 'role' => $role,
            'pager' => new Paginator($total, $page, $per, '/admin/users', ['q' => $q, 'status' => $status, 'role' => $role]),
        ]);
    }

    private function find(int $id): array
    {
        $user = Database::fetch('SELECT id, email, display_name, status, locale, created_at, last_login_at, email_verified_at FROM users WHERE id = :id', ['id' => $id]);
        if (!$user) {
            throw new HttpException(404);
        }
        $user['roles'] = array_column(Database::fetchAll('SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id', ['id' => $id]), 'code');
        return $user;
    }

    public function show(Request $request, string $id): Response
    {
        $user = $this->find((int) $id);
        // Aggregated activity only; private answers are never displayed to administrators.
        $activity = Database::fetch(
            "SELECT COUNT(*) AS total, SUM(status = 'completed') AS completed, AVG(CASE WHEN status = 'completed' THEN overall_score END) AS avg_score, MAX(updated_at) AS last
             FROM interview_sessions WHERE user_id = :u",
            ['u' => $user['id']]
        );
        $logins = Database::fetchAll('SELECT * FROM login_history WHERE user_id = :u ORDER BY id DESC LIMIT 10', ['u' => $user['id']]);
        $access = Database::fetchAll(
            'SELECT a.*, COALESCE(t1.name, t2.name) AS profession_name, pay.provider_reference
             FROM profession_access a
             LEFT JOIN profession_translations t1 ON t1.profession_id = a.profession_id AND t1.locale = :l
             LEFT JOIN profession_translations t2 ON t2.profession_id = a.profession_id AND t2.locale = :o
             LEFT JOIN payments pay ON pay.id = a.payment_id
             WHERE a.user_id = :u AND a.revoked_at IS NULL ORDER BY a.granted_at DESC',
            ['u' => $user['id'], 'l' => locale(), 'o' => locale() === 'fr' ? 'en' : 'fr']
        );
        return $this->view('admin/users/show', [
            'title' => $user['display_name'], 'user' => $user, 'activity' => $activity, 'logins' => $logins,
            'access' => $access, 'professions' => \App\Models\Profession::options(),
        ]);
    }

    public function status(Request $request, string $id): Response
    {
        $user = $this->find((int) $id);
        $status = $request->str('status');
        if (!in_array($status, ['active', 'suspended'], true) || (int) $user['id'] === Auth::id()) {
            $this->flash('error', t('admin.users.cannot_change_self'));
            return $this->redirect('/admin/users/' . $id);
        }
        Database::update('users', ['status' => $status, 'updated_at' => now()], ['id' => $user['id']]);
        AuditLogger::log('admin.user_status', 'user', (int) $user['id'], ['status' => $status]);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/users/' . $id);
    }

    public function roles(Request $request, string $id): Response
    {
        $user = $this->find((int) $id);
        $makeAdmin = $request->bool('admin');
        if ((int) $user['id'] === Auth::id() && !$makeAdmin) {
            $this->flash('error', t('admin.users.cannot_change_self'));
            return $this->redirect('/admin/users/' . $id);
        }
        $roleId = (int) Database::fetchColumn("SELECT id FROM roles WHERE code = 'admin'");
        if ($makeAdmin) {
            Database::query('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)', ['u' => $user['id'], 'r' => $roleId]);
        } else {
            Database::delete('user_roles', ['user_id' => $user['id'], 'role_id' => $roleId]);
        }
        AuditLogger::log('admin.user_roles', 'user', (int) $user['id'], ['admin' => $makeAdmin]);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/users/' . $id);
    }
}
