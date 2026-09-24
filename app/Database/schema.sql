-- Skema database aplikasi Tanda Terima Pengiriman.
-- Dijalankan via: php setup.php (sumber kebenaran ada di setup.php, file ini dokumentasi).

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 5000;

CREATE TABLE IF NOT EXISTS pengiriman (
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
);

CREATE INDEX IF NOT EXISTS idx_pengiriman_tanggal ON pengiriman(tanggal);
CREATE INDEX IF NOT EXISTS idx_pengiriman_nomor ON pengiriman(nomor_referensi);
CREATE INDEX IF NOT EXISTS idx_pengiriman_nama ON pengiriman(nama_penerima);

-- Agenda surat: nomor unik per grup (arah + sub_jenis), tampil "KODE-nomor" (mis. SMB-1).
CREATE TABLE IF NOT EXISTS agenda_surat (
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
);

CREATE INDEX IF NOT EXISTS idx_agenda_arah_tanggal ON agenda_surat(arah, tanggal);
CREATE INDEX IF NOT EXISTS idx_agenda_no_surat ON agenda_surat(no_surat);
CREATE INDEX IF NOT EXISTS idx_agenda_kepada ON agenda_surat(kepada);
CREATE INDEX IF NOT EXISTS idx_agenda_grup ON agenda_surat(arah, sub_jenis, no_agenda);

-- Master jenis surat: flag tampil + kode nomor per arah.
CREATE TABLE IF NOT EXISTS agenda_sub_jenis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    tampil_masuk INTEGER NOT NULL DEFAULT 1 CHECK(tampil_masuk IN (0,1)),
    tampil_keluar INTEGER NOT NULL DEFAULT 1 CHECK(tampil_keluar IN (0,1)),
    kode_masuk TEXT NULL,
    kode_keluar TEXT NULL,
    urutan INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

-- Riwayat disposisi: satu surat -> banyak entry kronologis.
CREATE TABLE IF NOT EXISTS agenda_disposisi (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agenda_id INTEGER NOT NULL REFERENCES agenda_surat(id) ON DELETE CASCADE,
    aktor TEXT NOT NULL,
    selesai INTEGER NOT NULL DEFAULT 0 CHECK(selesai IN (0,1)),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_disposisi_agenda ON agenda_disposisi(agenda_id, id);
