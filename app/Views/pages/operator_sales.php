<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $date */
/** @var array $operators */
/** @var array $salesByOperator */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 m-0">Operator savdo</h1>
    <div class="text-muted small">Kun: <?= htmlspecialchars($date) ?></div>
  </div>
  <form class="d-flex gap-2" method="get" action="/">
    <input type="hidden" name="page" value="operator_sales">
    <input class="form-control form-control-sm" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button class="btn btn-outline-secondary btn-sm" type="submit">Ko‘rsatish</button>
  </form>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead>
      <tr>
        <th>Operator</th>
        <th class="text-end">Fix</th>
        <th class="text-end">%</th>
        <th class="text-end">Savdo (so'm)</th>
        <th class="text-end">Buyurtma soni</th>
        <th class="text-end">Ish haqi (hisob)</th>
        <th>Izoh</th>
        <th></th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($operators as $op): ?>
        <?php
          $opId = (int)$op['id'];
          $sale = $salesByOperator[$opId] ?? null;
          $salesSum = $sale ? (float)$sale['sales_sum'] : 0.0;
          $orderCount = $sale ? (int)$sale['order_count'] : 0;
          $note = $sale ? (string)$sale['note'] : '';
          $fixed = (float)$op['fixed_salary'];
          $pct = (float)$op['percent_rate'];
          $salary = $fixed + ($salesSum * $pct / 100.0);
        ?>
        <tr>
          <td>
            <?= htmlspecialchars((string)$op['name']) ?>
            <?php if ((int)$op['is_active'] !== 1): ?>
              <span class="badge text-bg-secondary">Off</span>
            <?php endif; ?>
          </td>
          <td class="text-end"><?= number_format($fixed, 2, '.', ' ') ?></td>
          <td class="text-end"><?= number_format($pct, 3, '.', ' ') ?></td>
          <td class="text-end"><?= number_format($salesSum, 2, '.', ' ') ?></td>
          <td class="text-end"><?= (int)$orderCount ?></td>
          <td class="text-end fw-semibold"><?= number_format($salary, 2, '.', ' ') ?></td>
          <td class="text-muted small"><?= htmlspecialchars($note) ?></td>
          <td class="text-end">
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#editSale<?= $opId ?>">Kiritish</button>
          </td>
        </tr>
        <tr class="collapse" id="editSale<?= $opId ?>">
          <td colspan="8">
            <form method="post" action="/?page=operator_sales&action=save&date=<?= urlencode($date) ?>" class="row g-2">
              <input type="hidden" name="operator_id" value="<?= $opId ?>">
              <div class="col-12 col-lg-4">
                <label class="form-label small mb-1">Savdo (so'm)</label>
                <input class="form-control" name="sales_sum" value="<?= htmlspecialchars((string)$salesSum) ?>" required>
              </div>
              <div class="col-12 col-lg-3">
                <label class="form-label small mb-1">Buyurtma soni (ixtiyoriy)</label>
                <input class="form-control" name="order_count" value="<?= htmlspecialchars((string)$orderCount) ?>">
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label small mb-1">Izoh</label>
                <input class="form-control" name="note" value="<?= htmlspecialchars($note) ?>">
              </div>
              <div class="col-12 col-lg-1 d-flex align-items-end">
                <button class="btn btn-success w-100" type="submit">Saqlash</button>
              </div>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

