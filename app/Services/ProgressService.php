<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Per skill progression and goals. Updated once per completed session.
 */
final class ProgressService
{
    public static function recordSession(int $sessionId, int $userId): void
    {
        $rows = Database::fetchAll(
            'SELECT qs.skill_id, AVG(e.score) AS score
             FROM interview_answers a
             JOIN answer_evaluations e ON e.answer_id = a.id
             JOIN question_skills qs ON qs.question_id = a.question_id
             WHERE a.session_id = :s AND a.status IN (\'answered\', \'timed_out\')
             GROUP BY qs.skill_id',
            ['s' => $sessionId]
        );
        foreach ($rows as $row) {
            $score = round((float) $row['score'], 2);
            Database::query(
                'INSERT INTO user_skill_progress (user_id, skill_id, attempts, total_score, avg_score, last_score, best_score, updated_at)
                 VALUES (:u, :sk, 1, :t, :a, :l, :b, :d)
                 ON DUPLICATE KEY UPDATE attempts = attempts + 1, total_score = total_score + VALUES(total_score),
                    avg_score = ROUND((total_score) / (attempts), 2), last_score = VALUES(last_score),
                    best_score = GREATEST(best_score, VALUES(best_score)), updated_at = VALUES(updated_at)',
                ['u' => $userId, 'sk' => (int) $row['skill_id'], 't' => $score, 'a' => $score, 'l' => $score, 'b' => $score, 'd' => now()]
            );
        }
        self::checkGoals($userId);
    }

    /** Mark goals as achieved when their target is reached. */
    public static function checkGoals(int $userId): void
    {
        foreach (Database::fetchAll('SELECT * FROM user_goals WHERE user_id = :u AND achieved_at IS NULL', ['u' => $userId]) as $goal) {
            if (self::goalProgress($goal, $userId) >= (int) $goal['target_value']) {
                Database::update('user_goals', ['achieved_at' => now()], ['id' => $goal['id']]);
            }
        }
    }

    public static function goalProgress(array $goal, int $userId): float
    {
        return match ($goal['goal_type']) {
            'average_score' => (float) Database::fetchColumn(
                "SELECT COALESCE(AVG(overall_score), 0) FROM (SELECT overall_score FROM interview_sessions
                 WHERE user_id = :u AND status = 'completed' ORDER BY completed_at DESC LIMIT 5) x",
                ['u' => $userId]
            ),
            'sessions_completed' => (float) Database::fetchColumn(
                "SELECT COUNT(*) FROM interview_sessions WHERE user_id = :u AND status = 'completed' AND completed_at >= :c",
                ['u' => $userId, 'c' => $goal['created_at']]
            ),
            'skill_score' => (float) Database::fetchColumn(
                'SELECT COALESCE(avg_score, 0) FROM user_skill_progress WHERE user_id = :u AND skill_id = :s',
                ['u' => $userId, 's' => $goal['skill_id']]
            ),
            default => 0.0,
        };
    }
}
