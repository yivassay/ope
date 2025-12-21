<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;
use SimpleXMLElement;

/**
 * Minimal XLSX reader (first sheet) without composer.
 * Supports shared strings and inline numbers, and preserves empty cells using cell references.
 */
final class XlsxSheetReader
{
    /**
     * @return array<int, array<int, string>> rows (0-based), cols (0-based)
     */
    public function readFirstSheet(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Cannot open XLSX (zip)');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('sheet1.xml not found');
        }
        $zip->close();

        $xml = @simplexml_load_string($sheetXml);
        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Invalid sheet XML');
        }

        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = $xml->xpath('//m:sheetData/m:row') ?: [];

        // Determine max column count from header row by checking cell refs.
        $maxCol = 0;
        if (isset($rows[0])) {
            $cells = $rows[0]->xpath('m:c') ?: [];
            foreach ($cells as $c) {
                $ref = (string)$c['r'];
                $col = $this->colIndexFromRef($ref);
                $maxCol = max($maxCol, $col);
            }
        }
        $colCount = max(1, $maxCol + 1);

        $out = [];
        foreach ($rows as $r) {
            $rowArr = array_fill(0, $colCount, '');
            $cells = $r->xpath('m:c') ?: [];
            foreach ($cells as $c) {
                $ref = (string)$c['r'];
                $col = $this->colIndexFromRef($ref);
                if ($col < 0 || $col >= $colCount) {
                    continue;
                }
                $t = (string)($c['t'] ?? '');
                $v = $c->xpath('m:v');
                $val = isset($v[0]) ? (string)$v[0] : '';
                if ($t === 's') {
                    $idx = (int)$val;
                    $rowArr[$col] = $sharedStrings[$idx] ?? '';
                } else {
                    $rowArr[$col] = $val;
                }
            }
            $out[] = $rowArr;
        }
        return $out;
    }

    /**
     * @return array<int,string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $sx = @simplexml_load_string($xml);
        if (!$sx instanceof SimpleXMLElement) {
            return [];
        }
        $sx->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sis = $sx->xpath('//m:si') ?: [];

        $out = [];
        foreach ($sis as $si) {
            // shared string can be multiple <t> nodes
            $texts = $si->xpath('.//m:t') ?: [];
            $s = '';
            foreach ($texts as $t) {
                $s .= (string)$t;
            }
            $out[] = $s;
        }
        return $out;
    }

    private function colIndexFromRef(string $ref): int
    {
        // e.g. "AB12" -> 27 (0-based)
        $letters = '';
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $ch = $ref[$i];
            if ($ch >= 'A' && $ch <= 'Z') {
                $letters .= $ch;
            } elseif ($ch >= 'a' && $ch <= 'z') {
                $letters .= strtoupper($ch);
            } else {
                break;
            }
        }
        if ($letters === '') return -1;
        $n = 0;
        for ($i = 0, $l = strlen($letters); $i < $l; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $n - 1;
    }
}

