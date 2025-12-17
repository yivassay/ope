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
            $fixed = (float)($_POST['fixed_salary'] ?? 0);
            $pct = (float)($_POST['percent_rate'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($id > 0) {
                $stmt = $this->db->prepare('UPDATE operators SET name=:n, fixed_salary=:f, percent_rate=:p, is_active=:a WHERE id=:id');
                $stmt->execute(['n' => $name, 'f' => $fixed, 'p' => $pct, 'a' => $isActive, 'id' => $id]);
            } else {
                $stmt = $this->db->prepare('INSERT INTO operators (name, fixed_salary, percent_rate, is_active) VALUES (:n,:f,:p,:a)');
                $stmt->execute(['n' => $name, 'f' => $fixed, 'p' => $pct, 'a' => $isActive]);
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

