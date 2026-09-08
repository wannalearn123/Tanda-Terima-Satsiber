-- Skema database aplikasi Tanda Terima Pengiriman.
-- Dijalankan via: php setup.php (sumber kebenaran ada di setup.php, file ini dokumentasi).

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 5000;

CREATE TABLE IF NOT EXISTS preset_penerima (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL,
    kode TEXT NOT NULL UNIQUE,
    aktif INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS pengiriman (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nomor_referensi TEXT NOT NULL,
    preset_penerima_id INTEGER NOT NULL REFERENCES preset_penerima(id) ON UPDATE CASCADE ON DELETE RESTRICT,
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
);

CREATE INDEX IF NOT EXISTS idx_pengiriman_tanggal ON pengiriman(tanggal);
CREATE INDEX IF NOT EXISTS idx_pengiriman_nomor ON pengiriman(nomor_referensi);
CREATE INDEX IF NOT EXISTS idx_pengiriman_nama ON pengiriman(nama_penerima);
CREATE INDEX IF NOT EXISTS idx_pengiriman_pusat ON pengiriman(preset_penerima_id);
