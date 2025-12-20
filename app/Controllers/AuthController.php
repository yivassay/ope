<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;

final class AuthController extends BaseController
{
    public function login(): void
    {
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if ($this->auth->attempt($username, $password)) {
                if (($this->auth->role() ?? '') === 'callcenter_manager') {
                    Response::redirect('?page=bugungi');
                    return;
                }
                Response::redirect('?page=dashboard');
                return;
            }
            $error = $this->i18n->t('login.error');
        }

        $this->render('pages/login', [
            'error' => $error,
        ]);
    }

    public function logout(): void
    {
        $this->auth->logout();
        Response::redirect('?page=login');
    }
}

