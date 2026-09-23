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

// --- Agenda surat (masuk/keluar, disposisi 3 kolom, no_agenda angka unik per arah) ---
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
        disposisi_aktor TEXT NOT NULL DEFAULT '',
        disposisi_kegiatan TEXT NOT NULL DEFAULT '',
        disposisi_selesai INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(arah, no_agenda)
    )"
);

foreach ([
    "CREATE INDEX IF NOT EXISTS idx_agenda_arah_tanggal ON agenda_surat(arah, tanggal)",
    "CREATE INDEX IF NOT EXISTS idx_agenda_no_surat ON agenda_surat(no_surat)",
    "CREATE INDEX IF NOT EXISTS idx_agenda_kepada ON agenda_surat(kepada)",
] as $sql) {
    $pdo->exec($sql);
}

// --- Master jenis surat Agenda (satu tabel, flag tampil per arah; tanpa kolom aktif) ---
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS agenda_sub_jenis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL UNIQUE,
        tampil_masuk INTEGER NOT NULL DEFAULT 1 CHECK(tampil_masuk IN (0,1)),
        tampil_keluar INTEGER NOT NULL DEFAULT 1 CHECK(tampil_keluar IN (0,1)),
        urutan INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);

$seedJenis = [
    // [nama, tampil_masuk, tampil_keluar, urutan]
    ['Surat Rahasia', 1, 1, 1],
    ['Surat Telegram', 1, 1, 2],
    ['Surat Biasa', 1, 1, 3],
    ['Surat Edaran', 1, 1, 4],
    ['Surat Keputusan', 1, 1, 5],
    ['Surat Perintah (Sprin)', 1, 1, 6],
    ['Surat Ijin Jalan', 0, 1, 7],
    ['Surat Ijin Nikah', 0, 1, 8],
    ['Surat Keterangan', 0, 1, 9],
];
$stJenis = $pdo->prepare(
    "INSERT OR IGNORE INTO agenda_sub_jenis (nama, tampil_masuk, tampil_keluar, urutan, created_at, updated_at)
     VALUES (:nama, :m, :k, :u, :t, :t)"
);
foreach ($seedJenis as [$nama, $m, $k, $u]) {
    $stJenis->execute([':nama' => $nama, ':m' => $m, ':k' => $k, ':u' => $u, ':t' => $now]);
}

// --- Riwayat disposisi Agenda (satu surat -> banyak entry kronologis) ---
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS agenda_disposisi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        agenda_id INTEGER NOT NULL REFERENCES agenda_surat(id) ON DELETE CASCADE,
        aktor TEXT NOT NULL,
        kegiatan TEXT NOT NULL,
        selesai INTEGER NOT NULL DEFAULT 0 CHECK(selesai IN (0,1)),
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_disposisi_agenda ON agenda_disposisi(agenda_id, id)");

// Migrasi sekali jalan: pindahkan disposisi tunggal lama menjadi entry riwayat pertama.
$agendaCols = array_column($pdo->query("PRAGMA table_info(agenda_surat)")->fetchAll(), 'name');
if (in_array('disposisi_aktor', $agendaCols, true)) {
    $pdo->exec(
        "INSERT INTO agenda_disposisi (agenda_id, aktor, kegiatan, selesai, created_at, updated_at)
         SELECT id, disposisi_aktor, disposisi_kegiatan, disposisi_selesai, created_at, updated_at
         FROM agenda_surat WHERE disposisi_aktor <> '' OR disposisi_kegiatan <> ''"
    );
    foreach (['disposisi_aktor', 'disposisi_kegiatan', 'disposisi_selesai'] as $dropCol) {
        $pdo->exec("ALTER TABLE agenda_surat DROP COLUMN $dropCol");
    }
    echo "Migrasi disposisi -> riwayat selesai\n";
}

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
