<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class TelegramBotController extends BaseController
{
    public function index(): void
    {
        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        $date = (string)($_GET['date'] ?? (new DateTimeImmutable('now', $tz))->format('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        }

        if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $orderCount = (int)($_POST['order_count'] ?? 0);
            $sumFinal = (float)($_POST['sum_final'] ?? 0);
            $note = trim((string)($_POST['note'] ?? ''));

            $stmt = $this->db->prepare('INSERT INTO telegram_daily_stats (stat_date, order_count, sum_final, note, updated_by_user_id, updated_at)
                VALUES (:d,:c,:s,:n,:u,NOW())
                ON DUPLICATE KEY UPDATE order_count=VALUES(order_count), sum_final=VALUES(sum_final), note=VALUES(note), updated_by_user_id=VALUES(updated_by_user_id), updated_at=NOW()');
            $stmt->execute([
                'd' => $date,
                'c' => $orderCount,
                's' => $sumFinal,
                'n' => $note,
                'u' => (int)($this->auth->id() ?? 0),
            ]);

            Response::redirect('?page=telegram_bot&date=' . urlencode($date));
            return;
        }

        $stmt = $this->db->prepare('SELECT * FROM telegram_daily_stats WHERE stat_date = :d');
        $stmt->execute(['d' => $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $this->render('pages/telegram_bot', [
            'date' => $date,
            'row' => $row,
        ]);
    }
}

