<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use PDO;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $path = Config::dbPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Concurrency ringan-menengah: WAL + FK + busy timeout. Transaksi dibuat pendek.
        $pdo->exec("PRAGMA journal_mode = WAL;");
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $pdo->exec("PRAGMA busy_timeout = 5000;");
        self::$pdo = $pdo;
        return $pdo;
    }

    /** Untuk testing / reset antar proses. */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
