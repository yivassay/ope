<?php
declare(strict_types=1);

namespace App\Services;

use App\Settings;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class SmartomatoAggregator
{
    public function __construct(private PDO $db, private Settings $settings)
    {
    }

    /**
     * Fetch delivered orders for target date (Asia/Tashkent) and upsert aggregates.
     * Returns number of aggregate rows written.
     */
    public function runForDate(string $targetDate, ?string &$debugMessage = null): int
    {
        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new RuntimeException('Invalid date format, expected YYYY-MM-DD');
        }

        $dayStart = new DateTimeImmutable($targetDate . ' 00:00:00', $tz);
        $dayEnd = $dayStart->modify('+1 day');

        $baseUrl = $this->settings->get('smartomato.base_url', 'https://smartomato.ru');
        $login = (string)$this->settings->get('smartomato.login', '');
        $password = (string)$this->settings->get('smartomato.password', '');
        $deliveredStatus = $this->settings->get('smartomato.delivered_status', 'complete');
        $perPage = (int)($this->settings->get('smartomato.per_page', '100') ?: 100);

        if ($login === '' || $password === '') {
            throw new RuntimeException('Smartomato credentials not configured');
        }

        // channel mapping: "source=channel" per line
        $channelMapRaw = (string)$this->settings->get('smartomato.channel_map', '');
        $sourceToChannel = [];
        foreach (preg_split('/\r?\n/', $channelMapRaw) as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $sourceToChannel[trim($parts[0])] = trim($parts[1]);
            }
        }

        $client = new SmartomatoClient((string)$baseUrl, $login, $password, 60);
        $token = $client->createSessionToken();

        $page = 1;
        $pageCount = 1;
        $agg = [];
        $ordersSeen = 0;

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
                    continue;
                }

                $ordersSeen++;

                $restaurantId = (int)($o['restaurant_id'] ?? 0);
                $takeaway = (bool)($o['takeaway'] ?? false);
                $deliveryType = $takeaway ? 'pickup' : 'delivery';

                $source = (string)($o['source'] ?? 'unknown');
                $channel = $sourceToChannel[$source] ?? self::guessChannel($source);

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

        $debugMessage = "orders_seen={$ordersSeen}, agg_rows=" . count($agg);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO smartomato_runs (run_date, status, message, created_at)
                VALUES (:d, :s, :m, NOW())
                ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message), created_at=VALUES(created_at)');
            $stmt->execute(['d' => $targetDate, 's' => 'running', 'm' => $debugMessage]);

            // Important: if we re-import a date, we must remove old aggregates first.
            // Otherwise old rows can remain (e.g. if a channel/payment combo disappears), causing confusion.
            $del = $this->db->prepare('DELETE FROM smartomato_daily_stats WHERE stat_date = :d');
            $del->execute(['d' => $targetDate]);

            $ins = $this->db->prepare('INSERT INTO smartomato_daily_stats
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

            $stmt = $this->db->prepare('UPDATE smartomato_runs SET status=:s, message=:m WHERE run_date=:d');
            $stmt->execute(['s' => 'ok', 'm' => $debugMessage, 'd' => $targetDate]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            $stmt = $this->db->prepare('INSERT INTO smartomato_runs (run_date, status, message, created_at)
                VALUES (:d, :s, :m, NOW())
                ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message), created_at=VALUES(created_at)');
            $stmt->execute(['d' => $targetDate, 's' => 'error', 'm' => substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }

        return count($agg);
    }

    private static function guessChannel(string $source): string
    {
        // Exact mapping per your Smartomato sources:
        // - marketplace, marketplace_mobile => web
        // - mobile_application_android, mobile_application_ios => app
        // - foodfox => yandex (Yandex Eda)
        // - board => calls (we keep it in channel="board" and show as "Qo'ng'iroqlar")
        // - wolt => wolt
        $s = strtolower(trim($source));
        return match ($s) {
            'marketplace', 'marketplace_mobile' => 'web',
            'mobile_application_android', 'mobile_application_ios' => 'app',
            'foodfox' => 'yandex',
            'wolt' => 'wolt',
            'board' => 'board',
            default => 'other',
        };
    }
}

