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
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 m-0">Taxi (Yandex)</h1>
    <div class="text-muted small">Kun: <?= htmlspecialchars($date) ?></div>
  </div>
  <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="taxi">
    <input class="form-control form-control-sm" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button class="btn btn-outline-secondary btn-sm" type="submit">Ko‘rsatish</button>
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
    <h2 class="h6 mb-2">CSV yuklash</h2>
    <form method="post" action="?page=taxi&action=upload&date=<?= urlencode($date) ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
      <div class="col-12 col-lg-6">
        <label class="form-label">CSV fayl</label>
        <input class="form-control" type="file" name="file" accept=".csv,text/csv" required>
        <div class="form-text">CSV ruscha bo‘lsa CP1251 ham bo‘lishi mumkin — avtomatik UTF-8 ga o‘tkazamiz.</div>
      </div>
      <div class="col-12 col-lg-3">
        <button class="btn btn-primary w-100" type="submit">Yuklash va hisoblash</button>
      </div>
    </form>
    <?php if ($import): ?>
      <div class="text-muted small mt-2">
        Oxirgi import: <?= htmlspecialchars((string)$import['created_at']) ?> —
        <?= htmlspecialchars((string)$import['original_filename']) ?> —
        <strong><?= htmlspecialchars((string)$import['status']) ?></strong>
        <?= htmlspecialchars((string)$import['message']) ?>
      </div>
    <?php endif; ?>

    <?php if (($auth->role() ?? '') === 'admin' && trim($mappingRaw) === ''): ?>
      <div class="alert alert-warning mt-3 mb-0">
        Restaurant adres keyword sozlanmagan. Admin → <a href="?page=settings">Sozlamalar</a> → Taxi bo‘limida kiriting.
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Po‘ezdka (count)</div>
        <div class="fs-5 fw-semibold"><?= number_format($totalTrips, 0, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Summa (jami)</div>
        <div class="fs-5 fw-semibold"><?= number_format($totalSum + $totalPaidCancelSum + $totalReturnedSum, 2, '.', ' ') ?></div>
        <div class="text-muted small">(+ платная отмена + возврат)</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Pulatli kutish (jami)</div>
        <div class="fs-5 fw-semibold"><?= number_format($totalWaiting, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Платная отмена</div>
        <div class="fs-5 fw-semibold"><?= number_format($totalPaidCancelCnt, 0, '.', ' ') ?></div>
        <div class="text-muted small"><?= number_format($totalPaidCancelSum, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Возвращена</div>
        <div class="fs-5 fw-semibold"><?= number_format($totalReturnedCnt, 0, '.', ' ') ?></div>
        <div class="text-muted small"><?= number_format($totalReturnedSum, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Ikki marta jonatilgan</div>
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
        <h2 class="h6 mb-2">Restaurant bo‘yicha</h2>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
            <tr>
              <th>Restaurant</th>
              <th class="text-end">Po‘ezdka</th>
              <th class="text-end">Summa</th>
              <th class="text-end">Kutish</th>
              <th class="text-end">Платная отмена</th>
              <th class="text-end">Отмена сумма</th>
              <th class="text-end">Возврат</th>
              <th class="text-end">Возврат сумма</th>
              <th class="text-end">Tuda-obratno</th>
              <th class="text-end">Ikki marta</th>
              <th class="text-end">Ikki marta summa</th>
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
              <tr><td colspan="11" class="text-muted">Bu sanada import qilinmagan.</td></tr>
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
          <h2 class="h6 mb-2">Ikki marta jonatilgan (restaurant)</h2>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Restaurant</th><th class="text-end">Cnt</th><th class="text-end">Summa</th></tr></thead>
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

