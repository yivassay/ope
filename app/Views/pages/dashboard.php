<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var bool $smartomatoConfigured */
/** @var array $byDay */
/** @var string $from */
/** @var string $to */
/** @var array $total */
/** @var array $byDeliveryType */
/** @var array $byRestaurant */
/** @var array $byPayment */
/** @var array $byChannel */
/** @var array $telegram */
/** @var array $uzum */
/** @var array $commission */
require __DIR__ . '/../partials/layout_top.php';

$labels = array_map(static fn($r) => $r['stat_date'], $byDay);
$orders = array_map(static fn($r) => (int)$r['cnt'], $byDay);
$sums = array_map(static fn($r) => (float)$r['sum_final'], $byDay);

$totalOrders = (int)($total['cnt'] ?? 0);
$totalSum = (float)($total['sum_final'] ?? 0);

$deliveryCnt = 0;
$pickupCnt = 0;
foreach ($byDeliveryType as $r) {
    if (($r['delivery_type'] ?? '') === 'delivery') $deliveryCnt = (int)$r['cnt'];
    if (($r['delivery_type'] ?? '') === 'pickup') $pickupCnt = (int)$r['cnt'];
}

function payment_label(string $ps): string {
    return match ($ps) {
        'cash' => 'Naqt pul',
        'card' => 'Karta',
        'external_service_card_online' => 'Agregator',
        default => $ps,
    };
}
function channel_label(string $ch): string {
    return match ($ch) {
        'web' => 'Web-sayt',
        'app' => 'Ilova',
        'yandex' => 'Yandex Eda',
        'wolt' => 'Wolt',
        'board' => "Qo'ng'iroqlar",
        'other' => 'Boshqa',
        default => $ch,
    };
}

// Channel breakdown with Telegram subtraction from board ("calls")
$channels = [];
foreach ($byChannel as $r) {
    $channels[(string)$r['channel']] = [
        'cnt' => (int)$r['cnt'],
        'sum_final' => (float)$r['sum_final'],
    ];
}
$telegramCnt = (int)($telegram['cnt'] ?? 0);
$telegramSum = (float)($telegram['sum_final'] ?? 0);
$uzumCnt = (int)($uzum['cnt'] ?? 0);
$uzumSum = (float)($uzum['sum_final'] ?? 0);

$boardCnt = (int)($channels['board']['cnt'] ?? 0);
$boardSum = (float)($channels['board']['sum_final'] ?? 0);
$callsCnt = max(0, $boardCnt - $telegramCnt);
$callsSum = max(0.0, $boardSum - $telegramSum);

// Build display rows
$channelRows = [];
foreach ($channels as $ch => $v) {
    if ($ch === 'board') continue; // replaced by calls + telegram
    $channelRows[] = ['channel' => $ch, 'cnt' => (int)$v['cnt'], 'sum_final' => (float)$v['sum_final']];
}
$channelRows[] = ['channel' => 'calls', 'cnt' => $callsCnt, 'sum_final' => $callsSum];
$channelRows[] = ['channel' => 'telegram', 'cnt' => $telegramCnt, 'sum_final' => $telegramSum];
$channelRows[] = ['channel' => 'uzum', 'cnt' => $uzumCnt, 'sum_final' => $uzumSum];

usort($channelRows, static fn($a, $b) => ($b['cnt'] <=> $a['cnt']));
?>

<div class="row g-3">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h1 class="h4 mb-1">Dashboard</h1>
        <div class="text-muted small">Range: <?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?></div>
      </div>
      <div class="d-flex gap-2">
        <?php if (($auth->role() ?? '') === 'admin' && !$smartomatoConfigured): ?>
          <a class="btn btn-warning btn-sm" href="?page=settings">Smartomato sozlash</a>
        <?php endif; ?>
        <form method="get" action="" class="d-flex gap-2">
          <input type="hidden" name="page" value="dashboard">
          <input class="form-control form-control-sm" type="date" name="from" value="<?= htmlspecialchars($from) ?>">
          <input class="form-control form-control-sm" type="date" name="to" value="<?= htmlspecialchars($to) ?>">
          <button class="btn btn-primary btn-sm" type="submit">Filter</button>
        </form>
      </div>
    </div>
  </div>

  <?php if (!$byDay): ?>
    <div class="col-12">
      <div class="alert alert-info mb-0">
        Hozircha Smartomato statistikasi yo‘q. Avval <a href="?page=smartomato">Smartomato</a> sahifasida “Yig‘ish” tugmasini bosing yoki cron ishlashini kuting.
      </div>
    </div>
  <?php endif; ?>

  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Buyurtmalar (Smartomato)</div>
        <div class="fs-5 fw-semibold"><?= number_format($totalOrders, 0, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Summa (Smartomato)</div>
        <div class="fs-5 fw-semibold"><?= number_format($totalSum, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Delivery</div>
        <div class="fs-5 fw-semibold"><?= number_format($deliveryCnt, 0, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Pickup</div>
        <div class="fs-5 fw-semibold"><?= number_format($pickupCnt, 0, '.', ' ') ?></div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">Buyurtmalar va summa (kunlar bo‘yicha)</h2>
        </div>
        <canvas id="ordersSumChart" height="110"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0">Kanal bo‘yicha</h2>
          <a class="btn btn-outline-secondary btn-sm" href="?page=others">Boshqalar</a>
        </div>
        <div class="table-responsive mt-2">
          <table class="table table-sm mb-0">
            <thead>
            <tr>
              <th>Kanal</th>
              <th class="text-end">Buyurtma</th>
              <th class="text-end">Summa</th>
              <th class="text-end">Komissiya</th>
              <th class="text-end">Net</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($channelRows as $r): ?>
              <?php
                $ch = (string)$r['channel'];
                $cnt = (int)$r['cnt'];
                $sum = (float)$r['sum_final'];
                $pct = 0.0;
                if ($ch === 'yandex') $pct = (float)($commission['yandex'] ?? 0);
                if ($ch === 'wolt') $pct = (float)($commission['wolt'] ?? 0);
                if ($ch === 'uzum') $pct = (float)($commission['uzum'] ?? 0);
                $net = $sum * (1.0 - ($pct / 100.0));
              ?>
              <tr>
                <td>
                  <?= htmlspecialchars(match($ch) {
                      'calls' => "Qo'ng'iroqlar (board - telegram)",
                      'telegram' => 'Telegram bot (qo‘lda)',
                      'uzum' => 'Uzum (qo‘lda)',
                      default => channel_label($ch),
                  }) ?>
                </td>
                <td class="text-end"><?= number_format($cnt, 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format($sum, 2, '.', ' ') ?></td>
                <td class="text-end"><?= ($pct > 0) ? number_format($pct, 2, '.', ' ') . '%' : '-' ?></td>
                <td class="text-end"><?= ($pct > 0) ? number_format($net, 2, '.', ' ') : '-' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-0">To‘lov turlari</h2>
        <div class="table-responsive mt-2">
          <table class="table table-sm mb-0">
            <thead><tr><th>To‘lov</th><th class="text-end">Buyurtma</th><th class="text-end">Summa</th></tr></thead>
            <tbody>
            <?php foreach ($byPayment as $r): ?>
              <?php $ps = (string)$r['payment_source']; ?>
              <tr>
                <td><?= htmlspecialchars(payment_label($ps)) ?> <span class="text-muted small"><code><?= htmlspecialchars($ps) ?></code></span></td>
                <td class="text-end"><?= number_format((int)$r['cnt'], 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((float)$r['sum_final'], 2, '.', ' ') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-0">Restaurant bo‘yicha</h2>
        <div class="table-responsive mt-2">
          <table class="table table-sm mb-0">
            <thead><tr><th>Restaurant ID</th><th class="text-end">Buyurtma</th><th class="text-end">Summa</th></tr></thead>
            <tbody>
            <?php foreach ($byRestaurant as $r): ?>
              <tr>
                <td><?= htmlspecialchars((string)$r['restaurant_id']) ?></td>
                <td class="text-end"><?= number_format((int)$r['cnt'], 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((float)$r['sum_final'], 2, '.', ' ') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  const orders = <?= json_encode($orders, JSON_UNESCAPED_UNICODE) ?>;
  const sums = <?= json_encode($sums, JSON_UNESCAPED_UNICODE) ?>;

  new Chart(document.getElementById('ordersSumChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {label: 'Buyurtmalar', data: orders, yAxisID: 'y'},
        {label: 'Summa', data: sums, type: 'line', yAxisID: 'y1'}
      ]
    },
    options: {
      responsive: true,
      scales: {
        y: {position: 'left', ticks: {precision: 0}},
        y1: {position: 'right', grid: {drawOnChartArea: false}}
      }
    }
  });
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

