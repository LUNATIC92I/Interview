<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Translator;

final class Category
{
    private const SELECT = 'SELECT c.*, COALESCE(t1.name, t2.name) AS name, COALESCE(t1.description, t2.description) AS description,
            (t1.name IS NULL) AS is_fallback
        FROM profession_categories c
        LEFT JOIN profession_category_translations t1 ON t1.category_id = c.id AND t1.locale = :loc
        LEFT JOIN profession_category_translations t2 ON t2.category_id = c.id AND t2.locale = :other';

    public static function all(bool $activeOnly = true): array
    {
        $loc = Translator::locale();
        return Database::fetchAll(
            self::SELECT . ($activeOnly ? ' WHERE c.is_active = 1' : '') . ' ORDER BY c.sort_order, name',
            ['loc' => $loc, 'other' => Translator::other($loc)]
        );
    }

    /** Categories with their number of active professions. */
    public static function withCounts(): array
    {
        $loc = Translator::locale();
        return Database::fetchAll(
            'SELECT x.*, (SELECT COUNT(*) FROM professions p WHERE p.category_id = x.id AND p.is_active = 1) AS profession_count
             FROM (' . self::SELECT . ' WHERE c.is_active = 1) x ORDER BY x.sort_order',
            ['loc' => $loc, 'other' => Translator::other($loc)]
        );
    }

    public static function find(int $id): ?array
    {
        $loc = Translator::locale();
        return Database::fetch(self::SELECT . ' WHERE c.id = :id', ['id' => $id, 'loc' => $loc, 'other' => Translator::other($loc)]);
    }

    public static function translations(int $id): array
    {
        $rows = Database::fetchAll('SELECT * FROM profession_category_translations WHERE category_id = :id', ['id' => $id]);
        return array_column($rows, null, 'locale');
    }
}
