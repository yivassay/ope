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

            // Aggregator commissions (%)
            $settings->set('commission.yandex', trim((string)($_POST['commission_yandex'] ?? '0')));
            $settings->set('commission.wolt', trim((string)($_POST['commission_wolt'] ?? '0')));
            $settings->set('commission.uzum', trim((string)($_POST['commission_uzum'] ?? '0')));

            // Taxi: restaurant keywords mapping
            $settings->set('taxi.restaurant_keywords', trim((string)($_POST['taxi_restaurant_keywords'] ?? '')));

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
                'commission_yandex' => $settings->get('commission.yandex', '0'),
                'commission_wolt' => $settings->get('commission.wolt', '0'),
                'commission_uzum' => $settings->get('commission.uzum', '0'),
                'taxi_restaurant_keywords' => $settings->get('taxi.restaurant_keywords', ''),
            ],
        ]);
    }
}

