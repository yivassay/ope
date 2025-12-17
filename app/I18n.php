<?php
declare(strict_types=1);

namespace App;

final class I18n
{
    private array $dict;

    public function __construct(string $locale = 'uz')
    {
        $path = __DIR__ . '/lang/' . $locale . '.php';
        $this->dict = is_file($path) ? require $path : [];
    }

    public function t(string $key, string $fallback = ''): string
    {
        return $this->dict[$key] ?? ($fallback !== '' ? $fallback : $key);
    }
}

