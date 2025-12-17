<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var bool $smartomatoConfigured */
/** @var array $byDay */
/** @var array $byChannelToday */
/** @var array $byPaymentToday */
/** @var array $telegramByDay */
/** @var array $telegramToday */
/** @var string $activeDate */
require __DIR__ . '/../partials/layout_top.php';

$labels = array_map(static fn($r) => $r['stat_date'], $byDay);
$orders = array_map(static fn($r) => (int)$r['cnt'], $byDay);
$sums = array_map(static fn($r) => (float)$r['sum_final'], $byDay);

$channelLabels = array_map(static fn($r) => $r['channel'], $byChannelToday);
$channelCounts = array_map(static fn($r) => (int)$r['cnt'], $byChannelToday);

$paymentLabels = array_map(static fn($r) => $r['payment_source'], $byPaymentToday);
$paymentCounts = array_map(static fn($r) => (int)$r['cnt'], $byPaymentToday);

$tLabels = array_map(static fn($r) => $r['stat_date'], $telegramByDay);
$tCounts = array_map(static fn($r) => (int)$r['order_count'], $telegramByDay);
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h1 class="h4 mb-1">Dashboard</h1>
            <div class="text-muted small">
              Role: <?= htmlspecialchars($auth->role() ?? '-') ?>
            </div>
            <div class="text-muted small">
              Sana (Smartomato): <?= htmlspecialchars($activeDate ?? '') ?>
            </div>
          </div>
          <?php if (($auth->role() ?? '') === 'admin' && !$smartomatoConfigured): ?>
            <a class="btn btn-warning" href="?page=settings">Smartomato sozlash</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6">So‘nggi 7 kun: buyurtmalar</h2>
        <canvas id="ordersChart" height="140"></canvas>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6">Bugun: to‘lov turlari</h2>
        <canvas id="paymentsChart" height="140"></canvas>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6">Bugun: kanal bo‘yicha</h2>
        <canvas id="channelsChart" height="90"></canvas>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h2 class="h6 mb-1">Telegram bot (qo‘lda) — bugun</h2>
            <div class="text-muted small">Smartomato summalariga qo‘shilmaydi</div>
          </div>
          <a class="btn btn-outline-primary btn-sm" href="?page=telegram_bot">Kiritish</a>
        </div>
        <div class="row mt-3">
          <div class="col-6">
            <div class="text-muted small">Buyurtmalar</div>
            <div class="fs-5 fw-semibold"><?= (int)$telegramToday['order_count'] ?></div>
          </div>
          <div class="col-6">
            <div class="text-muted small">Summa</div>
            <div class="fs-5 fw-semibold"><?= number_format((float)$telegramToday['sum_final'], 2, '.', ' ') ?></div>
          </div>
        </div>
        <hr>
        <h3 class="h6">So‘nggi 7 kun: Telegram buyurtmalar</h3>
        <canvas id="telegramChart" height="90"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
  const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  const orders = <?= json_encode($orders, JSON_UNESCAPED_UNICODE) ?>;

  new Chart(document.getElementById('ordersChart'), {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{label: 'Buyurtmalar', data: orders}]
    }
  });

  const paymentLabels = <?= json_encode($paymentLabels, JSON_UNESCAPED_UNICODE) ?>;
  const paymentCounts = <?= json_encode($paymentCounts, JSON_UNESCAPED_UNICODE) ?>;
  new Chart(document.getElementById('paymentsChart'), {
    type: 'doughnut',
    data: {
      labels: paymentLabels,
      datasets: [{data: paymentCounts}]
    }
  });

  const channelLabels = <?= json_encode($channelLabels, JSON_UNESCAPED_UNICODE) ?>;
  const channelCounts = <?= json_encode($channelCounts, JSON_UNESCAPED_UNICODE) ?>;
  new Chart(document.getElementById('channelsChart'), {
    type: 'bar',
    data: {
      labels: channelLabels,
      datasets: [{label: 'Buyurtmalar', data: channelCounts}]
    },
    options: {
      indexAxis: 'y',
      plugins: {legend: {display: false}}
    }
  });

  const tLabels = <?= json_encode($tLabels, JSON_UNESCAPED_UNICODE) ?>;
  const tCounts = <?= json_encode($tCounts, JSON_UNESCAPED_UNICODE) ?>;
  new Chart(document.getElementById('telegramChart'), {
    type: 'line',
    data: {
      labels: tLabels,
      datasets: [{label: 'Telegram buyurtmalar', data: tCounts}]
    },
    options: {plugins: {legend: {display: false}}}
  });
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

