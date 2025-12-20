<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $from */
/** @var string $to */
/** @var string $period */
/** @var int $restaurantId */
/** @var array $restaurants */

/** @var array $dates */
/** @var array $ordersByDate */
/** @var array $salaryByDate */
/** @var array $taxiByDate */
/** @var array $millByDate */
/** @var array $errByDate */
/** @var array $aggByDate */
/** @var array $uzumByDate */
/** @var array $webappByDate */
/** @var array $telegramByDate */
/** @var array $boardByDate */
/** @var array $typesByDate */
/** @var array $opIds */
/** @var array $opNames */
/** @var array $opSeries */
require __DIR__ . '/../partials/layout_top.php';

$labels = $dates;
$orders = [];
$revenue = [];
$avgCheck = [];
$salary = [];
$taxi = [];
$errors = [];
$yandex = [];
$wolt = [];
$uzum = [];
$webapp = [];
$telegram = [];
$calls = [];
$delivery = [];
$pickup = [];

foreach ($labels as $d) {
    $o = $ordersByDate[$d] ?? ['orders' => 0, 'revenue' => 0, 'avg' => 0];
    $orders[] = (float)$o['orders'];
    $revenue[] = (float)$o['revenue'];
    $avgCheck[] = (float)$o['avg'];

    $salary[] = (float)(($salaryByDate[$d]['salary'] ?? 0));
    $t = (float)(($taxiByDate[$d]['taxi'] ?? 0)) + (float)(($millByDate[$d]['mill'] ?? 0));
    $taxi[] = $t;
    $errors[] = (float)(($errByDate[$d]['errors'] ?? 0));

    $a = $aggByDate[$d] ?? ['yandex' => 0, 'wolt' => 0, 'uzum' => 0];
    $yandex[] = (float)($a['yandex'] ?? 0);
    $wolt[] = (float)($a['wolt'] ?? 0);
    $uzum[] = (float)($uzumByDate[$d] ?? 0);

    $webapp[] = (float)($webappByDate[$d] ?? 0);
    $tg = (float)($telegramByDate[$d] ?? 0);
    $telegram[] = $tg;
    $b = (float)($boardByDate[$d] ?? 0);
    $calls[] = max(0.0, $b - $tg);

    $tt = $typesByDate[$d] ?? ['delivery' => 0, 'pickup' => 0];
    $delivery[] = (float)($tt['delivery'] ?? 0);
    $pickup[] = (float)($tt['pickup'] ?? 0);
}
?>

<div class="d-flex justify-content-between align-items-end mb-3">
  <div>
    <h1 class="h4 mb-1"><?= htmlspecialchars($t->t('dashboard.title', 'Analitika')) ?></h1>
    <div class="text-muted small"><?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?></div>
  </div>
  <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="dashboard">
    <select class="form-select form-select-sm" name="period">
      <option value="week" <?= $period==='week'?'selected':'' ?>><?= htmlspecialchars($t->t('common.period.week', 'Hafta')) ?></option>
      <option value="month" <?= $period==='month'?'selected':'' ?>><?= htmlspecialchars($t->t('common.period.month', 'Oy')) ?></option>
      <option value="year" <?= $period==='year'?'selected':'' ?>><?= htmlspecialchars($t->t('common.period.year', 'Yil')) ?></option>
    </select>
    <select class="form-select form-select-sm" name="restaurant_id">
      <option value="0"><?= htmlspecialchars($t->t('common.all_restaurants', 'Barcha restoran')) ?></option>
      <?php foreach ($restaurants as $id => $name): ?>
        <option value="<?= (int)$id ?>" <?= ((int)$restaurantId === (int)$id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm" type="submit"><?= htmlspecialchars($t->t('common.show', 'Ko‘rsatish')) ?></button>
  </form>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('dashboard.chart.orders', '1) Dinamika: buyurtmalar')) ?></h2>
        <canvas id="ordersChart" height="120"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('dashboard.chart.expenses', '2) Dinamika: xarajatlar')) ?></h2>
        <canvas id="expensesChart" height="120"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('dashboard.chart.aggregators', '3) Dinamika: agregatorlar (Yandex / Wolt / Uzum)')) ?></h2>
        <canvas id="aggregatorsChart" height="110"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('dashboard.chart.channels', "4) Dinamika: boshqa kanallar (Web+App / Telegram / Qo'ng'iroqlar)")) ?></h2>
        <canvas id="channelsChart" height="110"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('dashboard.chart.types', '5) Dinamika: buyurtma turi (Delivery / Pickup) — Web+App+Board')) ?></h2>
        <canvas id="typesChart" height="110"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('dashboard.chart.operators', '6) Dinamika: operatorlar (top 5) — savdo summasi')) ?></h2>
        <canvas id="operatorsChart" height="140"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
  const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  const orders = <?= json_encode($orders, JSON_UNESCAPED_UNICODE) ?>;
  const revenue = <?= json_encode($revenue, JSON_UNESCAPED_UNICODE) ?>;
  const avg = <?= json_encode($avgCheck, JSON_UNESCAPED_UNICODE) ?>;

  new Chart(document.getElementById('ordersChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        {label: <?= json_encode($t->t('dashboard.label.orders', 'Buyurtmalar'), JSON_UNESCAPED_UNICODE) ?>, data: orders, yAxisID: 'y'},
        {label: <?= json_encode($t->t('dashboard.label.revenue', 'Vyручka'), JSON_UNESCAPED_UNICODE) ?>, data: revenue, yAxisID: 'y1'},
        {label: <?= json_encode($t->t('dashboard.label.avg', 'O‘rtacha чек'), JSON_UNESCAPED_UNICODE) ?>, data: avg, yAxisID: 'y2'},
      ]
    },
    options: {
      responsive: true,
      interaction: {mode: 'index', intersect: false},
      scales: {
        y: {position: 'left', ticks: {precision: 0}},
        y1: {position: 'right', grid: {drawOnChartArea: false}},
        y2: {position: 'right', grid: {drawOnChartArea: false}, display: false},
      }
    }
  });

  const salary = <?= json_encode($salary, JSON_UNESCAPED_UNICODE) ?>;
  const taxi = <?= json_encode($taxi, JSON_UNESCAPED_UNICODE) ?>;
  const errors = <?= json_encode($errors, JSON_UNESCAPED_UNICODE) ?>;
  new Chart(document.getElementById('expensesChart'), {
    type: 'line',
    data: { labels, datasets: [
      {label: <?= json_encode($t->t('dashboard.label.salary', 'Ish haqi'), JSON_UNESCAPED_UNICODE) ?>, data: salary},
      {label: <?= json_encode($t->t('dashboard.label.taxi', 'Taxi'), JSON_UNESCAPED_UNICODE) ?>, data: taxi},
      {label: <?= json_encode($t->t('dashboard.label.errors', 'Xatolar'), JSON_UNESCAPED_UNICODE) ?>, data: errors},
    ]},
    options: {responsive:true, interaction:{mode:'index', intersect:false}}
  });

  const yandex = <?= json_encode($yandex, JSON_UNESCAPED_UNICODE) ?>;
  const wolt = <?= json_encode($wolt, JSON_UNESCAPED_UNICODE) ?>;
  const uzum = <?= json_encode($uzum, JSON_UNESCAPED_UNICODE) ?>;
  new Chart(document.getElementById('aggregatorsChart'), {
    type: 'line',
    data: { labels, datasets: [
      {label: 'Yandex', data: yandex},
      {label: 'Wolt', data: wolt},
      {label: 'Uzum', data: uzum},
    ]},
    options: {responsive:true, interaction:{mode:'index', intersect:false}}
  });

  const webapp = <?= json_encode($webapp, JSON_UNESCAPED_UNICODE) ?>;
  const telegram = <?= json_encode($telegram, JSON_UNESCAPED_UNICODE) ?>;
  const calls = <?= json_encode($calls, JSON_UNESCAPED_UNICODE) ?>;
  new Chart(document.getElementById('channelsChart'), {
    type: 'line',
    data: { labels, datasets: [
      {label: <?= json_encode($t->t('dashboard.label.webapp', 'Web+App'), JSON_UNESCAPED_UNICODE) ?>, data: webapp},
      {label: <?= json_encode($t->t('dashboard.label.telegram', 'Telegram'), JSON_UNESCAPED_UNICODE) ?>, data: telegram},
      {label: <?= json_encode($t->t('dashboard.label.calls', "Qo'ng'iroqlar"), JSON_UNESCAPED_UNICODE) ?>, data: calls},
    ]},
    options: {responsive:true, interaction:{mode:'index', intersect:false}}
  });

  const delivery = <?= json_encode($delivery, JSON_UNESCAPED_UNICODE) ?>;
  const pickup = <?= json_encode($pickup, JSON_UNESCAPED_UNICODE) ?>;
  new Chart(document.getElementById('typesChart'), {
    type: 'line',
    data: { labels, datasets: [
      {label: 'Delivery', data: delivery},
      {label: 'Pickup', data: pickup},
    ]},
    options: {responsive:true, interaction:{mode:'index', intersect:false}}
  });

  const opIds = <?= json_encode($opIds, JSON_UNESCAPED_UNICODE) ?>;
  const opNames = <?= json_encode($opNames, JSON_UNESCAPED_UNICODE) ?>;
  const opSeries = <?= json_encode($opSeries, JSON_UNESCAPED_UNICODE) ?>;
  const opDatasets = opIds.map((id) => {
    const map = opSeries[id] || {};
    const data = labels.map((d) => (map[d] || 0));
    return {label: (opNames[id] || ('Operator ' + id)), data};
  });
  new Chart(document.getElementById('operatorsChart'), {
    type: 'line',
    data: {labels, datasets: opDatasets},
    options: {responsive:true, interaction:{mode:'index', intersect:false}}
  });
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

