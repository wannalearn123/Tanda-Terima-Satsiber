<?php

declare(strict_types=1);

namespace App\Config;

final class Config
{
    private static array $env = [];
    private static bool $loaded = false;
    private static string $root = '';

    public static function root(): string
    {
        if (self::$root === '') {
            self::$root = dirname(__DIR__, 2);
        }
        return self::$root;
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        $file = self::root() . '/.env';
        if (!is_file($file)) {
            $file = self::root() . '/.env.example';
        }
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            self::$env[$key] = $val;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        self::load();
        return $_ENV[$key] ?? $_SERVER[$key] ?? self::$env[$key] ?? $default;
    }

    public static function dbPath(): string
    {
        $p = self::get('DB_PATH', 'database/database.sqlite');
        if (!str_starts_with($p, '/')) {
            $p = self::root() . '/' . $p;
        }
        return $p;
    }

    public static function perPage(): int
    {
        $n = (int) self::get('PER_PAGE', '20');
        return $n > 0 && $n <= 100 ? $n : 20;
    }

    public static function isDev(): bool
    {
        return self::get('APP_ENV', 'production') !== 'production';
    }
}
