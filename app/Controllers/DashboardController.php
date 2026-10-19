<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Paginator;
use App\Models\Skill;
use App\Services\Mailer;
use App\Services\ProgressService;

final class DashboardController extends Controller
{
    private const PROFESSION_NAME = "COALESCE((SELECT name FROM profession_translations WHERE profession_id = s.profession_id AND locale = :l1),
        (SELECT name FROM profession_translations WHERE profession_id = s.profession_id AND locale = :o1))";

    private function locParams(): array
    {
        $l = locale();
        return ['l1' => $l, 'o1' => $l === 'fr' ? 'en' : 'fr'];
    }

    public function index(Request $request): Response
    {
        $user = $this->user();
        $uid = (int) $user['id'];
        $totals = Database::fetch(
            "SELECT COUNT(*) AS total, SUM(status = 'completed') AS completed, AVG(CASE WHEN status = 'completed' THEN overall_score END) AS avg_score,
                MAX(CASE WHEN status = 'completed' THEN overall_score END) AS best
             FROM interview_sessions WHERE user_id = :u AND status <> 'abandoned'",
            ['u' => $uid]
        );
        $evolution = array_reverse(Database::fetchAll(
            "SELECT completed_at, overall_score FROM interview_sessions WHERE user_id = :u AND status = 'completed' ORDER BY completed_at DESC LIMIT 12",
            ['u' => $uid]
        ));
        $l = locale();
        $o = $l === 'fr' ? 'en' : 'fr';
        $byCategory = Database::fetchAll(
            "SELECT COALESCE(t1.name, t2.name) AS label, ROUND(AVG(s.overall_score)) AS score, COUNT(*) AS n
             FROM interview_sessions s JOIN professions p ON p.id = s.profession_id
             LEFT JOIN profession_category_translations t1 ON t1.category_id = p.category_id AND t1.locale = :l
             LEFT JOIN profession_category_translations t2 ON t2.category_id = p.category_id AND t2.locale = :o
             WHERE s.user_id = :u AND s.status = 'completed' GROUP BY p.category_id, t1.name, t2.name ORDER BY score DESC",
            ['u' => $uid, 'l' => $l, 'o' => $o]
        );
        $byType = Database::fetchAll(
            "SELECT interview_type, ROUND(AVG(overall_score)) AS score, COUNT(*) AS n FROM interview_sessions
             WHERE user_id = :u AND status = 'completed' GROUP BY interview_type ORDER BY score DESC",
            ['u' => $uid]
        );
        $professions = Database::fetchAll(
            'SELECT ' . self::PROFESSION_NAME . " AS name, COUNT(*) AS n, ROUND(AVG(s.overall_score)) AS score
             FROM interview_sessions s WHERE s.user_id = :u AND s.status <> 'abandoned' GROUP BY s.profession_id ORDER BY n DESC LIMIT 8",
            $this->locParams() + ['u' => $uid]
        );
        $skillsTop = Database::fetchAll('SELECT p.*, s.name_fr, s.name_en FROM user_skill_progress p JOIN skills s ON s.id = p.skill_id WHERE p.user_id = :u ORDER BY p.avg_score DESC LIMIT 5', ['u' => $uid]);
        $skillsLow = Database::fetchAll('SELECT p.*, s.name_fr, s.name_en FROM user_skill_progress p JOIN skills s ON s.id = p.skill_id WHERE p.user_id = :u ORDER BY p.avg_score ASC LIMIT 5', ['u' => $uid]);
        $recent = Database::fetchAll(
            'SELECT s.*, ' . self::PROFESSION_NAME . " AS profession_name FROM interview_sessions s
             WHERE s.user_id = :u AND s.status = 'completed' ORDER BY s.completed_at DESC LIMIT 5",
            $this->locParams() + ['u' => $uid]
        );
        $saved = Database::fetchAll(
            'SELECT s.*, ' . self::PROFESSION_NAME . " AS profession_name,
                (SELECT COUNT(*) FROM interview_answers a WHERE a.session_id = s.id AND a.status IN ('answered','skipped','timed_out')) AS done,
                (SELECT COUNT(*) FROM interview_answers a WHERE a.session_id = s.id) AS total_q
             FROM interview_sessions s WHERE s.user_id = :u AND s.status IN ('in_progress','paused') ORDER BY s.updated_at DESC LIMIT 6",
            $this->locParams() + ['u' => $uid]
        );
        $goals = Database::fetchAll('SELECT g.*, s.name_fr, s.name_en FROM user_goals g LEFT JOIN skills s ON s.id = g.skill_id WHERE g.user_id = :u ORDER BY g.achieved_at IS NOT NULL, g.created_at DESC', ['u' => $uid]);
        foreach ($goals as &$g) {
            $g['progress'] = ProgressService::goalProgress($g, $uid);
        }
        unset($g);

        return $this->view('dashboard/index', [
            'title' => t('dashboard.title'),
            'totals' => $totals,
            'evolution' => $evolution,
            'byCategory' => $byCategory,
            'byType' => $byType,
            'professions' => $professions,
            'skillsTop' => $skillsTop,
            'skillsLow' => $skillsLow,
            'recent' => $recent,
            'saved' => $saved,
            'goals' => $goals,
            'skills' => Skill::all(),
            'needsVerification' => Mailer::isConfigured() && $user['email_verified_at'] === null,
        ]);
    }

    public function history(Request $request): Response
    {
        $uid = (int) $this->user()['id'];
        $page = Paginator::page($request->query);
        $perPage = 15;
        $status = (string) ($request->query['status'] ?? '');
        $type = (string) ($request->query['type'] ?? '');
        $where = "s.user_id = :u AND s.status <> 'abandoned'";
        $params = ['u' => $uid];
        if (in_array($status, ['completed', 'in_progress', 'paused'], true)) {
            $where .= ' AND s.status = :st';
            $params['st'] = $status;
        }
        if (isset(\App\Models\Domain::INTERVIEW_TYPES[$type])) {
            $where .= ' AND s.interview_type = :ty';
            $params['ty'] = $type;
        }
        $total = (int) Database::fetchColumn("SELECT COUNT(*) FROM interview_sessions s WHERE $where", $params);
        $items = Database::fetchAll(
            'SELECT s.*, ' . self::PROFESSION_NAME . " AS profession_name,
                (SELECT COUNT(*) FROM interview_answers a WHERE a.session_id = s.id) AS total_q
             FROM interview_sessions s WHERE $where ORDER BY s.created_at DESC LIMIT :lim OFFSET :off",
            $params + $this->locParams() + ['lim' => $perPage, 'off' => ($page - 1) * $perPage]
        );
        return $this->view('dashboard/history', [
            'title' => t('history.title'),
            'items' => $items,
            'status' => $status,
            'type' => $type,
            'pager' => new Paginator($total, $page, $perPage, '/history', ['status' => $status, 'type' => $type]),
        ]);
    }

    public function storeGoal(Request $request): Response
    {
        $uid = (int) $this->user()['id'];
        $v = $this->validate($request, [
            'goal_type' => 'required|in:average_score,sessions_completed,skill_score',
            'target_value' => 'required|int|between:1,100',
            'deadline' => 'nullable|regex:/^\d{4}-\d{2}-\d{2}$/',
            'skill_id' => 'nullable|int',
        ]);
        $errors = $v->errors();
        $type = $request->str('goal_type');
        $skillId = $request->int('skill_id') ?: null;
        if ($type === 'skill_score' && (!$skillId || !Database::fetchColumn('SELECT 1 FROM skills WHERE id = :id', ['id' => $skillId]))) {
            $errors['skill_id'] = t('validation.required');
        }
        if ((int) Database::fetchColumn('SELECT COUNT(*) FROM user_goals WHERE user_id = :u AND achieved_at IS NULL', ['u' => $uid]) >= 10) {
            $errors['goal_type'] = t('goals.too_many');
        }
        if ($errors) {
            return $this->back($request, '/dashboard#goals', $errors);
        }
        Database::insert('user_goals', [
            'user_id' => $uid,
            'goal_type' => $type,
            'target_value' => $request->int('target_value'),
            'skill_id' => $type === 'skill_score' ? $skillId : null,
            'deadline' => $request->str('deadline') ?: null,
            'created_at' => now(),
        ]);
        ProgressService::checkGoals($uid);
        $this->flash('success', t('goals.created'));
        return $this->redirect('/dashboard#goals');
    }

    public function deleteGoal(Request $request, string $id): Response
    {
        $deleted = Database::delete('user_goals', ['id' => (int) $id, 'user_id' => (int) $this->user()['id']]);
        if (!$deleted) {
            throw new HttpException(404);
        }
        $this->flash('success', t('goals.deleted'));
        return $this->redirect('/dashboard#goals');
    }
}
