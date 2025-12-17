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
        $activeDate = (is_array($latest) && !empty($latest['d'])) ? (string)$latest['d'] : $today;
        $start = (new DateTimeImmutable($activeDate, $tz))->modify('-6 day')->format('Y-m-d'); // last 7 days ending at activeDate

        // Total orders sum by day
        $stmt = $this->db->prepare('
            SELECT stat_date, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date BETWEEN :s AND :e
            GROUP BY stat_date
            ORDER BY stat_date
        ');
        $stmt->execute(['s' => $start, 'e' => $activeDate]);
        $byDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Active date by channel
        $stmt = $this->db->prepare('
            SELECT channel, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date = :d
            GROUP BY channel
            ORDER BY cnt DESC
        ');
        $stmt->execute(['d' => $activeDate]);
        $byChannelToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Active date by payment
        $stmt = $this->db->prepare('
            SELECT payment_source, SUM(order_count) AS cnt, SUM(sum_final) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date = :d
            GROUP BY payment_source
            ORDER BY cnt DESC
        ');
        $stmt->execute(['d' => $activeDate]);
        $byPaymentToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Telegram bot (manual) - last 7 days
        $stmt = $this->db->prepare('
            SELECT stat_date, order_count, sum_final
            FROM telegram_daily_stats
            WHERE stat_date BETWEEN :s AND :e
            ORDER BY stat_date
        ');
        $stmt->execute(['s' => $start, 'e' => $activeDate]);
        $telegramByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare('SELECT order_count, sum_final FROM telegram_daily_stats WHERE stat_date = :d');
        $stmt->execute(['d' => $activeDate]);
        $telegramToday = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['order_count' => 0, 'sum_final' => 0];

        $this->render('pages/dashboard', [
            'smartomatoConfigured' => (bool)$settings->get('smartomato.login') && (bool)$settings->get('smartomato.password'),
            'byDay' => $byDay,
            'byChannelToday' => $byChannelToday,
            'byPaymentToday' => $byPaymentToday,
            'telegramByDay' => $telegramByDay,
            'telegramToday' => $telegramToday,
            'activeDate' => $activeDate,
        ]);
    }
}

