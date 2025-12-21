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

    /**
     * Parses money values from user input.
     * Supports "10 000 000", "10 000 000", "10000000", "10,5" (comma decimals).
     */
    protected function parseMoney(string $v): float
    {
        $v = trim($v);
        if ($v === '' || $v === '-') return 0.0;
        $v = str_replace(["\xC2\xA0", ' '], '', $v); // NBSP and spaces
        $v = str_replace(',', '.', $v);
        return (float)$v;
    }

    /**
     * Default UI date for forms.
     * Business rule: before 09:00 Tashkent time, default date is yesterday; otherwise today.
     */
    protected function defaultUiDate(\DateTimeZone $tz): string
    {
        $now = new \DateTimeImmutable('now', $tz);
        $cutoff = new \DateTimeImmutable($now->format('Y-m-d') . ' 09:00:00', $tz);
        if ($now < $cutoff) {
            return $now->modify('-1 day')->format('Y-m-d');
        }
        return $now->format('Y-m-d');
    }
}

