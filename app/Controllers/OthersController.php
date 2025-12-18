<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class OthersController extends BaseController
{
    public function index(): void
    {
        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        $date = (string)($_GET['date'] ?? (new DateTimeImmutable('now', $tz))->format('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        }

        if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $kind = (string)($_POST['kind'] ?? '');
            $orderCount = (int)($_POST['order_count'] ?? 0);
            $sumFinal = (float)($_POST['sum_final'] ?? 0);
            $note = trim((string)($_POST['note'] ?? ''));

            if ($kind === 'telegram') {
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
            } elseif ($kind === 'uzum') {
                $stmt = $this->db->prepare('INSERT INTO uzum_daily_stats (stat_date, order_count, sum_final, note, updated_by_user_id, updated_at)
                    VALUES (:d,:c,:s,:n,:u,NOW())
                    ON DUPLICATE KEY UPDATE order_count=VALUES(order_count), sum_final=VALUES(sum_final), note=VALUES(note), updated_by_user_id=VALUES(updated_by_user_id), updated_at=NOW()');
                $stmt->execute([
                    'd' => $date,
                    'c' => $orderCount,
                    's' => $sumFinal,
                    'n' => $note,
                    'u' => (int)($this->auth->id() ?? 0),
                ]);
            }

            Response::redirect('?page=others&date=' . urlencode($date));
            return;
        }

        $stmt = $this->db->prepare('SELECT * FROM telegram_daily_stats WHERE stat_date = :d');
        $stmt->execute(['d' => $date]);
        $telegram = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Uzum table may be added after update; if missing, show empty without fatal error.
        $uzum = null;
        try {
            $stmt = $this->db->prepare('SELECT * FROM uzum_daily_stats WHERE stat_date = :d');
            $stmt->execute(['d' => $date]);
            $uzum = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            $uzum = null;
        }

        $this->render('pages/others', [
            'date' => $date,
            'telegram' => $telegram,
            'uzum' => $uzum,
        ]);
    }
}

