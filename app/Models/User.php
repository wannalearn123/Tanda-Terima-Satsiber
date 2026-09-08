<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public static function findByUsername(PDO $pdo, string $username): ?array
    {
        $st = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
        $st->execute([':u' => $username]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** @return array semua user untuk halaman admin */
    public static function all(PDO $pdo): array
    {
        return $pdo->query("SELECT id, username, nama, role, aktif, created_at FROM users ORDER BY id ASC")->fetchAll();
    }

    public static function create(PDO $pdo, string $username, string $password, string $nama, string $role): int
    {
        $now = date('Y-m-d H:i:s');
        $st = $pdo->prepare(
            "INSERT INTO users (username, password_hash, nama, role, aktif, created_at, updated_at)
             VALUES (:u, :h, :n, :r, 1, :t, :t)"
        );
        $st->execute([
            ':u' => $username,
            ':h' => password_hash($password, PASSWORD_DEFAULT),
            ':n' => $nama,
            ':r' => $role,
            ':t' => $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function setAktif(PDO $pdo, int $id, bool $aktif): void
    {
        $st = $pdo->prepare("UPDATE users SET aktif = :a, updated_at = :t WHERE id = :id");
        $st->execute([':a' => $aktif ? 1 : 0, ':t' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    public static function setPassword(PDO $pdo, int $id, string $password): void
    {
        $st = $pdo->prepare("UPDATE users SET password_hash = :h, updated_at = :t WHERE id = :id");
        $st->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':t' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    public static function delete(PDO $pdo, int $id): void
    {
        $st = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $st->execute([':id' => $id]);
    }
}
