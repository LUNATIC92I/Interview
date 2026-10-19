<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Difficulty
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = array_column(Database::fetchAll('SELECT * FROM difficulty_levels ORDER BY id'), null, 'id');
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function label(int $id): string
    {
        $row = self::all()[$id] ?? null;
        return $row ? loc($row) : (string) $id;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
