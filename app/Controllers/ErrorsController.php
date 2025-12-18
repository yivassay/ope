<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use App\Settings;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class ErrorsController extends BaseController
{
    public function index(): void
    {
        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        $date = (string)($_GET['date'] ?? (new DateTimeImmutable('now', $tz))->format('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        }

        $settings = new Settings($this->db);
        $restaurantMapRaw = (string)$settings->get('restaurants.map', '');
        $restaurantMap = $this->parseRestaurantMap($restaurantMapRaw);

        // fallback restaurant ids from Smartomato stats if mapping empty
        if (!$restaurantMap) {
            try {
                $rows = $this->db->query('SELECT DISTINCT restaurant_id FROM smartomato_daily_stats ORDER BY restaurant_id')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $id = (int)$r['restaurant_id'];
                    if ($id > 0) {
                        $restaurantMap[(string)$id] = 'Restaurant ' . $id;
                    }
                }
            } catch (Throwable) {
                // ignore
            }
        }

        $operators = $this->db->query('SELECT id, name FROM operators WHERE is_active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

        $message = null;
        $error = null;

        if (($_GET['action'] ?? '') === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $targetType = (string)($_POST['target_type'] ?? 'operator');
            $amount = $this->parseMoney((string)($_POST['amount'] ?? '0'));
            $comment = trim((string)($_POST['comment'] ?? ''));

            $operatorId = null;
            $restaurantId = null;
            $restaurantName = '';

            if ($targetType === 'operator') {
                $operatorId = (int)($_POST['operator_id'] ?? 0);
                if ($operatorId <= 0) {
                    $error = 'Operator tanlanmagan';
                }
            } elseif ($targetType === 'restaurant') {
                $ridRaw = (string)($_POST['restaurant_id'] ?? '');
                if ($ridRaw === '' && isset($_POST['restaurant_name'])) {
                    $restaurantName = trim((string)$_POST['restaurant_name']);
                } else {
                    $restaurantId = (int)$ridRaw;
                    $restaurantName = $restaurantMap[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId);
                }
                if (($restaurantId === null || $restaurantId <= 0) && $restaurantName === '') {
                    $error = 'Restaurant tanlanmagan';
                }
            } else {
                $error = 'Target type noto‘g‘ri';
            }

            if ($error === null) {
                try {
                    $stmt = $this->db->prepare('INSERT INTO delivery_errors
                        (error_date, target_type, operator_id, restaurant_id, restaurant_name, amount, comment, created_by_user_id, created_at)
                        VALUES (:d,:tt,:op,:rid,:rname,:a,:c,:u,NOW())');
                    $stmt->execute([
                        'd' => $date,
                        'tt' => $targetType,
                        'op' => $operatorId,
                        'rid' => $restaurantId,
                        'rname' => $restaurantName,
                        'a' => $amount,
                        'c' => $comment,
                        'u' => (int)($this->auth->id() ?? 0),
                    ]);
                    Response::redirect('?page=errors&date=' . urlencode($date));
                    return;
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $stmt = $this->db->prepare('SELECT * FROM delivery_errors WHERE error_date = :d ORDER BY id DESC');
        $stmt->execute(['d' => $date]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sum = 0.0;
        foreach ($items as $it) {
            $sum += (float)$it['amount'];
        }

        $this->render('pages/errors', [
            'date' => $date,
            'message' => $message,
            'error' => $error,
            'operators' => $operators,
            'restaurantMap' => $restaurantMap,
            'items' => $items,
            'totalSum' => $sum,
        ]);
    }

    private function parseRestaurantMap(string $raw): array
    {
        // Format: id|name
        $out = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $parts = explode('|', $line, 2);
            if (count($parts) !== 2) continue;
            $id = trim($parts[0]);
            $name = trim($parts[1]);
            if ($id !== '' && $name !== '') {
                $out[$id] = $name;
            }
        }
        return $out;
    }

    private function parseMoney(string $v): float
    {
        $v = trim($v);
        if ($v === '' || $v === '-') return 0.0;
        $v = str_replace(["\xC2\xA0", ' '], '', $v);
        $v = str_replace(',', '.', $v);
        return (float)$v;
    }
}

