<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;
use App\Services\RestaurantMap;
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
        $fixedTotal = (float)($this->db->query('SELECT COALESCE(SUM(fixed_salary),0) AS s FROM operators WHERE is_active=1')->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(ods.sales_sum * o.percent_rate / 100.0),0) AS pct_sum FROM operator_daily_sales ods JOIN operators o ON o.id=ods.operator_id WHERE ods.sale_date=:d');
        $stmt->execute(['d' => $date]);
        $pct = (float)($stmt->fetch(PDO::FETCH_ASSOC)['pct_sum'] ?? 0);
        $salarySum = $fixedTotal + $pct;

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
        $q = 'SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS sum FROM delivery_errors WHERE error_date=:d';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND target_type="restaurant" AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $err = $stmt->fetch(PDO::FETCH_ASSOC) ?: $err;

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
            'taxi' => $taxi,
            'millSum' => $millSum,
            'err' => $err,
            'byChannel' => $byChannel,
            'telegram' => $telegram,
            'callsCnt' => $callsCnt,
            'callsSum' => $callsSum,
            'byPayment' => $byPayment,
        ]);
    }
}

