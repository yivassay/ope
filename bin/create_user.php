<?php
declare(strict_types=1);

// CLI: create a user.
// Usage: php bin/create_user.php <username> <password> <role>
// role: admin | callcenter_manager

require __DIR__ . '/../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';
$role = $argv[3] ?? 'callcenter_manager';

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php bin/create_user.php <username> <password> <role>\n");
    exit(2);
}
if (!in_array($role, ['admin', 'callcenter_manager'], true)) {
    fwrite(STDERR, "Role must be admin or callcenter_manager\n");
    exit(3);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare('INSERT INTO users (username, password_hash, role, is_active) VALUES (:u, :h, :r, 1)');
$stmt->execute(['u' => $username, 'h' => $hash, 'r' => $role]);

echo "OK user created: {$username} role={$role}\n";

