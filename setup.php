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
    "CREATE TABLE IF NOT EXISTS pengiriman (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nomor_referensi TEXT NOT NULL,
        nama_satuan_kerja TEXT NOT NULL,
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
] as $sql) {
    $pdo->exec($sql);
}

// --- Agenda surat (dikelompokkan per arah + sub-jenis; nomor MAX+1 per grup) ---
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS agenda_surat (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        arah TEXT NOT NULL CHECK(arah IN ('masuk','keluar')),
        sub_jenis TEXT NOT NULL,
        no_agenda INTEGER NOT NULL,
        no_surat TEXT NOT NULL,
        tanggal TEXT NOT NULL,
        kepada TEXT NOT NULL,
        perihal TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(arah, sub_jenis, no_agenda)
    )"
);

foreach ([
    "CREATE INDEX IF NOT EXISTS idx_agenda_arah_tanggal ON agenda_surat(arah, tanggal)",
    "CREATE INDEX IF NOT EXISTS idx_agenda_no_surat ON agenda_surat(no_surat)",
    "CREATE INDEX IF NOT EXISTS idx_agenda_kepada ON agenda_surat(kepada)",
    "CREATE INDEX IF NOT EXISTS idx_agenda_grup ON agenda_surat(arah, sub_jenis, no_agenda)",
] as $sql) {
    $pdo->exec($sql);
}

// --- Master jenis surat Agenda (satu tabel, flag tampil + kode nomor per arah) ---
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS agenda_sub_jenis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL UNIQUE,
        tampil_masuk INTEGER NOT NULL DEFAULT 1 CHECK(tampil_masuk IN (0,1)),
        tampil_keluar INTEGER NOT NULL DEFAULT 1 CHECK(tampil_keluar IN (0,1)),
        kode_masuk TEXT NULL,
        kode_keluar TEXT NULL,
        urutan INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);

$seedJenis = [
    // [nama, tampil_masuk, tampil_keluar, kode_masuk, kode_keluar, urutan]
    ['Surat Rahasia', 1, 1, 'SMR', 'SKR', 1],
    ['Surat Telegram', 1, 1, 'SMT', 'SKT', 2],
    ['Surat Biasa', 1, 1, 'SMB', 'SKB', 3],
    ['Surat Edaran', 1, 1, 'SME', 'SKE', 4],
    ['Surat Keputusan', 1, 1, 'SMK', 'SKK', 5],
    ['Surat Perintah (Sprin)', 1, 1, 'SMS', 'SKS', 6],
    ['Surat Ijin Jalan', 0, 1, null, 'SKIJ', 7],
    ['Surat Ijin Nikah', 0, 1, null, 'SKIN', 8],
    ['Surat Keterangan', 0, 1, null, 'SKKet', 9],
];
$stJenis = $pdo->prepare(
    "INSERT OR IGNORE INTO agenda_sub_jenis (nama, tampil_masuk, tampil_keluar, kode_masuk, kode_keluar, urutan, created_at, updated_at)
     VALUES (:nama, :m, :k, :km, :kk, :u, :t, :t)"
);
foreach ($seedJenis as [$nama, $m, $k, $km, $kk, $u]) {
    $stJenis->execute([':nama' => $nama, ':m' => $m, ':k' => $k, ':km' => $km, ':kk' => $kk, ':u' => $u, ':t' => $now]);
}

// --- Riwayat disposisi Agenda (satu surat -> banyak entry kronologis) ---
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS agenda_disposisi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        agenda_id INTEGER NOT NULL REFERENCES agenda_surat(id) ON DELETE CASCADE,
        aktor TEXT NOT NULL,
        kegiatan TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_disposisi_agenda ON agenda_disposisi(agenda_id, id)");

// --- Autentikasi ---
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

echo "OK database siap: $dbPath\n";
echo "WAL: " . $pdo->query("PRAGMA journal_mode")->fetchColumn() . "\n";
echo "Lanjut: php -S 0.0.0.0:8090 public/router.php\n";
