<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Models\Agenda;
use App\Models\AgendaDisposisi;
use App\Services\Validator;
use PDO;

final class AgendaController
{
    /**
     * Data satu halaman Agenda: form (tambah/edit) + tabel kanan.
     * Query: arah=masuk|keluar, sub=filter sub-jenis, q=cari, page, edit=id opsional.
     */
    public static function index(PDO $pdo, array $query, array $old = [], array $errors = []): array
    {
        $arah = Agenda::normalizeArah($query['arah'] ?? 'masuk');
        $subList = Agenda::subJenisFor($pdo, $arah);
        $subMasuk = Agenda::subJenisFor($pdo, Agenda::ARAH_MASUK);
        $subKeluar = Agenda::subJenisFor($pdo, Agenda::ARAH_KELUAR);

        $sub = trim((string) ($query['sub'] ?? ''));
        if ($sub !== '' && !in_array($sub, $subList, true)) {
            $sub = '';
        }
        $q = mb_substr(trim((string) ($query['q'] ?? '')), 0, 100);
        $rawPage = (string) ($query['page'] ?? '1');
        $page = ctype_digit($rawPage) ? (int) $rawPage : 1;

        $list = Agenda::paginate($pdo, $arah, $sub === '' ? null : $sub, $q === '' ? null : $q, $page, Config::perPage());

        $isEdit = false;
        $editId = null;
        $editNoAgenda = null;
        $riwayat = [];
        $dispErrors = [];
        $dispOld = [];
        $form = $old;
        if ($form === [] && isset($query['edit']) && ctype_digit((string) $query['edit'])) {
            $row = Agenda::find($pdo, (int) $query['edit']);
            if ($row !== null) {
                $isEdit = true;
                $editId = (int) $row['id'];
                $editNoAgenda = (int) $row['no_agenda'];
                $arah = Agenda::normalizeArah($row['arah']);
                $subList = Agenda::subJenisFor($pdo, $arah);
                $riwayat = AgendaDisposisi::forAgenda($pdo, $editId);
                $form = [
                    'arah' => $row['arah'],
                    'sub_jenis' => $row['sub_jenis'],
                    'no_surat' => $row['no_surat'],
                    'tanggal' => $row['tanggal'],
                    'kepada' => $row['kepada'],
                    'perihal' => $row['perihal'],
                ];
            }
        } elseif (isset($old['__edit_id'])) {
            $isEdit = true;
            $editId = (int) $old['__edit_id'];
            $found = Agenda::find($pdo, $editId);
            $editNoAgenda = $found !== null ? (int) $found['no_agenda'] : null;
            if ($found !== null) {
                $riwayat = AgendaDisposisi::forAgenda($pdo, $editId);
            }
            $dispOld = $old['__disp_old'] ?? [];
            $dispErrors = $old['__disp_errors'] ?? [];
            unset($form['__edit_id'], $form['__disp_old'], $form['__disp_errors']);
        }

        if ($form === []) {
            $form = ['arah' => $arah, 'tanggal' => date('Y-m-d')];
        } elseif (!isset($form['arah'])) {
            $form['arah'] = $arah;
        }

        $formArah = Agenda::normalizeArah($form['arah'] ?? $arah);
        $formSubList = $formArah === Agenda::ARAH_KELUAR ? $subKeluar : $subMasuk;

        $kodeMap = Agenda::kodeMap($pdo);
        $fmtNo = static function (array $r) use ($kodeMap): string {
            $a = Agenda::normalizeArah($r['arah'] ?? 'masuk');
            $s = (string) ($r['sub_jenis'] ?? '');
            $kode = $kodeMap[$a][$s] ?? ($a === Agenda::ARAH_KELUAR ? 'SK' : 'SM');
            return Agenda::formatNo($kode, (int) ($r['no_agenda'] ?? 0));
        };

        $editNoFmt = null;
        if ($isEdit && $editId !== null) {
            $found = Agenda::find($pdo, $editId);
            if ($found !== null) {
                $editNoFmt = $fmtNo($found);
            }
        }

        $summaries = AgendaDisposisi::summaryForMany($pdo, array_column($list['rows'], 'id'));

        return [
            'arah' => $arah,
            'subList' => $subList,
            'subMasuk' => $subMasuk,
            'subKeluar' => $subKeluar,
            'formSubList' => $formSubList,
            'sub' => $sub,
            'q' => $q,
            'rows' => $list['rows'],
            'total' => $list['total'],
            'page' => $list['page'],
            'pages' => $list['pages'],
            'counts' => Agenda::countByArah($pdo),
            'form' => $form,
            'errors' => $errors,
            'isEdit' => $isEdit,
            'editId' => $editId,
            'editNoAgenda' => $editNoAgenda,
            'editNoFmt' => $editNoFmt,
            'riwayat' => $riwayat,
            'dispErrors' => $dispErrors,
            'dispOld' => $dispOld,
            'summaries' => $summaries,
            'kodeMap' => $kodeMap,
            'fmtNo' => $fmtNo,
        ];
    }

    /** @return array{id: int, arah: string, no_agenda: int}|array{errors: array, old: array} */
    public static function store(PDO $pdo, array $input): array
    {
        if (!csrf_verify($input['_csrf'] ?? null)) {
            return ['errors' => ['_csrf' => 'Sesi kedaluwarsa. Muat ulang halaman.'], 'old' => $input];
        }
        [$errors, $clean] = Validator::agenda($input, $pdo);
        if ($errors !== []) {
            return ['errors' => $errors, 'old' => $input];
        }
        try {
            $res = Agenda::create($pdo, $clean);
            $res['no_fmt'] = Agenda::formatNo($res['kode'], $res['no_agenda']);
            return ['id' => $res['id'], 'arah' => $clean['arah'], 'no_agenda' => $res['no_agenda'], 'no_fmt' => $res['no_fmt']];
        } catch (\Throwable $e) {
            app_log('Agenda simpan gagal: ' . $e->getMessage());
            return ['errors' => ['form' => 'Data gagal disimpan. Silakan coba kembali.'], 'old' => $input];
        }
    }

    /** @return array{id: int, arah: string}|array{errors: array, old: array} */
    public static function update(PDO $pdo, int $id, array $input): array
    {
        if (!csrf_verify($input['_csrf'] ?? null)) {
            $input['__edit_id'] = $id;
            return ['errors' => ['_csrf' => 'Sesi kedaluwarsa. Muat ulang halaman.'], 'old' => $input];
        }
        if (Agenda::find($pdo, $id) === null) {
            throw new \RuntimeException('Data tidak ditemukan.');
        }
        [$errors, $clean] = Validator::agenda($input, $pdo, $id);
        if ($errors !== []) {
            $input['__edit_id'] = $id;
            return ['errors' => $errors, 'old' => $input];
        }
        try {
            Agenda::update($pdo, $id, $clean);
            return ['id' => $id, 'arah' => $clean['arah']];
        } catch (\Throwable $e) {
            app_log('Agenda update gagal id=' . $id . ': ' . $e->getMessage());
            $input['__edit_id'] = $id;
            return ['errors' => ['form' => 'Data gagal disimpan. Silakan coba kembali.'], 'old' => $input];
        }
    }

    /**
     * Tambah satu entry riwayat disposisi.
     * @return array{agenda_id: int, arah: string}|array{errors: array, old: array}
     */
    public static function tambahDisposisi(PDO $pdo, int $agendaId, array $input): array
    {
        if (!csrf_verify($input['_csrf'] ?? null)) {
            $input['__edit_id'] = $agendaId;
            $input['__disp_old'] = $input;
            return ['errors' => ['_csrf' => 'Sesi kedaluwarsa. Muat ulang halaman.'], 'old' => $input];
        }
        $surat = Agenda::find($pdo, $agendaId);
        if ($surat === null) {
            throw new \RuntimeException('Data tidak ditemukan.');
        }
        // Tambah-box hanya mengirim aktor: lengkapi field surat dari DB
        // agar form utama tetap terisi bila validasi disposisi gagal.
        foreach (['arah', 'sub_jenis', 'no_surat', 'tanggal', 'kepada', 'perihal'] as $k) {
            if (!isset($input[$k])) {
                $input[$k] = $surat[$k];
            }
        }
        [$errors, $clean] = Validator::agendaDisposisi($input);
        if ($errors !== []) {
            $input['__edit_id'] = $agendaId;
            $input['__disp_old'] = $input;
            $input['__disp_errors'] = $errors;
            return ['errors' => $errors, 'old' => $input];
        }
        try {
            AgendaDisposisi::create($pdo, $agendaId, $clean);
            return ['agenda_id' => $agendaId, 'arah' => Agenda::normalizeArah($surat['arah'])];
        } catch (\Throwable $e) {
            app_log('Agenda tambah disposisi gagal agenda=' . $agendaId . ': ' . $e->getMessage());
            $input['__edit_id'] = $agendaId;
            $input['__disp_old'] = $input;
            $input['__disp_errors'] = ['form' => 'Disposisi gagal ditambahkan. Silakan coba kembali.'];
            return ['errors' => ['form' => 'Disposisi gagal ditambahkan. Silakan coba kembali.'], 'old' => $input];
        }
    }

    /** Balik ceklis entry terakhir surat. Kembalikan agenda pemilik untuk redirect. */
    public static function toggleDisposisi(PDO $pdo, int $dispId, ?string $csrf): array
    {
        if (!csrf_verify($csrf)) {
            throw new \RuntimeException('Sesi kedaluwarsa.');
        }
        $row = AgendaDisposisi::toggle($pdo, $dispId);
        $surat = Agenda::find($pdo, (int) $row['agenda_id']);
        if ($surat === null) {
            throw new \RuntimeException('Data tidak ditemukan.');
        }
        return ['agenda_id' => (int) $row['agenda_id'], 'arah' => Agenda::normalizeArah($surat['arah'])];
    }
}
