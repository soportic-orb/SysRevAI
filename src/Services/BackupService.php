<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use PDO;
use SysRevAI\Core\Database;

/**
 * Database backups as gzipped SQL dumps, produced through PDO (no external
 * mysqldump dependency, so it works on restricted hosts).
 */
final class BackupService
{
    private static function dir(): string
    {
        $dir = (string) config('paths.backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** @return array{ok:bool,file?:string,message:string} */
    public static function create(): array
    {
        try {
            $pdo = Database::pdo();
            $file = self::dir() . '/sysrevai-' . date('Ymd-His') . '.sql.gz';
            $gz = gzopen($file, 'wb9');
            if ($gz === false) {
                return ['ok' => false, 'message' => 'Cannot open backup file for writing.'];
            }

            gzwrite($gz, "-- SysRevAI backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $ddl = $create['Create Table'] ?? '';
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n\n");

                $rows = $pdo->query("SELECT * FROM `{$table}`");
                foreach ($rows as $row) {
                    $cols = '`' . implode('`,`', array_keys($row)) . '`';
                    $vals = implode(',', array_map(
                        static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($row)
                    ));
                    gzwrite($gz, "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n");
                }
                gzwrite($gz, "\n");
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
            gzclose($gz);

            return ['ok' => true, 'file' => basename($file), 'message' => 'Backup created.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array<int,array{name:string,size:int,created:int}> */
    public static function list(): array
    {
        $files = glob(self::dir() . '/*.sql.gz') ?: [];
        rsort($files);
        return array_map(static fn ($f) => [
            'name'    => basename($f),
            'size'    => (int) filesize($f),
            'created' => (int) filemtime($f),
        ], $files);
    }

    /** Resolve a safe absolute path for a backup file, or null if invalid. */
    public static function path(string $name): ?string
    {
        if (!preg_match('/^sysrevai-\d{8}-\d{6}\.sql\.gz$/', $name)) {
            return null;
        }
        $path = self::dir() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    public static function delete(string $name): bool
    {
        $path = self::path($name);
        return $path !== null && @unlink($path);
    }
}
