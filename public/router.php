<?php

declare(strict_types=1);

// Router untuk PHP built-in server: php -S 0.0.0.0:8090 public/router.php
// Hanya /assets/ allowlist yang dilayani langsung; selain itu ke front-controller.

$rawPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rawurldecode($rawPath);
if ($path !== '/' && str_starts_with($path, '/assets/') && !str_contains($path, "\0")) {
    $base = realpath(__DIR__);
    $file = realpath(__DIR__ . $path);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $allowed = ['css' => true, 'js' => true, 'png' => true, 'jpg' => true, 'jpeg' => true, 'ico' => true];
    if (
        $base !== false && $file !== false
        && str_starts_with($file, $base . DIRECTORY_SEPARATOR)
        && is_file($file) && isset($allowed[$ext])
    ) {
        $mime = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'ico' => 'image/x-icon',
        ][$ext];
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
    // Path /assets/ tidak valid → 404.
    http_response_code(404);
    exit;
}

require __DIR__ . '/index.php';
