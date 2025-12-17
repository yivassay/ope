<?php
declare(strict_types=1);

// CLI: fetch delivered orders for previous day (Asia/Tashkent) and write aggregates.
// Usage: php bin/smartomato_fetch_daily.php [YYYY-MM-DD]

require __DIR__ . '/../app/bootstrap.php';

use App\Settings;
use App\Services\SmartomatoClient;

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

$dayStart = new DateTimeImmutable($targetDate . ' 00:00:00', $tz);
$dayEnd = $dayStart->modify('+1 day');

$settings = new Settings($db);
$baseUrl = $settings->get('smartomato.base_url', 'https://smartomato.ru');
$login = (string)$settings->get('smartomato.login', '');
$password = (string)$settings->get('smartomato.password', '');
$deliveredStatus = $settings->get('smartomato.delivered_status', 'complete');
$perPage = (int)($settings->get('smartomato.per_page', '100') ?: 100);

if ($login === '' || $password === '') {
    fwrite(STDERR, "Smartomato credentials not configured.\n");
    exit(2);
}

// channel mapping: "source=channel" per line
$channelMapRaw = (string)$settings->get('smartomato.channel_map', '');
$sourceToChannel = [];
foreach (preg_split('/\r?\n/', $channelMapRaw) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
        $sourceToChannel[trim($parts[0])] = trim($parts[1]);
    }
}

$client = new SmartomatoClient($baseUrl, $login, $password, 60);
$token = $client->createSessionToken();

// We'll page through orders and stop when created_at < dayStart.
// Doc does not list date filters, so we filter client-side.
$page = 1;
$pageCount = 1;

// Aggregation buffer: key => ['order_count'=>int,'sum_final'=>float]
$agg = [];

while ($page <= $pageCount) {
    $resp = $client->listOrders($token, [
        'page' => $page,
        'per_page' => $perPage,
        'status' => $deliveredStatus,
        'sort_by' => 'created_at',
    ]);
    $orders = $resp['orders'] ?? [];
    $meta = $resp['meta'] ?? [];
    $pageCount = (int)($meta['page_count'] ?? $pageCount);

    if (!is_array($orders)) {
        break;
    }

    $shouldStop = false;
    foreach ($orders as $o) {
        if (!is_array($o)) {
            continue;
        }
        $createdAt = $o['created_at'] ?? null;
        if (!is_string($createdAt) || $createdAt === '') {
            continue;
        }
        try {
            $created = new DateTimeImmutable($createdAt);
            $created = $created->setTimezone($tz);
        } catch (Throwable) {
            continue;
        }

        if ($created < $dayStart) {
            $shouldStop = true;
            continue;
        }
        if ($created >= $dayEnd) {
            // newer than target day (can happen if API sorts asc/desc differently)
            continue;
        }

        $restaurantId = (int)($o['restaurant_id'] ?? 0);
        $takeaway = (bool)($o['takeaway'] ?? false);
        $deliveryType = $takeaway ? 'pickup' : 'delivery';

        $source = (string)($o['source'] ?? 'unknown');
        $channel = $sourceToChannel[$source] ?? self_guess_channel($source);

        $payment = (string)($o['payment_source'] ?? 'unknown');
        $final = (float)($o['final_sum'] ?? 0);

        $key = implode('|', [$targetDate, $restaurantId, $deliveryType, $channel, $payment]);
        if (!isset($agg[$key])) {
            $agg[$key] = [
                'date' => $targetDate,
                'restaurant_id' => $restaurantId,
                'delivery_type' => $deliveryType,
                'channel' => $channel,
                'payment_source' => $payment,
                'order_count' => 0,
                'sum_final' => 0.0,
            ];
        }
        $agg[$key]['order_count']++;
        $agg[$key]['sum_final'] += $final;
    }

    if ($shouldStop) {
        break;
    }
    $page++;
}

// Persist aggregates (idempotent per date+dimensions)
$db->beginTransaction();
try {
    // mark run
    $stmt = $db->prepare('INSERT INTO smartomato_runs (run_date, status, message, created_at)
        VALUES (:d, :s, :m, NOW())
        ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message), created_at=VALUES(created_at)');
    $stmt->execute(['d' => $targetDate, 's' => 'running', 'm' => '']);

    $ins = $db->prepare('INSERT INTO smartomato_daily_stats
        (stat_date, restaurant_id, delivery_type, channel, payment_source, order_count, sum_final, updated_at)
        VALUES (:d,:r,:dt,:ch,:ps,:c,:sf,NOW())
        ON DUPLICATE KEY UPDATE order_count=VALUES(order_count), sum_final=VALUES(sum_final), updated_at=NOW()');

    foreach ($agg as $row) {
        $ins->execute([
            'd' => $row['date'],
            'r' => $row['restaurant_id'],
            'dt' => $row['delivery_type'],
            'ch' => $row['channel'],
            'ps' => $row['payment_source'],
            'c' => $row['order_count'],
            'sf' => $row['sum_final'],
        ]);
    }

    $stmt = $db->prepare('UPDATE smartomato_runs SET status=:s, message=:m WHERE run_date=:d');
    $stmt->execute(['s' => 'ok', 'm' => 'rows=' . count($agg), 'd' => $targetDate]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    $stmt = $db->prepare('INSERT INTO smartomato_runs (run_date, status, message, created_at)
        VALUES (:d, :s, :m, NOW())
        ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message), created_at=VALUES(created_at)');
    $stmt->execute(['d' => $targetDate, 's' => 'error', 'm' => substr($e->getMessage(), 0, 1000)]);
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(3);
}

echo "OK: {$targetDate} rows=" . count($agg) . "\n";

function self_guess_channel(string $source): string
{
    $s = strtolower(trim($source));
    if ($s === '') return 'other';
    if (str_contains($s, 'android') || $s === 'android') return 'app';
    if (str_contains($s, 'ios') || $s === 'ios' || str_contains($s, 'iphone')) return 'app';
    if (str_contains($s, 'web') || str_contains($s, 'site') || str_contains($s, 'widget')) return 'web';
    if (str_contains($s, 'yandex')) return 'yandex';
    if (str_contains($s, 'wolt')) return 'wolt';
    if (str_contains($s, 'board')) return 'board';
    return 'other';
}

