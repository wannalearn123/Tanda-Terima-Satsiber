<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Agenda
{
    public const ARAH_MASUK = 'masuk';
    public const ARAH_KELUAR = 'keluar';

    /**
     * Daftar jenis surat dari tabel master agenda_sub_jenis.
     * Satu tabel untuk dua arah; flag tampil_masuk / tampil_keluar = yes/no per baris.
     *
     * @return string[]
     */
    public static function subJenisFor(PDO $pdo, string $arah): array
    {
        $col = $arah === self::ARAH_KELUAR ? 'tampil_keluar' : 'tampil_masuk';
        $st = $pdo->prepare(
            "SELECT nama FROM agenda_sub_jenis WHERE $col = 1 ORDER BY urutan ASC, nama ASC"
        );
        $st->execute();
        return array_column($st->fetchAll(), 'nama');
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

    public static function normalizeArah(mixed $v): string
    {
        $a = strtolower(trim((string) $v));
        return $a === self::ARAH_KELUAR ? self::ARAH_KELUAR : self::ARAH_MASUK;
    }

    /** Nomor berikutnya per arah: MAX+1 (gap bekas hapus dibiarkan, tidak dipakai ulang). */
    public static function nextNoAgenda(PDO $pdo, string $arah): int
    {
        $st = $pdo->prepare('SELECT COALESCE(MAX(no_agenda), 0) AS m FROM agenda_surat WHERE arah = :arah');
        $st->execute([':arah' => $arah]);
        return (int) ($st->fetch()['m'] ?? 0) + 1;
    }

    /**
     * Buat surat + disposisi pertama dalam satu transaksi.
     * Nomor berikutnya per arah: MAX+1 (gap bekas hapus dibiarkan, tidak dipakai ulang).
     *
     * @param array $disp clean disposisi (disposisi_aktor, disposisi_kegiatan, now)
     * @return array{id: int, no_agenda: int}
     */
    public static function create(PDO $pdo, array $d, array $disp): array
    {
        $last = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $pdo->beginTransaction();
            try {
                $no = self::nextNoAgenda($pdo, $d['arah']);
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
                $st2 = $pdo->prepare(
                    'INSERT INTO agenda_disposisi (agenda_id, aktor, kegiatan, selesai, created_at, updated_at)
                     VALUES (:aid, :aktor, :kegiatan, :selesai, :now, :now)'
                );
                $st2->execute([
                    ':aid' => $id,
                    ':aktor' => $disp['disposisi_aktor'],
                    ':kegiatan' => $disp['disposisi_kegiatan'],
                    ':selesai' => $disp['disposisi_selesai'] ?? 0,
                    ':now' => $d['now'],
                ]);
                $pdo->commit();
                return ['id' => $id, 'no_agenda' => $no];
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $last = $e;
            }
        }
        throw $last;
    }

    /** Update semua field kecuali no_agenda (nomor dikunci setelah dibuat). */
    public static function update(PDO $pdo, int $id, array $d): bool
    {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'UPDATE agenda_surat SET
                    arah = :arah, sub_jenis = :sub,
                    no_surat = :nosurat, tanggal = :tanggal, kepada = :kepada,
                    perihal = :perihal, disposisi_aktor = :aktor,
                    disposisi_kegiatan = :kegiatan, disposisi_selesai = :selesai,
                    updated_at = :now
                WHERE id = :id'
            );
            $st->execute([
                ':arah' => $d['arah'],
                ':sub' => $d['sub_jenis'],
                ':nosurat' => $d['no_surat'],
                ':tanggal' => $d['tanggal'],
                ':kepada' => $d['kepada'],
                ':perihal' => $d['perihal'],
                ':aktor' => $d['disposisi_aktor'],
                ':kegiatan' => $d['disposisi_kegiatan'],
                ':selesai' => $d['disposisi_selesai'],
                ':now' => $d['now'],
                ':id' => $id,
            ]);
            $pdo->commit();
            return $st->rowCount() >= 0;
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

    /** Hapus surat + seluruh rantai disposisinya (eksplisit + CASCADE sebagai jaring pengaman). */
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

    public static function existsNoAgenda(PDO $pdo, string $arah, int $noAgenda, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM agenda_surat WHERE arah = :arah AND no_agenda = :no LIMIT 1';
        $params = [':arah' => $arah, ':no' => $noAgenda];
        if ($excludeId !== null) {
            $sql = 'SELECT 1 FROM agenda_surat WHERE arah = :arah AND no_agenda = :no AND id != :id LIMIT 1';
            $params[':id'] = $excludeId;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetch() !== false;
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
     * @return array{rows: array, total: int, page: int, pages: int}
     */
    public static function paginate(PDO $pdo, string $arah, ?string $sub, ?string $q, int $page, int $perPage): array
    {
        $where = ['a.arah = :arah'];
        $params = [':arah' => $arah];
        if ($sub !== null && $sub !== '') {
            $where[] = 'a.sub_jenis = :sub';
            $params[':sub'] = $sub;
        }
        if ($q !== null && $q !== '') {
            $where[] = '(a.no_surat LIKE :q OR a.kepada LIKE :q OR a.perihal LIKE :q OR CAST(a.no_agenda AS TEXT) LIKE :q)';
            $params[':q'] = '%' . $q . '%';
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
