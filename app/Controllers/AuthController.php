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
                // Optional alert bot: login notification
                try {
                    $settings = new \App\Settings($this->db);
                    $token = trim((string)($settings->get('telegram.alert_bot_token', '') ?? ''));
                    $chatId = trim((string)($settings->get('telegram.alert_chat_id', '') ?? ''));
                    if ($token !== '' && $chatId !== '') {
                        $tz = new \DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
                        $now = (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
                        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                        $text = "<b>👤 Login</b>\nTime: {$now}\nIP: <code>" . htmlspecialchars((string)$ip) . "</code>\nUser: <b>" . htmlspecialchars($username) . "</b>";
                        (new \App\Services\Telegram($token))->sendMessage($chatId, $text);
                    }
                } catch (\Throwable) {
                    // ignore
                }
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

