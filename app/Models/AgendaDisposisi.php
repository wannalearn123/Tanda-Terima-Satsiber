<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Riwayat disposisi: satu surat agenda -> banyak entry kronologis (append-only). */
final class AgendaDisposisi
{
    /** @return array<int, array> urut kronologis (tertua dulu) */
    public static function forAgenda(PDO $pdo, int $agendaId): array
    {
        $st = $pdo->prepare(
            'SELECT * FROM agenda_disposisi WHERE agenda_id = :id ORDER BY id ASC'
        );
        $st->execute([':id' => $agendaId]);
        return $st->fetchAll();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM agenda_disposisi WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** Entry riwayat baru (append-only). */
    public static function create(PDO $pdo, int $agendaId, array $d): int
    {
        $st = $pdo->prepare(
            'INSERT INTO agenda_disposisi (agenda_id, aktor, kegiatan, created_at, updated_at)
             VALUES (:aid, :aktor, :kegiatan, :now, :now)'
        );
        $st->execute([
            ':aid' => $agendaId,
            ':aktor' => $d['disposisi_aktor'],
            ':kegiatan' => $d['disposisi_kegiatan'],
            ':now' => $d['now'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Ringkasan untuk banyak surat sekaligus (hindari N+1):
     * [agenda_id => ['latest' => row, 'count' => n]].
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
            $aid = (int) $r['agenda_id'];
            if (!isset($out[$aid])) {
                $out[$aid] = ['latest' => $r, 'count' => 0];
            }
            $out[$aid]['latest'] = $r;
            $out[$aid]['count']++;
        }
        return $out;
    }
}
