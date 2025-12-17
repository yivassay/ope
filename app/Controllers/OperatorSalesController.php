<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class OperatorSalesController extends BaseController
{
    public function index(): void
    {
        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        $date = (string)($_GET['date'] ?? (new DateTimeImmutable('now', $tz))->format('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        }

        if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // manager can save daily sales; admin can too
            $operatorId = (int)($_POST['operator_id'] ?? 0);
            $salesSum = (float)($_POST['sales_sum'] ?? 0);
            $orderCount = (int)($_POST['order_count'] ?? 0);
            $note = trim((string)($_POST['note'] ?? ''));

            $stmt = $this->db->prepare('INSERT INTO operator_daily_sales (sale_date, operator_id, sales_sum, order_count, note, updated_by_user_id, updated_at)
                VALUES (:d,:op,:s,:c,:n,:u,NOW())
                ON DUPLICATE KEY UPDATE sales_sum=VALUES(sales_sum), order_count=VALUES(order_count), note=VALUES(note), updated_by_user_id=VALUES(updated_by_user_id), updated_at=NOW()');
            $stmt->execute([
                'd' => $date,
                'op' => $operatorId,
                's' => $salesSum,
                'c' => $orderCount,
                'n' => $note,
                'u' => (int)($this->auth->id() ?? 0),
            ]);

            Response::redirect('/?page=operator_sales&date=' . urlencode($date));
            return;
        }

        $operators = $this->db->query('SELECT id, name, fixed_salary, percent_rate, is_active FROM operators ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare('SELECT * FROM operator_daily_sales WHERE sale_date = :d');
        $stmt->execute(['d' => $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byOp = [];
        foreach ($rows as $r) {
            $byOp[(int)$r['operator_id']] = $r;
        }

        $this->render('pages/operator_sales', [
            'date' => $date,
            'operators' => $operators,
            'salesByOperator' => $byOp,
        ]);
    }
}

