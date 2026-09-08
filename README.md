# Tanda Terima Pengiriman — TNI Satuan Siber

PHP native + SQLite (PDO, WAL) + vanilla HTML/CSS/JS + Dompdf. Single server, single IP, single port. Tanpa login (single user).

## Syarat

- PHP 8.1+ (disarankan 8.3) dengan ekstensi `pdo_sqlite`, `mbstring`, `gd`, `dom`
- Composer (hanya untuk jalur non-Docker; lihat PDF di bawah)
- Docker + Compose (opsional, jalur termudah)

## Instalasi fresh

```bash
cp .env.example .env
composer install
php setup.php
php -S 0.0.0.0:8090 public/router.php
```

## Login

Semua halaman kecuali `/login` wajib login. Jalankan `php setup.php`
(aman diulang, idempotent) untuk membuat tabel `users` + akun awal:

- username: `admin`
- password: `admin123`
- Segera ganti password via menu **Pengguna** (admin saja).

Role:

- `admin`: form, rekap, lihat, edit, PDF, **hapus data**, kelola pengguna
  (tambah, aktif/nonaktif, reset password, hapus — kecuali akun sendiri).
- `operator`: form, rekap, lihat, edit, PDF. Tidak ada tombol hapus,
  akses langsung ke URL hapus/kelola-user ditolak (403).

Hapus data (`Hapus` di dashboard/detail, admin saja) menghapus baris database
dan file tanda tangannya sekaligus. Tanpa bulk delete.

## PDF

PDF dibuat dengan Dompdf dari data database (stream ke browser, tidak disimpan
sebagai BLOB). Dua jalur dependensi:

1. **Docker** (disarankan): `vendor/` sudah termasuk di folder project +
   image hasil `Dockerfile` sudah ada `gd` → PDF langsung jalan.
2. **Native**: jalankan `composer install` (butuh network ke Packagist),
   pastikan ekstensi `gd` aktif — tanpa `gd`, PDF tanpa gambar masih jalan,
   tapi PDF berisi tanda tangan akan gagal graceful (data tetap aman).

Buka `http://localhost:8090` (form) dan `http://localhost:8090/dashboard` (rekapitulasi).
Dari LAN: `http://SERVER_IP:8090`.

> `php -S 0.0.0.0:8090 -t public` hanya melayani `/` + file statis. Untuk pretty URL (`/dashboard`, `/pengiriman/...`) gunakan `public/router.php` seperti di atas, atau Apache/Nginx.

## Docker Compose (opsional, 1 service)

```bash
docker compose up -d --build
```

Build lokal satu image (`Dockerfile`: PHP 8.3 CLI + gd) lalu jalan di
`http://localhost:8090` (bind `0.0.0.0`, bisa via IP LAN).
Database (`database/database.sqlite`) dan `storage/` persisten via bind mount.
Session login tersimpan di `/tmp` container — recreate/logout ulang wajar.

## Apache (document root ke `public/`)

`.htaccess` sudah tersedia. Pastikan `AllowOverride All`.

## Nginx + PHP-FPM (contoh)

```nginx
server {
    listen 8090;
    root /path/app/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ { include fastcgi_params; fastcgi_pass 127.0.0.1:9000; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
    location ~ /\. { deny all; }
    location ~ \.sqlite { deny all; }
}
```

## Alur

Form (`/`) → validasi server-side → INSERT SQLite (transaksi pendek, lalu commit) → redirect ke `/pengiriman/{id}/pdf` (generate di luar transaksi). Bila PDF gagal, data tetap aman dan bisa dibuat ulang dari dashboard (Lihat / Edit / PDF).

Dashboard (`/dashboard`): filter Dari/Sampai + cari No.Ref/Nama, pagination 20/halaman (`PER_PAGE` di `.env`).

## Backup SQLite

```bash
sqlite3 database/database.sqlite ".backup 'backup-$(date +%F).sqlite'"
```

Jangan copy file mentah saat ada write aktif; gunakan `.backup` di atas.
Ikutkan folder `storage/tandatangan/` dalam backup — tanpa file-nya,
tanda tangan pada data lama tampil `-`.

## Struktur

```
app/{Config,Database,Controllers,Models,Services,Views,Helpers}/
public/{index.php,router.php,assets/}
database/ storage/{pdf,logs}/
setup.php composer.json .env.example
```
