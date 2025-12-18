<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;
use App\Services\RestaurantMap;
use App\Services\Series;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $settings = new Settings($this->db);

        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        $period = (string)($_GET['period'] ?? 'week'); // week|month|year
        if (!in_array($period, ['week', 'month', 'year'], true)) {
            $period = 'week';
        }
        $to = $today;
        $from = match ($period) {
            'week' => (new DateTimeImmutable($to, $tz))->modify('-6 day')->format('Y-m-d'),
            'month' => (new DateTimeImmutable($to, $tz))->modify('-29 day')->format('Y-m-d'),
            'year' => (new DateTimeImmutable($to, $tz))->modify('-364 day')->format('Y-m-d'),
        };

        $restaurantId = (int)($_GET['restaurant_id'] ?? 0); // 0=all

        $rm = new RestaurantMap($settings);
        $idToName = $rm->getIdToName();
        if (!$idToName) {
            // fallback: show ids that exist in data
            $rows = $this->db->query('SELECT DISTINCT restaurant_id FROM smartomato_daily_stats ORDER BY restaurant_id')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $id = (int)$r['restaurant_id'];
                if ($id > 0) $idToName[(string)$id] = 'Restaurant ' . $id;
            }
        }

        $dates = Series::dateRange($from, $to, $tz);

        // Orders dynamics
        $q = '
            SELECT stat_date, SUM(order_count) AS orders, SUM(sum_final) AS revenue
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e
        ';
        $params = ['s' => $from, 'e' => $to];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id = :rid';
            $params['rid'] = $restaurantId;
        }
        $q .= ' GROUP BY stat_date ORDER BY stat_date';
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ordersByDate = [];
        foreach ($rows as $r) {
            $d = (string)$r['stat_date'];
            $ordersByDate[$d] = [
                'orders' => (float)($r['orders'] ?? 0),
                'revenue' => (float)($r['revenue'] ?? 0),
                'avg' => ((float)($r['orders'] ?? 0) > 0) ? ((float)$r['revenue'] / (float)$r['orders']) : 0,
            ];
        }

        // Expenses dynamics
        // Salaries by day:
        // - operator: fixed + %sales
        // - logistic: manual_salary (100%)
        $salaryByDate = [];
        try {
            $stmt = $this->db->prepare('
                SELECT ods.sale_date,
                       COALESCE(SUM(
                          CASE
                            WHEN ods.role_mode = "logistic" THEN ods.manual_salary
                            ELSE (o.fixed_salary + (ods.sales_sum * o.percent_rate / 100.0))
                          END
                       ),0) AS salary_sum
                FROM operator_daily_sales ods
                JOIN operators o ON o.id = ods.operator_id
                WHERE ods.sale_date BETWEEN :s AND :e
                GROUP BY ods.sale_date
                ORDER BY ods.sale_date
            ');
            $stmt->execute(['s' => $from, 'e' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $d = (string)$r['sale_date'];
                $salaryByDate[$d] = ['salary' => (float)($r['salary_sum'] ?? 0)];
            }
        } catch (\Throwable) {
            $salaryByDate = [];
        }

        // Taxi costs per day (Yandex + paid cancel + returned) + Millennium
        $taxiByDate = [];
        try {
            $stmt = $this->db->prepare('
                SELECT stat_date, COALESCE(SUM(sum_total + paid_cancel_sum + returned_sum),0) AS taxi_sum
                FROM taxi_daily_stats
                WHERE stat_date BETWEEN :s AND :e
                GROUP BY stat_date
                ORDER BY stat_date
            ');
            $stmt->execute(['s' => $from, 'e' => $to]);
            $taxiRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($taxiRows as $r) {
                $taxiByDate[(string)$r['stat_date']] = ['taxi' => (float)($r['taxi_sum'] ?? 0)];
            }
        } catch (\Throwable) {
            $taxiByDate = [];
        }
        $millByDate = [];
        try {
            $stmt = $this->db->prepare('
                SELECT stat_date, COALESCE(SUM(sum_total),0) AS mill_sum
                FROM millennium_taxi_daily_stats
                WHERE stat_date BETWEEN :s AND :e
                GROUP BY stat_date
                ORDER BY stat_date
            ');
            $stmt->execute(['s' => $from, 'e' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $millByDate[(string)$r['stat_date']] = ['mill' => (float)($r['mill_sum'] ?? 0)];
            }
        } catch (\Throwable) {
            $millByDate = [];
        }

        // Errors per day (amount)
        $errByDate = [];
        try {
            $stmt = $this->db->prepare('
                SELECT error_date, COALESCE(SUM(amount),0) AS err_sum
                FROM delivery_errors
                WHERE error_date BETWEEN :s AND :e
                GROUP BY error_date
                ORDER BY error_date
            ');
            $stmt->execute(['s' => $from, 'e' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $errByDate[(string)$r['error_date']] = ['errors' => (float)($r['err_sum'] ?? 0)];
            }
        } catch (\Throwable) {
            $errByDate = [];
        }

        // Aggregators dynamics: yandex, wolt from Smartomato + uzum manual
        $aggByDate = [];
        $stmt = $this->db->prepare('
            SELECT stat_date, channel, COALESCE(SUM(order_count),0) AS cnt
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e AND channel IN ("yandex","wolt")
            GROUP BY stat_date, channel
            ORDER BY stat_date
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $d = (string)$r['stat_date'];
            if (!isset($aggByDate[$d])) $aggByDate[$d] = ['yandex' => 0, 'wolt' => 0, 'uzum' => 0];
            $aggByDate[$d][(string)$r['channel']] = (float)$r['cnt'];
        }
        $uzumByDate = [];
        try {
            $stmt = $this->db->prepare('SELECT stat_date, COALESCE(SUM(order_count),0) AS cnt FROM uzum_daily_stats WHERE stat_date BETWEEN :s AND :e GROUP BY stat_date ORDER BY stat_date');
            $stmt->execute(['s' => $from, 'e' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $uzumByDate[(string)$r['stat_date']] = (float)$r['cnt'];
            }
        } catch (\Throwable) {
            $uzumByDate = [];
        }

        // Other channels dynamics: web+app, telegram, calls (board - telegram)
        $stmt = $this->db->prepare('
            SELECT stat_date, COALESCE(SUM(order_count),0) AS cnt
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e AND channel IN ("web","app")
            GROUP BY stat_date
            ORDER BY stat_date
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $webappByDate = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $webappByDate[(string)$r['stat_date']] = (float)$r['cnt'];
        }
        $stmt = $this->db->prepare('
            SELECT stat_date, COALESCE(SUM(order_count),0) AS cnt
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e AND channel = "board"
            GROUP BY stat_date
            ORDER BY stat_date
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $boardByDate = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $boardByDate[(string)$r['stat_date']] = (float)$r['cnt'];
        }
        $telegramByDate = [];
        $stmt = $this->db->prepare('SELECT stat_date, COALESCE(SUM(order_count),0) AS cnt FROM telegram_daily_stats WHERE stat_date BETWEEN :s AND :e GROUP BY stat_date ORDER BY stat_date');
        $stmt->execute(['s' => $from, 'e' => $to]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $telegramByDate[(string)$r['stat_date']] = (float)$r['cnt'];
        }

        // Delivery types dynamics for web+app+board combined
        $stmt = $this->db->prepare('
            SELECT stat_date, delivery_type, COALESCE(SUM(order_count),0) AS cnt
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e AND channel IN ("web","app","board")
            GROUP BY stat_date, delivery_type
            ORDER BY stat_date
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $typesByDate = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $d = (string)$r['stat_date'];
            if (!isset($typesByDate[$d])) $typesByDate[$d] = ['delivery' => 0, 'pickup' => 0];
            $typesByDate[$d][(string)$r['delivery_type']] = (float)$r['cnt'];
        }

        // Operator dynamics (top 5 by order_count)
        $topOps = [];
        $opSeries = []; // opId => [date => count]
        $stmt = $this->db->prepare('
            SELECT operator_id, COALESCE(SUM(order_count),0) AS cnt
            FROM operator_daily_sales
            WHERE sale_date BETWEEN :s AND :e
            GROUP BY operator_id
            ORDER BY cnt DESC
            LIMIT 5
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $topOps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $opIds = array_map(static fn($r) => (int)$r['operator_id'], $topOps);
        $opNames = [];
        if ($opIds) {
            $in = implode(',', array_fill(0, count($opIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM operators WHERE id IN ($in)");
            $stmt->execute($opIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['id']] = (string)$r['name'];
            }
            $stmt = $this->db->prepare("SELECT sale_date, operator_id, COALESCE(order_count,0) AS cnt FROM operator_daily_sales WHERE sale_date BETWEEN ? AND ? AND operator_id IN ($in)");
            $stmt->execute(array_merge([$from, $to], $opIds));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $oid = (int)$r['operator_id'];
                $d = (string)$r['sale_date'];
                if (!isset($opSeries[$oid])) $opSeries[$oid] = [];
                $opSeries[$oid][$d] = (float)$r['cnt'];
            }
        }

        $this->render('pages/dashboard', [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'restaurantId' => $restaurantId,
            'restaurants' => $idToName,

            'dates' => $dates,
            'ordersByDate' => $ordersByDate,
            'salaryByDate' => $salaryByDate,
            'taxiByDate' => $taxiByDate,
            'millByDate' => $millByDate,
            'errByDate' => $errByDate,
            'aggByDate' => $aggByDate,
            'uzumByDate' => $uzumByDate,
            'webappByDate' => $webappByDate,
            'telegramByDate' => $telegramByDate,
            'boardByDate' => $boardByDate,
            'typesByDate' => $typesByDate,
            'opIds' => $opIds,
            'opNames' => $opNames,
            'opSeries' => $opSeries,
        ]);
    }
}

