<?php

declare(strict_types=1);

// Helper global: escape, session, CSRF, flash, redirect, logging.

function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Hardening cookie sesi: HttpOnly selalu, Secure bila HTTPS, SameSite=Lax
    // untuk mitigasi XSS-cookie-theft dan CSRF. Harus sebelum session_start().
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    }
    session_start();
}

const SESSION_MAX_IDLE = 1800; // 30 menit

function session_touch_idle(): void
{
    ensure_session();
    $_SESSION['last_activity'] = time();
}

/** True bila sesi login melewati batas idle dan harus diakhiri. */
function session_idle_expired(int $maxIdle = SESSION_MAX_IDLE): bool
{
    ensure_session();
    if (!isset($_SESSION['uid'])) {
        return false;
    }
    $last = $_SESSION['last_activity'] ?? null;
    if (!is_int($last)) {
        $_SESSION['last_activity'] = time();
        return false;
    }
    return (time() - $last) > $maxIdle;
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
