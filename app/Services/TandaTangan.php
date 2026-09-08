<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;

// Penyimpanan file tanda tangan (PNG). Tidak pernah sebagai BLOB database.
// Path yang disimpan di DB relatif terhadap storage/, contoh: tandatangan/ttd_....png

final class TandaTangan
{
    public static function dir(): string
    {
        $d = Config::root() . '/storage/tandatangan';
        if (!is_dir($d)) {
            mkdir($d, 0755, true);
        }
        return $d;
    }

    /** Simpan binary PNG, kembalikan path relatif. Null bila tidak ada gambar. */
    public static function save(?string $bin): ?string
    {
        if ($bin === null) {
            return null;
        }
        $name = 'ttd_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.png';
        $full = self::dir() . '/' . $name;
        if (file_put_contents($full, $bin) === false) {
            throw new \RuntimeException('Gagal menyimpan file tanda tangan.');
        }
        return 'tandatangan/' . $name;
    }

    /** Hapus file lama secara aman (guard direktori + ekstensi). */
    public static function delete(?string $rel): void
    {
        if ($rel === null || $rel === '') {
            return;
        }
        if (!preg_match('#^tandatangan/ttd_[A-Za-z0-9_\-]+\.png$#', $rel)) {
            return;
        }
        $full = Config::root() . '/storage/' . $rel;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    /** Untuk embed <img> di PDF (Dompdf remote dimatikan, jadi pakai data-URI). */
    public static function dataUri(?string $rel): ?string
    {
        if ($rel === null || $rel === '') {
            return null;
        }
        if (!preg_match('#^tandatangan/ttd_[A-Za-z0-9_\-]+\.png$#', $rel)) {
            return null;
        }
        $full = Config::root() . '/storage/' . $rel;
        if (!is_file($full)) {
            return null;
        }
        $bin = file_get_contents($full);
        if ($bin === false) {
            return null;
        }
        return 'data:image/png;base64,' . base64_encode($bin);
    }
}
