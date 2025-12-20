<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\I18n;
use App\View;
use PDO;

abstract class BaseController
{
    protected I18n $i18n;

    public function __construct(protected PDO $db, protected Auth $auth)
    {
        $locale = $_SESSION['locale'] ?? 'uz';
        if (!in_array($locale, ['uz', 'ru'], true)) {
            $locale = 'uz';
        }
        $this->i18n = new I18n($locale);
    }

    protected function render(string $template, array $data = []): void
    {
        View::render($template, $data + [
            'auth' => $this->auth,
            't' => $this->i18n,
        ]);
    }
}

