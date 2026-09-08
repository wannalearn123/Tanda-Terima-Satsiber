<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class PresetPenerima
{
    public static function allActive(PDO $pdo): array
    {
        $st = $pdo->query("SELECT id, nama, kode FROM preset_penerima WHERE aktif = 1 ORDER BY nama ASC");
        return $st->fetchAll();
    }
    
    public static function find(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare("SELECT * FROM preset_penerima WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }
}
