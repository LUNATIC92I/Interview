<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper. Every query uses prepared statements with bound parameters.
 * Table and column names passed to the helpers always come from application code, never from user input.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('database');
            $dsn = !empty($c['socket'])
                ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $c['socket'], $c['database'], $c['charset'])
                : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['database'], $c['charset']);
            try {
                self::$pdo = new PDO($dsn, (string) $c['username'], (string) $c['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'");
            } catch (PDOException $e) {
                Logger::error('Database connection failed', ['message' => $e->getMessage()]);
                throw new DatabaseUnavailableException('Database unavailable', 0, $e);
            }
        }
        return self::$pdo;
    }

    /** Replace the active connection (used by tests). */
    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /** @param array<int|string, mixed> $params */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return array<string, mixed>|null */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<string, mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $columns)),
            implode(', ', array_map(static fn ($c) => ":$c", $columns))
        );
        self::query($sql, $data);
        return (int) self::connection()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public static function update(string $table, array $data, array $where): int
    {
        $set = [];
        $params = [];
        foreach ($data as $col => $value) {
            $set[] = "`$col` = :set_$col";
            $params["set_$col"] = $value;
        }
        $conditions = [];
        foreach ($where as $col => $value) {
            $conditions[] = "`$col` = :where_$col";
            $params["where_$col"] = $value;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), implode(' AND ', $conditions));
        return self::query($sql, $params)->rowCount();
    }

    /** @param array<string, mixed> $where */
    public static function delete(string $table, array $where): int
    {
        $conditions = [];
        foreach (array_keys($where) as $col) {
            $conditions[] = "`$col` = :$col";
        }
        return self::query(sprintf('DELETE FROM `%s` WHERE %s', $table, implode(' AND ', $conditions)), $where)->rowCount();
    }

    /**
     * Run a callback inside a transaction (re-entrant: nested calls join the outer transaction).
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            return $callback();
        }
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Build "IN (:p0, :p1)" placeholders and merge their values into $params. */
    public static function in(array $values, array &$params, string $prefix = 'in'): string
    {
        if ($values === []) {
            return '(NULL)';
        }
        $names = [];
        foreach (array_values($values) as $i => $v) {
            $name = $prefix . $i;
            $names[] = ':' . $name;
            $params[$name] = $v;
        }
        return '(' . implode(', ', $names) . ')';
    }

    /** Escape LIKE wildcards in user supplied search terms. */
    public static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
