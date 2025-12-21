<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CsvReader
{
    /**
     * Reads CSV into rows (array of string columns).
     * Auto-detects delimiter (tab / ';' / ',') and converts to UTF-8 if needed.
     * Supports UTF-16LE/BE (common for some Yandex exports), CP1251, UTF-8.
     *
     * @return array<int, array<int, string>>
     */
    public function read(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Cannot read CSV');
        }

        // Normalize encoding to UTF-8
        $raw = $this->toUtf8($raw);

        // Split lines (keep non-empty)
        $lines = preg_split("/\\r\\n|\\n|\\r/", $raw);
        if (!is_array($lines)) {
            throw new RuntimeException('CSV parse error');
        }
        $lines = array_values(array_filter($lines, static fn($l) => trim((string)$l) !== ''));
        if (!$lines) {
            throw new RuntimeException('CSV is empty');
        }

        $delimiter = $this->detectDelimiter($lines[0]);

        $rows = [];
        foreach ($lines as $line) {
            // Use PHP CSV parser on a single line
            $row = str_getcsv($line, $delimiter, '"', "\\");
            // Trim BOM from first cell if present
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string)$row[0]) ?? (string)$row[0];
            }
            $rows[] = array_map(static fn($v) => is_string($v) ? trim($v) : '', $row);
        }

        return $rows;
    }

    private function detectDelimiter(string $headerLine): string
    {
        $tab = substr_count($headerLine, "\t");
        $comma = substr_count($headerLine, ',');
        $semi = substr_count($headerLine, ';');
        if ($tab >= $comma && $tab >= $semi) {
            return "\t";
        }
        return ($semi > $comma) ? ';' : ',';
    }

    private function toUtf8(string $raw): string
    {
        // BOM-based detection first
        if (str_starts_with($raw, "\xFF\xFE")) {
            $converted = @iconv('UTF-16LE', 'UTF-8//IGNORE', $raw);
            return is_string($converted) ? $converted : $raw;
        }
        if (str_starts_with($raw, "\xFE\xFF")) {
            $converted = @iconv('UTF-16BE', 'UTF-8//IGNORE', $raw);
            return is_string($converted) ? $converted : $raw;
        }

        // Heuristic: many NUL bytes often means UTF-16LE without BOM
        if (substr_count(substr($raw, 0, 2000), "\x00") > 20) {
            $converted = @iconv('UTF-16LE', 'UTF-8//IGNORE', $raw);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        // If already valid UTF-8, keep.
        if (preg_match('//u', $raw)) {
            return $raw;
        }
        // Try CP1251 -> UTF-8 (common for RU exports)
        $converted = @iconv('CP1251', 'UTF-8//IGNORE', $raw);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
        // Fallback: ISO-8859-1
        $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $raw);
        return is_string($converted) ? $converted : $raw;
    }
}

