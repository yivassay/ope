<?php
declare(strict_types=1);

namespace App\Controllers;

final class SmartomatoController extends BaseController
{
    public function index(): void
    {
        $this->render('pages/smartomato', []);
    }
}

