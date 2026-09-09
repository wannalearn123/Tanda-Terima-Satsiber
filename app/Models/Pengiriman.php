<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Pengiriman
{
    public static function create(PDO $pdo, array $d): int
    {
        // Transaksi pendek: hanya INSERT satu record.
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                "INSERT INTO pengiriman
                (nomor_referensi, nama_satuan_kerja, nama_penerima, pangkat_golongan, jabatan,
                 tanggal, pukul, telp_hp, tanda_tangan_path, created_at, updated_at)
                VALUES
                (:nomor, :satker, :nama, :pangkat, :jabatan, :tanggal, :pukul, :telp, :ttd, :now, :now)"
            );
            $st->execute([
                ':nomor' => $d['nomor_referensi'],
                ':satker' => $d['nama_satuan_kerja'],
                ':nama' => $d['nama_penerima'],
                ':pangkat' => $d['pangkat_golongan'],
                ':jabatan' => $d['jabatan'],
                ':tanggal' => $d['tanggal'],
                ':pukul' => $d['pukul'],
                ':telp' => $d['telp_hp'],
                ':ttd' => $d['tanda_tangan_path'],
                ':now' => $d['now'],
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function update(PDO $pdo, int $id, array $d): bool
    {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                "UPDATE pengiriman SET
                    nomor_referensi = :nomor, nama_satuan_kerja = :satker, nama_penerima = :nama,
                    pangkat_golongan = :pangkat, jabatan = :jabatan, tanggal = :tanggal,
                    pukul = :pukul, telp_hp = :telp, tanda_tangan_path = :ttd, updated_at = :now
                WHERE id = :id"
            );
            $st->execute([
                ':nomor' => $d['nomor_referensi'],
                ':satker' => $d['nama_satuan_kerja'],
                ':nama' => $d['nama_penerima'],
                ':pangkat' => $d['pangkat_golongan'],
                ':jabatan' => $d['jabatan'],
                ':tanggal' => $d['tanggal'],
                ':pukul' => $d['pukul'],
                ':telp' => $d['telp_hp'],
                ':ttd' => $d['tanda_tangan_path'],
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
        $st = $pdo->prepare(
            "SELECT p.*
             FROM pengiriman p
             WHERE p.id = :id LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** Hapus record. Kembalikan path tanda tangan lama (untuk bersih-bersih file) atau null bila tak ada. */
    public static function destroy(PDO $pdo, int $id): ?string
    {
        $row = self::find($pdo, $id);
        if ($row === null) {
            throw new \RuntimeException('Data tidak ditemukan.');
        }
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("DELETE FROM pengiriman WHERE id = :id");
            $st->execute([':id' => $id]);
            $pdo->commit();
            /** @var string|null */
            return $row['tanda_tangan_path'] ?? null;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Filter dashboard: rentang tanggal + search sederhana. Selalu pakai LIMIT/OFFSET. */
    public static function paginate(PDO $pdo, ?string $from, ?string $to, ?string $q, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        if ($from !== null && $from !== '') {
            $where[] = 'p.tanggal >= :from';
            $params[':from'] = $from;
        }
        if ($to !== null && $to !== '') {
            $where[] = 'p.tanggal <= :to';
            $params[':to'] = $to;
        }
        if ($q !== null && $q !== '') {
            $where[] = '(p.nomor_referensi LIKE :q OR p.nama_penerima LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $w = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $cs = $pdo->prepare("SELECT COUNT(*) AS c FROM pengiriman p $w");
        $cs->execute($params);
        $total = (int) ($cs->fetch()['c'] ?? 0);

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $st = $pdo->prepare(
            "SELECT p.*
             FROM pengiriman p
             $w ORDER BY p.tanggal DESC, p.id DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();

        return [
            'rows' => $st->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }
}
