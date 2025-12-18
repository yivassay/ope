<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;
use App\Response;
use Throwable;

final class SettingsController extends BaseController
{
    public function index(): void
    {
        $this->auth->requireRole('admin');

        $settings = new Settings($this->db);

        $saved = false;
        $wiped = false;
        $wipeError = null;

        if (($_GET['action'] ?? '') === 'wipe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $confirm = trim((string)($_POST['confirm_text'] ?? ''));
            if ($confirm !== 'DELETE') {
                $wipeError = 'Tasdiqlash uchun DELETE deb yozing';
            } else {
                try {
                    $this->db->beginTransaction();
                    // Keep users/settings/operators. Wipe only analytics and imported data.
                    $tables = [
                        'smartomato_daily_stats',
                        'smartomato_runs',
                        'operator_daily_sales',
                        'telegram_daily_stats',
                        'uzum_daily_stats',
                        'taxi_imports',
                        'taxi_trips',
                        'taxi_daily_stats',
                        'millennium_taxi_daily_stats',
                    ];
                    foreach ($tables as $t) {
                        try {
                            $this->db->exec('DELETE FROM `' . $t . '`');
                        } catch (Throwable) {
                            // some installations may not have all optional tables yet
                        }
                    }
                    $this->db->commit();
                    $wiped = true;
                } catch (Throwable $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    $wipeError = $e->getMessage();
                }
            }
        }

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

            // Restaurants mapping for UI selections: id|name
            $settings->set('restaurants.map', trim((string)($_POST['restaurants_map'] ?? '')));

            // Telegram report settings
            $settings->set('telegram.bot_token', trim((string)($_POST['telegram_bot_token'] ?? '')));
            $settings->set('telegram.chat_id', trim((string)($_POST['telegram_chat_id'] ?? '')));

            $saved = true;
        }

        $this->render('pages/settings', [
            'saved' => $saved,
            'wiped' => $wiped,
            'wipeError' => $wipeError,
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
                'restaurants_map' => $settings->get('restaurants.map', ''),
                'telegram_bot_token' => $settings->get('telegram.bot_token', ''),
                'telegram_chat_id' => $settings->get('telegram.chat_id', ''),
            ],
        ]);
    }
}

