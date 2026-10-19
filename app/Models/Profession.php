<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Translator;

final class Profession
{
    private static function select(): string
    {
        return 'SELECT p.*, COALESCE(t1.name, t2.name) AS name, COALESCE(t1.summary, t2.summary) AS summary,
                COALESCE(t1.responsibilities, t2.responsibilities) AS responsibilities, (t1.name IS NULL) AS is_fallback,
                COALESCE(ct1.name, ct2.name) AS category_name, c.slug AS category_slug, c.icon AS category_icon
            FROM professions p
            JOIN profession_categories c ON c.id = p.category_id
            LEFT JOIN profession_translations t1 ON t1.profession_id = p.id AND t1.locale = :loc
            LEFT JOIN profession_translations t2 ON t2.profession_id = p.id AND t2.locale = :other
            LEFT JOIN profession_category_translations ct1 ON ct1.category_id = c.id AND ct1.locale = :loc2
            LEFT JOIN profession_category_translations ct2 ON ct2.category_id = c.id AND ct2.locale = :other2';
    }

    private static function locParams(): array
    {
        $loc = Translator::locale();
        $other = Translator::other($loc);
        return ['loc' => $loc, 'other' => $other, 'loc2' => $loc, 'other2' => $other];
    }

    /**
     * Public catalogue with search and category filter, paginated server side.
     * @return array{items: list<array>, total: int}
     */
    public static function catalog(string $search = '', ?int $categoryId = null, int $page = 1, int $perPage = 24, bool $activeOnly = true): array
    {
        $params = self::locParams();
        $where = $activeOnly ? ['p.is_active = 1', 'c.is_active = 1'] : ['1 = 1'];
        if ($categoryId) {
            $where[] = 'p.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        if ($search !== '') {
            $where[] = '(t1.name LIKE :q1 OR t2.name LIKE :q2 OR t1.summary LIKE :q3 OR p.slug LIKE :q4)';
            $like = Database::like($search);
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $base = self::select() . ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::fetchColumn('SELECT COUNT(*) FROM (' . $base . ') x', $params);
        $items = Database::fetchAll(
            'SELECT x.*, (SELECT COUNT(*) FROM questions q WHERE q.profession_id = x.id AND q.is_active = 1) AS question_count
             FROM (' . $base . ') x ORDER BY x.category_id, x.sort_order, x.name LIMIT :lim OFFSET :off',
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['items' => $items, 'total' => $total];
    }

    /** Lightweight list for selects. */
    public static function options(bool $activeOnly = true): array
    {
        return Database::fetchAll(
            self::select() . ($activeOnly ? ' WHERE p.is_active = 1' : '') . ' ORDER BY c.sort_order, name',
            self::locParams()
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(self::select() . ' WHERE p.id = :id', self::locParams() + ['id' => $id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch(self::select() . ' WHERE p.slug = :slug AND p.is_active = 1', self::locParams() + ['slug' => $slug]);
    }

    public static function skills(int $professionId): array
    {
        return Database::fetchAll(
            'SELECT s.*, sc.name_fr AS category_name_fr, sc.name_en AS category_name_en FROM profession_skills ps
             JOIN skills s ON s.id = ps.skill_id JOIN skill_categories sc ON sc.id = s.skill_category_id
             WHERE ps.profession_id = :id ORDER BY sc.sort_order, s.id',
            ['id' => $professionId]
        );
    }

    /** Question counts by type for a profession (specific + category + generic). */
    public static function questionStats(array $profession): array
    {
        return Database::fetchAll(
            'SELECT question_type, COUNT(*) AS n FROM questions
             WHERE is_active = 1 AND (profession_id = :p OR (profession_id IS NULL AND category_id = :c) OR (profession_id IS NULL AND category_id IS NULL))
             GROUP BY question_type ORDER BY n DESC',
            ['p' => $profession['id'], 'c' => $profession['category_id']]
        );
    }

    public static function translations(int $id): array
    {
        return array_column(Database::fetchAll('SELECT * FROM profession_translations WHERE profession_id = :id', ['id' => $id]), null, 'locale');
    }

    public static function related(array $profession, int $limit = 4): array
    {
        return Database::fetchAll(
            self::select() . ' WHERE p.category_id = :cat AND p.id <> :id AND p.is_active = 1 ORDER BY p.sort_order LIMIT :lim',
            self::locParams() + ['cat' => $profession['category_id'], 'id' => $profession['id'], 'lim' => $limit]
        );
    }
}
