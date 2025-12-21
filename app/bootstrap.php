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

// Alerts (web only): send visit + errors to a separate Telegram bot/group
if (php_sapi_name() !== 'cli') {
    try {
        $settings = new \App\Settings($db);
        $alertToken = trim((string)($settings->get('telegram.alert_bot_token', '') ?? ''));
        $alertChatId = trim((string)($settings->get('telegram.alert_chat_id', '') ?? ''));

        $sendAlert = static function (string $title, string $body) use ($alertToken, $alertChatId): void {
            static $guard = false;
            if ($guard) return;
            if ($alertToken === '' || $alertChatId === '') return;
            $guard = true;
            try {
                $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
                $now = (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                $uri = ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . ($_SERVER['REQUEST_URI'] ?? '');
                $user = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';
                $userLine = ($user !== '') ? ("User: <b>" . htmlspecialchars($user) . "</b>\n") : '';

                $text = "<b>🚨{$title}</b>\n"
                    . "Time: {$now}\n"
                    . "IP: <code>" . htmlspecialchars((string)$ip) . "</code>\n"
                    . $userLine
                    . "URL: <code>" . htmlspecialchars($uri) . "</code>\n\n"
                    . htmlspecialchars($body);

                // Telegram class throws on empty token/chat; already checked
                (new \App\Services\Telegram($alertToken))->sendMessage($alertChatId, $text);
            } catch (Throwable) {
                // ignore alert failures
            } finally {
                $guard = false;
            }
        };

        // Global exception handler
        set_exception_handler(static function (Throwable $e) use ($sendAlert): void {
            $msg = get_class($e) . ': ' . $e->getMessage() . "\n"
                . 'File: ' . $e->getFile() . ':' . $e->getLine();
            $sendAlert('Xatolik / Ошибка', $msg);
            // default behavior: show generic 500
            http_response_code(500);
            echo "500";
        });

        // Error handler (warnings/errors)
        set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($sendAlert): bool {
            // Skip notices/deprecated to reduce noise
            if (in_array($severity, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT], true)) {
                return false;
            }
            $msg = $message . "\n" . 'File: ' . $file . ':' . $line;
            $sendAlert('PHP warning/error', $msg);
            return false; // allow normal handling too
        });

        // Fatal errors on shutdown
        register_shutdown_function(static function () use ($sendAlert): void {
            $err = error_get_last();
            if (!$err) return;
            $type = (int)($err['type'] ?? 0);
            if (!in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
            $msg = (string)($err['message'] ?? 'Fatal') . "\n"
                . 'File: ' . (string)($err['file'] ?? '') . ':' . (int)($err['line'] ?? 0);
            $sendAlert('Fatal error', $msg);
        });

        // Visit alert (once per session)
        if (empty($_SESSION['__visit_alert_sent'])) {
            $_SESSION['__visit_alert_sent'] = 1;
            $sendAlert('Site visit', 'Kirish / Вход на сайт');
        }
    } catch (Throwable) {
        // ignore bootstrap alert failures
    }
}

