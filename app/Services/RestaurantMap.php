<?php
declare(strict_types=1);

namespace App\Services;

use App\Settings;

final class RestaurantMap
{
    public function __construct(private Settings $settings)
    {
    }

    /**
     * @return array<string,string> id => name
     */
    public function getIdToName(): array
    {
        $raw = (string)$this->settings->get('restaurants.map', '');
        $out = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $parts = explode('|', $line, 2);
            if (count($parts) !== 2) continue;
            $id = trim($parts[0]);
            $name = trim($parts[1]);
            if ($id !== '' && $name !== '') {
                $out[$id] = $name;
            }
        }
        return $out;
    }

    public function label(int $id): string
    {
        $map = $this->getIdToName();
        return $map[(string)$id] ?? ('Restaurant ' . $id);
    }
}

