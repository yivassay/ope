<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Settings;
use App\Services\RestaurantMap;
use App\Services\Telegram;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class BugungiController extends BaseController
{
    public function index(): void
    {
        $settings = new Settings($this->db);
        $rm = new RestaurantMap($settings);

        $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Tashkent');
        $date = (string)($_GET['date'] ?? $this->defaultUiDate($tz));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $this->defaultUiDate($tz);
        }

        $restaurantId = (int)($_GET['restaurant_id'] ?? 0); // 0=all
        $restaurants = $rm->getIdToName();
        if (!$restaurants) {
            $rows = $this->db->query('SELECT DISTINCT restaurant_id FROM smartomato_daily_stats ORDER BY restaurant_id')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $id = (int)$r['restaurant_id'];
                if ($id > 0) $restaurants[(string)$id] = 'Restaurant ' . $id;
            }
        }

        $commission = [
            'yandex' => (float)($settings->get('commission.yandex', '0') ?? 0),
            'wolt' => (float)($settings->get('commission.wolt', '0') ?? 0),
            'uzum' => (float)($settings->get('commission.uzum', '0') ?? 0),
        ];

        // Profit base: split Smartomato into non-aggregator delivery/pickup and aggregator gross.
        // IMPORTANT: channel values may be customized via settings (smartomato.channel_map),
        // so we classify channels with aliases instead of hardcoding channel="yandex".
        $yandexAliases = ['yandex', 'foodfox', 'yandex_eda', 'yandexeda', 'yandex_eda_mobile', 'yandexeda_mobile'];
        $woltAliases = ['wolt', 'wolt_mobile'];
        $normalize = static function (string $v): string {
            $v = strtolower(trim($v));
            $v = str_replace([' ', "\t", "\r", "\n"], '', $v);
            return $v;
        };

        // Totals for the day (all channels)
        $q = 'SELECT COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final FROM smartomato_daily_stats WHERE stat_date=:d';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'sum_final' => 0];

        // Fetch day split by channel + delivery_type, then classify.
        $q = '
            SELECT channel, delivery_type,
                   COALESCE(SUM(order_count),0) AS cnt,
                   COALESCE(SUM(sum_final),0) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date=:d
        ';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $q .= ' GROUP BY channel, delivery_type';
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deliveryNoAgg = ['cnt' => 0, 'sum_final' => 0];
        $pickupNoAgg = ['cnt' => 0, 'sum_final' => 0];
        $agg = ['yandex' => ['cnt' => 0, 'sum_final' => 0], 'wolt' => ['cnt' => 0, 'sum_final' => 0]];
        foreach ($rows as $r) {
            $ch = $normalize((string)($r['channel'] ?? ''));
            $dt = (string)($r['delivery_type'] ?? '');
            $cnt = (int)($r['cnt'] ?? 0);
            $sum = (float)($r['sum_final'] ?? 0);

            if (in_array($ch, array_map($normalize, $yandexAliases), true)) {
                $agg['yandex']['cnt'] += $cnt;
                $agg['yandex']['sum_final'] += $sum;
                continue;
            }
            if (in_array($ch, array_map($normalize, $woltAliases), true)) {
                $agg['wolt']['cnt'] += $cnt;
                $agg['wolt']['sum_final'] += $sum;
                continue;
            }

            // Non-aggregator: split by delivery_type
            if ($dt === 'delivery') {
                $deliveryNoAgg['cnt'] += $cnt;
                $deliveryNoAgg['sum_final'] += $sum;
            } elseif ($dt === 'pickup') {
                $pickupNoAgg['cnt'] += $cnt;
                $pickupNoAgg['sum_final'] += $sum;
            }
        }

        // Keep same shape as before (arrays from DB)
        $agg['yandex']['channel'] = 'yandex';
        $agg['wolt']['channel'] = 'wolt';
        $deliveryNoAgg['delivery_type'] = 'delivery';
        $pickupNoAgg['delivery_type'] = 'pickup';
        $uzum = ['cnt' => 0, 'sum_final' => 0];
        try {
            $stmt = $this->db->prepare('SELECT COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final FROM uzum_daily_stats WHERE stat_date=:d');
            $stmt->execute(['d' => $date]);
            $uzum = $stmt->fetch(PDO::FETCH_ASSOC) ?: $uzum;
        } catch (Throwable) {
            $uzum = ['cnt' => 0, 'sum_final' => 0];
        }

        // Expenses: salary for day (supports day/night shifts)
        $salarySum = 0.0;
        $salaryRows = [];
        try {
            // Prefer new schema with shift + fixed_day/night + guaranteed
            $stmt = $this->db->prepare('
                SELECT o.id AS operator_id, o.name, o.fixed_salary, o.fixed_salary_day, o.fixed_salary_night, o.percent_rate, o.guaranteed_salary,
                       ods.shift, ods.role_mode, ods.order_count, ods.sales_sum, ods.manual_salary
                FROM operator_daily_sales ods
                JOIN operators o ON o.id = ods.operator_id
                WHERE ods.sale_date = :d
                ORDER BY o.name, ods.shift
            ');
            $stmt->execute(['d' => $date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $mode = (string)($r['role_mode'] ?? 'operator');
                $shift = (string)($r['shift'] ?? 'day');
                $fixedLegacy = (float)($r['fixed_salary'] ?? 0);
                $fixedDay = (float)($r['fixed_salary_day'] ?? 0);
                $fixedNight = (float)($r['fixed_salary_night'] ?? 0);
                if ($fixedDay <= 0) $fixedDay = $fixedLegacy;
                if ($fixedNight <= 0) $fixedNight = $fixedLegacy;
                $fixed = ($shift === 'night') ? $fixedNight : $fixedDay;
                $pct = (float)($r['percent_rate'] ?? 0);
                $guaranteed = (float)($r['guaranteed_salary'] ?? 0);
                $salesSum = (float)($r['sales_sum'] ?? 0);
                $manualSalary = (float)($r['manual_salary'] ?? 0);
                $base = $fixed + ($salesSum * $pct / 100.0);
                $salaryValue = ($mode === 'logistic') ? $manualSalary : max($base, $guaranteed);
                $r['salary_value'] = $salaryValue;
                $salaryRows[] = $r;
                $salarySum += $salaryValue;
            }
        } catch (Throwable) {
            // Fallback for old schema (no shift / no guaranteed / no fixed_day/night)
            try {
                $stmt = $this->db->prepare('
                    SELECT o.name, ods.role_mode, ods.order_count, ods.sales_sum, ods.manual_salary,
                           CASE
                             WHEN ods.role_mode="logistic" THEN ods.manual_salary
                             ELSE (o.fixed_salary + (ods.sales_sum * o.percent_rate / 100.0))
                           END AS salary_value
                    FROM operator_daily_sales ods
                    JOIN operators o ON o.id = ods.operator_id
                    WHERE ods.sale_date = :d
                    ORDER BY o.name
                ');
                $stmt->execute(['d' => $date]);
                $salaryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($salaryRows as $r) {
                    $salarySum += (float)($r['salary_value'] ?? 0);
                }
            } catch (Throwable) {
                $salarySum = 0.0;
                $salaryRows = [];
            }
        }

        // Taxi yandex sums (optionally filter by restaurantName via mapping)
        $taxi = [
            'sum_total' => 0.0,
            'sum_waiting' => 0.0,
            'paid_cancel_count' => 0,
            'paid_cancel_sum' => 0.0,
            'roundtrip_count' => 0,
            'duplicate_3h_count' => 0,
            'duplicate_3h_sum_total' => 0.0,
            'returned_count' => 0,
            'returned_sum' => 0.0,
        ];
        try {
            if ($restaurantId > 0) {
                $rName = $restaurants[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId);
                $stmt = $this->db->prepare('
                    SELECT COALESCE(SUM(sum_total + paid_cancel_sum + returned_sum),0) AS sum_total,
                           COALESCE(SUM(sum_waiting),0) AS sum_waiting,
                           COALESCE(SUM(paid_cancel_count),0) AS paid_cancel_count,
                           COALESCE(SUM(paid_cancel_sum),0) AS paid_cancel_sum,
                           COALESCE(SUM(roundtrip_count),0) AS roundtrip_count,
                           COALESCE(SUM(duplicate_3h_count),0) AS duplicate_3h_count,
                           COALESCE(SUM(duplicate_3h_sum_total),0) AS duplicate_3h_sum_total,
                           COALESCE(SUM(returned_count),0) AS returned_count,
                           COALESCE(SUM(returned_sum),0) AS returned_sum
                    FROM taxi_daily_stats
                    WHERE stat_date=:d AND restaurant_name=:rn
                ');
                $stmt->execute(['d' => $date, 'rn' => $rName]);
                $taxi = $stmt->fetch(PDO::FETCH_ASSOC) ?: $taxi;
            } else {
                $stmt = $this->db->prepare('
                    SELECT COALESCE(SUM(sum_total + paid_cancel_sum + returned_sum),0) AS sum_total,
                           COALESCE(SUM(sum_waiting),0) AS sum_waiting,
                           COALESCE(SUM(paid_cancel_count),0) AS paid_cancel_count,
                           COALESCE(SUM(paid_cancel_sum),0) AS paid_cancel_sum,
                           COALESCE(SUM(roundtrip_count),0) AS roundtrip_count,
                           COALESCE(SUM(duplicate_3h_count),0) AS duplicate_3h_count,
                           COALESCE(SUM(duplicate_3h_sum_total),0) AS duplicate_3h_sum_total,
                           COALESCE(SUM(returned_count),0) AS returned_count,
                           COALESCE(SUM(returned_sum),0) AS returned_sum
                    FROM taxi_daily_stats
                    WHERE stat_date=:d
                ');
                $stmt->execute(['d' => $date]);
                $taxi = $stmt->fetch(PDO::FETCH_ASSOC) ?: $taxi;
            }
        } catch (Throwable) {
            // ignore
        }

        // Waiting by restaurant (for "top waiting" insight)
        $waitingTop = ['restaurant_name' => '', 'sum_waiting' => 0.0];
        $waitingByRestaurant = [];
        try {
            if ($restaurantId > 0) {
                $rName = $restaurants[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId);
                $waitingByRestaurant = [['restaurant_name' => $rName, 'sum_waiting' => (float)($taxi['sum_waiting'] ?? 0)]];
                $waitingTop = $waitingByRestaurant[0];
            } else {
                $stmt = $this->db->prepare('
                    SELECT restaurant_name, COALESCE(SUM(sum_waiting),0) AS sum_waiting
                    FROM taxi_daily_stats
                    WHERE stat_date=:d
                    GROUP BY restaurant_name
                    ORDER BY sum_waiting DESC
                ');
                $stmt->execute(['d' => $date]);
                $waitingByRestaurant = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($waitingByRestaurant) {
                    $waitingTop = [
                        'restaurant_name' => (string)($waitingByRestaurant[0]['restaurant_name'] ?? ''),
                        'sum_waiting' => (float)($waitingByRestaurant[0]['sum_waiting'] ?? 0),
                    ];
                }
            }
        } catch (Throwable) {
            $waitingTop = ['restaurant_name' => '', 'sum_waiting' => 0.0];
            $waitingByRestaurant = [];
        }

        // Roundtrip sum (not stored in taxi_daily_stats)
        $taxiRoundtrip = ['cnt' => 0, 'sum_total' => 0.0];
        try {
            $q = 'SELECT COALESCE(COUNT(*),0) AS cnt, COALESCE(SUM(sum_total),0) AS sum_total FROM taxi_trips WHERE trip_date=:d AND is_roundtrip=1';
            $params = ['d' => $date];
            if ($restaurantId > 0) {
                $q .= ' AND restaurant_name=:rn';
                $params['rn'] = $restaurants[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId);
            }
            $stmt = $this->db->prepare($q);
            $stmt->execute($params);
            $taxiRoundtrip = $stmt->fetch(PDO::FETCH_ASSOC) ?: $taxiRoundtrip;
        } catch (Throwable) {
            $taxiRoundtrip = ['cnt' => 0, 'sum_total' => 0.0];
        }

        // Millennium taxi
        $millSum = 0.0;
        try {
            if ($restaurantId > 0) {
                $rName = $restaurants[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId);
                $stmt = $this->db->prepare('SELECT COALESCE(SUM(sum_total),0) AS s FROM millennium_taxi_daily_stats WHERE stat_date=:d AND restaurant_name=:rn');
                $stmt->execute(['d' => $date, 'rn' => $rName]);
            } else {
                $stmt = $this->db->prepare('SELECT COALESCE(SUM(sum_total),0) AS s FROM millennium_taxi_daily_stats WHERE stat_date=:d');
                $stmt->execute(['d' => $date]);
            }
            $millSum = (float)($stmt->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);
        } catch (Throwable) {
            $millSum = 0.0;
        }

        // Our couriers (manual)
        $courierSum = 0.0;
        try {
            if ($restaurantId > 0) {
                $rName = $restaurants[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId);
                $stmt = $this->db->prepare('SELECT COALESCE(SUM(sum_total),0) AS s FROM courier_daily_stats WHERE stat_date=:d AND restaurant_name=:rn');
                $stmt->execute(['d' => $date, 'rn' => $rName]);
            } else {
                $stmt = $this->db->prepare('SELECT COALESCE(SUM(sum_total),0) AS s FROM courier_daily_stats WHERE stat_date=:d');
                $stmt->execute(['d' => $date]);
            }
            $courierSum = (float)($stmt->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);
        } catch (Throwable) {
            $courierSum = 0.0;
        }

        // Errors (day)
        $err = ['cnt' => 0, 'sum' => 0.0];
        try {
            $q = 'SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS sum FROM delivery_errors WHERE error_date=:d';
            $params = ['d' => $date];
            if ($restaurantId > 0) {
                $q .= ' AND target_type="restaurant" AND restaurant_id=:rid';
                $params['rid'] = $restaurantId;
            }
            $stmt = $this->db->prepare($q);
            $stmt->execute($params);
            $err = $stmt->fetch(PDO::FETCH_ASSOC) ?: $err;
        } catch (Throwable) {
            $err = ['cnt' => 0, 'sum' => 0.0];
        }

        // Orders by channel (day) with calls=board-telegram and plus telegram/uzum
        $q = '
            SELECT channel, COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date=:d
        ';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $q .= ' GROUP BY channel';
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $byChannel = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $byChannel[(string)$r['channel']] = $r;
        }
        $telegram = ['cnt' => 0, 'sum_final' => 0];
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final FROM telegram_daily_stats WHERE stat_date=:d');
        $stmt->execute(['d' => $date]);
        $telegram = $stmt->fetch(PDO::FETCH_ASSOC) ?: $telegram;
        $board = $byChannel['board'] ?? ['cnt' => 0, 'sum_final' => 0];
        $callsCnt = max(0, (int)$board['cnt'] - (int)$telegram['cnt']);
        $callsSum = max(0.0, (float)$board['sum_final'] - (float)$telegram['sum_final']);

        // Payment types (day)
        $q = '
            SELECT payment_source, COALESCE(SUM(order_count),0) AS cnt, COALESCE(SUM(sum_final),0) AS sum_final
            FROM smartomato_daily_stats
            WHERE stat_date=:d
        ';
        $params = ['d' => $date];
        if ($restaurantId > 0) {
            $q .= ' AND restaurant_id=:rid';
            $params['rid'] = $restaurantId;
        }
        $q .= ' GROUP BY payment_source ORDER BY cnt DESC';
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $byPayment = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Client paid delivery (if DB has delivery_client_sum column and Smartomato import fills it)
        $clientPaidDelivery = 0.0;
        $clientPaidDeliverySupported = true;
        try {
            $q = 'SELECT COALESCE(SUM(delivery_client_sum),0) AS s FROM smartomato_daily_stats WHERE stat_date=:d';
            $params = ['d' => $date];
            if ($restaurantId > 0) {
                $q .= ' AND restaurant_id=:rid';
                $params['rid'] = $restaurantId;
            }
            $stmt = $this->db->prepare($q);
            $stmt->execute($params);
            $clientPaidDelivery = (float)($stmt->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);
        } catch (Throwable $e) {
            // Most common: DB schema not updated => Unknown column delivery_client_sum
            $clientPaidDeliverySupported = false;
            $clientPaidDelivery = 0.0;
        }

        // Xisobotni yuborish (Telegram)
        $sendMessage = null;
        $sendError = null;
        if (($_GET['action'] ?? '') === 'send_report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // checks:
                // 1) Smartomato imported for date
                $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM smartomato_daily_stats WHERE stat_date=:d');
                $stmt->execute(['d' => $date]);
                $hasOrders = ((int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
                if (!$hasOrders) {
                    throw new \RuntimeException($this->i18n->t('bugungi.err.no_orders', 'Smartomato buyurtmalar yuklanmagan'));
                }

                // 2) Taxi imported for date
                $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM taxi_daily_stats WHERE stat_date=:d');
                $stmt->execute(['d' => $date]);
                $hasTaxi = ((int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
                if (!$hasTaxi) {
                    throw new \RuntimeException($this->i18n->t('bugungi.err.no_taxi', 'Yandex taxi yuklanmagan'));
                }

                // 3) Salaries at least 3 staff
                $stmt = $this->db->prepare('SELECT COUNT(DISTINCT operator_id) AS c FROM operator_daily_sales WHERE sale_date=:d');
                $stmt->execute(['d' => $date]);
                $staffCnt = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
                if ($staffCnt < 3) {
                    throw new \RuntimeException($this->i18n->t('bugungi.err.no_staff', 'Ishchilar ish haqi kam (kamida 3 ta kiriting)'));
                }

                $botToken = (string)$settings->get('telegram.bot_token', '');
                $chatId = (string)$settings->get('telegram.chat_id', '');

                // Profit: aggregator sums should be net (commission removed)
                $ySum = (float)($agg['yandex']['sum_final'] ?? 0);
                $wSum = (float)($agg['wolt']['sum_final'] ?? 0);
                $uSum = (float)($uzum['sum_final'] ?? 0);
                $yNet = $ySum * (1 - ((float)$commission['yandex'] / 100));
                $wNet = $wSum * (1 - ((float)$commission['wolt'] / 100));
                $uNet = $uSum * (1 - ((float)$commission['uzum'] / 100));

                $nonAggSum = (float)($deliveryNoAgg['sum_final'] ?? 0) + (float)($pickupNoAgg['sum_final'] ?? 0);
                $profitTotal = max(0.0, $nonAggSum + $yNet + $wNet + $uNet);

                // Expenses: subtract what client paid for delivery
                $expensesTotal = (float)$salarySum
                    + (float)($taxi['sum_total'] ?? 0)
                    + (float)$millSum
                    + (float)$courierSum
                    + (float)($err['sum'] ?? 0)
                    - (float)$clientPaidDelivery;

                $expenseBase = (float)($deliveryNoAgg['sum_final'] ?? 0) + (float)($pickupNoAgg['sum_final'] ?? 0);
                $pctExpense = static function (float $v) use ($expenseBase): string {
                    if ($expenseBase <= 0) return '0%';
                    return number_format(($v / $expenseBase) * 100.0, 1, '.', '') . '%';
                };
                $money = static function (float $v): string {
                    return number_format($v, 2, '.', ' ');
                };

                $title = ($restaurantId > 0) ? (' ' . ($restaurants[(string)$restaurantId] ?? ('Restaurant ' . $restaurantId))) : '';
                $netProfit = $profitTotal - $expensesTotal;
                $taxiGross = (float)($taxi['sum_total'] ?? 0) + (float)$millSum;
                $taxiDiff = $taxiGross - (float)$clientPaidDelivery;

                $lines = [];
                $lines[] = "📞CALL CENTER MK {$date}{$title}";
                $lines[] = "";
                $lines[] = "<b>💰Foyda</b>";
                $grossTotal = (float)($total['sum_final'] ?? 0) + (float)($uzum['sum_final'] ?? 0);
                $lines[] = "Jami summa: <b>{$money($grossTotal)}</b>";
                $lines[] = "Foyda: <b>{$money($netProfit)}</b>";
                $lines[] = "Yandex Eda: {$money($yNet)}";
                $lines[] = "Uzum: {$money($uNet)}";
                $lines[] = "Wolt: {$money($wNet)}";
                $lines[] = "";
                $lines[] = "<b>💸Xarajatlar</b>";
                $lines[] = "Jami summa: <b>{$money($expensesTotal)}</b> ({$pctExpense($expensesTotal)})";
                $lines[] = "- Ish haqi: {$money((float)$salarySum)} ({$pctExpense((float)$salarySum)})";
                $lines[] = "- Yandex taxi: {$money((float)($taxi['sum_total'] ?? 0))} ({$pctExpense((float)($taxi['sum_total'] ?? 0))})";
                $lines[] = "- Millennium: {$money((float)$millSum)} ({$pctExpense((float)$millSum)})";
                $lines[] = "- Bizning kuryerlar: {$money((float)$courierSum)} ({$pctExpense((float)$courierSum)})";
                if ((int)($err['cnt'] ?? 0) > 0) {
                    $lines[] = "- Kosyaklar: " . (int)$err['cnt'] . " ta, {$money((float)$err['sum'])} ({$pctExpense((float)($err['sum'] ?? 0))})";
                }
                $lines[] = "- Opłatıl klient (−): {$money((float)$clientPaidDelivery)} ({$pctExpense((float)$clientPaidDelivery)})";
                $lines[] = "- Farq (Yandex+Millennium − Opłatıl klient): <b>{$money($taxiDiff)}</b> ({$pctExpense((float)$taxiDiff)})";

                $lines[] = "";
                $lines[] = "<b>🙂Ishchilar:</b>";
                foreach ($salaryRows as $r) {
                    $name = (string)$r['name'];
                    $shift = (string)($r['shift'] ?? '');
                    if ($shift === 'night') {
                        $name .= ' (tungi)';
                    } elseif ($shift === 'day') {
                        $name .= ' (kunduz)';
                    }
                    $mode = (string)$r['role_mode'];
                    $sal = (float)($r['salary_value'] ?? 0);
                    if ($mode === 'logistic') {
                        $lines[] = "{$name}: {$money($sal)} (logist)";
                    } else {
                        $oc = (int)($r['order_count'] ?? 0);
                        $lines[] = "{$name}: {$money($sal)} ({$oc} ta)";
                    }
                }

                $lines[] = "";
                $lines[] = "<b>ℹ️E'tibor bering:</b>";
                $lines[] = "Płatnoe ojidanie (jami): {$money((float)($taxi['sum_waiting'] ?? 0))}";
                if (!empty($waitingTop['restaurant_name']) && (float)($waitingTop['sum_waiting'] ?? 0) > 0) {
                    $lines[] = "Top ojidanie: {$waitingTop['restaurant_name']} — {$money((float)$waitingTop['sum_waiting'])}";
                }
                $topList = array_slice($waitingByRestaurant, 0, 3);
                if ($restaurantId === 0 && $topList) {
                    $lines[] = "Ojidanie (top 3):";
                    foreach ($topList as $wr) {
                        $rn = (string)($wr['restaurant_name'] ?? '');
                        $sv = (float)($wr['sum_waiting'] ?? 0);
                        if ($rn !== '' && $sv > 0) {
                            $lines[] = "- {$rn}: {$money($sv)}";
                        }
                    }
                }

                if ((int)($taxi['paid_cancel_count'] ?? 0) > 0) {
                    $lines[] = "Płatnaya otmena: " . (int)$taxi['paid_cancel_count'] . " ta, {$money((float)($taxi['paid_cancel_sum'] ?? 0))}";
                }
                if ((int)($taxi['returned_count'] ?? 0) > 0) {
                    $lines[] = "Vozvrashena: " . (int)$taxi['returned_count'] . " ta, {$money((float)($taxi['returned_sum'] ?? 0))}";
                }
                if ((int)($taxi['duplicate_3h_count'] ?? 0) > 0) {
                    $lines[] = "Otpravleno dva raza: " . (int)$taxi['duplicate_3h_count'] . " ta, {$money((float)($taxi['duplicate_3h_sum_total'] ?? 0))}";
                }
                if ((int)($taxiRoundtrip['cnt'] ?? 0) > 0) {
                    $lines[] = "Tuda-obratno: " . (int)$taxiRoundtrip['cnt'] . " ta, {$money((float)($taxiRoundtrip['sum_total'] ?? 0))}";
                }

                $text = implode("\n", $lines);
                (new Telegram($botToken))->sendMessage($chatId, $text);
                $sendMessage = $this->i18n->t('bugungi.sent', 'Telegramga yuborildi');
            } catch (Throwable $e) {
                $sendError = $e->getMessage();
            }
        }

        $this->render('pages/bugungi', [
            'date' => $date,
            'restaurantId' => $restaurantId,
            'restaurants' => $restaurants,
            'commission' => $commission,
            'total' => $total,
            'deliveryNoAgg' => $deliveryNoAgg,
            'pickupNoAgg' => $pickupNoAgg,
            'agg' => $agg,
            'uzum' => $uzum,
            'salarySum' => $salarySum,
            'salaryRows' => $salaryRows,
            'taxi' => $taxi,
            'waitingTop' => $waitingTop,
            'waitingByRestaurant' => $waitingByRestaurant,
            'taxiRoundtrip' => $taxiRoundtrip,
            'millSum' => $millSum,
            'courierSum' => $courierSum,
            'err' => $err,
            'byChannel' => $byChannel,
            'telegram' => $telegram,
            'callsCnt' => $callsCnt,
            'callsSum' => $callsSum,
            'byPayment' => $byPayment,
            'clientPaidDelivery' => $clientPaidDelivery,
            'clientPaidDeliverySupported' => $clientPaidDeliverySupported,
            'sendMessage' => $sendMessage,
            'sendError' => $sendError,
        ]);
    }
}

