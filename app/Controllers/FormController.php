<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Models\Pengiriman;
use App\Services\TandaTangan;
use App\Services\Validator;
use PDO;

final class FormController
{
    /** @return array{old: array, errors: array} */
    public static function show(PDO $pdo, array $old = [], array $errors = []): array
    {
        return ['old' => $old, 'errors' => $errors];
    }

    /** @return array{id: int}|array{errors: array, old: array} */
    public static function store(PDO $pdo, array $input): array
    {
        if (!csrf_verify($input['_csrf'] ?? null)) {
            return ['errors' => ['_csrf' => 'Sesi kedaluwarsa. Muat ulang form.'], 'old' => $input];
        }
        [$errors, $clean] = Validator::pengiriman($input, $pdo);
        if ($errors !== []) {
            return ['errors' => $errors, 'old' => $input];
        }
        $newPath = null;
        try {
            // File ditulis dulu; bila DB gagal, file dibersihkan. Tidak ada orphan.
            if ($clean['tanda_tangan_bin'] !== null) {
                $newPath = TandaTangan::save($clean['tanda_tangan_bin']);
            }
            $clean['tanda_tangan_path'] = $newPath;
            $id = Pengiriman::create($pdo, $clean);
            return ['id' => $id];
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                TandaTangan::delete($newPath);
            }
            app_log('Simpan gagal: ' . $e->getMessage());
            return ['errors' => ['tanda_tangan' => 'Data gagal disimpan. Silakan coba kembali.'], 'old' => $input];
        }
    }

    public static function update(PDO $pdo, int $id, array $input): array
    {
        if (!csrf_verify($input['_csrf'] ?? null)) {
            return ['errors' => ['_csrf' => 'Sesi kedaluwarsa. Muat ulang form.'], 'old' => $input];
        }
        $old = Pengiriman::find($pdo, $id);
        $oldPath = $old['tanda_tangan_path'] ?? null;
        [$errors, $clean] = Validator::pengiriman($input, $pdo, $oldPath);
        if ($errors !== []) {
            return ['errors' => $errors, 'old' => $input];
        }
        // Gambar ulang → ganti; kosong → pertahankan file lama.
        $newPath = $oldPath;
        $replaced = false;
        try {
            if ($clean['tanda_tangan_bin'] !== null) {
                $newPath = TandaTangan::save($clean['tanda_tangan_bin']);
                $replaced = true;
            }
            $clean['tanda_tangan_path'] = $newPath;
            Pengiriman::update($pdo, $id, $clean);
            if ($replaced) {
                TandaTangan::delete($oldPath); // hapus lama hanya bila DB sukses
            }
            return ['id' => $id];
        } catch (\Throwable $e) {
            if ($replaced) {
                TandaTangan::delete($newPath);
            }
            app_log('Update gagal id=' . $id . ': ' . $e->getMessage());
            return ['errors' => ['tanda_tangan' => 'Data gagal disimpan. Silakan coba kembali.'], 'old' => $input];
        }
    }
}
