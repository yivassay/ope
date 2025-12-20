<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $date */
/** @var int $restaurantId */
/** @var array $restaurants */
/** @var array $commission */
/** @var array $total */
/** @var array $deliveryNoAgg */
/** @var array $pickupNoAgg */
/** @var array $agg */
/** @var array $uzum */
/** @var float $salarySum */
/** @var array $taxi */
/** @var float $millSum */
/** @var array $err */
/** @var array $byChannel */
/** @var array $telegram */
/** @var int $callsCnt */
/** @var float $callsSum */
/** @var array $byPayment */
/** @var array $salaryRows */
/** @var float $clientPaidDelivery */
/** @var bool $clientPaidDeliverySupported */
/** @var string|null $sendMessage */
/** @var string|null $sendError */
require __DIR__ . '/../partials/layout_top.php';

function money($v): string { return number_format((float)$v, 2, '.', ' '); }
function num0($v): string { return number_format((float)$v, 0, '.', ' '); }
$paymentLabel = static function (string $ps) use ($t): string {
    return match ($ps) {
        'cash' => $t->t('payment.cash', 'Naqt pul'),
        'card' => $t->t('payment.card', 'Karta'),
        'external_service_card_online' => $t->t('payment.aggregator', 'Agregator'),
        default => $ps,
    };
};
?>

<?php
$y = $agg['yandex'] ?? ['cnt'=>0,'sum_final'=>0];
$w = $agg['wolt'] ?? ['cnt'=>0,'sum_final'=>0];
$u = $uzum ?? ['cnt'=>0,'sum_final'=>0];
$ySum = (float)($y['sum_final'] ?? 0);
$wSum = (float)($w['sum_final'] ?? 0);
$uSum = (float)($u['sum_final'] ?? 0);
$yNet = $ySum * (1 - ((float)$commission['yandex']/100));
$wNet = $wSum * (1 - ((float)$commission['wolt']/100));
$uNet = $uSum * (1 - ((float)$commission['uzum']/100));

// Profit total should use aggregator net (commission removed)
$nonAggSum = (float)($total['sum_final'] ?? 0) - $ySum - $wSum;
$profitTotal = max(0.0, $nonAggSum + $yNet + $wNet + $uNet);

$pct = static function (float $v) use ($profitTotal): string {
    if ($profitTotal <= 0) return '0%';
    return number_format(($v / $profitTotal) * 100.0, 1, '.', '') . '%';
};

// Expenses total: salaries + taxi + errors - client paid delivery
$expensesTotal = (float)$salarySum
    + (float)($taxi['sum_total'] ?? 0)
    + (float)$millSum
    + (float)($err['sum'] ?? 0)
    - (float)$clientPaidDelivery;

$netProfit = $profitTotal - $expensesTotal;

$totalOrdersAll = (float)($total['cnt'] ?? 0) + (float)($uzum['cnt'] ?? 0);
$taxiGross = (float)($taxi['sum_total'] ?? 0) + (float)$millSum;
$taxiDiff = $taxiGross - (float)$clientPaidDelivery;
?>

<div class="d-flex justify-content-between align-items-end mb-3">
  <div>
    <h1 class="h4 mb-1"><?= htmlspecialchars($t->t('bugungi.title', 'Bugungi')) ?></h1>
    <div class="text-muted small"><?= htmlspecialchars($t->t('common.day', 'Kun')) ?>: <?= htmlspecialchars($date) ?></div>
  </div>
  <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="bugungi">
    <input class="form-control form-control-sm" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <select class="form-select form-select-sm" name="restaurant_id">
      <option value="0"><?= htmlspecialchars($t->t('common.all_restaurants', 'Barcha restoran')) ?></option>
      <?php foreach ($restaurants as $id => $name): ?>
        <option value="<?= (int)$id ?>" <?= ((int)$restaurantId === (int)$id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm" type="submit"><?= htmlspecialchars($t->t('common.show', 'Ko‘rsatish')) ?></button>
  </form>
</div>

<?php if (($sendMessage ?? null)): ?>
  <div class="alert alert-success"><?= htmlspecialchars((string)$sendMessage) ?></div>
<?php endif; ?>
<?php if (($sendError ?? null)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars((string)$sendError) ?></div>
<?php endif; ?>

<?php if (!($clientPaidDeliverySupported ?? true)): ?>
  <div class="alert alert-warning">
    <?= htmlspecialchars($t->t('bugungi.client_paid.not_supported', '“Opłatil klient” ishlashi uchun bazada delivery_client_sum ustuni bo‘lishi kerak. Schema yangilang va Smartomato ni qayta yig‘ing.')) ?>
  </div>
<?php endif; ?>

<div class="mb-3">
  <form method="post" action="?page=bugungi&action=send_report&date=<?= urlencode($date) ?>&restaurant_id=<?= (int)$restaurantId ?>">
    <button class="btn btn-success" type="submit"><?= htmlspecialchars($t->t('bugungi.send_report', 'Xisobotni yuborish')) ?></button>
  </form>
</div>

<div class="row gy-4 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card shadow-none border bg-gradient-start-1 h-100">
      <div class="card-body p-20">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div>
            <p class="fw-medium text-primary-light mb-1"><?= htmlspecialchars($t->t('bugungi.card.income', 'Jami summa')) ?></p>
            <h6 class="mb-0"><?= money($profitTotal) ?></h6>
          </div>
          <div class="w-50-px h-50-px bg-cyan rounded-circle d-flex justify-content-center align-items-center">
            <i class="ri-money-dollar-circle-line text-white text-2xl mb-0"></i>
          </div>
        </div>
        <p class="fw-medium text-sm text-primary-light mt-12 mb-0 d-flex align-items-center gap-2">
          <span class="d-inline-flex align-items-center gap-1 text-success-main">
            <i class="ri-arrow-right-up-line text-xs"></i> <?= htmlspecialchars($pct($profitTotal)) ?>
          </span>
          <?= htmlspecialchars($t->t('bugungi.card.of_total_profit', 'ulushi')) ?>
        </p>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card shadow-none border bg-gradient-start-2 h-100">
      <div class="card-body p-20">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div>
            <p class="fw-medium text-primary-light mb-1"><?= htmlspecialchars($t->t('bugungi.expenses.total', 'Jami xarajat')) ?></p>
            <h6 class="mb-0"><?= money($expensesTotal) ?></h6>
          </div>
          <div class="w-50-px h-50-px bg-warning-main rounded-circle d-flex justify-content-center align-items-center">
            <i class="ri-wallet-3-line text-white text-2xl mb-0"></i>
          </div>
        </div>
        <p class="fw-medium text-sm text-primary-light mt-12 mb-0 d-flex align-items-center gap-2">
          <span class="d-inline-flex align-items-center gap-1 text-danger-main">
            <i class="ri-arrow-right-down-line text-xs"></i> <?= htmlspecialchars($pct($expensesTotal)) ?>
          </span>
          <?= htmlspecialchars($t->t('bugungi.card.of_total_profit', 'ulushi')) ?>
        </p>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card shadow-none border bg-gradient-start-3 h-100">
      <div class="card-body p-20">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div>
            <p class="fw-medium text-primary-light mb-1"><?= htmlspecialchars($t->t('bugungi.profit.net_profit', 'Chistaya foyda')) ?></p>
            <h6 class="mb-0"><?= money($netProfit) ?></h6>
          </div>
          <div class="w-50-px h-50-px bg-purple rounded-circle d-flex justify-content-center align-items-center">
            <i class="ri-hand-coin-line text-white text-2xl mb-0"></i>
          </div>
        </div>
        <p class="fw-medium text-sm text-primary-light mt-12 mb-0 d-flex align-items-center gap-2">
          <span class="d-inline-flex align-items-center gap-1 <?= ($netProfit >= 0) ? 'text-success-main' : 'text-danger-main' ?>">
            <i class="<?= ($netProfit >= 0) ? 'ri-arrow-right-up-line' : 'ri-arrow-right-down-line' ?> text-xs"></i> <?= htmlspecialchars($pct($netProfit)) ?>
          </span>
          <?= htmlspecialchars($t->t('bugungi.card.of_total_profit', 'ulushi')) ?>
        </p>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card shadow-none border bg-gradient-start-4 h-100">
      <div class="card-body p-20">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div>
            <p class="fw-medium text-primary-light mb-1"><?= htmlspecialchars($t->t('bugungi.expenses.client_paid', 'Zaplatil klient')) ?></p>
            <h6 class="mb-0"><?= money($clientPaidDelivery ?? 0) ?></h6>
          </div>
          <div class="w-50-px h-50-px bg-success-main rounded-circle d-flex justify-content-center align-items-center">
            <i class="ri-coupon-3-line text-white text-2xl mb-0"></i>
          </div>
        </div>
        <p class="fw-medium text-sm text-primary-light mt-12 mb-0 d-flex align-items-center gap-2">
          <span class="d-inline-flex align-items-center gap-1 text-success-main">
            <i class="ri-arrow-right-up-line text-xs"></i> <?= htmlspecialchars($pct((float)($clientPaidDelivery ?? 0))) ?>
          </span>
          <?= htmlspecialchars($t->t('bugungi.card.of_total_profit', 'ulushi')) ?>
        </p>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('bugungi.section.profit', '1) Foyda (buyurtmalar)')) ?></h2>
  </div>
  <div class="col-12 col-xl-6">
    <div class="card">
      <div class="card-body">
        <h3 class="h6 mb-3"><?= htmlspecialchars($t->t('bugungi.breakdown.profit', 'Foyda taqsimoti')) ?></h3>
        <?php
          $profitItems = [
            ['label' => $t->t('bugungi.profit.delivery_no_agg', 'Delivery (no agg)'), 'value' => (float)($deliveryNoAgg['sum_final'] ?? 0), 'color' => 'bg-warning-main'],
            ['label' => $t->t('bugungi.profit.pickup_no_agg', 'Pickup (no agg)'), 'value' => (float)($pickupNoAgg['sum_final'] ?? 0), 'color' => 'bg-info-main'],
            ['label' => 'Yandex (net)', 'value' => (float)$yNet, 'color' => 'bg-danger-main'],
            ['label' => 'Wolt (net)', 'value' => (float)$wNet, 'color' => 'bg-purple'],
            ['label' => 'Uzum (net)', 'value' => (float)$uNet, 'color' => 'bg-success-main'],
          ];
        ?>
        <div class="progress mb-3" style="height: 8px;">
          <?php foreach ($profitItems as $it): ?>
            <?php $w = ($profitTotal > 0) ? max(0.0, ((float)$it['value'] / $profitTotal) * 100.0) : 0.0; ?>
            <div class="progress-bar <?= htmlspecialchars($it['color']) ?>" role="progressbar" style="width: <?= number_format($w, 2, '.', '') ?>%"></div>
          <?php endforeach; ?>
        </div>
        <div class="d-flex flex-column gap-16">
          <?php foreach ($profitItems as $it): ?>
            <div class="d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-3">
                <span class="w-12-px h-12-px rounded-circle <?= htmlspecialchars($it['color']) ?>"></span>
                <span class="fw-medium"><?= htmlspecialchars((string)$it['label']) ?></span>
              </div>
              <div class="d-flex align-items-center gap-3">
                <span class="fw-semibold"><?= money((float)$it['value']) ?></span>
                <span class="text-secondary-light fw-semibold"><?= htmlspecialchars($pct((float)$it['value'])) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h3 class="h6 mb-2"><?= htmlspecialchars($t->t('bugungi.profit.aggregators', 'Agregatorlar')) ?></h3>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th><?= htmlspecialchars($t->t('common.channel', 'Kanal')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.count', 'Cnt')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.commission', 'Komissiya')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.net', 'Net')) ?></th></tr></thead>
            <tbody>
              <tr><td>Yandex Eda</td><td class="text-end"><?= num0($y['cnt']) ?></td><td class="text-end"><?= money($y['sum_final']) ?></td><td class="text-end"><?= money($commission['yandex']) ?>%</td><td class="text-end"><?= money($yNet) ?> <span class="text-muted small">(<?= $pct((float)$yNet) ?>)</span></td></tr>
              <tr><td>Wolt</td><td class="text-end"><?= num0($w['cnt']) ?></td><td class="text-end"><?= money($w['sum_final']) ?></td><td class="text-end"><?= money($commission['wolt']) ?>%</td><td class="text-end"><?= money($wNet) ?> <span class="text-muted small">(<?= $pct((float)$wNet) ?>)</span></td></tr>
              <tr><td>Uzum (qo‘lda)</td><td class="text-end"><?= num0($u['cnt']) ?></td><td class="text-end"><?= money($u['sum_final']) ?></td><td class="text-end"><?= money($commission['uzum']) ?>%</td><td class="text-end"><?= money($uNet) ?> <span class="text-muted small">(<?= $pct((float)$uNet) ?>)</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('bugungi.section.expenses', '2) Xarajatlar')) ?></h2>
  </div>
  <div class="col-12 col-xl-6">
    <div class="card">
      <div class="card-body">
        <h3 class="h6 mb-3"><?= htmlspecialchars($t->t('bugungi.breakdown.expenses', 'Xarajatlar taqsimoti')) ?></h3>
        <?php
          $errSum = (float)($err['sum'] ?? 0);
          $expensePosTotal = max(0.0, (float)$salarySum + (float)$taxiGross + (float)$errSum);
          $expenseItems = [
            ['label' => $t->t('bugungi.expenses.salary', 'Ish haqi (jami)'), 'value' => (float)$salarySum, 'color' => 'bg-warning-main'],
            ['label' => $t->t('bugungi.expenses.taxi_yandex', 'Taxi Yandex (jami)') . ' + ' . $t->t('bugungi.expenses.taxi_millennium', 'Taxi Millennium'), 'value' => (float)$taxiGross, 'color' => 'bg-info-main'],
            ['label' => $t->t('bugungi.expenses.errors', 'Ko‘syaklar'), 'value' => (float)$errSum, 'color' => 'bg-danger-main'],
          ];
        ?>
        <div class="progress mb-3" style="height: 8px;">
          <?php foreach ($expenseItems as $it): ?>
            <?php $w = ($expensePosTotal > 0) ? max(0.0, ((float)$it['value'] / $expensePosTotal) * 100.0) : 0.0; ?>
            <div class="progress-bar <?= htmlspecialchars($it['color']) ?>" role="progressbar" style="width: <?= number_format($w, 2, '.', '') ?>%"></div>
          <?php endforeach; ?>
        </div>
        <div class="d-flex flex-column gap-16">
          <?php foreach ($expenseItems as $it): ?>
            <div class="d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-3">
                <span class="w-12-px h-12-px rounded-circle <?= htmlspecialchars($it['color']) ?>"></span>
                <span class="fw-medium"><?= htmlspecialchars((string)$it['label']) ?></span>
              </div>
              <div class="d-flex align-items-center gap-3">
                <span class="fw-semibold"><?= money((float)$it['value']) ?></span>
                <span class="text-secondary-light fw-semibold"><?= htmlspecialchars(($expensePosTotal > 0) ? number_format(((float)$it['value'] / $expensePosTotal) * 100.0, 0, '.', '') . '%' : '0%') ?></span>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <div class="d-flex align-items-center gap-3">
              <span class="w-12-px h-12-px rounded-circle bg-success-main"></span>
              <span class="fw-medium"><?= htmlspecialchars($t->t('bugungi.expenses.client_paid', 'Zaplatil klient')) ?> (−)</span>
            </div>
            <div class="d-flex align-items-center gap-3">
              <span class="fw-semibold"><?= money((float)($clientPaidDelivery ?? 0)) ?></span>
              <span class="text-secondary-light fw-semibold"><?= htmlspecialchars($pct((float)($clientPaidDelivery ?? 0))) ?></span>
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="fw-semibold"><?= htmlspecialchars($t->t('bugungi.expenses.total', 'Jami xarajat')) ?></div>
            <div class="fw-semibold"><?= money($expensesTotal) ?> <span class="text-secondary-light fw-semibold">(<?= htmlspecialchars($pct((float)$expensesTotal)) ?>)</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card"><div class="card-body py-3">
      <div class="text-muted small"><?= htmlspecialchars($t->t('bugungi.expenses.total', 'Jami xarajat')) ?></div>
      <div class="fs-5 fw-semibold"><?= money($expensesTotal) ?></div>
      <div class="text-muted small"><?= $pct($expensesTotal) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body py-3">
      <div class="text-muted small"><?= htmlspecialchars($t->t('bugungi.expenses.salary', 'Ish haqi (jami)')) ?></div>
      <div class="fs-5 fw-semibold"><?= money($salarySum) ?></div>
      <div class="text-muted small"><?= $pct((float)$salarySum) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body py-3">
      <div class="text-muted small"><?= htmlspecialchars($t->t('bugungi.expenses.taxi_yandex', 'Taxi Yandex (jami)')) ?></div>
      <div class="fs-5 fw-semibold"><?= money($taxi['sum_total'] ?? 0) ?></div>
      <div class="text-muted small"><?= $pct((float)($taxi['sum_total'] ?? 0)) ?> · <?= htmlspecialchars($t->t('bugungi.expenses.waiting', 'Kutish')) ?>: <?= money($taxi['sum_waiting'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body py-3">
      <div class="text-muted small"><?= htmlspecialchars($t->t('bugungi.expenses.taxi_millennium', 'Taxi Millennium')) ?></div>
      <div class="fs-5 fw-semibold"><?= money($millSum) ?></div>
      <div class="text-muted small"><?= $pct((float)$millSum) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body py-3">
      <div class="text-muted small"><?= htmlspecialchars($t->t('bugungi.expenses.errors', 'Ko‘syaklar')) ?></div>
      <div class="fs-5 fw-semibold"><?= num0($err['cnt'] ?? 0) ?></div>
      <div class="text-muted small"><?= money($err['sum'] ?? 0) ?> (<?= $pct((float)($err['sum'] ?? 0)) ?>)</div>
    </div></div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body py-3">
      <div class="text-muted small"><?= htmlspecialchars($t->t('bugungi.expenses.client_paid', 'Zaplatil klient')) ?></div>
      <div class="fs-5 fw-semibold"><?= money($clientPaidDelivery ?? 0) ?></div>
      <div class="text-muted small"><?= $pct((float)($clientPaidDelivery ?? 0)) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body py-3">
      <div class="text-muted small"><?= htmlspecialchars($t->t('bugungi.expenses.taxi_diff', 'Farq')) ?></div>
      <div class="fs-5 fw-semibold"><?= money($taxiDiff) ?></div>
      <div class="text-muted small"><?= htmlspecialchars($t->t('bugungi.expenses.taxi_diff_hint', 'Yandex+Millennium − Zaplatil klient')) ?> · <?= $pct((float)$taxiDiff) ?></div>
    </div></div>
  </div>

  <div class="col-12">
    <div class="card"><div class="card-body">
      <h3 class="h6 mb-2"><?= htmlspecialchars($t->t('bugungi.expenses.taxi_extra', 'Taxi qo‘shimcha')) ?></h3>
      <div class="row g-2">
        <div class="col-6 col-lg-3"><div class="text-muted small"><?= htmlspecialchars($t->t('taxi.roundtrip', 'Tuda-obratno')) ?></div><div class="fw-semibold"><?= num0($taxi['roundtrip_count'] ?? 0) ?></div></div>
        <div class="col-6 col-lg-3"><div class="text-muted small"><?= htmlspecialchars($t->t('taxi.duplicate', 'Ikki marta')) ?></div><div class="fw-semibold"><?= num0($taxi['duplicate_3h_count'] ?? 0) ?></div><div class="text-muted small"><?= money($taxi['duplicate_3h_sum_total'] ?? 0) ?></div></div>
        <div class="col-6 col-lg-3"><div class="text-muted small"><?= htmlspecialchars($t->t('taxi.returned', 'Qaytgan (возврат)')) ?></div><div class="fw-semibold"><?= num0($taxi['returned_count'] ?? 0) ?></div><div class="text-muted small"><?= money($taxi['returned_sum'] ?? 0) ?></div></div>
      </div>
    </div></div>
  </div>

  <div class="col-12">
    <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('bugungi.section.channels', '3) Buyurtmalar kanallar bo‘yicha')) ?></h2>
    <?php
      $tg = $telegram ?? ['cnt'=>0,'sum_final'=>0];
      $board = $byChannel['board'] ?? ['cnt'=>0,'sum_final'=>0];
    ?>
    <div class="card"><div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th><?= htmlspecialchars($t->t('common.channel', 'Kanal')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.count', 'Cnt')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($byChannel as $ch => $r): ?>
              <?php if ($ch === 'board') continue; ?>
              <tr><td><?= htmlspecialchars((string)$ch) ?></td><td class="text-end"><?= num0($r['cnt'] ?? 0) ?></td><td class="text-end"><?= money($r['sum_final'] ?? 0) ?></td></tr>
            <?php endforeach; ?>
            <tr><td><?= htmlspecialchars($t->t('bugungi.calls', "Qo'ng'iroqlar (board - telegram)")) ?></td><td class="text-end"><?= num0($callsCnt) ?></td><td class="text-end"><?= money($callsSum) ?></td></tr>
            <tr><td><?= htmlspecialchars($t->t('bugungi.telegram_manual', "Telegram (qo‘lda)")) ?></td><td class="text-end"><?= num0($tg['cnt'] ?? 0) ?></td><td class="text-end"><?= money($tg['sum_final'] ?? 0) ?></td></tr>
            <tr><td><?= htmlspecialchars($t->t('bugungi.uzum_manual', "Uzum (qo‘lda)")) ?></td><td class="text-end"><?= num0($uzum['cnt'] ?? 0) ?></td><td class="text-end"><?= money($uzum['sum_final'] ?? 0) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>

  <div class="col-12">
    <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('bugungi.section.payments', "4) To‘lov turlari")) ?></h2>
    <div class="card"><div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th><?= htmlspecialchars($t->t('bugungi.payment', "To‘lov")) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.count', 'Cnt')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($byPayment as $r): ?>
              <?php $ps = (string)$r['payment_source']; ?>
              <tr><td><?= htmlspecialchars($paymentLabel($ps)) ?> <span class="text-muted small"><code><?= htmlspecialchars($ps) ?></code></span></td><td class="text-end"><?= num0($r['cnt'] ?? 0) ?></td><td class="text-end"><?= money($r['sum_final'] ?? 0) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>

  <div class="col-12">
    <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('bugungi.section.staff', '🙂Ishchilar')) ?></h2>
    <div class="card"><div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th><?= htmlspecialchars($t->t('staff.name', 'Ism')) ?></th><th><?= htmlspecialchars($t->t('operator_sales.col.mode', 'Rejim')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('staff.salary', 'Ish haqi')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('staff.orders', 'Buyurtma')) ?></th></tr></thead>
          <tbody>
          <?php foreach (($salaryRows ?? []) as $r): ?>
            <?php
              $mode = (string)($r['role_mode'] ?? '');
              $oc = (int)($r['order_count'] ?? 0);
              $sal = (float)($r['salary_value'] ?? 0);
            ?>
            <tr>
              <td><?= htmlspecialchars((string)$r['name']) ?></td>
              <td><?= htmlspecialchars(($mode === 'logistic') ? $t->t('mode.logistic', 'Logist') : $t->t('mode.operator', 'Operator')) ?></td>
              <td class="text-end"><?= money($sal) ?></td>
              <td class="text-end"><?= ($mode === 'logistic') ? htmlspecialchars($t->t('mode.logistic_short', 'logist')) : num0($oc) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!($salaryRows ?? [])): ?>
            <tr><td colspan="4" class="text-muted"><?= htmlspecialchars($t->t('staff.empty', "Hozircha ish haqi kiritilmagan.")) ?></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

