<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $date */
/** @var string|null $message */
/** @var string|null $error */
/** @var array|null $import */
/** @var array $stats */
/** @var array $dupByRestaurant */
/** @var string $mappingRaw */
/** @var array $restaurantOptions */
/** @var array $millennium */
/** @var array $couriers */
require __DIR__ . '/../partials/layout_top.php';

$totalTrips = 0;
$totalSum = 0.0;
$totalWaiting = 0.0;
$totalPaidCancelCnt = 0;
$totalPaidCancelSum = 0.0;
$totalReturnedCnt = 0;
$totalReturnedSum = 0.0;
foreach ($stats as $s) {
    $totalTrips += (int)$s['trips_count'];
    $totalSum += (float)$s['sum_total'];
    $totalWaiting += (float)$s['sum_waiting'];
    $totalPaidCancelCnt += (int)($s['paid_cancel_count'] ?? 0);
    $totalPaidCancelSum += (float)($s['paid_cancel_sum'] ?? 0);
    $totalReturnedCnt += (int)($s['returned_count'] ?? 0);
    $totalReturnedSum += (float)($s['returned_sum'] ?? 0);
}

$millTrips = 0;
$millSum = 0.0;
foreach ($millennium as $m) {
    $millTrips += (int)($m['trips_count'] ?? 0);
    $millSum += (float)($m['sum_total'] ?? 0);
}

$courTrips = 0;
$courSum = 0.0;
foreach ($couriers as $c) {
    $courTrips += (int)($c['trips_count'] ?? 0);
    $courSum += (float)($c['sum_total'] ?? 0);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 m-0"><?= htmlspecialchars($t->t('taxi.title', 'Taxi (Yandex)')) ?></h1>
    <div class="text-muted small"><?= htmlspecialchars($t->t('common.day', 'Kun')) ?>: <?= htmlspecialchars($date) ?></div>
  </div>
  <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="taxi">
    <input class="form-control form-control-sm" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button class="btn btn-outline-secondary btn-sm" type="submit"><?= htmlspecialchars($t->t('common.show', 'Ko‘rsatish')) ?></button>
  </form>
</div>

<?php if ($message): ?>
  <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('taxi.upload.title', 'CSV yuklash')) ?></h2>
    <form method="post" action="?page=taxi&action=upload" enctype="multipart/form-data" class="row g-2 align-items-end">
      <div class="col-12 col-lg-6">
        <label class="form-label"><?= htmlspecialchars($t->t('taxi.upload.file', 'CSV fayl')) ?></label>
        <input class="form-control" type="file" name="file" accept=".csv,text/csv" required>
        <div class="form-text"><?= htmlspecialchars($t->t('taxi.upload.hint', "Fayl ichida bir nechta kun bo‘lsa ham bo‘ladi — hammasi import qilinadi.")) ?></div>
      </div>
      <div class="col-12 col-lg-3">
        <button class="btn btn-primary w-100" type="submit"><?= htmlspecialchars($t->t('taxi.upload.btn', 'Yuklash va hisoblash')) ?></button>
      </div>
    </form>
    <?php if ($import): ?>
      <div class="text-muted small mt-2">
        <?= htmlspecialchars($t->t('taxi.last_import', 'Oxirgi import')) ?>: <?= htmlspecialchars((string)$import['created_at']) ?> —
        <?= htmlspecialchars((string)$import['original_filename']) ?> —
        <strong><?= htmlspecialchars((string)$import['status']) ?></strong>
        <?= htmlspecialchars((string)$import['message']) ?>
      </div>
    <?php endif; ?>

    <?php if (($auth->role() ?? '') === 'admin' && trim($mappingRaw) === ''): ?>
      <div class="alert alert-warning mt-3 mb-0">
        <?= htmlspecialchars($t->t('taxi.no_mapping', "Restaurant adres keyword sozlanmagan.")) ?>
        <a href="?page=settings"><?= htmlspecialchars($t->t('nav.settings', 'Sozlamalar')) ?></a>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small"><?= htmlspecialchars($t->t('taxi.card.trips', "Po‘ezdka (count)")) ?></div>
        <div class="fs-5 fw-semibold"><?= number_format($totalTrips, 0, '.', ' ') ?></div>
        <div class="text-muted small"><?= htmlspecialchars($t->t('taxi.card.plus_millennium', '(+ Millennium)')) ?>: <?= number_format($millTrips, 0, '.', ' ') ?></div>
        <div class="text-muted small"><?= htmlspecialchars($t->t('taxi.couriers.title', 'Our couriers')) ?>: <?= number_format($courTrips, 0, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small"><?= htmlspecialchars($t->t('taxi.card.sum_total', 'Summa (jami)')) ?></div>
        <div class="fs-5 fw-semibold"><?= number_format($totalSum + $totalPaidCancelSum + $totalReturnedSum + $millSum + $courSum, 2, '.', ' ') ?></div>
        <div class="text-muted small"><?= htmlspecialchars($t->t('taxi.card.sum_total_hint', '(+ paid cancel + returned + Millennium)')) ?> + <?= htmlspecialchars($t->t('taxi.couriers.title', 'Our couriers')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small"><?= htmlspecialchars($t->t('taxi.card.waiting', 'Pulatli kutish (jami)')) ?></div>
        <div class="fs-5 fw-semibold"><?= number_format($totalWaiting, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small"><?= htmlspecialchars($t->t('taxi.card.paid_cancel', 'Платная отмена')) ?></div>
        <div class="fs-5 fw-semibold"><?= number_format($totalPaidCancelCnt, 0, '.', ' ') ?></div>
        <div class="text-muted small"><?= number_format($totalPaidCancelSum, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small"><?= htmlspecialchars($t->t('taxi.card.returned', 'Возвращена')) ?></div>
        <div class="fs-5 fw-semibold"><?= number_format($totalReturnedCnt, 0, '.', ' ') ?></div>
        <div class="text-muted small"><?= number_format($totalReturnedSum, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small"><?= htmlspecialchars($t->t('taxi.card.duplicate', 'Ikki marta jonatilgan')) ?></div>
        <div class="fs-5 fw-semibold">
          <?php
            $dupCnt = 0;
            $dupSum = 0.0;
            foreach ($stats as $s) { $dupCnt += (int)$s['duplicate_3h_count']; $dupSum += (float)$s['duplicate_3h_sum_total']; }
            echo number_format($dupCnt, 0, '.', ' ');
          ?>
        </div>
        <div class="text-muted small"><?= number_format($dupSum, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('taxi.by_restaurant', "Restaurant bo‘yicha")) ?></h2>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
            <tr>
              <th><?= htmlspecialchars($t->t('common.restaurant', 'Restaurant')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('taxi.th.trips', "Po‘ezdka")) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('bugungi.expenses.waiting', 'Kutish')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('taxi.card.paid_cancel', 'Платная отмена')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('taxi.th.paid_cancel_sum', 'Отмена сумма')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('taxi.th.returned_cnt', 'Возврат')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('taxi.th.returned_sum', 'Возврат сумма')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('taxi.roundtrip', 'Tuda-obratno')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('taxi.duplicate', 'Ikki marta')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('taxi.th.duplicate_sum', 'Ikki marta summa')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($stats as $s): ?>
              <tr>
                <td><?= htmlspecialchars((string)$s['restaurant_name']) ?></td>
                <td class="text-end"><?= number_format((int)$s['trips_count'], 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((float)$s['sum_total'], 2, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((float)$s['sum_waiting'], 2, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((int)($s['paid_cancel_count'] ?? 0), 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((float)($s['paid_cancel_sum'] ?? 0), 2, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((int)($s['returned_count'] ?? 0), 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((float)($s['returned_sum'] ?? 0), 2, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((int)$s['roundtrip_count'], 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((int)$s['duplicate_3h_count'], 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((float)$s['duplicate_3h_sum_total'], 2, '.', ' ') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$stats): ?>
              <tr><td colspan="11" class="text-muted"><?= htmlspecialchars($t->t('taxi.empty', "Bu sanada import qilinmagan.")) ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0"><?= htmlspecialchars($t->t('taxi.millennium.title', "Millennium taxi (qo‘lda)")) ?></h2>
        </div>
        <form method="post" action="?page=taxi&action=millennium_save&date=<?= urlencode($date) ?>" class="row g-2 mt-2">
          <div class="col-12">
            <label class="form-label"><?= htmlspecialchars($t->t('common.restaurant', 'Restaurant')) ?></label>
            <?php if ($restaurantOptions): ?>
              <select class="form-select" name="restaurant_name" required>
                <option value=""><?= htmlspecialchars($t->t('common.select', 'Tanlang')) ?></option>
                <?php foreach ($restaurantOptions as $r): ?>
                  <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input class="form-control" name="restaurant_name" placeholder="<?= htmlspecialchars($t->t('errors.restaurant_name', 'Restaurant nomi')) ?>" required>
              <div class="form-text"><?= htmlspecialchars($t->t('taxi.millennium.no_options', "Agar drop-down bo‘sh bo‘lsa: Settings → Taxi mapping ni kiriting.")) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-6">
            <label class="form-label"><?= htmlspecialchars($t->t('taxi.millennium.trips_count', "Po‘ezdka soni")) ?></label>
            <input class="form-control" name="trips_count" value="0" required>
          </div>
          <div class="col-6">
            <label class="form-label"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></label>
            <input class="form-control" name="sum_total" value="0" required>
          </div>
          <div class="col-12">
            <label class="form-label"><?= htmlspecialchars($t->t('common.note', 'Izoh')) ?></label>
            <input class="form-control" name="note" value="">
          </div>
          <div class="col-12">
            <button class="btn btn-success" type="submit"><?= htmlspecialchars($t->t('common.save', 'Saqlash')) ?></button>
          </div>
        </form>

        <hr>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th><?= htmlspecialchars($t->t('common.restaurant', 'Restaurant')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.count', 'Cnt')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></th><th><?= htmlspecialchars($t->t('common.note', 'Izoh')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($millennium as $m): ?>
              <tr>
                <td><?= htmlspecialchars((string)$m['restaurant_name']) ?></td>
                <td class="text-end"><?= number_format((int)$m['trips_count'], 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((float)$m['sum_total'], 2, '.', ' ') ?></td>
                <td class="text-muted small"><?= htmlspecialchars((string)($m['note'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$millennium): ?>
              <tr><td colspan="4" class="text-muted"><?= htmlspecialchars($t->t('taxi.millennium.empty', "Hozircha ma'lumot yo‘q.")) ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0"><?= htmlspecialchars($t->t('taxi.couriers.title', "Our couriers")) ?></h2>
        </div>
        <form method="post" action="?page=taxi&action=courier_save&date=<?= urlencode($date) ?>" class="row g-2 mt-2">
          <div class="col-12">
            <label class="form-label"><?= htmlspecialchars($t->t('common.restaurant', 'Restaurant')) ?></label>
            <?php if ($restaurantOptions): ?>
              <select class="form-select" name="restaurant_name" required>
                <option value=""><?= htmlspecialchars($t->t('common.select', 'Tanlang')) ?></option>
                <?php foreach ($restaurantOptions as $r): ?>
                  <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input class="form-control" name="restaurant_name" placeholder="<?= htmlspecialchars($t->t('errors.restaurant_name', 'Restaurant nomi')) ?>" required>
            <?php endif; ?>
          </div>
          <div class="col-6">
            <label class="form-label"><?= htmlspecialchars($t->t('taxi.couriers.trips_count', "Po‘ezdka soni")) ?></label>
            <input class="form-control" name="trips_count" value="0" required>
          </div>
          <div class="col-6">
            <label class="form-label"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></label>
            <input class="form-control" name="sum_total" value="0" required>
          </div>
          <div class="col-12">
            <label class="form-label"><?= htmlspecialchars($t->t('common.note', 'Izoh')) ?></label>
            <input class="form-control" name="note" value="">
          </div>
          <div class="col-12">
            <button class="btn btn-success" type="submit"><?= htmlspecialchars($t->t('common.save', 'Saqlash')) ?></button>
          </div>
        </form>

        <hr>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th><?= htmlspecialchars($t->t('common.restaurant', 'Restaurant')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.count', 'Cnt')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></th><th><?= htmlspecialchars($t->t('common.note', 'Izoh')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($couriers as $c): ?>
              <tr>
                <td><?= htmlspecialchars((string)$c['restaurant_name']) ?></td>
                <td class="text-end"><?= number_format((int)($c['trips_count'] ?? 0), 0, '.', ' ') ?></td>
                <td class="text-end"><?= number_format((float)($c['sum_total'] ?? 0), 2, '.', ' ') ?></td>
                <td class="text-muted small"><?= htmlspecialchars((string)($c['note'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$couriers): ?>
              <tr><td colspan="4" class="text-muted"><?= htmlspecialchars($t->t('taxi.couriers.empty', "Hozircha ma'lumot yo‘q.")) ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <?php if ($dupByRestaurant): ?>
    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-body">
          <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('taxi.duplicate_by_restaurant', 'Ikki marta jonatilgan (restaurant)')) ?></h2>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th><?= htmlspecialchars($t->t('common.restaurant', 'Restaurant')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.count', 'Cnt')) ?></th><th class="text-end"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></th></tr></thead>
              <tbody>
              <?php foreach ($dupByRestaurant as $r): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$r['restaurant_name']) ?></td>
                  <td class="text-end"><?= number_format((int)$r['cnt'], 0, '.', ' ') ?></td>
                  <td class="text-end"><?= number_format((float)$r['sum_total'], 2, '.', ' ') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

