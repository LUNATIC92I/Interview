<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Translator;

final class Scenario
{
    private static function select(): string
    {
        return 'SELECT s.*, COALESCE(t1.title, t2.title) AS title, COALESCE(t1.description, t2.description) AS description,
                (t1.title IS NULL) AS is_fallback,
                (SELECT COUNT(*) FROM scenario_questions sq WHERE sq.scenario_id = s.id) AS question_count
            FROM scenarios s
            LEFT JOIN scenario_translations t1 ON t1.scenario_id = s.id AND t1.locale = :loc
            LEFT JOIN scenario_translations t2 ON t2.scenario_id = s.id AND t2.locale = :other';
    }

    private static function lp(): array
    {
        $l = Translator::locale();
        return ['loc' => $l, 'other' => Translator::other($l)];
    }

    public static function all(bool $activeOnly = true): array
    {
        return Database::fetchAll(self::select() . ($activeOnly ? ' WHERE s.is_active = 1' : '') . ' ORDER BY s.id', self::lp());
    }

    /** Scenarios specific to the profession, to its category, or generic (no profession and no category). */
    public static function forProfession(array $profession): array
    {
        return Database::fetchAll(
            self::select() . ' WHERE s.is_active = 1 AND (s.profession_id = :p OR (s.profession_id IS NULL AND (s.category_id = :c OR s.category_id IS NULL)))
                ORDER BY s.profession_id IS NULL, s.category_id IS NULL, s.id',
            self::lp() + ['p' => $profession['id'], 'c' => $profession['category_id']]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(self::select() . ' WHERE s.id = :id', self::lp() + ['id' => $id]);
    }

    public static function questionIds(int $scenarioId): array
    {
        return array_map('intval', array_column(Database::fetchAll(
            'SELECT question_id FROM scenario_questions WHERE scenario_id = :id ORDER BY position',
            ['id' => $scenarioId]
        ), 'question_id'));
    }

    public static function translations(int $id): array
    {
        return array_column(Database::fetchAll('SELECT * FROM scenario_translations WHERE scenario_id = :id', ['id' => $id]), null, 'locale');
    }
}
