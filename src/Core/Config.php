<?php

declare(strict_types=1);

namespace SysRevAI\Core;

use SysRevAI\Models\Setting;

/**
 * In-memory cache of the DB-backed settings, so we hit the database at most
 * once per request. Backs the global setting() helper.
 */
final class Config
{
    /** @var array<string,array{value:?string,type:string}>|null */
    private static ?array $cache = null;

    private static function ensureLoaded(): void
    {
        if (self::$cache !== null) {
            return;
        }
        try {
            self::$cache = Setting::loadAll();
        } catch (\Throwable) {
            // Settings table unavailable (e.g. pre-install): degrade to defaults.
            self::$cache = [];
        }
    }

    /** Inject a cache directly (tests). */
    public static function seed(array $cache): void
    {
        self::$cache = $cache;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function has(string $key): bool
    {
        self::ensureLoaded();
        return isset(self::$cache[$key]);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureLoaded();
        if (!isset(self::$cache[$key])) {
            return $default;
        }
        $entry = self::$cache[$key];
        $value = Setting::deserialize($entry['value'], $entry['type']);
        return $value ?? $default;
    }

    public static function type(string $key): ?string
    {
        self::ensureLoaded();
        return self::$cache[$key]['type'] ?? null;
    }

    /** True if a stored encrypted value exists (without decrypting it). */
    public static function hasEncrypted(string $key): bool
    {
        self::ensureLoaded();
        $entry = self::$cache[$key] ?? null;
        return $entry !== null && $entry['type'] === 'encrypted' && !empty($entry['value']);
    }

    public static function set(
        string $key,
        mixed $value,
        string $type = 'string',
        string $group = 'general',
        bool $isPublic = false
    ): void {
        Setting::save($key, $value, $type, $group, $isPublic, Auth::id());
        self::ensureLoaded();
        self::$cache[$key] = ['value' => Setting::serialize($value, $type), 'type' => $type];
    }
}
