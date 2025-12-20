<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use App\Settings;
use App\Services\CsvReader;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class TaxiController extends BaseController
{
    public function index(): void
    {
        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        $date = (string)($_GET['date'] ?? (new DateTimeImmutable('now', $tz))->format('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        }

        $settings = new Settings($this->db);
        $mappingRaw = (string)$settings->get('taxi.restaurant_keywords', '');
        $restaurantKeywords = $this->parseRestaurantKeywords($mappingRaw);
        $restaurantOptions = $this->restaurantOptionsFromKeywords($restaurantKeywords);

        $message = null;
        $error = null;

        if (($_GET['action'] ?? '') === 'millennium_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $restaurant = trim((string)($_POST['restaurant_name'] ?? ''));
            $tripsCount = (int)($_POST['trips_count'] ?? 0);
            $sumTotal = $this->parseMoney((string)($_POST['sum_total'] ?? '0'));
            $note = trim((string)($_POST['note'] ?? ''));

            if ($restaurant === '') {
                $error = $this->i18n->t('taxi.err.no_restaurant', 'Restaurant tanlanmagan');
            } else {
                try {
                    $stmt = $this->db->prepare('INSERT INTO millennium_taxi_daily_stats (stat_date, restaurant_name, trips_count, sum_total, note, updated_by_user_id, updated_at)
                        VALUES (:d,:rn,:c,:s,:n,:u,NOW())
                        ON DUPLICATE KEY UPDATE trips_count=VALUES(trips_count), sum_total=VALUES(sum_total), note=VALUES(note), updated_by_user_id=VALUES(updated_by_user_id), updated_at=NOW()');
                    $stmt->execute([
                        'd' => $date,
                        'rn' => $restaurant,
                        'c' => $tripsCount,
                        's' => $sumTotal,
                        'n' => $note,
                        'u' => (int)($this->auth->id() ?? 0),
                    ]);
                    Response::redirect('?page=taxi&date=' . urlencode($date));
                    return;
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        if (($_GET['action'] ?? '') === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
                $error = $this->i18n->t('taxi.err.no_file', 'Fayl topilmadi');
            } else {
                try {
                    $tmp = $_FILES['file']['tmp_name'] ?? '';
                    $name = (string)($_FILES['file']['name'] ?? 'taxi.csv');
                    if ($tmp === '' || !is_uploaded_file($tmp)) {
                        throw new RuntimeException($this->i18n->t('taxi.err.upload_failed', 'Fayl yuklanmadi'));
                    }

                    $reader = new CsvReader();
                    $rows = $reader->read($tmp);
                    if (count($rows) < 2) {
                        throw new RuntimeException($this->i18n->t('taxi.err.bad_csv', 'CSV bo‘sh yoki format noto‘g‘ri'));
                    }

                    $header = $rows[0];
                    $idx = $this->headerIndex($header);

                    $trips = [];
                    $unknown = 0;
                    $datesInFile = [];
                    $statusCounts = [];
                    $dateParseFailed = 0;

                    foreach (array_slice($rows, 1) as $r) {
                        $rowDate = $this->excelDateToYmd($r[$idx['date_order']] ?? '', $tz);
                        if ($rowDate === null) {
                            $dateParseFailed++;
                            continue;
                        }
                        $datesInFile[$rowDate] = ($datesInFile[$rowDate] ?? 0) + 1;

                        $status = trim((string)($r[$idx['status_order']] ?? ''));
                        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

                        $paidCancel = $this->parseMoney((string)($r[$idx['paid_cancel']] ?? '0'));
                        $isSuccess = $this->isSuccessfulStatus($status);
                        $isReturned = $this->isReturnedStatus($status);
                        $isPaidCancel = !$isSuccess && $paidCancel > 0;
                        if (!$isSuccess && !$isPaidCancel && !$isReturned) {
                            continue; // we store only: successful trips + paid cancellations + returned
                        }

                        $time = $this->excelTimeToHms($r[$idx['time_order']] ?? '');
                        $dt = new DateTimeImmutable($rowDate . ' ' . ($time ?: '00:00:00'), $tz);

                        $sender = trim((string)($r[$idx['sender_address']] ?? ''));
                        $receiver = trim((string)($r[$idx['receiver_address']] ?? ''));

                        $restaurant = $this->matchRestaurant($sender, $restaurantKeywords) ?? 'Unknown';
                        if ($restaurant === 'Unknown') {
                            $unknown++;
                        }

                        $sumTotal = $isSuccess ? $this->parseMoney((string)($r[$idx['sum_total']] ?? '0')) : 0.0;
                        $sumWaiting = $isSuccess ? $this->parseMoney((string)($r[$idx['sum_waiting']] ?? '0')) : 0.0;
                        $returnedSum = $isReturned ? $this->parseMoney((string)($r[$idx['sum_total']] ?? '0')) : 0.0;

                        $isRoundtrip = $isSuccess && $this->normalizeAddress($sender) !== '' && $this->normalizeAddress($sender) === $this->normalizeAddress($receiver);

                        $trips[] = [
                            'trip_date' => $rowDate,
                            'trip_time' => $dt->format('Y-m-d H:i:s'),
                            'application_id' => (string)($r[$idx['application_id']] ?? ''),
                            'tariff' => (string)($r[$idx['tariff']] ?? ''),
                            'delivery_variant' => (string)($r[$idx['delivery_variant']] ?? ''),
                            'status' => $status,
                            'order_source' => (string)($r[$idx['order_source']] ?? ''),
                            'city' => (string)($r[$idx['city']] ?? ''),
                            'sender_address' => $sender,
                            'receiver_address' => $receiver,
                            'restaurant_name' => $restaurant,
                            'sum_total' => $sumTotal,
                            'sum_waiting' => $sumWaiting,
                            'paid_cancel_sum' => $isPaidCancel ? $paidCancel : 0.0,
                            'is_paid_cancel' => $isPaidCancel ? 1 : 0,
                            'returned_sum' => $returnedSum,
                            'is_returned' => $isReturned ? 1 : 0,
                            'is_roundtrip' => $isRoundtrip ? 1 : 0,
                            'is_duplicate_3h' => 0, // set later
                        ];
                    }

                    // Duplicate detection: same receiver more than once within 3 hours (successful trips),
                    // calculated per-date (so multi-date files work).
                    $byDateIdx = [];
                    foreach ($trips as $i => $t) {
                        $byDateIdx[(string)$t['trip_date']][] = $i;
                    }
                    foreach ($byDateIdx as $d => $indexes) {
                        $byReceiver = [];
                        foreach ($indexes as $i) {
                            $t = $trips[$i];
                            if ((int)$t['is_paid_cancel'] === 1 || (int)$t['is_returned'] === 1) {
                                continue;
                            }
                            $key = $this->normalizeAddress((string)$t['receiver_address']);
                            if ($key === '') continue;
                            $byReceiver[$key][] = $i;
                        }
                        foreach ($byReceiver as $idxs) {
                            usort($idxs, function ($a, $b) use ($trips) {
                                return strcmp((string)$trips[$a]['trip_time'], (string)$trips[$b]['trip_time']);
                            });
                            $prev = null;
                            foreach ($idxs as $j) {
                                $cur = new DateTimeImmutable((string)$trips[$j]['trip_time'], $tz);
                                if ($prev !== null) {
                                    $diff = $cur->getTimestamp() - $prev->getTimestamp();
                                    if ($diff > 0 && $diff <= 3 * 3600) {
                                        $trips[$j]['is_duplicate_3h'] = 1; // mark the 2nd+ trip
                                    }
                                }
                                $prev = $cur;
                            }
                        }
                    }

                    // Re-import: delete old data for ALL dates present in the file to avoid confusion
                    $this->db->beginTransaction();
                    $datesToRefresh = array_keys($datesInFile);
                    foreach ($datesToRefresh as $d) {
                        $this->db->prepare('DELETE FROM taxi_trips WHERE trip_date = :d')->execute(['d' => $d]);
                        $this->db->prepare('DELETE FROM taxi_daily_stats WHERE stat_date = :d')->execute(['d' => $d]);
                        $this->db->prepare('INSERT INTO taxi_imports (import_date, original_filename, uploaded_by_user_id, status, message, created_at)
                            VALUES (:d,:f,:u,:s,:m,NOW())
                            ON DUPLICATE KEY UPDATE original_filename=VALUES(original_filename), uploaded_by_user_id=VALUES(uploaded_by_user_id), status=VALUES(status), message=VALUES(message), created_at=VALUES(created_at)')
                            ->execute(['d' => $d, 'f' => $name, 'u' => (int)($this->auth->id() ?? 0), 's' => 'ok', 'm' => 'refreshing']);
                    }

                    // Insert trips
                    $insTrip = $this->db->prepare('INSERT INTO taxi_trips
                        (trip_date, trip_time, application_id, tariff, delivery_variant, status, order_source, city, sender_address, receiver_address, restaurant_name, sum_total, sum_waiting, paid_cancel_sum, is_paid_cancel, returned_sum, is_returned, is_roundtrip, is_duplicate_3h, created_at)
                        VALUES (:d,:t,:id,:tar,:dv,:st,:src,:city,:sa,:ra,:rn,:sum,:wait,:pc,:ipc,:rs,:ir,:rt,:dup,NOW())');

                    foreach ($trips as $t) {
                        $insTrip->execute([
                            'd' => $t['trip_date'],
                            't' => $t['trip_time'],
                            'id' => $t['application_id'],
                            'tar' => $t['tariff'],
                            'dv' => $t['delivery_variant'],
                            'st' => $t['status'],
                            'src' => $t['order_source'],
                            'city' => $t['city'],
                            'sa' => $t['sender_address'],
                            'ra' => $t['receiver_address'],
                            'rn' => $t['restaurant_name'],
                            'sum' => $t['sum_total'],
                            'wait' => $t['sum_waiting'],
                            'pc' => $t['paid_cancel_sum'],
                            'ipc' => $t['is_paid_cancel'],
                            'rs' => $t['returned_sum'],
                            'ir' => $t['is_returned'],
                            'rt' => $t['is_roundtrip'],
                            'dup' => $t['is_duplicate_3h'],
                        ]);
                    }

                    // Aggregate per restaurant per date
                    $agg = []; // [date][restaurant] => stats
                    foreach ($trips as $t) {
                        $d = (string)$t['trip_date'];
                        $rn = (string)$t['restaurant_name'];
                        if (!isset($agg[$d])) $agg[$d] = [];
                        if (!isset($agg[$d][$rn])) {
                            $agg[$d][$rn] = [
                                'trips_count' => 0,
                                'sum_total' => 0.0,
                                'sum_waiting' => 0.0,
                                'paid_cancel_count' => 0,
                                'paid_cancel_sum' => 0.0,
                                'returned_count' => 0,
                                'returned_sum' => 0.0,
                                'roundtrip_count' => 0,
                                'duplicate_3h_count' => 0,
                                'duplicate_3h_sum_total' => 0.0,
                            ];
                        }
                        if ((int)$t['is_paid_cancel'] === 1) {
                            $agg[$d][$rn]['paid_cancel_count']++;
                            $agg[$d][$rn]['paid_cancel_sum'] += (float)$t['paid_cancel_sum'];
                        } elseif ((int)$t['is_returned'] === 1) {
                            $agg[$d][$rn]['returned_count']++;
                            $agg[$d][$rn]['returned_sum'] += (float)$t['returned_sum'];
                        } else {
                            $agg[$d][$rn]['trips_count']++;
                            $agg[$d][$rn]['sum_total'] += (float)$t['sum_total'];
                            $agg[$d][$rn]['sum_waiting'] += (float)$t['sum_waiting'];
                            $agg[$d][$rn]['roundtrip_count'] += (int)$t['is_roundtrip'];
                            if ((int)$t['is_duplicate_3h'] === 1) {
                                $agg[$d][$rn]['duplicate_3h_count']++;
                                $agg[$d][$rn]['duplicate_3h_sum_total'] += (float)$t['sum_total'];
                            }
                        }
                    }

                    $ins = $this->db->prepare('INSERT INTO taxi_daily_stats
                        (stat_date, restaurant_name, trips_count, sum_total, sum_waiting, paid_cancel_count, paid_cancel_sum, returned_count, returned_sum, roundtrip_count, duplicate_3h_count, duplicate_3h_sum_total, updated_at)
                        VALUES (:d,:rn,:c,:sum,:wait,:pcc,:pcs,:rc,:rs,:rt,:dc,:ds,NOW())
                        ON DUPLICATE KEY UPDATE trips_count=VALUES(trips_count), sum_total=VALUES(sum_total), sum_waiting=VALUES(sum_waiting),
                          paid_cancel_count=VALUES(paid_cancel_count), paid_cancel_sum=VALUES(paid_cancel_sum), returned_count=VALUES(returned_count), returned_sum=VALUES(returned_sum),
                          roundtrip_count=VALUES(roundtrip_count), duplicate_3h_count=VALUES(duplicate_3h_count), duplicate_3h_sum_total=VALUES(duplicate_3h_sum_total),
                          updated_at=NOW()');

                    $restaurantCountTotal = 0;
                    foreach ($agg as $d => $byR) {
                        $restaurantCountTotal += count($byR);
                        foreach ($byR as $rn => $a) {
                            $ins->execute([
                                'd' => $d,
                                'rn' => $rn,
                                'c' => (int)$a['trips_count'],
                                'sum' => (float)$a['sum_total'],
                                'wait' => (float)$a['sum_waiting'],
                                'pcc' => (int)$a['paid_cancel_count'],
                                'pcs' => (float)$a['paid_cancel_sum'],
                                'rc' => (int)$a['returned_count'],
                                'rs' => (float)$a['returned_sum'],
                                'rt' => (int)$a['roundtrip_count'],
                                'dc' => (int)$a['duplicate_3h_count'],
                                'ds' => (float)$a['duplicate_3h_sum_total'],
                            ]);
                        }
                    }

                    $msg = 'dates=' . count($datesInFile) . ', records=' . count($trips) . ', restaurants=' . $restaurantCountTotal . ', unknown=' . $unknown;
                    if ($dateParseFailed > 0) {
                        $msg .= '; date_parse_failed=' . $dateParseFailed;
                    }
                    // Write message per date
                    foreach (array_keys($datesInFile) as $d) {
                        $this->db->prepare('UPDATE taxi_imports SET status="ok", message=:m WHERE import_date=:d')->execute(['m' => $msg, 'd' => $d]);
                    }

                    $this->db->commit();

                    $message = "Yuklandi: {$msg}";
                } catch (Throwable $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    $error = $e->getMessage();
                }
            }
        }

        $stmt = $this->db->prepare('SELECT * FROM taxi_daily_stats WHERE stat_date = :d ORDER BY restaurant_name');
        $stmt->execute(['d' => $date]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $imp = $this->db->prepare('SELECT * FROM taxi_imports WHERE import_date = :d');
        $imp->execute(['d' => $date]);
        $import = $imp->fetch(PDO::FETCH_ASSOC) ?: null;

        $dups = $this->db->prepare('SELECT restaurant_name, COUNT(*) AS cnt, SUM(sum_total) AS sum_total
            FROM taxi_trips WHERE trip_date=:d AND is_duplicate_3h=1 GROUP BY restaurant_name ORDER BY cnt DESC');
        $dups->execute(['d' => $date]);
        $dupByRestaurant = $dups->fetchAll(PDO::FETCH_ASSOC);

        $mill = [];
        try {
            $stmt = $this->db->prepare('SELECT * FROM millennium_taxi_daily_stats WHERE stat_date = :d ORDER BY restaurant_name');
            $stmt->execute(['d' => $date]);
            $mill = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $mill = [];
        }

        $this->render('pages/taxi', [
            'date' => $date,
            'message' => $message,
            'error' => $error,
            'import' => $import,
            'stats' => $stats,
            'dupByRestaurant' => $dupByRestaurant,
            'mappingRaw' => $mappingRaw,
            'restaurantOptions' => $restaurantOptions,
            'millennium' => $mill,
        ]);
    }

    private function parseRestaurantKeywords(string $raw): array
    {
        // Format:
        // Restaurant name | keyword1, keyword2
        $out = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $parts = explode('|', $line, 2);
            if (count($parts) !== 2) continue;
            $name = trim($parts[0]);
            $keywords = array_filter(array_map('trim', explode(',', $parts[1])));
            foreach ($keywords as $kw) {
                $out[] = ['name' => $name, 'kw' => mb_strtolower($kw), 'len' => mb_strlen($kw)];
            }
        }
        // prefer longer keyword first
        usort($out, static fn($a, $b) => $b['len'] <=> $a['len']);
        return $out;
    }

    private function matchRestaurant(string $senderAddress, array $keywords): ?string
    {
        $a = mb_strtolower($senderAddress);
        foreach ($keywords as $k) {
            if ($k['kw'] !== '' && mb_strpos($a, (string)$k['kw']) !== false) {
                return (string)$k['name'];
            }
        }
        return null;
    }

    private function restaurantOptionsFromKeywords(array $keywords): array
    {
        $names = [];
        foreach ($keywords as $k) {
            $n = (string)($k['name'] ?? '');
            if ($n !== '') $names[$n] = true;
        }
        $out = array_keys($names);
        sort($out);
        return $out;
    }

    private function normalizeAddress(string $a): string
    {
        $a = mb_strtolower(trim($a));
        $a = preg_replace('/[\\s,\\.\\-\\(\\)\\[\\]\\/\\\\]+/u', ' ', $a) ?? $a;
        $a = trim($a);
        return $a;
    }

    private function isSuccessfulStatus(string $status): bool
    {
        $s = mb_strtolower(trim($status));
        return $s === 'доставлено' || $s === 'доставлен' || $s === 'заказ выполнен' || $s === 'выполнено';
    }

    private function isReturnedStatus(string $status): bool
    {
        $s = mb_strtolower(trim($status));
        return $s === 'возвращена' || $s === 'возвращено' || $s === 'возврат';
    }

    private function headerIndex(array $header): array
    {
        // CSV headers often differ: extra spaces, BOM, quotes, minor wording changes.
        // We match using normalized headers + aliases + "contains" fallback.
        $need = [
            'application_id' => ['ID заявки', 'ID', 'id заявки', 'id'],
            'tariff' => ['Тариф', 'tariff'],
            'delivery_variant' => ['Вариант доставки', 'вариант доставки'],
            'status_order' => ['Статус заказа', 'статус заказа', 'статус'],
            'order_source' => ['Источник заказа', 'источник заказа', 'source'],
            'date_order' => ['Дата заказа', 'дата заказа', 'Дата'],
            'time_order' => ['Время заказа', 'время заказа', 'Время'],
            'city' => ['Населённый пункт', 'населенный пункт', 'город'],
            'sender_address' => ['Адрес отправителя', 'адрес отправителя'],
            'receiver_address' => ['Адреса получателей', 'адреса получателей', 'адрес получателя', 'Адрес получателя'],
            'sum_total' => ['Фактическая стоимость заявки с НДС сум', 'Фактическая стоимость заявки с НДС', 'стоимость заявки с ндс'],
            'sum_waiting' => ['Стоимость платного ожидания итого  сум', 'Стоимость платного ожидания итого сум', 'стоимость платного ожидания'],
            'paid_cancel' => ['Платная отмена до прибытия курьера', 'платная отмена'],
        ];

        $idx = [];
        foreach ($need as $k => $candidates) {
            $idx[$k] = $this->findHeaderIndex($header, $candidates);
        }
        return $idx;
    }

    private function findHeaderIndex(array $header, array $candidates): int
    {
        $normHeader = [];
        foreach ($header as $i => $h) {
            $normHeader[$this->normHeader((string)$h)] = (int)$i;
        }

        // 1) exact normalized match against aliases
        foreach ($candidates as $cand) {
            $n = $this->normHeader((string)$cand);
            if ($n !== '' && array_key_exists($n, $normHeader)) {
                return (int)$normHeader[$n];
            }
        }

        // 2) "contains" match (first match wins), useful when export adds units or wording
        $headerList = array_map(static fn($h) => (string)$h, $header);
        $normList = array_map(fn($h) => $this->normHeader($h), $headerList);

        foreach ($candidates as $cand) {
            $n = $this->normHeader((string)$cand);
            if ($n === '') continue;
            $found = null;
            foreach ($normList as $i => $h) {
                if ($h !== '' && mb_strpos($h, $n) !== false) {
                    $found = $i;
                    break;
                }
            }
            if ($found !== null) {
                return (int)$found;
            }
        }

        $sample = array_slice($headerList, 0, 20);
        throw new RuntimeException(
            $this->i18n->t('taxi.err.column_not_found', 'Ustun topilmadi.') . ' ' .
            $this->i18n->t('taxi.err.searched', 'Qidirildi') . ': ' . implode(' / ', array_map('strval', $candidates)) .
            '. ' . $this->i18n->t('taxi.err.header_first', 'Header (first 20)') . ': ' . implode(' | ', $sample)
        );
    }

    private function normHeader(string $s): string
    {
        $s = preg_replace('/^\\xEF\\xBB\\xBF/', '', $s) ?? $s;
        $s = mb_strtolower($s);
        $s = trim($s);
        $s = preg_replace('/\\s+/u', ' ', $s) ?? $s;
        $s = str_replace(["\u{00A0}"], ' ', $s); // NBSP
        $s = trim($s);
        return $s;
    }

    private function excelDateToYmd(string $excelValue, DateTimeZone $tz): ?string
    {
        $v = trim($excelValue);
        if ($v === '' || $v === '-') return null;
        // CSV may contain already formatted dates like 2025-12-18 / 18.12.2025
        if (!preg_match('/^\\d+(\\.\\d+)?$/', $v)) {
            $try = strtotime($v);
            if ($try !== false) {
                return (new DateTimeImmutable('@' . $try))->setTimezone($tz)->format('Y-m-d');
            }
        }
        if (preg_match('/^\\d+(\\.\\d+)?$/', $v)) {
            // Excel serial date (days since 1899-12-30)
            $days = (int)floor((float)$v);
            $base = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
            $dt = $base->add(new DateInterval('P' . $days . 'D'))->setTimezone($tz);
            return $dt->format('Y-m-d');
        }
        return null;
    }

    private function excelTimeToHms(string $excelValue): ?string
    {
        $v = trim($excelValue);
        if ($v === '' || $v === '-') return null;
        if (preg_match('/^\\d+(\\.\\d+)?$/', $v)) {
            $f = (float)$v;
            // in Excel time is fractional day
            $seconds = (int)round($f * 86400);
            $seconds = $seconds % 86400;
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);
            $s = $seconds % 60;
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
        // "HH:MM:SS" or "HH:MM"
        if (preg_match('/^\\d{1,2}:\\d{2}(:\\d{2})?$/', $v)) {
            return strlen($v) === 5 ? ($v . ':00') : $v;
        }
        return null;
    }

    private function parseMoney(string $v): float
    {
        $v = trim($v);
        if ($v === '' || $v === '-') return 0.0;
        // remove spaces and currency artifacts
        $v = str_replace(["\xC2\xA0", ' '], '', $v); // NBSP and spaces
        // decimal comma -> dot
        $v = str_replace(',', '.', $v);
        return (float)$v;
    }
}

