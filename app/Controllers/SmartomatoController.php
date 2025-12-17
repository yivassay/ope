<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;
use App\Services\SmartomatoAggregator;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class SmartomatoController extends BaseController
{
    public function index(): void
    {
        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        $yesterday = (new DateTimeImmutable('now', $tz))->modify('-1 day')->format('Y-m-d');

        $settings = new Settings($this->db);

        $message = null;
        $error = null;

        if (($_GET['action'] ?? '') === 'fetch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->auth->requireRole('admin');
            $date = (string)($_POST['date'] ?? $yesterday);
            try {
                $agg = new SmartomatoAggregator($this->db, $settings);
                $debug = null;
                $rows = $agg->runForDate($date, $debug);
                $message = "OK: {$date} rows={$rows} {$debug}";
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
            'runs' => $runs,
            'stats' => $stats,
        ]);
    }
}

