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
    <h1 class="h4 m-0"><?= htmlspecialchars($t->t('operator_sales.title', 'Operator savdo')) ?></h1>
    <div class="text-muted small"><?= htmlspecialchars($t->t('common.day', 'Kun')) ?>: <?= htmlspecialchars($date) ?></div>
  </div>
  <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="operator_sales">
    <input class="form-control form-control-sm" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button class="btn btn-outline-secondary btn-sm" type="submit"><?= htmlspecialchars($t->t('common.show', 'Ko‘rsatish')) ?></button>
  </form>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead>
      <tr>
        <th><?= htmlspecialchars($t->t('operator_sales.col.operator', 'Operator')) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.fixed', 'Fix')) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.percent', '%')) ?></th>
        <th><?= htmlspecialchars($t->t('operator_sales.col.mode', 'Rejim')) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.sales_sum', "Savdo (so'm)")) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.order_count', 'Buyurtma soni')) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.manual_salary', 'Logist ish haqi')) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.salary_calc', 'Ish haqi (hisob)')) ?></th>
        <th><?= htmlspecialchars($t->t('operator_sales.col.note', 'Izoh')) ?></th>
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
          $roleMode = $sale ? (string)($sale['role_mode'] ?? 'operator') : 'operator';
          $manualSalary = $sale ? (float)($sale['manual_salary'] ?? 0) : 0.0;
          $fixed = (float)$op['fixed_salary'];
          $pct = (float)$op['percent_rate'];
          $salary = ($roleMode === 'logistic') ? $manualSalary : ($fixed + ($salesSum * $pct / 100.0));
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
          <td><?= htmlspecialchars(($roleMode === 'logistic') ? $t->t('mode.logistic', 'Logist') : $t->t('mode.operator', 'Operator')) ?></td>
          <td class="text-end"><?= number_format($salesSum, 2, '.', ' ') ?></td>
          <td class="text-end"><?= (int)$orderCount ?></td>
          <td class="text-end"><?= number_format($manualSalary, 2, '.', ' ') ?></td>
          <td class="text-end fw-semibold"><?= number_format($salary, 2, '.', ' ') ?></td>
          <td class="text-muted small"><?= htmlspecialchars($note) ?></td>
          <td class="text-end">
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#editSale<?= $opId ?>"><?= htmlspecialchars($t->t('operator_sales.action.enter', 'Kiritish')) ?></button>
          </td>
        </tr>
        <tr class="collapse" id="editSale<?= $opId ?>">
          <td colspan="10">
            <form method="post" action="?page=operator_sales&action=save&date=<?= urlencode($date) ?>" class="row g-2">
              <input type="hidden" name="operator_id" value="<?= $opId ?>">
              <div class="col-12 col-lg-3">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.mode', 'Rejim')) ?></label>
                <select class="form-select" name="role_mode">
                  <option value="operator" <?= ($roleMode === 'operator') ? 'selected' : '' ?>><?= htmlspecialchars($t->t('mode.operator', 'Operator')) ?></option>
                  <option value="logistic" <?= ($roleMode === 'logistic') ? 'selected' : '' ?>><?= htmlspecialchars($t->t('mode.logistic', 'Logist')) ?></option>
                </select>
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.sales_sum', "Savdo (so'm)")) ?></label>
                <input class="form-control" name="sales_sum" value="<?= htmlspecialchars((string)$salesSum) ?>" required>
              </div>
              <div class="col-12 col-lg-3">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.order_count', 'Buyurtma soni')) ?> (<?= htmlspecialchars($t->t('common.optional', 'ixtiyoriy')) ?>)</label>
                <input class="form-control" name="order_count" value="<?= htmlspecialchars((string)$orderCount) ?>">
              </div>
              <div class="col-12 col-lg-2">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.manual_salary', 'Logist ish haqi')) ?></label>
                <input class="form-control" name="manual_salary" value="<?= htmlspecialchars((string)$manualSalary) ?>">
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.note', 'Izoh')) ?></label>
                <input class="form-control" name="note" value="<?= htmlspecialchars($note) ?>">
              </div>
              <div class="col-12 col-lg-1 d-flex align-items-end">
                <button class="btn btn-success w-100" type="submit"><?= htmlspecialchars($t->t('common.save', 'Saqlash')) ?></button>
              </div>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  // Toggle fields based on role_mode inside each edit form
  document.querySelectorAll('tr[id^="editSale"] form').forEach((form) => {
    const select = form.querySelector('select[name="role_mode"]');
    const sales = form.querySelector('input[name="sales_sum"]')?.closest('.col-12');
    const orders = form.querySelector('input[name="order_count"]')?.closest('.col-12');
    const manual = form.querySelector('input[name="manual_salary"]')?.closest('.col-12');
    const salesInput = form.querySelector('input[name="sales_sum"]');
    const manualInput = form.querySelector('input[name="manual_salary"]');

    function sync() {
      const mode = select?.value || 'operator';
      const isLogist = mode === 'logistic';

      if (sales) sales.classList.toggle('d-none', isLogist);
      if (orders) orders.classList.toggle('d-none', isLogist);
      if (manual) manual.classList.toggle('d-none', !isLogist);

      if (salesInput) salesInput.required = !isLogist;
      if (manualInput) manualInput.required = isLogist;
    }

    select?.addEventListener('change', sync);
    sync();
  });
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

