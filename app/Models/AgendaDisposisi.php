<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Disposisi: satu surat -> banyak entry kronologis (append-only). */
final class AgendaDisposisi
{
    public static function find(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM agenda_disposisi WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** Entry baru (append-only). */
    public static function create(PDO $pdo, int $agendaId, array $d): int
    {
        $st = $pdo->prepare(
            'INSERT INTO agenda_disposisi (agenda_id, aktor, selesai, created_at, updated_at)
             VALUES (:aid, :aktor, :selesai, :now, :now)'
        );
        $st->execute([
            ':aid' => $agendaId,
            ':aktor' => $d['disposisi_aktor'],
            ':selesai' => $d['disposisi_selesai'] ?? 0,
            ':now' => $d['now'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** Balik ceklis 0/1 satu entry. Kembalikan baris sesudah toggle. */
    public static function toggle(PDO $pdo, int $id): array
    {
        $pdo->beginTransaction();
        try {
            $row = self::find($pdo, $id);
            if ($row === null) {
                throw new \RuntimeException('Disposisi tidak ditemukan.');
            }
            $st = $pdo->prepare(
                'UPDATE agenda_disposisi SET selesai = 1 - selesai, updated_at = :now WHERE id = :id'
            );
            $st->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id]);
            $pdo->commit();
            $updated = self::find($pdo, $id);
            \assert($updated !== null);
            return $updated;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ringkasan untuk banyak surat sekaligus (hindari N+1):
     * [agenda_id => [entry kronologis]].
     */
    public static function summaryForMany(PDO $pdo, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare(
            "SELECT * FROM agenda_disposisi WHERE agenda_id IN ($placeholders) ORDER BY agenda_id ASC, id ASC"
        );
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['agenda_id']][] = $r;
        }
        return $out;
    }
}
