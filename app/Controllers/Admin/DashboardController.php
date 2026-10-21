<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Aggregated statistics only: no candidate answer, password or secret is displayed here.
 */
final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $since = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
        $stats = [
            'users' => (int) Database::fetchColumn('SELECT COUNT(*) FROM users'),
            'users_30' => (int) Database::fetchColumn('SELECT COUNT(*) FROM users WHERE created_at >= :d', ['d' => $since]),
            'sessions' => (int) Database::fetchColumn("SELECT COUNT(*) FROM interview_sessions WHERE status <> 'abandoned'"),
            'completed' => (int) Database::fetchColumn("SELECT COUNT(*) FROM interview_sessions WHERE status = 'completed'"),
            'avg' => (float) Database::fetchColumn("SELECT COALESCE(AVG(overall_score), 0) FROM interview_sessions WHERE status = 'completed'"),
            'questions' => (int) Database::fetchColumn('SELECT COUNT(*) FROM questions WHERE is_active = 1'),
            'professions' => (int) Database::fetchColumn('SELECT COUNT(*) FROM professions WHERE is_active = 1'),
            'scenarios' => (int) Database::fetchColumn('SELECT COUNT(*) FROM scenarios WHERE is_active = 1'),
            'open_reports' => (int) Database::fetchColumn("SELECT COUNT(*) FROM content_reports WHERE status = 'open'"),
            'missing_en' => (int) Database::fetchColumn("SELECT COUNT(*) FROM questions q WHERE NOT EXISTS (SELECT 1 FROM question_translations t WHERE t.question_id = q.id AND t.locale = 'en')"),
            'missing_fr' => (int) Database::fetchColumn("SELECT COUNT(*) FROM questions q WHERE NOT EXISTS (SELECT 1 FROM question_translations t WHERE t.question_id = q.id AND t.locale = 'fr')"),
        ];
        $byType = Database::fetchAll(
            "SELECT interview_type, COUNT(*) AS n, ROUND(AVG(overall_score)) AS score FROM interview_sessions WHERE status = 'completed' GROUP BY interview_type ORDER BY n DESC"
        );
        $byMode = Database::fetchAll("SELECT mode, COUNT(*) AS n FROM interview_sessions WHERE status <> 'abandoned' GROUP BY mode ORDER BY n DESC");
        $l = locale();
        $topProfessions = Database::fetchAll(
            "SELECT COALESCE(t1.name, t2.name) AS name, COUNT(*) AS n FROM interview_sessions s
             LEFT JOIN profession_translations t1 ON t1.profession_id = s.profession_id AND t1.locale = :l
             LEFT JOIN profession_translations t2 ON t2.profession_id = s.profession_id AND t2.locale = :o
             WHERE s.status <> 'abandoned' GROUP BY s.profession_id, t1.name, t2.name ORDER BY n DESC LIMIT 8",
            ['l' => $l, 'o' => $l === 'fr' ? 'en' : 'fr']
        );
        $evaluators = Database::fetchAll('SELECT evaluator, COUNT(*) AS n FROM answer_evaluations GROUP BY evaluator');
        $audit = Database::fetchAll('SELECT a.*, u.display_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 8');
        return $this->view('admin/dashboard', compact('stats', 'byType', 'byMode', 'topProfessions', 'evaluators', 'audit') + ['title' => t('admin.dashboard.title')]);
    }
}
