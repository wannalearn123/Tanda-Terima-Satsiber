<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class Validator
{
    /**
     * @return array{0: array<string,string>, 1: array<string,mixed>}
     * @return [errors, clean]
     */
    public static function pengiriman(array $input, PDO $pdo, ?string $existingTtd = null): array
    {
        $errors = [];
        $s = fn(string $k): string => trim((string) ($input[$k] ?? ''));

        $nomor = mb_substr($s('nomor_referensi'), 0, 100);
        $satker = mb_substr($s('nama_satuan_kerja'), 0, 100);
        $nama = mb_substr($s('nama_penerima'), 0, 100);
        $pangkat = mb_substr($s('pangkat_golongan'), 0, 100);
        $jabatan = mb_substr($s('jabatan'), 0, 100);
        $tanggal = $s('tanggal');
        $pukul = $s('pukul');
        $telp = mb_substr($s('telp_hp'), 0, 30);
        $ttd = (string) ($input['tanda_tangan'] ?? '');
        $ttdBin = null;

        if ($satker === '') {
            $errors['nama_satuan_kerja'] = 'Nama satuan kerja wajib diisi.';
        }

        if ($nomor === '') {
            $errors['nomor_referensi'] = 'Nomor referensi wajib diisi.';
        }
        if ($nama === '') {
            $errors['nama_penerima'] = 'Nama penerima wajib diisi.';
        }
        if ($pangkat === '') {
            $errors['pangkat_golongan'] = 'Pangkat / golongan wajib diisi.';
        }
        if ($jabatan === '') {
            $errors['jabatan'] = 'Jabatan wajib diisi.';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $tanggal);
        if ($dt === false || $dt->format('Y-m-d') !== $tanggal) {
            $errors['tanggal'] = 'Format tanggal tidak valid (YYYY-MM-DD).';
        }
        $tm = \DateTime::createFromFormat('H:i', $pukul);
        if ($tm === false || $tm->format('H:i') !== $pukul) {
            $errors['pukul'] = 'Format pukul tidak valid (HH:MM).';
        }
        if ($telp === '') {
            $errors['telp_hp'] = 'Telp / HP wajib diisi.';
        } elseif (!preg_match('/^[0-9+\-\s()]{5,30}$/', $telp)) {
            $errors['telp_hp'] = 'Nomor telepon tidak valid.';
        }
        if ($ttd === '') {
            // Create wajib gambar; update boleh kosong bila file lama masih ada.
            if ($existingTtd === null || $existingTtd === '') {
                $errors['tanda_tangan'] = 'Tanda tangan wajib diisi.';
            }
        } else {
            $prefix = 'data:image/png;base64,';
            if (!str_starts_with($ttd, $prefix)) {
                $errors['tanda_tangan'] = 'Format tanda tangan tidak valid.';
            } elseif (strlen($ttd) > 700000) {
                $errors['tanda_tangan'] = 'Gambar tanda tangan terlalu besar (maks ~500KB).';
            } else {
                $bin = base64_decode(substr($ttd, strlen($prefix)), true);
                $info = ($bin !== false) ? @getimagesizefromstring($bin) : false;
                if ($bin === false || $info === false || (int) ($info[2] ?? 0) !== IMAGETYPE_PNG) {
                    $errors['tanda_tangan'] = 'Gambar tanda tangan tidak valid (harus PNG).';
                } elseif ($info[0] > 2000 || $info[1] > 2000) {
                    $errors['tanda_tangan'] = 'Dimensi tanda tangan terlalu besar.';
                } else {
                    $ttdBin = $bin;
                }
            }
        }

        $clean = [
            'nomor_referensi' => $nomor,
            'nama_satuan_kerja' => $satker,
            'nama_penerima' => $nama,
            'pangkat_golongan' => $pangkat,
            'jabatan' => $jabatan,
            'tanggal' => $tanggal,
            'pukul' => $pukul,
            'telp_hp' => $telp,
            'tanda_tangan_bin' => $ttdBin, // string binary PNG atau null
            'now' => date('Y-m-d H:i:s'),
        ];
        return [$errors, $clean];
    }

    public static function dateOpt(?string $v): ?string
    {
        if ($v === null || trim($v) === '') {
            return null;
        }
        $v = trim($v);
        $dt = \DateTime::createFromFormat('Y-m-d', $v);
        return ($dt !== false && $dt->format('Y-m-d') === $v) ? $v : null;
    }
}
