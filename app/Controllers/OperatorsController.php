<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use PDO;

final class OperatorsController extends BaseController
{
    public function index(): void
    {
        // both roles can view/operators; only admin can edit operator master data
        $action = $_GET['action'] ?? 'list';

        if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->auth->requireRole('admin');
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            // In UI we use day/night fixed. Keep fixed_salary as legacy fallback.
            $fixedDay = (float)($_POST['fixed_salary_day'] ?? 0);
            $fixedNight = (float)($_POST['fixed_salary_night'] ?? 0);
            $fixedLegacy = (float)($_POST['fixed_salary'] ?? 0);
            $fixed = $fixedLegacy;
            if ($fixed <= 0) {
                $fixed = ($fixedDay > 0) ? $fixedDay : (($fixedNight > 0) ? $fixedNight : 0.0);
            }
            if ($fixedDay <= 0) $fixedDay = $fixed;
            if ($fixedNight <= 0) $fixedNight = $fixed;
            $pct = (float)($_POST['percent_rate'] ?? 0);
            $guaranteed = (float)($_POST['guaranteed_salary'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($id > 0) {
                $stmt = $this->db->prepare('UPDATE operators SET name=:n, fixed_salary=:f, fixed_salary_day=:fd, fixed_salary_night=:fn, percent_rate=:p, guaranteed_salary=:g, is_active=:a WHERE id=:id');
                $stmt->execute(['n' => $name, 'f' => $fixed, 'fd' => $fixedDay, 'fn' => $fixedNight, 'p' => $pct, 'g' => $guaranteed, 'a' => $isActive, 'id' => $id]);
            } else {
                $stmt = $this->db->prepare('INSERT INTO operators (name, fixed_salary, fixed_salary_day, fixed_salary_night, percent_rate, guaranteed_salary, is_active) VALUES (:n,:f,:fd,:fn,:p,:g,:a)');
                $stmt->execute(['n' => $name, 'f' => $fixed, 'fd' => $fixedDay, 'fn' => $fixedNight, 'p' => $pct, 'g' => $guaranteed, 'a' => $isActive]);
            }
            Response::redirect('?page=operators');
            return;
        }

        $operators = $this->db->query('SELECT * FROM operators ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

        $this->render('pages/operators', [
            'operators' => $operators,
        ]);
    }
}

