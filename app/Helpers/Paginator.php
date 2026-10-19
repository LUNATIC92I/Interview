<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Server side pagination metadata and links that keep the current filters.
 */
final class Paginator
{
    public readonly int $pages;

    public function __construct(
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly string $path,
        public readonly array $query = [],
    ) {
        $this->pages = max(1, (int) ceil($total / max(1, $perPage)));
    }

    public static function page(array $query): int
    {
        return max(1, (int) ($query['page'] ?? 1));
    }

    public function url(int $page): string
    {
        $q = array_filter($this->query, static fn ($v) => $v !== '' && $v !== null);
        $q['page'] = $page;
        return url($this->path, $q);
    }

    /** @return list<int|null> page numbers with null as gaps */
    public function window(): array
    {
        $out = [];
        for ($i = 1; $i <= $this->pages; $i++) {
            if ($i === 1 || $i === $this->pages || abs($i - $this->page) <= 2) {
                $out[] = $i;
            } elseif (end($out) !== null) {
                $out[] = null;
            }
        }
        return $out;
    }
}
