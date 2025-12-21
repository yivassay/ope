<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;
use App\Services\SmartomatoAggregator;
use App\Services\SmartomatoClient;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class SmartomatoController extends BaseController
{
    public function index(): void
    {
        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        // Default date for UI: before 09:00 -> yesterday, otherwise today
        $yesterday = $this->defaultUiDate($tz);

        $settings = new Settings($this->db);

        $message = null;
        $error = null;
        $debug = null;

        if (($_GET['action'] ?? '') === 'fetch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->auth->requireRole('admin');
            $date = (string)($_POST['date'] ?? $yesterday);
            try {
                $agg = new SmartomatoAggregator($this->db, $settings);
                $debugMsg = null;
                $rows = $agg->runForDate($date, $debugMsg);
                $message = "OK: {$date} rows={$rows} {$debugMsg}";
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        if (($_GET['action'] ?? '') === 'debug' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->auth->requireRole('admin');
            $date = (string)($_POST['date'] ?? $yesterday);
            try {
                $baseUrl = $settings->get('smartomato.base_url', 'https://smartomato.ru');
                $login = (string)$settings->get('smartomato.login', '');
                $password = (string)$settings->get('smartomato.password', '');
                $deliveredStatus = $settings->get('smartomato.delivered_status', 'complete');
                $perPage = (int)($settings->get('smartomato.per_page', '100') ?: 100);

                $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
                // Same window as SmartomatoAggregator: 09:00 -> next day 01:00
                $dayStart = new DateTimeImmutable($date . ' 09:00:00', $tz);
                $dayEnd = $dayStart->modify('+16 hours');

                $client = new SmartomatoClient((string)$baseUrl, $login, $password, 60);
                $token = $client->createSessionToken();

                $page = 1;
                $pageCount = 1;
                $sources = [];
                $payments = [];
                $takeaway = ['pickup' => 0, 'delivery' => 0];
                $examples = [];
                $ordersSeen = 0;
                $sumFinalTotal = 0.0;
                $sourceSums = [];
                $paymentSums = [];

                $deliveryKeyCounts = [];

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
                        $src = (string)($o['source'] ?? 'unknown');
                        $ps = (string)($o['payment_source'] ?? 'unknown');
                        $sources[$src] = ($sources[$src] ?? 0) + 1;
                        $payments[$ps] = ($payments[$ps] ?? 0) + 1;

                        $isTakeaway = (bool)($o['takeaway'] ?? false);
                        $takeaway[$isTakeaway ? 'pickup' : 'delivery']++;

                        $finalSum = (float)($o['final_sum'] ?? 0);
                        $sumFinalTotal += $finalSum;
                        $sourceSums[$src] = ($sourceSums[$src] ?? 0.0) + $finalSum;
                        $paymentSums[$ps] = ($paymentSums[$ps] ?? 0.0) + $finalSum;

                        if (count($examples) < 5) {
                            $deliveryLike = $this->extractDeliveryLikeFields($o);
                            foreach (array_keys($deliveryLike) as $k) {
                                $deliveryKeyCounts[$k] = ($deliveryKeyCounts[$k] ?? 0) + 1;
                            }
                            $examples[] = [
                                'id' => (int)($o['id'] ?? 0),
                                'created_at' => $createdAt,
                                'restaurant_id' => (int)($o['restaurant_id'] ?? 0),
                                'takeaway' => $isTakeaway ? 1 : 0,
                                'source' => $src,
                                'payment_source' => $ps,
                                'final_sum' => $finalSum,
                                'payment_id' => $o['payment_id'] ?? null,
                                'delivery_like' => $deliveryLike,
                            ];
                        }
                    }

                    if ($shouldStop) {
                        break;
                    }
                    $page++;
                }

                // Enrich examples with full order details (payments array / payment_id)
                $details = [];
                foreach ($examples as $ex) {
                    if (($ex['id'] ?? 0) <= 0) continue;
                    $full = $client->getOrder($token, (int)$ex['id']);
                    $order = $full['order'] ?? [];
                    $deliveryLike = is_array($order) ? $this->extractDeliveryLikeFields($order) : [];
                    $details[] = [
                        'id' => (int)$ex['id'],
                        'source' => $ex['source'],
                        'payment_source' => $ex['payment_source'],
                        'payment_id' => $order['payment_id'] ?? null,
                        'payments_count' => is_array($full['payments'] ?? null) ? count($full['payments']) : null,
                        'payments' => $full['payments'] ?? null,
                        'delivery_like' => $deliveryLike,
                    ];
                }

                arsort($sources);
                arsort($payments);
                arsort($sourceSums);
                arsort($paymentSums);

                $debug = [
                    'date' => $date,
                    'orders_seen' => $ordersSeen,
                    'window' => [
                        'start' => $dayStart->format('Y-m-d H:i:s'),
                        'end' => $dayEnd->format('Y-m-d H:i:s'),
                        'timezone' => $tz->getName(),
                    ],
                    'sum_final_total' => $sumFinalTotal,
                    'sources' => $sources,
                    'sources_sum' => $sourceSums,
                    'payments' => $payments,
                    'payments_sum' => $paymentSums,
                    'takeaway' => $takeaway,
                    'examples' => $examples,
                    'details' => $details,
                    'delivery_keys_top' => $deliveryKeyCounts,
                ];
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $runs = $this->db->query('SELECT run_date, status, message, created_at FROM smartomato_runs ORDER BY run_date DESC LIMIT 14')
            ->fetchAll(PDO::FETCH_ASSOC);

        $stats = $this->db->query('
            SELECT stat_date, restaurant_id, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            GROUP BY stat_date, restaurant_id
            ORDER BY stat_date DESC, restaurant_id
            LIMIT 30
        ')->fetchAll(PDO::FETCH_ASSOC);

        $this->render('pages/smartomato', [
            'yesterday' => $yesterday,
            'smartomatoConfigured' => (bool)$settings->get('smartomato.login') && (bool)$settings->get('smartomato.password'),
            'message' => $message,
            'error' => $error,
            'debug' => $debug,
            'runs' => $runs,
            'stats' => $stats,
        ]);
    }

    private function extractDeliveryLikeFields(array $order): array
    {
        $out = [];
        foreach ($order as $k => $v) {
            if (!is_string($k)) continue;
            $lk = strtolower($k);
            if (strpos($lk, 'delivery') === false && strpos($lk, 'shipping') === false) {
                continue;
            }
            if (is_scalar($v) || $v === null) {
                $out[$k] = $v;
            }
            if (count($out) >= 12) break;
        }
        return $out;
    }
}

