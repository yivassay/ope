<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;
use App\Services\RestaurantMap;
use App\Services\Telegram;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class BugungiController extends BaseController
{
    public function index(): void
    {
        $settings = new Settings($this->db);
        $rm = new RestaurantMap($settings);

        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        $date = (string)($_GET['date'] ?? (new DateTimeImmutable('now', $tz))->format('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        }

        $restaurantId = (int)($_GET['restaurant_id'] ?? 0); // 0=all
        $restaurants = $rm->getIdToName();
        if (!$restaurants) {
            $rows = $this->db->query('SELECT DISTINCT restaurant_id FROM smartomato_daily_stats ORDER BY restaurant_id')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $id = (int)$r['restaurant_id'];
                if ($id > 0) $restaurants[(string)$id] = 'Restaurant ' . $id;
            }
        }

        $commission = [
            'yandex' => (float)($settings->get('commission.yandex', '0') ?? 0),
            'wolt' => (float)($settings->get('commission.wolt', '0') ?? 0),
            'uzum' => (float)($settings->get('commission.uzum', '0') ?? 0),
        ];

        // Profit: totals
        $q = 'SELECT COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final FROM smartomato_daily_stats WHERE stat_date=:d';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_final' => 0];

        // Delivery/pickup without aggregators (exclude yandex/wolt channels)
        $q = '
            SELECT delivery_type, COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date=:d AND channel NOT IN ("yandex","wolt")
        ';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $q .= ' GROUP BY delivery_type';
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $byTypeNoAgg = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $deliveryNoAgg = ['cnt' => 0, 'sum_final' => 0];
        $pickupNoAgg = ['cnt' => 0, 'sum_final' => 0];
        foreach ($byTypeNoAgg as $r) {
            if (($r['delivery_type'] ?? '') === 'delivery') $deliveryNoAgg = $r;
            if (($r['delivery_type'] ?? '') === 'pickup') $pickupNoAgg = $r;
        }

        // Aggregators totals: yandex+wolt from Smartomato + uzum manual
        $q = '
            SELECT channel, COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date=:d AND channel IN ("yandex","wolt")
        ';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $q .= ' GROUP BY channel';
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $agg = ['yandex' => ['cnt' => 0, 'sum_final' => 0], 'wolt' => ['cnt' => 0, 'sum_final' => 0]];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $agg[(string)$r['channel']] = $r;
        }
        $uzum = ['cnt' => 0, 'sum_final' => 0];
        try {
            $stmt = $this->db->prepare('SELECT COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final FROM uzum_daily_stats WHERE stat_date=:d');
            $stmt->execute(['d' => $date]);
            $uzum = $stmt->fetch(PDO::FETCH_ASSOC) ?: $uzum;
        } catch (Throwable) {
            $uzum = ['cnt' => 0, 'sum_final' => 0];
        }

        // Expenses: salary for day
        $salarySum = 0.0;
        $salaryRows = [];
        try {
            $stmt = $this->db->prepare('
                SELECT o.name, ods.role_mode, ods.order_count, ods.sales_sum, ods.manual_salary,
                       CASE
                         WHEN ods.role_mode="logistic" THEN ods.manual_salary
                         ELSE (o.fixed_salary + (ods.sales_sum * o.percent_rate / 100.0))
                       END AS salary_value
                FROM operator_daily_sales ods
                JOIN operators o ON o.id = ods.operator_id
                WHERE ods.sale_date = :d
                ORDER BY o.name
            ');
            $stmt->execute(['d' => $date]);
            $salaryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($salaryRows as $r) {
                $salarySum += (float)($r['salary_value'] ?? 0);
            }
        } catch (Throwable) {
            $salarySum = 0.0;
            $salaryRows = [];
        }

        // Taxi yandex sums (optionally filter by restaurantName via mapping)
        $taxi = [
            'sum_total' => 0.0,
            'sum_waiting' => 0.0,
            'roundtrip_count' => 0,
            'duplicate_3h_count' => 0,
            'duplicate_3h_sum_total' => 0.0,
            'returned_count' => 0,
            'returned_sum' => 0.0,
        ];
        try {
            if ($restaurantId > 0) {
                $rName = $restaurants[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId);
                $stmt = $this->db->prepare('
                    SELECT COALESCE(SUM(sum_total + paid_cancel_sum + returned_sum),0) AS sum_total,
                           COALESCE(SUM(sum_waiting),0) AS sum_waiting,
                           COALESCE(SUM(roundtrip_count),0) AS roundtrip_count,
                           COALESCE(SUM(duplicate_3h_count),0) AS duplicate_3h_count,
                           COALESCE(SUM(duplicate_3h_sum_total),0) AS duplicate_3h_sum_total,
                           COALESCE(SUM(returned_count),0) AS returned_count,
                           COALESCE(SUM(returned_sum),0) AS returned_sum
                    FROM taxi_daily_stats
                    WHERE stat_date=:d AND restaurant_name=:rn
                ');
                $stmt->execute(['d' => $date, 'rn' => $rName]);
                $taxi = $stmt->fetch(PDO::FETCH_ASSOC) ?: $taxi;
            } else {
                $stmt = $this->db->prepare('
                    SELECT COALESCE(SUM(sum_total + paid_cancel_sum + returned_sum),0) AS sum_total,
                           COALESCE(SUM(sum_waiting),0) AS sum_waiting,
                           COALESCE(SUM(roundtrip_count),0) AS roundtrip_count,
                           COALESCE(SUM(duplicate_3h_count),0) AS duplicate_3h_count,
                           COALESCE(SUM(duplicate_3h_sum_total),0) AS duplicate_3h_sum_total,
                           COALESCE(SUM(returned_count),0) AS returned_count,
                           COALESCE(SUM(returned_sum),0) AS returned_sum
                    FROM taxi_daily_stats
                    WHERE stat_date=:d
                ');
                $stmt->execute(['d' => $date]);
                $taxi = $stmt->fetch(PDO::FETCH_ASSOC) ?: $taxi;
            }
        } catch (Throwable) {
            // ignore
        }

        // Millennium taxi
        $millSum = 0.0;
        try {
            if ($restaurantId > 0) {
                $rName = $restaurants[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId);
                $stmt = $this->db->prepare('SELECT COALESCE(SUM(sum_total),0) AS s FROM millennium_taxi_daily_stats WHERE stat_date=:d AND restaurant_name=:rn');
                $stmt->execute(['d' => $date, 'rn' => $rName]);
            } else {
                $stmt = $this->db->prepare('SELECT COALESCE(SUM(sum_total),0) AS s FROM millennium_taxi_daily_stats WHERE stat_date=:d');
                $stmt->execute(['d' => $date]);
            }
            $millSum = (float)($stmt->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);
        } catch (Throwable) {
            $millSum = 0.0;
        }

        // Errors (day)
        $err = ['cnt' => 0, 'sum' => 0.0];
        try {
            $q = 'SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS sum FROM delivery_errors WHERE error_date=:d';
            $params = ['d' => $date];
            if ($restaurantId > 0) {
                $q .= ' AND target_type="restaurant" AND restaurant_id=:rid';
                $params['rid'] = $restaurantId;
            }
            $stmt = $this->db->prepare($q);
            $stmt->execute($params);
            $err = $stmt->fetch(PDO::FETCH_ASSOC) ?: $err;
        } catch (Throwable) {
            $err = ['cnt' => 0, 'sum' => 0.0];
        }

        // Orders by channel (day) with calls=board-telegram and plus telegram/uzum
        $q = '
            SELECT channel, COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date=:d
        ';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $q .= ' GROUP BY channel';
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $byChannel = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $byChannel[(string)$r['channel']] = $r;
        }
        $telegram = ['cnt' => 0, 'sum_final' => 0];
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final FROM telegram_daily_stats WHERE stat_date=:d');
        $stmt->execute(['d' => $date]);
        $telegram = $stmt->fetch(PDO::FETCH_ASSOC) ?: $telegram;
        $board = $byChannel['board'] ?? ['cnt' => 0, 'sum_final' => 0];
        $callsCnt = max(0, (int)$board['cnt'] - (int)$telegram['cnt']);
        $callsSum = max(0.0, (float)$board['sum_final'] - (float)$telegram['sum_final']);

        // Payment types (day)
        $q = '
            SELECT payment_source, COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date=:d
        ';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $q .= ' GROUP BY payment_source ORDER BY cnt DESC';
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $byPayment = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Xisobotni yuborish (Telegram)
        $sendMessage = null;
        $sendError = null;
        if (($_GET['action'] ?? '') === 'send_report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // checks:
                // 1) Smartomato imported for date
                $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM smartomato_daily_stats WHERE stat_date=:d');
                $stmt->execute(['d' => $date]);
                $hasOrders = ((int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
                if (!$hasOrders) {
                    throw new \RuntimeException('Smartomato buyurtmalar yuklanmagan');
                }

                // 2) Taxi imported for date
                $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM taxi_daily_stats WHERE stat_date=:d');
                $stmt->execute(['d' => $date]);
                $hasTaxi = ((int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
                if (!$hasTaxi) {
                    throw new \RuntimeException('Yandex taxi yuklanmagan');
                }

                // 3) Salaries at least 3 staff
                $stmt = $this->db->prepare('SELECT COUNT(DISTINCT operator_id) AS c FROM operator_daily_sales WHERE sale_date=:d');
                $stmt->execute(['d' => $date]);
                $staffCnt = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
                if ($staffCnt < 3) {
                    throw new \RuntimeException('Ishchilar ish haqi kam (kamida 3 ta kiriting)');
                }

                $botToken = (string)$settings->get('telegram.bot_token', '');
                $chatId = (string)$settings->get('telegram.chat_id', '');

                $profitTotal = (float)($total['sum_final'] ?? 0) + (float)($uzum['sum_final'] ?? 0);
                $profitTotal = max(0.0, $profitTotal);

                $ySum = (float)($y['sum_final'] ?? 0);
                $wSum = (float)($w['sum_final'] ?? 0);
                $uSum = (float)($u['sum_final'] ?? 0);
                $yNet = $ySum * (1 - ((float)$commission['yandex']/100));
                $wNet = $wSum * (1 - ((float)$commission['wolt']/100));
                $uNet = $uSum * (1 - ((float)$commission['uzum']/100));

                $expensesTotal = (float)$salarySum + (float)($taxi['sum_total'] ?? 0) + (float)$millSum + (float)($err['sum'] ?? 0);

                $pct = static function (float $v) use ($profitTotal): string {
                    if ($profitTotal <= 0) return '0%';
                    return number_format(($v / $profitTotal) * 100.0, 1, '.', '') . '%';
                };
                $money = static function (float $v): string {
                    return number_format($v, 2, '.', ' ');
                };

                $title = ($restaurantId > 0) ? (' (' . ($restaurants[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId)) . ')') : '';

                $lines = [];
                $lines[] = "<b>💰Foyda{$title}</b>";
                $lines[] = "Jami summa: <b>{$money($profitTotal)}</b>";
                $lines[] = "Yandex: {$money($yNet)}";
                $lines[] = "Uzum: {$money($uNet)}";
                $lines[] = "Wolt: {$money($wNet)}";
                $lines[] = "";
                $lines[] = "<b>💸Xarajatlar</b>";
                $lines[] = "Jami summa: <b>{$money($expensesTotal)}</b> ({$pct($expensesTotal)})";
                $lines[] = "Ish haqi (jami): {$money((float)$salarySum)} ({$pct((float)$salarySum)})";
                $lines[] = "Yandex taxi: {$money((float)($taxi['sum_total'] ?? 0))} ({$pct((float)($taxi['sum_total'] ?? 0))})";
                $lines[] = "Millenium: {$money((float)$millSum)} ({$pct((float)$millSum)})";
                if ((int)($err['cnt'] ?? 0) > 0) {
                    $lines[] = "Kosyaklar: " . (int)$err['cnt'] . " ta, {$money((float)$err['sum'])}";
                }
                if ((int)($taxi['roundtrip_count'] ?? 0) > 0) {
                    $lines[] = "Tuda-obratno: " . (int)$taxi['roundtrip_count'] . " ta";
                }
                if ((int)($taxi['duplicate_3h_count'] ?? 0) > 0) {
                    $lines[] = "Ikki marta: " . (int)$taxi['duplicate_3h_count'] . " ta, {$money((float)$taxi['duplicate_3h_sum_total'])}";
                }
                if ((int)($taxi['returned_count'] ?? 0) > 0) {
                    $lines[] = "Qaytgan (возврат): " . (int)$taxi['returned_count'] . " ta, {$money((float)$taxi['returned_sum'])}";
                }

                $lines[] = "";
                $lines[] = "<b>🙂Ishchilar:</b>";
                foreach ($salaryRows as $r) {
                    $name = (string)$r['name'];
                    $mode = (string)$r['role_mode'];
                    $sal = (float)($r['salary_value'] ?? 0);
                    if ($mode === 'logistic') {
                        $lines[] = "{$name}: {$money($sal)} (logist)";
                    } else {
                        $oc = (int)($r['order_count'] ?? 0);
                        $lines[] = "{$name}: {$money($sal)} ({$oc} ta)";
                    }
                }

                $text = implode("\n", $lines);
                (new Telegram($botToken))->sendMessage($chatId, $text);
                $sendMessage = 'Telegramga yuborildi';
            } catch (Throwable $e) {
                $sendError = $e->getMessage();
            }
        }

        $this->render('pages/bugungi', [
            'date' => $date,
            'restaurantId' => $restaurantId,
            'restaurants' => $restaurants,
            'commission' => $commission,
            'total' => $total,
            'deliveryNoAgg' => $deliveryNoAgg,
            'pickupNoAgg' => $pickupNoAgg,
            'agg' => $agg,
            'uzum' => $uzum,
            'salarySum' => $salarySum,
            'salaryRows' => $salaryRows,
            'taxi' => $taxi,
            'millSum' => $millSum,
            'err' => $err,
            'byChannel' => $byChannel,
            'telegram' => $telegram,
            'callsCnt' => $callsCnt,
            'callsSum' => $callsSum,
            'byPayment' => $byPayment,
            'sendMessage' => $sendMessage,
            'sendError' => $sendError,
        ]);
    }
}

