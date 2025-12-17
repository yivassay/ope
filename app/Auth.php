<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    public function __construct(private PDO $db)
    {
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public function requireRole(string $role): void
    {
        if (($this->role() ?? '') !== $role) {
            http_response_code(403);
            echo "403";
            exit;
        }
    }

    public function attempt(string $username, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :u AND is_active = 1 LIMIT 1');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        if (!password_verify($password, $row['password_hash'])) {
            return false;
        }
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['username'] = $row['username'];
        return true;
    }

    public function logout(): void
    {
        session_destroy();
        $_SESSION = [];
    }
}

