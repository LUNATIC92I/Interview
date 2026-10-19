<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\Domain;
use App\Models\Question;
use App\Models\Scenario;
use App\Services\Evaluation\Lexicon;
use App\Services\Evaluation\TextAnalyzer;

/**
 * Simulation engine: builds a session from the candidate's parameters, serves one question at a time,
 * saves drafts, records answers with their duration, inserts predefined follow up questions and
 * closes the session. Every state lives in MySQL so a reload or a short disconnection loses nothing.
 */
final class InterviewEngine
{
    /** Display order of question types inside a session (opening questions first, salary last). */
    private const TYPE_ORDER = [
        'hr' => 1, 'motivation' => 2, 'behavioral' => 3, 'technical' => 4, 'problem_solving' => 5,
        'situational' => 6, 'managerial' => 7, 'international' => 8, 'negotiation' => 9,
    ];

    private const TIMER_GRACE_SECONDS = 5;

    /**
     * Validate and normalise the configuration posted by the setup form.
     * @return array{0: array<string,mixed>, 1: array<string,string>} [config, errors]
     */
    public static function normaliseConfig(array $in): array
    {
        $errors = [];
        $cfg = [
            'profession_id' => (int) ($in['profession_id'] ?? 0),
            'scenario_id' => (int) ($in['scenario_id'] ?? 0) ?: null,
            'experience_level' => (int) ($in['experience_level'] ?? 0),
            'interview_type' => (string) ($in['interview_type'] ?? ''),
            'locale' => (string) ($in['locale'] ?? ''),
            'difficulty' => (int) ($in['difficulty'] ?? 0),
            'duration_minutes' => (int) ($in['duration_minutes'] ?? 0),
            'question_count' => (int) ($in['question_count'] ?? 0),
            'mode' => (string) ($in['mode'] ?? ''),
        ];
        $profession = $cfg['profession_id'] ? Database::fetch('SELECT id, category_id FROM professions WHERE id = :id AND is_active = 1', ['id' => $cfg['profession_id']]) : null;
        if (!$profession) {
            $errors['profession_id'] = t('validation.required');
        }
        if (!isset(Domain::LEVELS[$cfg['experience_level']])) {
            $errors['experience_level'] = t('validation.in');
        }
        if (!isset(Domain::INTERVIEW_TYPES[$cfg['interview_type']])) {
            $errors['interview_type'] = t('validation.in');
        }
        if (!in_array($cfg['locale'], ['fr', 'en'], true)) {
            $errors['locale'] = t('validation.in');
        }
        if ($cfg['difficulty'] < 1 || $cfg['difficulty'] > 5) {
            $errors['difficulty'] = t('validation.between', ['min' => 1, 'max' => 5]);
        }
        if (!in_array($cfg['duration_minutes'], Domain::DURATIONS, true)) {
            $errors['duration_minutes'] = t('validation.in');
        }
        if ($cfg['question_count'] < 3 || $cfg['question_count'] > 15) {
            $errors['question_count'] = t('validation.between', ['min' => 3, 'max' => 15]);
        }
        if (!isset(Domain::MODES[$cfg['mode']])) {
            $errors['mode'] = t('validation.in');
        }
        if ($cfg['scenario_id'] !== null) {
            $scenario = Database::fetch('SELECT * FROM scenarios WHERE id = :id AND is_active = 1', ['id' => $cfg['scenario_id']]);
            if (!$scenario || ($profession && $scenario['profession_id'] !== null && (int) $scenario['profession_id'] !== $cfg['profession_id'])) {
                $errors['scenario_id'] = t('validation.in');
            } else {
                $cfg['interview_type'] = $scenario['interview_type'];
            }
        }

        $rules = Domain::MODES[$cfg['mode']] ?? Domain::MODES['training'];
        if ($cfg['mode'] === 'custom') {
            $rules['skip'] = !empty($in['allow_skip']);
            $rules['back'] = !empty($in['allow_back']);
            $rules['pause'] = !empty($in['allow_pause']);
            $rules['timer'] = !empty($in['use_timer']);
            $rules['followups'] = !empty($in['followups']);
            $rules['instant_feedback'] = !empty($in['instant_feedback']);
        }
        $cfg['allow_skip'] = $rules['skip'] ? 1 : 0;
        $cfg['allow_back'] = $rules['back'] ? 1 : 0;
        $cfg['allow_pause'] = $rules['pause'] ? 1 : 0;
        $cfg['followups_enabled'] = $rules['followups'] ? 1 : 0;
        $cfg['instant_feedback'] = $rules['instant_feedback'];
        $cfg['per_question_seconds'] = null;
        if ($rules['timer'] && $cfg['question_count'] > 0) {
            $seconds = (int) round($cfg['duration_minutes'] * 60 / $cfg['question_count']);
            if ($cfg['mode'] === 'intensive') {
                $seconds = (int) round($seconds * 0.8);
            }
            $cfg['per_question_seconds'] = max(60, min(600, $seconds));
        }
        if ($cfg['mode'] === 'intensive') {
            $cfg['difficulty'] = max($cfg['difficulty'], 4);
        }
        $cfg['category_id'] = $profession['category_id'] ?? null;
        return [$cfg, $errors];
    }

    /** Create a session and its ordered answer slots. */
    public static function create(int $userId, array $cfg): array
    {
        $questionIds = $cfg['scenario_id']
            ? array_slice(Scenario::questionIds((int) $cfg['scenario_id']), 0, max($cfg['question_count'], 1) + 10)
            : self::selectQuestions($userId, $cfg);
        if ($cfg['scenario_id']) {
            $cfg['question_count'] = count($questionIds);
        }
        if ($questionIds === []) {
            throw new \DomainException(t('interview.no_questions'));
        }
        return Database::transaction(static function () use ($userId, $cfg, $questionIds) {
            $publicId = bin2hex(random_bytes(16));
            $now = now();
            $sessionId = Database::insert('interview_sessions', [
                'public_id' => $publicId,
                'user_id' => $userId,
                'profession_id' => $cfg['profession_id'],
                'scenario_id' => $cfg['scenario_id'],
                'interview_type' => $cfg['interview_type'],
                'experience_level' => $cfg['experience_level'],
                'locale' => $cfg['locale'],
                'difficulty' => $cfg['difficulty'],
                'mode' => $cfg['mode'],
                'question_count' => count($questionIds),
                'duration_minutes' => $cfg['duration_minutes'],
                'per_question_seconds' => $cfg['per_question_seconds'],
                'allow_skip' => $cfg['allow_skip'],
                'allow_back' => $cfg['allow_back'],
                'allow_pause' => $cfg['allow_pause'],
                'followups_enabled' => $cfg['followups_enabled'],
                'status' => 'in_progress',
                'current_position' => 1,
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach (array_values($questionIds) as $i => $qid) {
                Database::insert('interview_answers', [
                    'session_id' => $sessionId,
                    'question_id' => $qid,
                    'position' => $i + 1,
                    'status' => 'pending',
                    'shown_at' => $i === 0 ? $now : null,
                ]);
            }
            return self::load($publicId, $userId);
        });
    }

    /**
     * Pick questions for the session: distributes the requested count across question types according to
     * the interview type, prefers profession specific questions, then the profession's category, then the
     * generic bank; respects the experience level, approaches the requested difficulty, favours questions
     * available in the chosen language and questions the candidate has seen least.
     *
     * @return list<int>
     */
    public static function selectQuestions(int $userId, array $cfg): array
    {
        $count = (int) $cfg['question_count'];
        $allocation = self::allocate(Domain::INTERVIEW_TYPES[$cfg['interview_type']], $count);
        $picked = [];
        $pickedType = [];
        foreach ($allocation as $type => $n) {
            foreach (self::candidates($userId, $cfg, $type, $n, $picked) as $row) {
                $picked[] = (int) $row['id'];
                $pickedType[(int) $row['id']] = $row['question_type'];
            }
        }
        if (count($picked) < $count) {
            foreach (self::candidates($userId, $cfg, null, $count - count($picked), $picked) as $row) {
                $picked[] = (int) $row['id'];
                $pickedType[(int) $row['id']] = $row['question_type'];
            }
        }
        usort($picked, static fn ($a, $b) => (self::TYPE_ORDER[$pickedType[$a]] ?? 50) <=> (self::TYPE_ORDER[$pickedType[$b]] ?? 50));
        return $picked;
    }

    /** Largest remainder allocation of $count slots across weighted types. */
    public static function allocate(array $weights, int $count): array
    {
        $sum = array_sum($weights);
        $alloc = [];
        $remainders = [];
        foreach ($weights as $type => $w) {
            $exact = $count * $w / $sum;
            $alloc[$type] = (int) floor($exact);
            $remainders[$type] = $exact - floor($exact);
        }
        arsort($remainders);
        $left = $count - array_sum($alloc);
        foreach (array_keys($remainders) as $type) {
            if ($left-- <= 0) {
                break;
            }
            $alloc[$type]++;
        }
        return array_filter($alloc);
    }

    private static function candidates(int $userId, array $cfg, ?string $type, int $limit, array $exclude): array
    {
        if ($limit <= 0) {
            return [];
        }
        $params = [
            'prof' => $cfg['profession_id'],
            'prof2' => $cfg['profession_id'],
            'cat' => $cfg['category_id'],
            'cat2' => $cfg['category_id'],
            'level' => $cfg['experience_level'],
            'level2' => $cfg['experience_level'],
            'diff' => $cfg['difficulty'],
            'loc' => $cfg['locale'],
            'uid' => $userId,
            'lim' => $limit,
        ];
        $where = ['q.is_active = 1', 'q.level_min <= :level', 'q.level_max >= :level2',
            '(q.profession_id = :prof OR (q.profession_id IS NULL AND (q.category_id = :cat OR q.category_id IS NULL)))'];
        if ($type !== null) {
            $where[] = 'q.question_type = :type';
            $params['type'] = $type;
        }
        if ($exclude !== []) {
            $where[] = 'q.id NOT IN ' . Database::in($exclude, $params, 'ex');
        }
        $sql = 'SELECT q.id, q.question_type FROM questions q
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY
                EXISTS (SELECT 1 FROM question_translations t WHERE t.question_id = q.id AND t.locale = :loc) DESC,
                (q.profession_id = :prof2) DESC,
                (q.category_id = :cat2) DESC,
                (SELECT COUNT(*) FROM interview_answers a JOIN interview_sessions s ON s.id = a.session_id
                    WHERE a.question_id = q.id AND s.user_id = :uid) ASC,
                ABS(CAST(q.difficulty AS SIGNED) - :diff) ASC,
                RAND()
            LIMIT :lim';
        return Database::fetchAll($sql, $params);
    }

    /** Load a session owned by the user, or fail with 404 (never reveal other users' sessions). */
    public static function load(string $publicId, int $userId): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $publicId)) {
            throw new HttpException(404);
        }
        $session = Database::fetch('SELECT * FROM interview_sessions WHERE public_id = :p AND user_id = :u', ['p' => $publicId, 'u' => $userId]);
        if (!$session) {
            throw new HttpException(404);
        }
        return $session;
    }

    public static function answers(int $sessionId): array
    {
        return Database::fetchAll('SELECT * FROM interview_answers WHERE session_id = :s ORDER BY position', ['s' => $sessionId]);
    }

    public static function answerAt(int $sessionId, int $position): ?array
    {
        return Database::fetch('SELECT * FROM interview_answers WHERE session_id = :s AND position = :p', ['s' => $sessionId, 'p' => $position]);
    }

    /** Remaining seconds for the current question, null when there is no per question timer. */
    public static function remainingSeconds(array $session, array $answer): ?int
    {
        if (!$session['per_question_seconds'] || !$answer['shown_at'] || in_array($answer['status'], ['answered', 'skipped', 'timed_out'], true)) {
            return null;
        }
        $elapsed = time() - strtotime($answer['shown_at'] . ' UTC');
        if ($session['status'] === 'paused' && $session['paused_at']) {
            $elapsed -= time() - strtotime($session['paused_at'] . ' UTC');
        }
        return max(0, (int) $session['per_question_seconds'] - $elapsed);
    }

    /** Full state used by the interview page and its JavaScript client. */
    public static function state(array $session): array
    {
        $answers = self::answers((int) $session['id']);
        $total = count($answers);
        $position = min(max(1, (int) $session['current_position']), max(1, $total));
        $current = null;
        foreach ($answers as $a) {
            if ((int) $a['position'] === $position) {
                $current = $a;
            }
        }
        $done = count(array_filter($answers, static fn ($a) => in_array($a['status'], ['answered', 'skipped', 'timed_out'], true)));
        $payload = [
            'session' => [
                'id' => $session['public_id'],
                'status' => $session['status'],
                'mode' => $session['mode'],
                'locale' => $session['locale'],
                'allow_skip' => (bool) $session['allow_skip'],
                'allow_back' => (bool) $session['allow_back'],
                'allow_pause' => (bool) $session['allow_pause'],
                'instant_feedback' => self::instantFeedback($session),
                'per_question_seconds' => $session['per_question_seconds'] ? (int) $session['per_question_seconds'] : null,
                'duration_minutes' => (int) $session['duration_minutes'],
                'elapsed_seconds' => self::elapsedSeconds($session),
            ],
            'progress' => ['position' => $position, 'total' => $total, 'done' => $done, 'all_done' => $done >= $total],
            'nav' => array_map(static fn ($a) => ['position' => (int) $a['position'], 'status' => $a['status'], 'followup' => $a['followup_id'] !== null], $answers),
            'question' => null,
        ];
        if ($current) {
            if ($current['shown_at'] === null && $session['status'] === 'in_progress') {
                Database::update('interview_answers', ['shown_at' => now()], ['id' => $current['id']]);
                $current['shown_at'] = now();
            }
            $payload['question'] = self::questionPayload($session, $current);
        }
        return $payload;
    }

    public static function instantFeedback(array $session): bool
    {
        if ($session['mode'] === 'training') {
            return true;
        }
        if ($session['mode'] !== 'custom') {
            return false;
        }
        // Custom sessions with "back" allowed behave like training for feedback; otherwise feedback at the end.
        return (bool) $session['allow_back'];
    }

    private static function questionPayload(array $session, array $answer): array
    {
        $locale = $session['locale'];
        $q = Question::find((int) $answer['question_id'], $locale);
        $body = $q['body'] ?? '';
        $isFallback = (bool) ($q['is_fallback'] ?? false);
        if ($answer['followup_id']) {
            $f = Question::followup((int) $answer['followup_id'], $locale);
            $body = $f['body'] ?? $body;
            $isFallback = (bool) ($f['is_fallback'] ?? false);
        }
        $data = [
            'position' => (int) $answer['position'],
            'answer_id' => (int) $answer['id'],
            'is_followup' => $answer['followup_id'] !== null,
            'type' => $q['question_type'] ?? '',
            'type_label' => t('question_types.' . ($q['question_type'] ?? 'hr')),
            'difficulty' => (int) ($q['difficulty'] ?? 0),
            'difficulty_label' => \App\Models\Difficulty::label((int) ($q['difficulty'] ?? 2)),
            'recommended_seconds' => (int) ($q['recommended_seconds'] ?? 180),
            'body' => $body,
            'is_fallback' => $isFallback,
            'status' => $answer['status'],
            'text' => (string) ($answer['answer_text'] ?? ''),
            'remaining_seconds' => self::remainingSeconds($session, $answer),
            'draft_saved_at' => $answer['draft_saved_at'],
            'question_id' => (int) $answer['question_id'],
        ];
        if (self::instantFeedback($session) && !$answer['followup_id']) {
            $data['guidance'] = $q['guidance'] ?? '';
        }
        if (self::instantFeedback($session) && in_array($answer['status'], ['answered', 'timed_out', 'skipped'], true)) {
            $data['feedback'] = ReportService::answerFeedback((int) $answer['id'], $locale);
        }
        return $data;
    }

    public static function elapsedSeconds(array $session): int
    {
        $end = $session['completed_at'] ? strtotime($session['completed_at'] . ' UTC') : time();
        $elapsed = $end - strtotime($session['started_at'] . ' UTC') - (int) $session['paused_seconds'];
        if ($session['status'] === 'paused' && $session['paused_at']) {
            $elapsed -= time() - strtotime($session['paused_at'] . ' UTC');
        }
        return max(0, $elapsed);
    }

    private static function assertActive(array $session): void
    {
        if ($session['status'] !== 'in_progress') {
            throw new \DomainException(t($session['status'] === 'paused' ? 'interview.errors.paused' : 'interview.errors.closed'));
        }
    }

    /** Autosave a draft. Drafts never overwrite a submitted answer. */
    public static function saveDraft(array $session, int $position, string $text): array
    {
        self::assertActive($session);
        $answer = self::answerAt((int) $session['id'], $position);
        if (!$answer) {
            throw new HttpException(404);
        }
        if (in_array($answer['status'], ['answered', 'skipped', 'timed_out'], true) && !$session['allow_back']) {
            throw new \DomainException(t('interview.errors.locked'));
        }
        $text = self::cleanText($text);
        $now = now();
        Database::update('interview_answers', [
            'answer_text' => $text,
            'word_count' => min(65535, (new TextAnalyzer($text))->wordCount),
            'status' => in_array($answer['status'], ['pending', 'draft'], true) ? 'draft' : $answer['status'],
            'draft_saved_at' => $now,
        ], ['id' => $answer['id']]);
        Database::update('interview_sessions', ['updated_at' => $now], ['id' => $session['id']]);
        return ['saved_at' => $now];
    }

    /**
     * Submit (or skip) the answer at $position, evaluate it, insert a follow up when one applies and move on.
     */
    public static function submit(array $session, int $position, string $text, bool $skip = false): array
    {
        self::assertActive($session);
        $answer = self::answerAt((int) $session['id'], $position);
        if (!$answer) {
            throw new HttpException(404);
        }
        $alreadyDone = in_array($answer['status'], ['answered', 'skipped', 'timed_out'], true);
        if ($alreadyDone && !$session['allow_back']) {
            throw new \DomainException(t('interview.errors.locked'));
        }
        if ($position !== (int) $session['current_position'] && !$session['allow_back']) {
            throw new \DomainException(t('interview.errors.order'));
        }
        $now = time();
        $shown = $answer['shown_at'] ? strtotime($answer['shown_at'] . ' UTC') : $now;
        $spent = max(0, $now - $shown);
        $expired = $session['per_question_seconds'] && $spent >= (int) $session['per_question_seconds'];
        // When the timer has run out the question is closed even if skipping is otherwise not allowed.
        if ($skip && !$session['allow_skip'] && !$expired) {
            throw new \DomainException(t('interview.errors.skip_not_allowed'));
        }
        $text = self::cleanText($text);
        if (!$skip && trim($text) === '') {
            throw new \DomainException(t('interview.errors.empty'));
        }

        $status = $skip ? 'skipped' : 'answered';
        if ($session['per_question_seconds'] && $spent > (int) $session['per_question_seconds'] + self::TIMER_GRACE_SECONDS) {
            $status = $skip ? 'skipped' : 'timed_out';
            $spent = (int) $session['per_question_seconds'];
        }

        $result = Database::transaction(static function () use ($session, $answer, $text, $skip, $status, $spent, $alreadyDone) {
            Database::update('interview_answers', [
                'answer_text' => $skip ? null : $text,
                'word_count' => $skip ? 0 : min(65535, (new TextAnalyzer($text))->wordCount),
                'status' => $status,
                'time_spent_seconds' => $alreadyDone ? max((int) $answer['time_spent_seconds'], $spent) : $spent,
                'submitted_at' => now(),
            ], ['id' => $answer['id']]);

            EvaluationService::evaluateAnswer((int) $answer['id']);

            $followupInserted = false;
            if (!$skip && !$alreadyDone && $answer['followup_id'] === null && $session['followups_enabled']) {
                $followupInserted = self::maybeInsertFollowup($session, $answer, $text);
            }
            $next = self::nextOpenPosition((int) $session['id'], (int) $answer['position']);
            Database::update('interview_sessions', ['current_position' => $next ?? (int) $answer['position'], 'updated_at' => now()], ['id' => $session['id']]);
            if ($next !== null) {
                $nextAnswer = self::answerAt((int) $session['id'], $next);
                if ($nextAnswer && $nextAnswer['shown_at'] === null) {
                    Database::update('interview_answers', ['shown_at' => now()], ['id' => $nextAnswer['id']]);
                }
            }
            return ['followup' => $followupInserted, 'next' => $next];
        });
        return $result;
    }

    /** Next unanswered position after $from, or the first open one before it, null when all are done. */
    private static function nextOpenPosition(int $sessionId, int $from): ?int
    {
        $after = Database::fetchColumn(
            "SELECT MIN(position) FROM interview_answers WHERE session_id = :s AND position > :p AND status IN ('pending','draft')",
            ['s' => $sessionId, 'p' => $from]
        );
        if ($after !== null) {
            return (int) $after;
        }
        $before = Database::fetchColumn(
            "SELECT MIN(position) FROM interview_answers WHERE session_id = :s AND status IN ('pending','draft')",
            ['s' => $sessionId]
        );
        return $before !== null ? (int) $before : null;
    }

    /**
     * Predefined follow ups stored in question_followups. Triggers:
     * keywords (answer mentions one of the trigger words), short_answer (fewer than min_words words),
     * missing_results (no result marker or figure), missing_personal_role (no first person action),
     * always (every time follow ups are enabled). Intensive mode always asks the first defined follow up.
     */
    public static function maybeInsertFollowup(array $session, array $answer, string $text): bool
    {
        $followups = Question::followups((int) $answer['question_id'], $session['locale']);
        if ($followups === []) {
            return false;
        }
        $existing = (int) Database::fetchColumn('SELECT COUNT(*) FROM interview_answers WHERE session_id = :s AND followup_id IS NOT NULL', ['s' => $session['id']]);
        if ($existing >= max(2, intdiv((int) $session['question_count'], 2))) {
            return false;
        }
        $chosen = null;
        foreach ($followups as $f) {
            if (self::followupApplies($f, $text, $session['locale'])) {
                $chosen = $f;
                break;
            }
        }
        if ($chosen === null && $session['mode'] === 'intensive') {
            $chosen = $followups[0];
        }
        if ($chosen === null) {
            return false;
        }
        $pos = (int) $answer['position'];
        // Shift later slots, highest first, to keep the (session, position) unique key valid.
        Database::query(
            'UPDATE interview_answers SET position = position + 1 WHERE session_id = :s AND position > :p ORDER BY position DESC',
            ['s' => $session['id'], 'p' => $pos]
        );
        Database::insert('interview_answers', [
            'session_id' => $session['id'],
            'question_id' => $answer['question_id'],
            'followup_id' => $chosen['id'],
            'parent_answer_id' => $answer['id'],
            'position' => $pos + 1,
            'status' => 'pending',
        ]);
        return true;
    }

    public static function followupApplies(array $followup, string $text, string $locale): bool
    {
        $a = new TextAnalyzer($text);
        return match ($followup['trigger_type']) {
            'always' => true,
            'short_answer' => $a->wordCount < max(1, (int) $followup['min_words']),
            'keywords' => $a->countTerms(array_filter(array_map('trim', explode(',', (string) $followup['trigger_keywords'])))) > 0,
            'missing_results' => $a->countTerms(Lexicon::get('results', $locale)) === 0 && $a->numberCount() === 0,
            'missing_personal_role' => $a->countTerms(Lexicon::get('actions', $locale)) === 0,
            default => false,
        };
    }

    public static function goTo(array $session, int $position): void
    {
        self::assertActive($session);
        $answer = self::answerAt((int) $session['id'], $position);
        if (!$answer) {
            throw new HttpException(404);
        }
        $open = in_array($answer['status'], ['pending', 'draft'], true);
        if (!$session['allow_back'] && !($open && $position === self::nextOpenPosition((int) $session['id'], 0))) {
            throw new \DomainException(t('interview.errors.back_not_allowed'));
        }
        Database::update('interview_sessions', ['current_position' => $position, 'updated_at' => now()], ['id' => $session['id']]);
        if ($answer['shown_at'] === null) {
            Database::update('interview_answers', ['shown_at' => now()], ['id' => $answer['id']]);
        }
    }

    public static function pause(array $session): void
    {
        self::assertActive($session);
        if (!$session['allow_pause']) {
            throw new \DomainException(t('interview.errors.pause_not_allowed'));
        }
        Database::update('interview_sessions', ['status' => 'paused', 'paused_at' => now(), 'updated_at' => now()], ['id' => $session['id']]);
    }

    public static function resume(array $session): void
    {
        if ($session['status'] !== 'paused') {
            return;
        }
        $pausedFor = max(0, time() - strtotime(($session['paused_at'] ?? now()) . ' UTC'));
        Database::transaction(static function () use ($session, $pausedFor) {
            Database::update('interview_sessions', [
                'status' => 'in_progress',
                'paused_at' => null,
                'paused_seconds' => (int) $session['paused_seconds'] + $pausedFor,
                'updated_at' => now(),
            ], ['id' => $session['id']]);
            // The current question's clock does not run while paused.
            Database::query(
                'UPDATE interview_answers SET shown_at = DATE_ADD(shown_at, INTERVAL :sec SECOND)
                 WHERE session_id = :s AND position = :p AND shown_at IS NOT NULL',
                ['sec' => $pausedFor, 's' => $session['id'], 'p' => $session['current_position']]
            );
        });
    }

    /**
     * Close the session: drafts are submitted as they are, untouched questions count as skipped (score 0),
     * then the overall score and the skill progression are computed.
     */
    public static function finish(array $session): array
    {
        if ($session['status'] === 'completed') {
            return $session;
        }
        if ($session['status'] === 'abandoned') {
            throw new \DomainException(t('interview.errors.closed'));
        }
        Database::transaction(static function () use ($session) {
            foreach (self::answers((int) $session['id']) as $a) {
                if ($a['status'] === 'draft' && trim((string) $a['answer_text']) !== '') {
                    Database::update('interview_answers', ['status' => 'answered', 'submitted_at' => now(),
                        'time_spent_seconds' => max((int) $a['time_spent_seconds'], $a['shown_at'] ? time() - strtotime($a['shown_at'] . ' UTC') : 0)], ['id' => $a['id']]);
                    EvaluationService::evaluateAnswer((int) $a['id']);
                } elseif (in_array($a['status'], ['pending', 'draft'], true)) {
                    Database::update('interview_answers', ['status' => 'skipped', 'answer_text' => null, 'submitted_at' => now()], ['id' => $a['id']]);
                    EvaluationService::evaluateAnswer((int) $a['id']);
                }
            }
            $score = Database::fetchColumn(
                'SELECT AVG(e.score) FROM answer_evaluations e JOIN interview_answers a ON a.id = e.answer_id WHERE a.session_id = :s',
                ['s' => $session['id']]
            );
            $evaluators = Database::fetchAll(
                'SELECT DISTINCT e.evaluator FROM answer_evaluations e JOIN interview_answers a ON a.id = e.answer_id WHERE a.session_id = :s',
                ['s' => $session['id']]
            );
            $pausedExtra = $session['status'] === 'paused' && $session['paused_at'] ? time() - strtotime($session['paused_at'] . ' UTC') : 0;
            Database::update('interview_sessions', [
                'status' => 'completed',
                'completed_at' => now(),
                'paused_at' => null,
                'paused_seconds' => (int) $session['paused_seconds'] + $pausedExtra,
                'overall_score' => $score === null ? 0 : round((float) $score, 2),
                'evaluator' => count($evaluators) > 1 ? 'mixed' : ($evaluators[0]['evaluator'] ?? 'rules'),
                'updated_at' => now(),
            ], ['id' => $session['id']]);
            ProgressService::recordSession((int) $session['id'], (int) $session['user_id']);
        });
        return Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]) ?? $session;
    }

    public static function abandon(array $session): void
    {
        if (in_array($session['status'], ['in_progress', 'paused'], true)) {
            Database::update('interview_sessions', ['status' => 'abandoned', 'updated_at' => now()], ['id' => $session['id']]);
        }
    }

    private static function cleanText(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        return mb_substr($text, 0, 12000);
    }
}
