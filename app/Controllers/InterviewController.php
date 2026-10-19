<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Domain;
use App\Models\Profession;
use App\Models\Scenario;
use App\Services\AuditLogger;
use App\Services\InterviewEngine;
use App\Services\RateLimiter;
use App\Services\Settings;

final class InterviewController extends Controller
{
    public function setup(Request $request): Response
    {
        $user = $this->user();
        $prefs = Database::fetch('SELECT * FROM user_preferences WHERE user_id = :u', ['u' => $user['id']]) ?? [];
        $profile = Database::fetch('SELECT * FROM profiles WHERE user_id = :u', ['u' => $user['id']]) ?? [];
        $professions = Profession::options();
        $selected = null;
        $slug = (string) ($request->query['profession'] ?? '');
        if ($slug !== '') {
            $selected = Profession::findBySlug($slug);
        } elseif (!empty($profile['target_profession_id'])) {
            $selected = Profession::find((int) $profile['target_profession_id']);
        }
        $scenarios = [];
        foreach (Scenario::all() as $s) {
            $scenarios[] = $s;
        }
        return $this->view('interview/setup', [
            'title' => t('setup.title'),
            'professions' => $professions,
            'selected' => $selected,
            'scenarios' => $scenarios,
            'scenarioId' => (int) ($request->query['scenario'] ?? 0),
            'defaults' => [
                'mode' => $prefs['default_mode'] ?? 'training',
                'question_count' => (int) ($prefs['default_question_count'] ?? Settings::int('default_question_count')),
                'experience_level' => (int) ($profile['experience_level'] ?? 2),
                'locale' => locale(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->user();
        [$cfg, $errors] = InterviewEngine::normaliseConfig($request->post);
        if ($errors) {
            return $this->back($request, '/interview/new', $errors);
        }
        $todayCount = (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM interview_sessions WHERE user_id = :u AND created_at >= :d',
            ['u' => $user['id'], 'd' => gmdate('Y-m-d 00:00:00')]
        );
        if ($todayCount >= Settings::int('max_sessions_per_day') || !RateLimiter::attempt('session-create:' . $user['id'], 30, 3600)) {
            return $this->back($request, '/interview/new', [], t('setup.limit_reached'));
        }
        try {
            $session = InterviewEngine::create((int) $user['id'], $cfg);
        } catch (\DomainException $e) {
            return $this->back($request, '/interview/new', [], $e->getMessage());
        }
        AuditLogger::log('interview.created', 'interview_session', (int) $session['id'], ['mode' => $cfg['mode'], 'type' => $cfg['interview_type']]);
        return $this->redirect('/interview/' . $session['public_id']);
    }

    public function run(Request $request, string $publicId): Response
    {
        $session = InterviewEngine::load($publicId, (int) $this->user()['id']);
        if ($session['status'] === 'completed') {
            return $this->redirect('/reports/' . $publicId);
        }
        if ($session['status'] === 'abandoned') {
            $this->flash('info', t('interview.errors.closed'));
            return $this->redirect('/dashboard');
        }
        $profession = Profession::find((int) $session['profession_id']);
        $scenario = $session['scenario_id'] ? Scenario::find((int) $session['scenario_id']) : null;
        return $this->view('interview/run', [
            'title' => t('interview.title'),
            'session' => $session,
            'profession' => $profession,
            'scenario' => $scenario,
            'state' => InterviewEngine::state($session),
        ]);
    }

    /** Run an engine action and return the refreshed state, mapping business errors to 422. */
    private function api(string $publicId, callable $action): Response
    {
        $session = InterviewEngine::load($publicId, (int) $this->user()['id']);
        try {
            $extra = $action($session) ?? [];
        } catch (\DomainException $e) {
            $fresh = Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]);
            return $this->json(['ok' => false, 'error' => $e->getMessage(), 'state' => InterviewEngine::state($fresh)], 422);
        }
        $fresh = Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]);
        return $this->json(['ok' => true, 'state' => $fresh['status'] === 'completed' ? null : InterviewEngine::state($fresh)] + $extra);
    }

    public function state(Request $request, string $publicId): Response
    {
        return $this->api($publicId, static fn () => null);
    }

    public function draft(Request $request, string $publicId): Response
    {
        $session = InterviewEngine::load($publicId, (int) $this->user()['id']);
        try {
            $saved = InterviewEngine::saveDraft($session, $request->int('position'), (string) $request->input('text', ''));
        } catch (\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        return $this->json(['ok' => true, 'saved_at' => $saved['saved_at']]);
    }

    public function submit(Request $request, string $publicId): Response
    {
        return $this->api($publicId, static function (array $session) use ($request) {
            $result = InterviewEngine::submit($session, $request->int('position'), (string) $request->input('text', ''), $request->bool('skip'));
            return ['followup' => $result['followup']];
        });
    }

    public function goTo(Request $request, string $publicId): Response
    {
        return $this->api($publicId, static fn (array $s) => InterviewEngine::goTo($s, $request->int('position')));
    }

    public function pause(Request $request, string $publicId): Response
    {
        return $this->api($publicId, static fn (array $s) => InterviewEngine::pause($s));
    }

    public function resume(Request $request, string $publicId): Response
    {
        return $this->api($publicId, static fn (array $s) => InterviewEngine::resume($s));
    }

    public function finish(Request $request, string $publicId): Response
    {
        $session = InterviewEngine::load($publicId, (int) $this->user()['id']);
        try {
            InterviewEngine::finish($session);
        } catch (\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        AuditLogger::log('interview.completed', 'interview_session', (int) $session['id']);
        return $this->json(['ok' => true, 'redirect' => url('/reports/' . $publicId)]);
    }

    public function abandon(Request $request, string $publicId): Response
    {
        $session = InterviewEngine::load($publicId, (int) $this->user()['id']);
        InterviewEngine::abandon($session);
        $this->flash('success', t('interview.abandoned'));
        return $this->redirect('/dashboard');
    }

    /** Candidates can report an unclear, incorrect or badly translated question. */
    public function reportQuestion(Request $request, string $id): Response
    {
        $user = $this->user();
        $reason = $request->str('reason');
        if (!in_array($reason, Domain::REPORT_REASONS, true) || !Database::fetchColumn('SELECT 1 FROM questions WHERE id = :id', ['id' => (int) $id])) {
            return $this->json(['ok' => false, 'error' => t('validation.in')], 422);
        }
        if (!RateLimiter::attempt('content-report:' . $user['id'], 20, 86400)) {
            throw new HttpException(429);
        }
        Database::insert('content_reports', [
            'user_id' => $user['id'],
            'question_id' => (int) $id,
            'reason' => $reason,
            'message' => mb_substr($request->str('message'), 0, 1000) ?: null,
            'status' => 'open',
            'created_at' => now(),
        ]);
        return $this->json(['ok' => true, 'message' => t('report_question.thanks')]);
    }
}
