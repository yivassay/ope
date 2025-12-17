<?php
declare(strict_types=1);

// CLI: fetch delivered orders for previous day (Asia/Tashkent) and write aggregates.
// Usage: php bin/smartomato_fetch_daily.php [YYYY-MM-DD]

require __DIR__ . '/../app/bootstrap.php';

use App\Settings;
use App\Services\SmartomatoAggregator;

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
$targetDate = $argv[1] ?? null;
if ($targetDate === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
    // default: yesterday in Tashkent time
    $targetDate = (new DateTimeImmutable('now', $tz))->modify('-1 day')->format('Y-m-d');
}

$settings = new Settings($db);
try {
    $aggService = new SmartomatoAggregator($db, $settings);
    $debug = null;
    $rows = $aggService->runForDate($targetDate, $debug);
    echo "OK: {$targetDate} rows={$rows} {$debug}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(3);
}

