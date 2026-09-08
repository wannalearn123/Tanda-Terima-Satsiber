<?php

declare(strict_types=1);

// Router untuk PHP built-in server. Jalankan dari root proyek:
//   php -S 0.0.0.0:8080 public/router.php
// File statis (css/js) dilayani langsung, sisanya ke front-controller.

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file) && str_contains($path, '..') === false) {
    // Layani file statis langsung agar independen dari docroot (-t public atau tidak).
    $mime = [
        '.css' => 'text/css; charset=utf-8',
        '.js' => 'application/javascript; charset=utf-8',
        '.png' => 'image/png',
        '.jpg' => 'image/jpeg',
        '.ico' => 'image/x-icon',
    ][strtolower(substr($file, strrpos($file, '.')))] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

require __DIR__ . '/index.php';
