<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use PDO;

final class AuthController
{
    /** User login saat ini (dimuat fresh dari DB tiap request) atau null. */
    public static function current(PDO $pdo): ?array
    {
        ensure_session();
        if (function_exists('session_idle_expired') && session_idle_expired()) {
            unset($_SESSION['uid'], $_SESSION['last_activity']);
            session_regenerate_id(true);
            return null;
        }
        $uid = $_SESSION['uid'] ?? null;
        if (!is_int($uid)) {
            return null;
        }
        $u = User::find($pdo, $uid);
        if ($u === null || (int) $u['aktif'] !== 1) {
            unset($_SESSION['uid']);
            return null;
        }
        if (function_exists('session_touch_idle')) {
            session_touch_idle();
        }
        return $u;
    }

    /** @return array{ok: bool, error?: string} */
    public static function login(PDO $pdo, string $username, string $password): array
    {
        ensure_session();
        $u = User::findByUsername($pdo, trim($username));
        if ($u === null || (int) $u['aktif'] !== 1 || !password_verify($password, $u['password_hash'])) {
            return ['ok' => false, 'error' => 'Username atau password salah.'];
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['last_activity'] = time();
        return ['ok' => true];
    }

    public static function logout(): void
    {
        ensure_session();
        unset($_SESSION['uid'], $_SESSION['last_activity']);
        session_regenerate_id(true);
    }

    public static function isAdmin(?array $user): bool
    {
        return $user !== null && ($user['role'] ?? '') === 'admin';
    }
}
