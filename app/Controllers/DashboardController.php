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
        $start = (new DateTimeImmutable('now', $tz))->modify('-6 day')->format('Y-m-d'); // last 7 days

        // Total orders sum by day
        $stmt = $this->db->prepare('
            SELECT stat_date, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e
            GROUP BY stat_date
            ORDER BY stat_date
        ');
        $stmt->execute(['s' => $start, 'e' => $today]);
        $byDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Today by channel
        $stmt = $this->db->prepare('
            SELECT channel, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date = :d
            GROUP BY channel
            ORDER BY cnt DESC
        ');
        $stmt->execute(['d' => $today]);
        $byChannelToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Today by payment
        $stmt = $this->db->prepare('
            SELECT payment_source, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date = :d
            GROUP BY payment_source
            ORDER BY cnt DESC
        ');
        $stmt->execute(['d' => $today]);
        $byPaymentToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('pages/dashboard', [
            'smartomatoConfigured' => (bool)$settings->get('smartomato.login') && (bool)$settings->get('smartomato.password'),
            'byDay' => $byDay,
            'byChannelToday' => $byChannelToday,
            'byPaymentToday' => $byPaymentToday,
        ]);
    }
}

