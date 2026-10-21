<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\InterviewEngine;
use App\Services\UserService;
use Tests\Support\TestCase;

final class InterviewEngineTest extends TestCase
{
    private int $userId;
    private int $socId;

    public function setUpClass(): void
    {
        $this->userId = UserService::create('engine-' . uniqid() . '@test.local', 'Engine', 'Password123', 'fr');
        $this->socId = (int) Database::fetchColumn("SELECT id FROM professions WHERE slug = 'soc-analyst-junior'");
    }

    private function config(array $overrides = []): array
    {
        [$cfg, $errors] = InterviewEngine::normaliseConfig($overrides + [
            'profession_id' => $this->socId, 'experience_level' => 2, 'interview_type' => 'technical', 'locale' => 'fr',
            'difficulty' => 3, 'duration_minutes' => 30, 'question_count' => 6, 'mode' => 'realistic',
        ]);
        $this->assertSame([], $errors, 'valid configuration');
        return $cfg;
    }

    public function testAllocationKeepsTotal(): void
    {
        foreach ([3, 5, 6, 8, 12] as $n) {
            $this->assertSame($n, array_sum(InterviewEngine::allocate(['a' => 7, 'b' => 2, 'c' => 1], $n)));
        }
    }

    public function testInvalidConfigurationIsRejected(): void
    {
        [, $errors] = InterviewEngine::normaliseConfig(['profession_id' => 999999, 'experience_level' => 9, 'interview_type' => 'x', 'locale' => 'de', 'difficulty' => 0, 'duration_minutes' => 7, 'question_count' => 1, 'mode' => 'x']);
        foreach (['profession_id', 'experience_level', 'interview_type', 'locale', 'difficulty', 'duration_minutes', 'question_count', 'mode'] as $f) {
            $this->assertTrue(isset($errors[$f]), "error on $f");
        }
    }

    public function testSelectionRespectsProfessionLevelAndUniqueness(): void
    {
        $cfg = $this->config();
        $ids = InterviewEngine::selectQuestions($this->userId, $cfg);
        $this->assertSame(6, count($ids));
        $this->assertSame(6, count(array_unique($ids)));
        $params = [];
        $in = Database::in($ids, $params);
        $rows = Database::fetchAll("SELECT * FROM questions WHERE id IN $in", $params);
        $catId = (int) Database::fetchColumn('SELECT category_id FROM professions WHERE id = :id', ['id' => $this->socId]);
        $technical = 0;
        foreach ($rows as $q) {
            $this->assertTrue($q['level_min'] <= 2 && $q['level_max'] >= 2, 'level compatible');
            $this->assertTrue($q['profession_id'] === null ? ($q['category_id'] === null || (int) $q['category_id'] === $catId) : (int) $q['profession_id'] === $this->socId, 'profession compatible');
            $technical += $q['question_type'] === 'technical' ? 1 : 0;
        }
        $this->assertGreaterThan(2, $technical, 'technical interview is mostly technical');
    }

    public function testModesSetRules(): void
    {
        $timed = $this->config(['mode' => 'timed', 'duration_minutes' => 30, 'question_count' => 6]);
        $this->assertSame(300, $timed['per_question_seconds']);
        $this->assertSame(0, $timed['allow_back']);
        $intensive = $this->config(['mode' => 'intensive', 'difficulty' => 1]);
        $this->assertSame(4, $intensive['difficulty'], 'intensive raises difficulty');
        $this->assertSame(0, $intensive['allow_skip']);
        $custom = $this->config(['mode' => 'custom', 'allow_back' => '1', 'use_timer' => '1']);
        $this->assertSame(1, $custom['allow_back']);
        $this->assertSame(0, $custom['allow_skip']);
        $this->assertSame(300, $custom['per_question_seconds']);
    }

    public function testFullSessionLifecycle(): void
    {
        $session = InterviewEngine::create($this->userId, $this->config());
        $this->assertSame('in_progress', $session['status']);
        $this->assertSame(6, count(InterviewEngine::answers((int) $session['id'])));

        InterviewEngine::saveDraft($session, 1, 'Brouillon');
        $state = InterviewEngine::state($session);
        $this->assertSame('Brouillon', $state['question']['text'], 'draft restored in state');

        $this->expectException(\DomainException::class, fn () => InterviewEngine::submit($session, 2, 'hors ordre'));
        $this->expectException(\DomainException::class, fn () => InterviewEngine::submit($session, 1, '   '));

        InterviewEngine::submit($session, 1, "D'abord j'analyse le contexte, ensuite je corrèle les journaux et enfin je documente. Résultat obtenu en 20 minutes.");
        $s = Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]);
        $this->assertGreaterThan(1, (int) $s['current_position']);
        $this->expectException(\DomainException::class, fn () => InterviewEngine::saveDraft($s, 1, 'modif'), 'answered question is locked in realistic mode');

        InterviewEngine::pause($s);
        $paused = Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]);
        $this->expectException(\DomainException::class, fn () => InterviewEngine::submit($paused, (int) $paused['current_position'], 'texte'));
        InterviewEngine::resume($paused);

        $final = InterviewEngine::finish(Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]));
        $this->assertSame('completed', $final['status']);
        $this->assertTrue($final['overall_score'] !== null);
        $evaluated = (int) Database::fetchColumn('SELECT COUNT(*) FROM answer_evaluations e JOIN interview_answers a ON a.id = e.answer_id WHERE a.session_id = :s', ['s' => $session['id']]);
        $this->assertSame(count(InterviewEngine::answers((int) $session['id'])), $evaluated, 'every answer evaluated');
        $this->assertGreaterThan(0, (int) Database::fetchColumn('SELECT COUNT(*) FROM user_skill_progress WHERE user_id = :u', ['u' => $this->userId]), 'skill progress updated');
    }

    public function testFollowupInsertedWhenPersonalRoleMissing(): void
    {
        $scenarioId = (int) Database::fetchColumn("SELECT id FROM scenarios WHERE slug = 'soc-analyst-l1-interview'");
        $session = InterviewEngine::create($this->userId, $this->config(['scenario_id' => $scenarioId]));
        $this->assertSame(8, count(InterviewEngine::answers((int) $session['id'])), 'scenario questions in order');
        $s = $session;
        // Position 2 (privileged login) has a "missing_personal_role" follow up.
        InterviewEngine::submit($s, 1, "Nous avons une formation en cybersécurité et une expérience en SOC avec Sentinel et Splunk depuis deux ans.");
        $s = Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]);
        $result = InterviewEngine::submit($s, 2, 'Le compte est vérifié, les journaux sont consultés puis le ticket est escaladé au niveau supérieur.');
        $this->assertTrue($result['followup'], 'follow up inserted');
        $rows = InterviewEngine::answers((int) $session['id']);
        $this->assertSame(9, count($rows));
        $this->assertTrue($rows[2]['followup_id'] !== null, 'follow up placed right after the question');
        $this->assertSame((int) $rows[1]['id'], (int) $rows[2]['parent_answer_id']);
    }

    public function testTimerExpiryMarksTimedOut(): void
    {
        $session = InterviewEngine::create($this->userId, $this->config(['mode' => 'intensive']));
        Database::query('UPDATE interview_answers SET shown_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR) WHERE session_id = :s AND position = 1', ['s' => $session['id']]);
        // Skipping is not allowed in intensive mode, except once the timer has expired.
        InterviewEngine::submit($session, 1, '', true);
        $row = InterviewEngine::answerAt((int) $session['id'], 1);
        $this->assertSame('skipped', $row['status']);
        $s = Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]);
        Database::query('UPDATE interview_answers SET shown_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR) WHERE session_id = :s AND position = :p', ['s' => $s['id'], 'p' => $s['current_position']]);
        InterviewEngine::submit($s, (int) $s['current_position'], 'Réponse tardive mais présente.');
        $this->assertSame('timed_out', InterviewEngine::answerAt((int) $s['id'], (int) $s['current_position'])['status']);
        $this->assertSame((int) $s['per_question_seconds'], (int) InterviewEngine::answerAt((int) $s['id'], (int) $s['current_position'])['time_spent_seconds']);
    }
}
