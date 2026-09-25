<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Agenda
{
    public const ARAH_MASUK = 'masuk';
    public const ARAH_KELUAR = 'keluar';

    /**
     * Seluruh master jenis surat dalam 1 query.
     * @return array<int, array<string,mixed>>
     */
    public static function masterJenis(PDO $pdo): array
    {
        $st = $pdo->query(
            'SELECT nama, tampil_masuk, tampil_keluar, kode_masuk, kode_keluar
             FROM agenda_sub_jenis ORDER BY urutan ASC, nama ASC'
        );
        return $st->fetchAll();
    }

    /** Nama jenis untuk satu arah dari hasil masterJenis(). @return string[] */
    public static function subNames(array $master, string $arah): array
    {
        $col = $arah === self::ARAH_KELUAR ? 'tampil_keluar' : 'tampil_masuk';
        $out = [];
        foreach ($master as $r) {
            if ((int) ($r[$col] ?? 0) === 1) {
                $out[] = (string) $r['nama'];
            }
        }
        return $out;
    }

    public static function isValidSubJenis(PDO $pdo, string $arah, string $nama): bool
    {
        $col = $arah === self::ARAH_KELUAR ? 'tampil_keluar' : 'tampil_masuk';
        $st = $pdo->prepare(
            "SELECT 1 FROM agenda_sub_jenis WHERE nama = :nama AND $col = 1 LIMIT 1"
        );
        $st->execute([':nama' => $nama]);
        return $st->fetch() !== false;
    }

    /** Kode nomor per grup (arah + sub-jenis), mis. masuk+Biasa=SMB. @return array{masuk: array<string,string>, keluar: array<string,string>} */
    public static function kodeMap(PDO $pdo): array
    {
        return self::kodeMapFrom(self::masterJenis($pdo));
    }

    /** Turunan kodeMap() dari hasil masterJenis() (tanpa query). @return array{masuk: array<string,string>, keluar: array<string,string>} */
    public static function kodeMapFrom(array $master): array
    {
        $map = ['masuk' => [], 'keluar' => []];
        foreach ($master as $r) {
            $nama = (string) ($r['nama'] ?? '');
            if ($nama === '') {
                continue;
            }
            if (($r['kode_masuk'] ?? null) !== null && (string) $r['kode_masuk'] !== '') {
                $map['masuk'][$nama] = (string) $r['kode_masuk'];
            }
            if (($r['kode_keluar'] ?? null) !== null && (string) $r['kode_keluar'] !== '') {
                $map['keluar'][$nama] = (string) $r['kode_keluar'];
            }
        }
        return $map;
    }

    public static function kodeFor(PDO $pdo, string $arah, string $subJenis): string
    {
        return self::kodeFrom(self::masterJenis($pdo), $arah, $subJenis);
    }

    /** Turunan kodeFor() dari hasil masterJenis() (tanpa query). */
    public static function kodeFrom(array $master, string $arah, string $subJenis): string
    {
        $col = $arah === self::ARAH_KELUAR ? 'kode_keluar' : 'kode_masuk';
        foreach ($master as $r) {
            if ((string) ($r['nama'] ?? '') === $subJenis) {
                $kode = (string) ($r[$col] ?? '');
                return $kode !== '' ? $kode : ($arah === self::ARAH_KELUAR ? 'SK' : 'SM');
            }
        }
        return $arah === self::ARAH_KELUAR ? 'SK' : 'SM';
    }

    /** Format tampil: "SMB-1". */
    public static function formatNo(string $kode, int $no): string
    {
        return $kode . '-' . $no;
    }

    public static function normalizeArah(mixed $v): string
    {
        $a = strtolower(trim((string) $v));
        return $a === self::ARAH_KELUAR ? self::ARAH_KELUAR : self::ARAH_MASUK;
    }

    /** Nomor berikutnya per grup (arah + sub-jenis): MAX+1 (gap bekas hapus dibiarkan). */
    public static function nextNoAgenda(PDO $pdo, string $arah, string $subJenis): int
    {
        $st = $pdo->prepare(
            'SELECT COALESCE(MAX(no_agenda), 0) AS m FROM agenda_surat WHERE arah = :arah AND sub_jenis = :sub'
        );
        $st->execute([':arah' => $arah, ':sub' => $subJenis]);
        return (int) ($st->fetch()['m'] ?? 0) + 1;
    }

    /**
     * Buat surat (nomor MAX+1 per grup, gap tidak dipakai ulang).
     * @return array{id: int, no_agenda: int, kode: string}
     */
    public static function create(PDO $pdo, array $d): array
    {
        $last = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $pdo->beginTransaction();
            try {
                $no = self::nextNoAgenda($pdo, $d['arah'], $d['sub_jenis']);
                $st = $pdo->prepare(
                    'INSERT INTO agenda_surat
                    (arah, sub_jenis, no_agenda, no_surat, tanggal, kepada, perihal,
                     created_at, updated_at)
                    VALUES
                    (:arah, :sub, :noagenda, :nosurat, :tanggal, :kepada, :perihal,
                     :now, :now)'
                );
                $st->execute([
                    ':arah' => $d['arah'],
                    ':sub' => $d['sub_jenis'],
                    ':noagenda' => $no,
                    ':nosurat' => $d['no_surat'],
                    ':tanggal' => $d['tanggal'],
                    ':kepada' => $d['kepada'],
                    ':perihal' => $d['perihal'],
                    ':now' => $d['now'],
                ]);
                $id = (int) $pdo->lastInsertId();
                $pdo->commit();
                return ['id' => $id, 'no_agenda' => $no, 'kode' => self::kodeFor($pdo, $d['arah'], $d['sub_jenis'])];
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $last = $e;
            }
        }
        throw $last;
    }

    /** Update field isi saja. Arah, sub-jenis, dan no_agenda dikunci. */
    public static function update(PDO $pdo, int $id, array $d): void
    {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'UPDATE agenda_surat SET
                    no_surat = :nosurat, tanggal = :tanggal, kepada = :kepada,
                    perihal = :perihal, updated_at = :now
                WHERE id = :id'
            );
            $st->execute([
                ':nosurat' => $d['no_surat'],
                ':tanggal' => $d['tanggal'],
                ':kepada' => $d['kepada'],
                ':perihal' => $d['perihal'],
                ':now' => $d['now'],
                ':id' => $id,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM agenda_surat WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** Hapus surat + disposisinya. */
    public static function destroy(PDO $pdo, int $id): void
    {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('DELETE FROM agenda_disposisi WHERE agenda_id = :id');
            $st->execute([':id' => $id]);
            $st = $pdo->prepare('DELETE FROM agenda_surat WHERE id = :id');
            $st->execute([':id' => $id]);
            if ($st->rowCount() === 0) {
                throw new \RuntimeException('Data tidak ditemukan.');
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{masuk: int, keluar: int} */
    public static function countByArah(PDO $pdo): array
    {
        $out = ['masuk' => 0, 'keluar' => 0];
        $st = $pdo->query('SELECT arah, COUNT(*) AS c FROM agenda_surat GROUP BY arah');
        foreach ($st->fetchAll() as $r) {
            $a = (string) ($r['arah'] ?? '');
            if (isset($out[$a])) {
                $out[$a] = (int) $r['c'];
            }
        }
        return $out;
    }

    /**
     * @param array{masuk: array<string,string>, keluar: array<string,string>}|null $kodeMap peta siap pakai (hindari query ulang)
     * @return array{rows: array, total: int, page: int, pages: int}
     */
    public static function paginate(PDO $pdo, string $arah, ?string $sub, ?string $q, int $page, int $perPage, ?array $kodeMap = null): array
    {
        $where = ['a.arah = :arah'];
        $params = [':arah' => $arah];
        if ($sub !== null && $sub !== '') {
            $where[] = 'a.sub_jenis = :sub';
            $params[':sub'] = $sub;
        }
        if ($q !== null && $q !== '') {
            // Dukung cari nomor berprefix: "SMB-1" -> sub_jenis terkait + no_agenda=1,
            // atau kode saja ("SMB") -> semua nomor grup tersebut.
            if (preg_match('/^([A-Za-z]+)\s*-?\s*(\d*)$/', trim($q), $m) && $m[1] !== '') {
                $kode = strtoupper($m[1]);
                $num = ltrim($m[2], '0');
                $num = $num === '' ? null : $num;
                $found = null;
                foreach (($kodeMap ?? self::kodeMap($pdo)) as $ka => $pairs) {
                    foreach ($pairs as $nama => $kk) {
                        if (strtoupper($kk) === $kode) {
                            $found = ['arah' => $ka, 'sub' => $nama];
                            break 2;
                        }
                    }
                }
                if ($found !== null && $found['arah'] === $arah
                    && ($sub === null || $sub === '' || $sub === $found['sub'])) {
                    $where[] = 'a.sub_jenis = :ksub';
                    $params[':ksub'] = $found['sub'];
                    if ($num !== null) {
                        $where[] = 'CAST(a.no_agenda AS TEXT) LIKE :kq';
                        $params[':kq'] = '%' . $num . '%';
                    }
                } elseif ($num !== null) {
                    $where[] = 'CAST(a.no_agenda AS TEXT) LIKE :q';
                    $params[':q'] = '%' . $num . '%';
                } else {
                    $where[] = '(a.no_surat LIKE :q OR a.kepada LIKE :q OR a.perihal LIKE :q OR CAST(a.no_agenda AS TEXT) LIKE :q)';
                    $params[':q'] = '%' . $q . '%';
                }
            } else {
                $where[] = '(a.no_surat LIKE :q OR a.kepada LIKE :q OR a.perihal LIKE :q OR CAST(a.no_agenda AS TEXT) LIKE :q)';
                $params[':q'] = '%' . $q . '%';
            }
        }
        $w = 'WHERE ' . implode(' AND ', $where);

        $cs = $pdo->prepare("SELECT COUNT(*) AS c FROM agenda_surat a $w");
        $cs->execute($params);
        $total = (int) ($cs->fetch()['c'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $st = $pdo->prepare(
            "SELECT a.* FROM agenda_surat a $w
             ORDER BY a.no_agenda DESC, a.id DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
