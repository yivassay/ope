<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Settings
{
    public function __construct(private PDO $db)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->db->prepare('SELECT value FROM settings WHERE `key` = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch();
        if (!$row) {
            return $default;
        }
        return $row['value'];
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->db->prepare('INSERT INTO settings (`key`, value) VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $stmt->execute(['k' => $key, 'v' => $value]);
    }

    public function allByPrefix(string $prefix): array
    {
        $stmt = $this->db->prepare('SELECT `key`, value FROM settings WHERE `key` LIKE :p ORDER BY `key`');
        $stmt->execute(['p' => $prefix . '%']);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    }
}

