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

        $error = null;
        if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // manager can save daily sales; admin can too
            $operatorId = (int)($_POST['operator_id'] ?? 0);
            $shift = (string)($_POST['shift'] ?? 'day'); // day|night
            if (!in_array($shift, ['day', 'night'], true)) {
                $shift = 'day';
            }
            $roleMode = (string)($_POST['role_mode'] ?? 'operator'); // operator|logistic
            if (!in_array($roleMode, ['operator', 'logistic'], true)) {
                $roleMode = 'operator';
            }
            $salesSum = (float)($_POST['sales_sum'] ?? 0);
            $orderCount = (int)($_POST['order_count'] ?? 0);
            $manualSalary = (float)($_POST['manual_salary'] ?? 0);
            $note = trim((string)($_POST['note'] ?? ''));

            if ($roleMode === 'logistic') {
                // logistic: salary is manual, sales/orders are not used
                $salesSum = 0;
                $orderCount = 0;
            } else {
                // operator: salary is calculated from fixed + % of sales
                $manualSalary = 0;
            }

            try {
                $stmt = $this->db->prepare('INSERT INTO operator_daily_sales (sale_date, operator_id, shift, sales_sum, order_count, role_mode, manual_salary, note, updated_by_user_id, updated_at)
                    VALUES (:d,:op,:sh,:s,:c,:rm,:ms,:n,:u,NOW())
                    ON DUPLICATE KEY UPDATE sales_sum=VALUES(sales_sum), order_count=VALUES(order_count), role_mode=VALUES(role_mode), manual_salary=VALUES(manual_salary), note=VALUES(note), updated_by_user_id=VALUES(updated_by_user_id), updated_at=NOW()');
                $stmt->execute([
                    'd' => $date,
                    'op' => $operatorId,
                    'sh' => $shift,
                    's' => $salesSum,
                    'c' => $orderCount,
                    'rm' => $roleMode,
                    'ms' => $manualSalary,
                    'n' => $note,
                    'u' => (int)($this->auth->id() ?? 0),
                ]);

                Response::redirect('?page=operator_sales&date=' . urlencode($date));
                return;
            } catch (\Throwable) {
                $error = $this->i18n->t('operator_sales.err.schema', 'Baza yangilanmagan: smena/zarplata ustunlarini qo‘shing (schema.sql).');
            }
        }

        // Prefer new operator fields; fallback for old schema
        try {
            $operators = $this->db->query('SELECT id, name, fixed_salary, fixed_salary_day, fixed_salary_night, percent_rate, guaranteed_salary, is_active FROM operators ORDER BY name')
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $operators = $this->db->query('SELECT id, name, fixed_salary, percent_rate, is_active FROM operators ORDER BY name')
                ->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->db->prepare('SELECT * FROM operator_daily_sales WHERE sale_date = :d');
        $stmt->execute(['d' => $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byOpShift = [];
        foreach ($rows as $r) {
            $oid = (int)$r['operator_id'];
            $sh = (string)($r['shift'] ?? 'day');
            $byOpShift[$oid . '|' . $sh] = $r;
        }

        $this->render('pages/operator_sales', [
            'date' => $date,
            'operators' => $operators,
            'salesByOperator' => $byOpShift,
            'error' => $error,
        ]);
    }
}

