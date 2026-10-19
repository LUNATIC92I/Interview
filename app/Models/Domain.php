<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Domain vocabularies shared by the simulation engine, forms and validation.
 * Labels are translated in lang/*.php under the matching keys.
 */
final class Domain
{
    /** Experience levels (ordinal value => code). */
    public const LEVELS = [1 => 'student', 2 => 'junior', 3 => 'intermediate', 4 => 'senior', 5 => 'manager'];

    public const QUESTION_TYPES = [
        'hr', 'behavioral', 'technical', 'motivation', 'situational',
        'managerial', 'negotiation', 'international', 'problem_solving',
    ];

    /**
     * Interview types with the share of each question type used by the question picker.
     * @var array<string, array<string, int>>
     */
    public const INTERVIEW_TYPES = [
        'hr' => ['hr' => 4, 'behavioral' => 3, 'motivation' => 2, 'situational' => 1],
        'technical' => ['technical' => 7, 'problem_solving' => 2, 'behavioral' => 1],
        'behavioral' => ['behavioral' => 7, 'situational' => 2, 'hr' => 1],
        'manager' => ['technical' => 3, 'behavioral' => 3, 'situational' => 2, 'managerial' => 2],
        'director' => ['managerial' => 4, 'situational' => 3, 'motivation' => 2, 'behavioral' => 1],
        'international' => ['international' => 6, 'behavioral' => 2, 'motivation' => 2],
        'motivation' => ['motivation' => 6, 'hr' => 3, 'behavioral' => 1],
        'salary' => ['negotiation' => 7, 'motivation' => 2, 'hr' => 1],
        'problem_solving' => ['problem_solving' => 6, 'technical' => 2, 'situational' => 2],
        'situational' => ['situational' => 6, 'technical' => 2, 'problem_solving' => 2],
        'phone_screen' => ['hr' => 5, 'motivation' => 3, 'technical' => 2],
        'panel' => ['behavioral' => 3, 'technical' => 3, 'motivation' => 2, 'situational' => 2],
        'promotion' => ['managerial' => 4, 'behavioral' => 3, 'motivation' => 2, 'situational' => 1],
        'young_professionals' => ['international' => 4, 'behavioral' => 3, 'motivation' => 2, 'problem_solving' => 1],
    ];

    /**
     * Simulation modes and the rules they impose. "custom" lets the candidate choose.
     * per_question: seconds per question (null = no per question timer).
     */
    public const MODES = [
        'training' => ['skip' => true, 'back' => true, 'pause' => true, 'timer' => false, 'instant_feedback' => true, 'followups' => true],
        'realistic' => ['skip' => true, 'back' => false, 'pause' => true, 'timer' => false, 'instant_feedback' => false, 'followups' => true],
        'timed' => ['skip' => true, 'back' => false, 'pause' => false, 'timer' => true, 'instant_feedback' => false, 'followups' => false],
        'intensive' => ['skip' => false, 'back' => false, 'pause' => false, 'timer' => true, 'instant_feedback' => false, 'followups' => true],
        'custom' => ['skip' => true, 'back' => true, 'pause' => true, 'timer' => false, 'instant_feedback' => false, 'followups' => true],
    ];

    public const DURATIONS = [10, 15, 20, 30, 45, 60];
    public const QUESTION_COUNTS = [3, 5, 6, 8, 10, 12];
    public const FOLLOWUP_TRIGGERS = ['keywords', 'short_answer', 'missing_results', 'missing_personal_role', 'always'];
    public const REPORT_REASONS = ['incorrect', 'unclear', 'translation', 'inappropriate', 'other'];

    public static function levelCode(int $level): string
    {
        return self::LEVELS[$level] ?? 'intermediate';
    }

    public static function levelLabel(int $level): string
    {
        return t('levels.' . self::levelCode($level));
    }
}
