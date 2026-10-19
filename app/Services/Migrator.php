<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Versioned SQL migrations (database/migrations/NNN_name.sql), applied once each and recorded in
 * schema_migrations. Migrations only add or alter structures; they never drop user data.
 */
final class Migrator
{
    public function __construct(private readonly string $directory)
    {
    }

    private function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> */
    public function files(): array
    {
        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    /** @return array<string, string|null> version => applied_at */
    public function status(): array
    {
        $this->ensureTable();
        $applied = array_column(Database::fetchAll('SELECT version, applied_at FROM schema_migrations'), 'applied_at', 'version');
        $out = [];
        foreach ($this->files() as $file) {
            $v = basename($file, '.sql');
            $out[$v] = $applied[$v] ?? null;
        }
        return $out;
    }

    /** @return list<string> versions applied now */
    public function migrate(?callable $log = null): array
    {
        $done = [];
        foreach ($this->status() as $version => $appliedAt) {
            if ($appliedAt !== null) {
                continue;
            }
            $sql = (string) file_get_contents($this->directory . '/' . $version . '.sql');
            foreach (self::split($sql) as $statement) {
                Database::connection()->exec($statement);
            }
            Database::insert('schema_migrations', ['version' => $version, 'applied_at' => now()]);
            $done[] = $version;
            if ($log) {
                $log($version);
            }
        }
        return $done;
    }

    /** Split a SQL file into statements (statements end with ";" at end of line; "--" comments ignored). */
    public static function split(string $sql): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $sql)), static fn ($l) => !str_starts_with(ltrim($l), '--'));
        $statements = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $statements), static fn ($s) => $s !== ''));
    }
}
