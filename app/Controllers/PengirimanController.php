<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Pengiriman;
use App\Models\PresetPenerima;
use App\Services\PdfService;
use PDO;

final class PengirimanController
{
    public static function show(PDO $pdo, int $id): ?array
    {
        return Pengiriman::find($pdo, $id);
    }

    /** @return array{row: array, preset: array}|null */
    public static function edit(PDO $pdo, int $id): ?array
    {
        $row = Pengiriman::find($pdo, $id);
        if ($row === null) {
            return null;
        }
        return ['row' => $row, 'preset' => PresetPenerima::allActive($pdo)];
    }

    /** Stream PDF ke browser. Melempar RuntimeException bila gagal (data DB tetap aman). */
    public static function pdf(PDO $pdo, int $id): void
    {
        $bin = PdfService::generate($pdo, $id); // di luar transaksi DB
        $len = strlen($bin);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="tanda-terima-' . $id . '.pdf"');
        header('Content-Length: ' . $len);
        echo $bin;
        exit;
    }
}
