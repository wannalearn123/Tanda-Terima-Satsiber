<?php

declare(strict_types=1);

// Helper global: escape, session, CSRF, flash, redirect, logging.

function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function csrf_token(): string
{
    ensure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    ensure_session();
    $sess = $_SESSION['csrf'] ?? '';
    if ($sess === '' || $token === null) {
        return false;
    }
    return hash_equals($sess, $token);
}

function flash(string $key, ?string $message = null): ?string
{
    ensure_session();
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function app_log(string $message): void
{
    $dir = dirname(__DIR__, 2) . '/storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $dir . '/app.log');
}

function now_ts(): string
{
    return date('Y-m-d H:i:s');
}
