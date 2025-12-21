<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $date */
/** @var array $operators */
/** @var array $entries */
/** @var string|null $error */
/** @var string|null $message */
require __DIR__ . '/../partials/layout_top.php';
?>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger mb-3"><?= htmlspecialchars((string)$error) ?></div>
<?php endif; ?>
<?php if (!empty($message)): ?>
  <div class="alert alert-success mb-3"><?= htmlspecialchars((string)$message) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 m-0"><?= htmlspecialchars($t->t('operator_sales.title', 'Operator savdo')) ?></h1>
    <div class="text-muted small"><?= htmlspecialchars($t->t('common.day', 'Kun')) ?>: <?= htmlspecialchars($date) ?></div>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addEntry">
      <?= htmlspecialchars($t->t('operator_sales.add', 'Add employee')) ?>
    </button>
    <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="operator_sales">
    <input class="form-control form-control-sm" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button class="btn btn-outline-secondary btn-sm" type="submit"><?= htmlspecialchars($t->t('common.show', 'Ko‘rsatish')) ?></button>
    </form>
  </div>
</div>

<div class="collapse mb-3" id="addEntry">
  <div class="card">
    <div class="card-body">
      <form method="post" action="?page=operator_sales&action=save&date=<?= urlencode($date) ?>" class="row g-2 operator-sale-form">
        <div class="col-12 col-lg-3">
          <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.operator', 'Operator')) ?></label>
          <select class="form-select" name="operator_id" required>
            <option value=""><?= htmlspecialchars($t->t('common.select', 'Tanlang')) ?></option>
            <?php foreach ($operators as $op): ?>
              <option value="<?= (int)$op['id'] ?>"><?= htmlspecialchars((string)$op['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.shift', 'Smena')) ?></label>
          <select class="form-select" name="shift" required>
            <option value="day"><?= htmlspecialchars($t->t('shift.day', 'Day')) ?></option>
            <option value="night"><?= htmlspecialchars($t->t('shift.night', 'Night')) ?></option>
          </select>
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.mode', 'Rejim')) ?></label>
          <select class="form-select" name="role_mode">
            <option value="operator"><?= htmlspecialchars($t->t('mode.operator', 'Operator')) ?></option>
            <option value="logistic"><?= htmlspecialchars($t->t('mode.logistic', 'Logist')) ?></option>
          </select>
        </div>
        <div class="col-12 col-lg-3 field-sales">
          <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.sales_sum', "Savdo (so'm)")) ?></label>
          <input class="form-control" name="sales_sum" value="0" required>
        </div>
        <div class="col-12 col-lg-2 field-orders">
          <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.order_count', 'Buyurtma soni')) ?> (<?= htmlspecialchars($t->t('common.optional', 'ixtiyoriy')) ?>)</label>
          <input class="form-control" name="order_count" value="0">
        </div>
        <div class="col-12 col-lg-2 field-manual d-none">
          <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.manual_salary', 'Logist ish haqi')) ?></label>
          <input class="form-control" name="manual_salary" value="0">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.note', 'Izoh')) ?></label>
          <input class="form-control" name="note" value="">
        </div>
        <div class="col-12 col-lg-2 d-flex align-items-end">
          <button class="btn btn-success w-100" type="submit"><?= htmlspecialchars($t->t('common.save', 'Saqlash')) ?></button>
        </div>
      </form>
      <div class="text-muted small mt-2">
        <?= htmlspecialchars($t->t('operator_sales.hint.multi_shift', 'One employee can be added for day and night.')) ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead>
      <tr>
        <th><?= htmlspecialchars($t->t('operator_sales.col.operator', 'Operator')) ?></th>
        <th><?= htmlspecialchars($t->t('operator_sales.col.shift', 'Smena')) ?></th>
        <th><?= htmlspecialchars($t->t('operator_sales.col.mode', 'Rejim')) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.sales_sum', "Savdo (so'm)")) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.order_count', 'Buyurtma soni')) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.manual_salary', 'Logist ish haqi')) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.salary_calc', 'Ish haqi (hisob)')) ?></th>
        <th><?= htmlspecialchars($t->t('operator_sales.col.note', 'Izoh')) ?></th>
        <th class="text-end"><?= htmlspecialchars($t->t('operator_sales.col.actions', 'Actions')) ?></th>
      </tr>
      </thead>
      <tbody>
      <?php foreach (($entries ?? []) as $r): ?>
        <?php
          $opId = (int)($r['operator_id'] ?? 0);
          $shift = (string)($r['shift'] ?? 'day');
          $roleMode = (string)($r['role_mode'] ?? 'operator');
          $salesSum = (float)($r['sales_sum'] ?? 0);
          $orderCount = (int)($r['order_count'] ?? 0);
          $manualSalary = (float)($r['manual_salary'] ?? 0);
          $pct = (float)($r['percent_rate'] ?? 0);
          $guaranteed = (float)($r['guaranteed_salary'] ?? 0);
          $fixedLegacy = (float)($r['fixed_salary'] ?? 0);
          $fixedDay = (float)($r['fixed_salary_day'] ?? 0);
          $fixedNight = (float)($r['fixed_salary_night'] ?? 0);
          if ($fixedDay <= 0) $fixedDay = $fixedLegacy;
          if ($fixedNight <= 0) $fixedNight = $fixedLegacy;
          $fixed = ($shift === 'night') ? $fixedNight : $fixedDay;
          $base = $fixed + ($salesSum * $pct / 100.0);
          $salary = ($roleMode === 'logistic') ? $manualSalary : max($base, $guaranteed);
          $collapseId = 'editEntry_' . $opId . '_' . $shift;
        ?>
        <tr>
          <td><?= htmlspecialchars((string)($r['name'] ?? '')) ?></td>
          <td><?= htmlspecialchars($t->t('shift.' . $shift, $shift)) ?></td>
          <td><?= htmlspecialchars(($roleMode === 'logistic') ? $t->t('mode.logistic', 'Logist') : $t->t('mode.operator', 'Operator')) ?></td>
          <td class="text-end"><?= number_format($salesSum, 2, '.', ' ') ?></td>
          <td class="text-end"><?= (int)$orderCount ?></td>
          <td class="text-end"><?= number_format($manualSalary, 2, '.', ' ') ?></td>
          <td class="text-end fw-semibold"><?= number_format($salary, 2, '.', ' ') ?></td>
          <td class="text-muted small"><?= htmlspecialchars((string)($r['note'] ?? '')) ?></td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapseId) ?>">
                <?= htmlspecialchars($t->t('common.edit', 'Edit')) ?>
              </button>
              <form method="post" action="?page=operator_sales&action=delete&date=<?= urlencode($date) ?>" onsubmit="return confirm('<?= htmlspecialchars($t->t('common.confirm_delete', 'Delete?')) ?>');">
                <input type="hidden" name="operator_id" value="<?= $opId ?>">
                <input type="hidden" name="shift" value="<?= htmlspecialchars($shift) ?>">
                <button class="btn btn-outline-danger" type="submit"><?= htmlspecialchars($t->t('common.delete', 'Delete')) ?></button>
              </form>
            </div>
          </td>
        </tr>
        <tr class="collapse" id="<?= htmlspecialchars($collapseId) ?>">
          <td colspan="9">
            <form method="post" action="?page=operator_sales&action=save&date=<?= urlencode($date) ?>" class="row g-2 operator-sale-form">
              <input type="hidden" name="operator_id" value="<?= $opId ?>">
              <input type="hidden" name="shift" value="<?= htmlspecialchars($shift) ?>">
              <div class="col-12 col-lg-3">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.mode', 'Rejim')) ?></label>
                <select class="form-select" name="role_mode">
                  <option value="operator" <?= ($roleMode === 'operator') ? 'selected' : '' ?>><?= htmlspecialchars($t->t('mode.operator', 'Operator')) ?></option>
                  <option value="logistic" <?= ($roleMode === 'logistic') ? 'selected' : '' ?>><?= htmlspecialchars($t->t('mode.logistic', 'Logist')) ?></option>
                </select>
              </div>
              <div class="col-12 col-lg-4 field-sales">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.sales_sum', "Savdo (so'm)")) ?></label>
                <input class="form-control" name="sales_sum" value="<?= htmlspecialchars((string)$salesSum) ?>" required>
              </div>
              <div class="col-12 col-lg-3 field-orders">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.order_count', 'Buyurtma soni')) ?> (<?= htmlspecialchars($t->t('common.optional', 'ixtiyoriy')) ?>)</label>
                <input class="form-control" name="order_count" value="<?= htmlspecialchars((string)$orderCount) ?>">
              </div>
              <div class="col-12 col-lg-2 field-manual">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.manual_salary', 'Logist ish haqi')) ?></label>
                <input class="form-control" name="manual_salary" value="<?= htmlspecialchars((string)$manualSalary) ?>">
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label small mb-1"><?= htmlspecialchars($t->t('operator_sales.col.note', 'Izoh')) ?></label>
                <input class="form-control" name="note" value="<?= htmlspecialchars((string)($r['note'] ?? '')) ?>">
              </div>
              <div class="col-12 col-lg-2 d-flex align-items-end">
                <button class="btn btn-success w-100" type="submit"><?= htmlspecialchars($t->t('common.save', 'Saqlash')) ?></button>
              </div>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!($entries ?? [])): ?>
        <tr>
          <td colspan="9" class="text-muted"><?= htmlspecialchars($t->t('operator_sales.empty', "Hozircha ma'lumot yo‘q. “Add employee” tugmasi orqali qo‘shing.")) ?></td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  // Toggle fields based on role_mode inside each operator sale form
  document.querySelectorAll('form.operator-sale-form').forEach((form) => {
    const select = form.querySelector('select[name="role_mode"]');
    const salesWrap = form.querySelector('.field-sales');
    const ordersWrap = form.querySelector('.field-orders');
    const manualWrap = form.querySelector('.field-manual');
    const salesInput = form.querySelector('input[name="sales_sum"]');
    const manualInput = form.querySelector('input[name="manual_salary"]');

    function sync() {
      const mode = select?.value || 'operator';
      const isLogist = mode === 'logistic';
      if (salesWrap) salesWrap.classList.toggle('d-none', isLogist);
      if (ordersWrap) ordersWrap.classList.toggle('d-none', isLogist);
      if (manualWrap) manualWrap.classList.toggle('d-none', !isLogist);
      if (salesInput) salesInput.required = !isLogist;
      if (manualInput) manualInput.required = isLogist;
    }
    select?.addEventListener('change', sync);
    sync();
  });
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

