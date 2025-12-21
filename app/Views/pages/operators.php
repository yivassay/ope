<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var array $operators */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 m-0"><?= htmlspecialchars($t->t('operators.title', 'Operatorlar')) ?></h1>
  <?php if (($auth->role() ?? '') === 'admin'): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newOperator"><?= htmlspecialchars($t->t('operators.new', 'Yangi operator')) ?></button>
  <?php endif; ?>
</div>

<?php if (($auth->role() ?? '') === 'admin'): ?>
  <div class="collapse mb-3" id="newOperator">
    <div class="card">
      <div class="card-body">
        <form method="post" action="?page=operators&action=save" class="row g-2">
          <input type="hidden" name="id" value="0">
          <div class="col-12 col-lg-4">
            <input class="form-control" name="name" placeholder="<?= htmlspecialchars($t->t('operators.name', 'Ism')) ?>" required>
          </div>
          <div class="col-6 col-lg-2">
            <input class="form-control" name="fixed_salary_day" placeholder="<?= htmlspecialchars($t->t('operators.fixed_day', "Fix day")) ?>" value="0">
          </div>
          <div class="col-6 col-lg-2">
            <input class="form-control" name="fixed_salary_night" placeholder="<?= htmlspecialchars($t->t('operators.fixed_night', "Fix night")) ?>" value="0">
          </div>
          <div class="col-6 col-lg-2">
            <input class="form-control" name="percent_rate" placeholder="<?= htmlspecialchars($t->t('operators.percent', '%')) ?>" value="0">
          </div>
          <div class="col-6 col-lg-2">
            <input class="form-control" name="guaranteed_salary" placeholder="<?= htmlspecialchars($t->t('operators.guaranteed', 'Zarplata')) ?>" value="0">
          </div>
          <div class="col-12 col-lg-2 d-flex align-items-center gap-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" checked>
              <label class="form-check-label"><?= htmlspecialchars($t->t('common.active', 'Active')) ?></label>
            </div>
            <button class="btn btn-success btn-sm" type="submit"><?= htmlspecialchars($t->t('common.save', 'Saqlash')) ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
      <tr>
        <th><?= htmlspecialchars($t->t('operators.name', 'Ism')) ?></th>
        <th><?= htmlspecialchars($t->t('operators.fixed_day_short', 'Day')) ?></th>
        <th><?= htmlspecialchars($t->t('operators.fixed_night_short', 'Night')) ?></th>
        <th><?= htmlspecialchars($t->t('operators.percent', '%')) ?></th>
        <th><?= htmlspecialchars($t->t('operators.guaranteed_short', 'Zarplata')) ?></th>
        <th><?= htmlspecialchars($t->t('common.status', 'Status')) ?></th>
        <?php if (($auth->role() ?? '') === 'admin'): ?><th></th><?php endif; ?>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($operators as $op): ?>
        <tr>
          <td><?= htmlspecialchars((string)$op['name']) ?></td>
          <td><?= htmlspecialchars((string)($op['fixed_salary_day'] ?? ($op['fixed_salary'] ?? '0'))) ?></td>
          <td><?= htmlspecialchars((string)($op['fixed_salary_night'] ?? ($op['fixed_salary'] ?? '0'))) ?></td>
          <td><?= htmlspecialchars((string)$op['percent_rate']) ?></td>
          <td><?= htmlspecialchars((string)($op['guaranteed_salary'] ?? '0')) ?></td>
          <td><?= ((int)$op['is_active'] === 1) ? htmlspecialchars($t->t('common.active', 'Active')) : htmlspecialchars($t->t('common.off', 'Off')) ?></td>
          <?php if (($auth->role() ?? '') === 'admin'): ?>
            <td class="text-end">
              <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#edit<?= (int)$op['id'] ?>"><?= htmlspecialchars($t->t('common.edit', 'Edit')) ?></button>
            </td>
          <?php endif; ?>
        </tr>
        <?php if (($auth->role() ?? '') === 'admin'): ?>
          <tr class="collapse" id="edit<?= (int)$op['id'] ?>">
            <td colspan="7">
              <form method="post" action="?page=operators&action=save" class="row g-2">
                <input type="hidden" name="id" value="<?= (int)$op['id'] ?>">
                <div class="col-12 col-lg-4">
                  <input class="form-control" name="name" value="<?= htmlspecialchars((string)$op['name']) ?>" required>
                </div>
                <div class="col-6 col-lg-2">
                  <input class="form-control" name="fixed_salary_day" value="<?= htmlspecialchars((string)($op['fixed_salary_day'] ?? ($op['fixed_salary'] ?? '0'))) ?>" placeholder="<?= htmlspecialchars($t->t('operators.fixed_day', 'Fix day')) ?>">
                </div>
                <div class="col-6 col-lg-2">
                  <input class="form-control" name="fixed_salary_night" value="<?= htmlspecialchars((string)($op['fixed_salary_night'] ?? ($op['fixed_salary'] ?? '0'))) ?>" placeholder="<?= htmlspecialchars($t->t('operators.fixed_night', 'Fix night')) ?>">
                </div>
                <div class="col-6 col-lg-2">
                  <input class="form-control" name="percent_rate" value="<?= htmlspecialchars((string)$op['percent_rate']) ?>">
                </div>
                <div class="col-6 col-lg-2">
                  <input class="form-control" name="guaranteed_salary" value="<?= htmlspecialchars((string)($op['guaranteed_salary'] ?? '0')) ?>" placeholder="<?= htmlspecialchars($t->t('operators.guaranteed', 'Zarplata')) ?>">
                </div>
                <div class="col-12 col-lg-2 d-flex align-items-center gap-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" <?= ((int)$op['is_active'] === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label"><?= htmlspecialchars($t->t('common.active', 'Active')) ?></label>
                  </div>
                  <button class="btn btn-success btn-sm" type="submit"><?= htmlspecialchars($t->t('common.save', 'Saqlash')) ?></button>
                </div>
              </form>
            </td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

