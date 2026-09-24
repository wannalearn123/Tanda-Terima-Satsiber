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

    private static function collapseSpaces(string $v): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($v));
    }

    private static function toTitle(string $v): string
    {
        $v = self::collapseSpaces($v);
        return mb_convert_case(mb_strtolower($v, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Validasi + standardisasi Agenda sebelum masuk DB.
     * No. Agenda TIDAK diinput user: otomatis MAX+1 per grup (arah + sub_jenis)
     * saat create, dikunci saat update. Arah dan sub_jenis juga dikunci saat
     * update (surat yang sudah masuk tidak bisa ganti jenis).
     * Aturan case: arah lower, no_surat UPPER, kepada/aktor Title Case,
     * perihal trim+collapse (huruf pertama kapital).
     *
     * @return array{0: array<string,string>, 1: array<string,mixed>}
     */
    public static function agenda(array $input, PDO $pdo, ?int $excludeId = null): array
    {
        $errors = [];

        $arah = strtolower(trim((string) ($input['arah'] ?? 'masuk')));
        if (!in_array($arah, ['masuk', 'keluar'], true)) {
            $arah = 'masuk';
        }
        $sub = self::collapseSpaces((string) ($input['sub_jenis'] ?? ''));
        if ($sub === '') {
            $errors['sub_jenis'] = 'Sub-jenis wajib dipilih.';
        } else {
            if ($excludeId !== null) {
                $existing = \App\Models\Agenda::find($pdo, $excludeId);
                if ($existing !== null
                    && ((string) ($existing['arah'] ?? '') !== $arah
                        || (string) ($existing['sub_jenis'] ?? '') !== $sub)) {
                    $errors['sub_jenis'] = 'Arah dan jenis surat tidak dapat diubah setelah disimpan.';
                    $arah = (string) ($existing['arah'] ?? $arah);
                    $sub = (string) ($existing['sub_jenis'] ?? $sub);
                }
            } elseif (!\App\Models\Agenda::isValidSubJenis($pdo, $arah, $sub)) {
                $errors['sub_jenis'] = 'Sub-jenis tidak valid untuk surat ' . $arah . '.';
            }
        }

        $noSurat = mb_strtoupper(self::collapseSpaces((string) ($input['no_surat'] ?? '')), 'UTF-8');
        $noSurat = mb_substr($noSurat, 0, 100);
        if ($noSurat === '') {
            $errors['no_surat'] = 'No. Surat wajib diisi.';
        }

        $tanggal = trim((string) ($input['tanggal'] ?? ''));
        $dt = \DateTime::createFromFormat('Y-m-d', $tanggal);
        if ($dt === false || $dt->format('Y-m-d') !== $tanggal) {
            $errors['tanggal'] = 'Format tanggal tidak valid (YYYY-MM-DD).';
        }

        $kepada = self::toTitle((string) ($input['kepada'] ?? ''));
        $kepada = mb_substr($kepada, 0, 150);
        if ($kepada === '') {
            $errors['kepada'] = 'Kepada (instansi / perorangan) wajib diisi.';
        }

        $perihal = self::collapseSpaces((string) ($input['perihal'] ?? ''));
        $perihal = mb_substr($perihal, 0, 500);
        if ($perihal === '') {
            $errors['perihal'] = 'Perihal / ringkasan surat wajib diisi.';
        } else {
            $perihal = mb_strtoupper(mb_substr($perihal, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($perihal, 1, null, 'UTF-8');
        }

        $clean = [
            'arah' => $arah,
            'sub_jenis' => $sub,
            'no_surat' => $noSurat,
            'tanggal' => $tanggal,
            'kepada' => $kepada,
            'perihal' => $perihal,
            'now' => date('Y-m-d H:i:s'),
        ];
        return [$errors, $clean];
    }

    /**
     * Validasi satu entry disposisi: hanya aktor + ceklis opsional.
     *
     * @return array{0: array<string,string>, 1: array<string,mixed>}
     */
    public static function agendaDisposisi(array $input): array
    {
        $errors = [];

        $aktor = self::toTitle((string) ($input['disposisi_aktor'] ?? ''));
        $aktor = mb_substr($aktor, 0, 100);
        if ($aktor === '') {
            $errors['disposisi_aktor'] = 'Aktor disposisi wajib diisi (contoh: Perwira 1).';
        }

        $selesai = (isset($input['disposisi_selesai']) && (string) $input['disposisi_selesai'] === '1') ? 1 : 0;

        return [$errors, [
            'disposisi_aktor' => $aktor,
            'disposisi_selesai' => $selesai,
            'now' => date('Y-m-d H:i:s'),
        ]];
    }
}
