<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Crypto;
use SysRevAI\Core\Database;

/**
 * Persistence for the key/value settings table. Encryption of sensitive values
 * (type = 'encrypted') happens here so callers never handle ciphertext.
 */
final class Setting
{
    /** @return array<string,array{value:?string,type:string}> */
    public static function loadAll(): array
    {
        $table = Database::table('settings');
        $rows = Database::select("SELECT `key`, `value`, `type` FROM `{$table}`");
        $map = [];
        foreach ($rows as $row) {
            $map[$row['key']] = ['value' => $row['value'], 'type' => $row['type']];
        }
        return $map;
    }

    public static function save(
        string $key,
        mixed $value,
        string $type = 'string',
        string $group = 'general',
        bool $isPublic = false,
        ?int $updatedBy = null
    ): void {
        $stored = self::serialize($value, $type);

        $table = Database::table('settings');
        Database::affecting(
            "INSERT INTO `{$table}` (`key`,`value`,`type`,`group`,`is_public`,`updated_by`)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                `value`=VALUES(`value`), `type`=VALUES(`type`),
                `group`=VALUES(`group`), `is_public`=VALUES(`is_public`),
                `updated_by`=VALUES(`updated_by`)",
            [$key, $stored, $type, $group, $isPublic ? 1 : 0, $updatedBy]
        );
    }

    /** Convert a PHP value to its stored string form for a given type. */
    public static function serialize(mixed $value, string $type): ?string
    {
        return match ($type) {
            'bool'      => $value ? '1' : '0',
            'int'       => (string) (int) $value,
            'json'      => json_encode($value, JSON_UNESCAPED_UNICODE),
            'encrypted' => ($value === null || $value === '') ? null : Crypto::encrypt((string) $value),
            default     => $value === null ? null : (string) $value,
        };
    }

    /** Convert a stored string back to a typed PHP value. */
    public static function deserialize(?string $stored, string $type): mixed
    {
        if ($stored === null) {
            return null;
        }
        return match ($type) {
            'bool'      => $stored === '1' || $stored === 'true',
            'int'       => (int) $stored,
            'json'      => json_decode($stored, true),
            'encrypted' => Crypto::decrypt($stored),
            default     => $stored,
        };
    }
}
