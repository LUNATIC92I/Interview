<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Rubric
{
    public static function all(): array
    {
        return Database::fetchAll('SELECT * FROM evaluation_rubrics ORDER BY id');
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM evaluation_rubrics WHERE id = :id', ['id' => $id]);
    }

    /** @return list<array> criteria ordered for display */
    public static function criteria(int $rubricId): array
    {
        return Database::fetchAll('SELECT * FROM rubric_criteria WHERE rubric_id = :id ORDER BY sort_order, id', ['id' => $rubricId]);
    }

    /** Criteria indexed by rubric id (avoids repeated queries when rendering reports). */
    public static function criteriaByRubric(): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT * FROM rubric_criteria ORDER BY sort_order, id') as $row) {
            $out[(int) $row['rubric_id']][$row['code']] = $row;
        }
        return $out;
    }
}
