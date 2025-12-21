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
        $message = null;

        if (($_GET['action'] ?? '') === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $operatorId = (int)($_POST['operator_id'] ?? 0);
            $shift = (string)($_POST['shift'] ?? 'day');
            if (!in_array($shift, ['day', 'night'], true)) {
                $shift = 'day';
            }
            try {
                $stmt = $this->db->prepare('DELETE FROM operator_daily_sales WHERE sale_date=:d AND operator_id=:op AND shift=:sh');
                $stmt->execute(['d' => $date, 'op' => $operatorId, 'sh' => $shift]);
                Response::redirect('?page=operator_sales&date=' . urlencode($date));
                return;
            } catch (\Throwable) {
                $error = $this->i18n->t('operator_sales.err.schema', 'Baza yangilanmagan: smena/zarplata ustunlarini qo‘shing (schema.sql).');
            }
        }

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
            $salesSum = $this->parseMoney((string)($_POST['sales_sum'] ?? '0'));
            $orderCount = (int)($_POST['order_count'] ?? 0);
            $manualSalary = $this->parseMoney((string)($_POST['manual_salary'] ?? '0'));
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
            $operators = $this->db->query('SELECT id, name, fixed_salary, fixed_salary_day, fixed_salary_night, percent_rate, guaranteed_salary, is_active FROM operators WHERE is_active=1 ORDER BY name')
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $operators = $this->db->query('SELECT id, name, fixed_salary, percent_rate, is_active FROM operators WHERE is_active=1 ORDER BY name')
                ->fetchAll(PDO::FETCH_ASSOC);
        }

        $entries = [];
        try {
            $stmt = $this->db->prepare('
                SELECT ods.sale_date, ods.operator_id, ods.shift, ods.sales_sum, ods.order_count, ods.role_mode, ods.manual_salary, ods.note,
                       o.name, o.fixed_salary, o.fixed_salary_day, o.fixed_salary_night, o.percent_rate, o.guaranteed_salary
                FROM operator_daily_sales ods
                JOIN operators o ON o.id = ods.operator_id
                WHERE ods.sale_date = :d
                ORDER BY o.name, ods.shift
            ');
            $stmt->execute(['d' => $date]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            // If schema is old (no shift/guaranteed), show empty + error
            if ($error === null) {
                $error = $this->i18n->t('operator_sales.err.schema', 'Baza yangilanmagan: smena/zarplata ustunlarini qo‘shing (schema.sql).');
            }
            $entries = [];
        }

        $this->render('pages/operator_sales', [
            'date' => $date,
            'operators' => $operators,
            'entries' => $entries,
            'error' => $error,
            'message' => $message,
        ]);
    }
}

