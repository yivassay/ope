<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;

final class ProfileController extends BaseController
{
    public function index(): void
    {
        $error = null;
        $saved = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $current = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $new2 = (string)($_POST['new_password2'] ?? '');

            if ($new === '' || $new !== $new2) {
                $error = 'Yangi parol mos emas';
            } elseif (strlen($new) < 6) {
                $error = 'Yangi parol kamida 6 ta belgidan iborat bo‘lsin';
            } else {
                // verify current password
                $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => (int)$this->auth->id()]);
                $row = $stmt->fetch();
                if (!$row || !password_verify($current, (string)$row['password_hash'])) {
                    $error = 'Joriy parol noto‘g‘ri';
                } else {
                    $hash = password_hash($new, PASSWORD_DEFAULT);
                    $upd = $this->db->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
                    $upd->execute(['h' => $hash, 'id' => (int)$this->auth->id()]);
                    $saved = true;
                }
            }
        }

        $this->render('pages/profile', [
            'error' => $error,
            'saved' => $saved,
        ]);
    }
}

