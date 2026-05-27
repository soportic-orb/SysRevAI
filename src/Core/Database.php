<?php

declare(strict_types=1);

namespace SysRevAI\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper: a single shared connection plus small query helpers.
 * All queries use prepared statements; there are no string-interpolated values.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = [
                'host'     => config('database.host'),
                'port'     => config('database.port'),
                'database' => config('database.database'),
                'username' => config('database.username'),
                'password' => config('database.password'),
                'charset'  => config('database.charset', 'utf8mb4'),
            ];
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset={$c['charset']}";
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /** Allow tests/installer to inject a connection. */
    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function prefix(): string
    {
        return (string) config('database.prefix', 'sra_');
    }

    /** Prefix a logical table name, e.g. table('users') => 'sra_users'. */
    public static function table(string $name): string
    {
        return self::prefix() . $name;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function select(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function affecting(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }
}
