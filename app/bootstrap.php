<?php
declare(strict_types=1);

// Basic bootstrap (no composer)
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// Defaults
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');

// DB connection
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'callcenter_analytics';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

try {
    $db = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB connection error. Check env DB_HOST/DB_NAME/DB_USER/DB_PASS.\n";
    exit;
}

// Start session after DB ok
session_start();

