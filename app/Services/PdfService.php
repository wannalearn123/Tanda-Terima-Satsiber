<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pengiriman;
use PDO;

final class PdfService
{
    public static function buildHtml(array $row): string
    {
        $h = fn(mixed $v): string => htmlspecialchars((string) ($v ?? '-'), ENT_QUOTES, 'UTF-8');
        $preset = $row['preset_nama'] ?? '-';
        $tanggal = $h($row['tanggal'] ?? '-') . ' — Pukul ' . $h($row['pukul'] ?? '-');
        $ttdPath = (string) ($row['tanda_tangan_path'] ?? '');
        $ttdImg = TandaTangan::dataUri($row['tanda_tangan_path'] ?? null);
        if ($ttdPath !== '' && $ttdImg === null) {
            app_log('PDF tanpa gambar: file tanda tangan hilang: ' . $ttdPath);
        }
        $ttdBox = $ttdImg !== null
            ? '<br><img src="' . $ttdImg . '" width="180" style="width:180px;max-width:180px;max-height:90px;background:#fff;"><br>'
            : '<br><br><br><br>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#111;}'
            . '.kop{text-align:center;border-bottom:3px double #111;padding-bottom:10px;margin-bottom:16px;}'
            . '.kop h1{font-size:16px;margin:0;letter-spacing:1px;}'
            . '.kop h2{font-size:14px;margin:2px 0;}'
            . '.kop p{font-size:12px;margin:2px 0;font-weight:bold;}'
            . 'table{width:100%;border-collapse:collapse;margin-top:8px;}'
            . 'td,th{border:1px solid #333;padding:6px 8px;vertical-align:top;}'
            . 'th{background:#eee;width:32%;text-align:left;}'
            . '.ttd{margin-top:28px;width:100%;} .ttd td{border:none;text-align:center;}'
            . '</style></head><body>'
            . '<div class="kop"><h1>TENTARA NASIONAL INDONESIA</h1><h2>SATUAN SIBER</h2><p>TANDA TERIMA PENGIRIMAN</p></div>'
            . '<table>'
            . '<tr><th>Nomor Referensi</th><td>' . $h($row['nomor_referensi']) . '</td></tr>'
            . '<tr><th>Preset Penerima</th><td>' . $h($preset) . '</td></tr>'
            . '<tr><th>Nama Penerima</th><td>' . $h($row['nama_penerima']) . '</td></tr>'
            . '<tr><th>Pangkat / Golongan</th><td>' . $h($row['pangkat_golongan']) . '</td></tr>'
            . '<tr><th>Jabatan</th><td>' . $h($row['jabatan']) . '</td></tr>'
            . '<tr><th>Tanggal / Pukul</th><td>' . $tanggal . '</td></tr>'
            . '<tr><th>Telp / HP</th><td>' . $h($row['telp_hp']) . '</td></tr>'
            . '</table>'
            . '<table class="ttd"><tr><td>Pengirim<br><br><br><br>( .................... )</td>'
            . '<td>Penerima' . $ttdBox . '<br>( ' . $h($row['nama_penerima']) . ' )</td></tr></table>'
            . '</body></html>';
    }

    /**
     * Generate PDF binary untuk satu record. Dipanggil SETELAH commit DB,
     * tidak pernah di dalam transaksi database.
     */
    public static function generate(PDO $pdo, int $id): string
    {
        $row = Pengiriman::find($pdo, $id);
        if ($row === null) {
            throw new \RuntimeException('Data tidak ditemukan.');
        }
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('Dependensi PDF belum tersedia (folder vendor/ belum terinstal).');
        }
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml(self::buildHtml($row), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
