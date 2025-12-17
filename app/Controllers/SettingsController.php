<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;

final class SettingsController extends BaseController
{
    public function index(): void
    {
        $this->auth->requireRole('admin');

        $settings = new Settings($this->db);

        $saved = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings->set('smartomato.base_url', trim((string)($_POST['smartomato_base_url'] ?? 'https://smartomato.ru')));
            $settings->set('smartomato.login', trim((string)($_POST['smartomato_login'] ?? '')));
            $settings->set('smartomato.password', (string)($_POST['smartomato_password'] ?? ''));
            $settings->set('smartomato.delivered_status', trim((string)($_POST['smartomato_delivered_status'] ?? 'complete')));
            $settings->set('smartomato.per_page', trim((string)($_POST['smartomato_per_page'] ?? '100')));

            // Channel mapping: simple CSV "source=channel"
            $settings->set('smartomato.channel_map', trim((string)($_POST['smartomato_channel_map'] ?? '')));

            $saved = true;
        }

        $this->render('pages/settings', [
            'saved' => $saved,
            'smartomato' => [
                'base_url' => $settings->get('smartomato.base_url', 'https://smartomato.ru'),
                'login' => $settings->get('smartomato.login', ''),
                'password' => $settings->get('smartomato.password', ''),
                'delivered_status' => $settings->get('smartomato.delivered_status', 'complete'),
                'per_page' => $settings->get('smartomato.per_page', '100'),
                'channel_map' => $settings->get('smartomato.channel_map', ''),
            ],
        ]);
    }
}

