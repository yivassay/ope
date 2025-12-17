<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $settings = new Settings($this->db);

        $this->render('pages/dashboard', [
            'smartomatoConfigured' => (bool)$settings->get('smartomato.login') && (bool)$settings->get('smartomato.password'),
        ]);
    }
}

