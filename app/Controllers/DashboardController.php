<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;
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

        // Use latest available Smartomato date (because cron imports yesterday)
        $latest = $this->db->query('SELECT MAX(stat_date) AS d FROM smartomato_daily_stats')->fetch(PDO::FETCH_ASSOC);
        $defaultTo = (is_array($latest) && !empty($latest['d'])) ? (string)$latest['d'] : $today;

        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? $defaultTo);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $defaultTo;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = (new DateTimeImmutable($to, $tz))->modify('-13 day')->format('Y-m-d'); // default 14 days
        }

        // Total orders sum by day (range)
        $stmt = $this->db->prepare('
            SELECT stat_date, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e
            GROUP BY stat_date
            ORDER BY stat_date
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $byDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Summary (range)
        $stmt = $this->db->prepare('
            SELECT SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_final' => 0];

        $stmt = $this->db->prepare('
            SELECT delivery_type, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e
            GROUP BY delivery_type
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $byDeliveryType = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // By restaurant (range)
        $stmt = $this->db->prepare('
            SELECT restaurant_id, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e
            GROUP BY restaurant_id
            ORDER BY cnt DESC
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $byRestaurant = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // By payment (range)
        $stmt = $this->db->prepare('
            SELECT payment_source, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e
            GROUP BY payment_source
            ORDER BY cnt DESC
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $byPayment = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // By channel (range)
        $stmt = $this->db->prepare('
            SELECT channel, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e
            GROUP BY channel
            ORDER BY cnt DESC
        ');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $byChannel = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Manual "others" (range): telegram + uzum (do not add to totals)
        $stmt = $this->db->prepare('SELECT SUM(order_count) AS cnt, SUM(sum_final) AS sum_final FROM telegram_daily_stats WHERE stat_date BETWEEN :s AND :e');
        $stmt->execute(['s' => $from, 'e' => $to]);
        $telegram = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_final' => 0];

        $uzum = ['cnt' => 0, 'sum_final' => 0];
        try {
            $stmt = $this->db->prepare('SELECT SUM(order_count) AS cnt, SUM(sum_final) AS sum_final FROM uzum_daily_stats WHERE stat_date BETWEEN :s AND :e');
            $stmt->execute(['s' => $from, 'e' => $to]);
            $uzum = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_final' => 0];
        } catch (\Throwable) {
            $uzum = ['cnt' => 0, 'sum_final' => 0];
        }

        // Commission settings
        $commission = [
            'yandex' => (float)($settings->get('commission.yandex', '0') ?? 0),
            'wolt' => (float)($settings->get('commission.wolt', '0') ?? 0),
            'uzum' => (float)($settings->get('commission.uzum', '0') ?? 0),
        ];

        $this->render('pages/dashboard', [
            'smartomatoConfigured' => (bool)$settings->get('smartomato.login') && (bool)$settings->get('smartomato.password'),
            'byDay' => $byDay,
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'byDeliveryType' => $byDeliveryType,
            'byRestaurant' => $byRestaurant,
            'byPayment' => $byPayment,
            'byChannel' => $byChannel,
            'telegram' => $telegram,
            'uzum' => $uzum,
            'commission' => $commission,
        ]);
    }
}

