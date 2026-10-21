<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Skill
{
    public static function all(): array
    {
        return Database::fetchAll(
            'SELECT s.*, sc.code AS category_code, sc.name_fr AS category_name_fr, sc.name_en AS category_name_en
             FROM skills s JOIN skill_categories sc ON sc.id = s.skill_category_id ORDER BY sc.sort_order, s.id'
        );
    }

    public static function byIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $params = [];
        $in = Database::in(array_map('intval', $ids), $params, 'sk');
        return array_column(Database::fetchAll("SELECT * FROM skills WHERE id IN $in", $params), null, 'id');
    }

    public static function categories(): array
    {
        return Database::fetchAll('SELECT * FROM skill_categories ORDER BY sort_order, id');
    }
}
