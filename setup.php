<?php

declare(strict_types=1);

// Inisialisasi database fresh deployment. Jalankan dari root proyek:
//   php setup.php
// CLI only. Membuat database/database.sqlite + tabel + index + seed.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('setup.php hanya untuk CLI.');
}

$root = __DIR__;
require $root . '/app/Config/Config.php';

use App\Config\Config;

$dbPath = Config::dbPath();
foreach ([$root . '/database', $root . '/storage/pdf', $root . '/storage/logs', $root . '/storage/tandatangan'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "mkdir $dir\n";
    }
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("PRAGMA journal_mode = WAL;");
$pdo->exec("PRAGMA foreign_keys = ON;");
$pdo->exec("PRAGMA busy_timeout = 5000;");

$now = date('Y-m-d H:i:s');

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS pusat_penerima (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        kode TEXT NOT NULL UNIQUE,
        aktif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS pengiriman (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nomor_referensi TEXT NOT NULL,
        pusat_penerima_id INTEGER NULL REFERENCES pusat_penerima(id) ON UPDATE CASCADE ON DELETE SET NULL,
        nama_penerima TEXT NOT NULL,
        pangkat_golongan TEXT NOT NULL DEFAULT '',
        jabatan TEXT NOT NULL DEFAULT '',
        tanggal TEXT NOT NULL,
        pukul TEXT NOT NULL,
        telp_hp TEXT NOT NULL DEFAULT '',
        catatan TEXT NOT NULL DEFAULT '',
        tanda_tangan_path TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);
foreach ([
    "CREATE INDEX IF NOT EXISTS idx_pengiriman_tanggal ON pengiriman(tanggal)",
    "CREATE INDEX IF NOT EXISTS idx_pengiriman_nomor ON pengiriman(nomor_referensi)",
    "CREATE INDEX IF NOT EXISTS idx_pengiriman_nama ON pengiriman(nama_penerima)",
    "CREATE INDEX IF NOT EXISTS idx_pengiriman_pusat ON pengiriman(pusat_penerima_id)",
] as $sql) {
    $pdo->exec($sql);
}

// --- Autentikasi (tambah belakangan; idempotent untuk DB lama) ---
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        nama TEXT NOT NULL DEFAULT '',
        role TEXT NOT NULL DEFAULT 'operator',
        aktif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);
$count = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count === 0) {
    $st = $pdo->prepare(
        "INSERT INTO users (username, password_hash, nama, role, aktif, created_at, updated_at)
         VALUES ('admin', :h, 'Administrator', 'admin', 1, :t, :t)"
    );
    $st->execute([':h' => password_hash('admin123', PASSWORD_DEFAULT), ':t' => $now]);
    echo "Seed user: admin / admin123 (SEGERA ganti password via menu Pengguna)\n";
}

$seed = [
    ['Kompi TNI', 'KOMPI'],
    ['Satker TNI', 'SATKER'],
    ['Kogabwilhan TNI', 'KOGABWILHAN'],
];
$st = $pdo->prepare("INSERT OR IGNORE INTO pusat_penerima (nama, kode, aktif, created_at, updated_at) VALUES (:n, :k, 1, :t, :t)");
foreach ($seed as [$nama, $kode]) {
    $st->execute([':n' => $nama, ':k' => $kode, ':t' => $now]);
}

echo "OK database siap: $dbPath\n";
echo "WAL: " . $pdo->query("PRAGMA journal_mode")->fetchColumn() . "\n";
echo "Lanjut: composer install && php -S 0.0.0.0:8090 public/router.php\n";
