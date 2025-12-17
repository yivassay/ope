<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var array $operators */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 m-0">Operatorlar</h1>
  <?php if (($auth->role() ?? '') === 'admin'): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newOperator">Yangi operator</button>
  <?php endif; ?>
</div>

<?php if (($auth->role() ?? '') === 'admin'): ?>
  <div class="collapse mb-3" id="newOperator">
    <div class="card">
      <div class="card-body">
        <form method="post" action="/?page=operators&action=save" class="row g-2">
          <input type="hidden" name="id" value="0">
          <div class="col-12 col-lg-4">
            <input class="form-control" name="name" placeholder="Ism" required>
          </div>
          <div class="col-6 col-lg-3">
            <input class="form-control" name="fixed_salary" placeholder="Fix (so'm)" value="0">
          </div>
          <div class="col-6 col-lg-3">
            <input class="form-control" name="percent_rate" placeholder="%" value="0">
          </div>
          <div class="col-12 col-lg-2 d-flex align-items-center gap-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" checked>
              <label class="form-check-label">Active</label>
            </div>
            <button class="btn btn-success btn-sm" type="submit">Saqlash</button>
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
        <th>Ism</th>
        <th>Fix</th>
        <th>%</th>
        <th>Status</th>
        <?php if (($auth->role() ?? '') === 'admin'): ?><th></th><?php endif; ?>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($operators as $op): ?>
        <tr>
          <td><?= htmlspecialchars((string)$op['name']) ?></td>
          <td><?= htmlspecialchars((string)$op['fixed_salary']) ?></td>
          <td><?= htmlspecialchars((string)$op['percent_rate']) ?></td>
          <td><?= ((int)$op['is_active'] === 1) ? 'Active' : 'Off' ?></td>
          <?php if (($auth->role() ?? '') === 'admin'): ?>
            <td class="text-end">
              <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#edit<?= (int)$op['id'] ?>">Edit</button>
            </td>
          <?php endif; ?>
        </tr>
        <?php if (($auth->role() ?? '') === 'admin'): ?>
          <tr class="collapse" id="edit<?= (int)$op['id'] ?>">
            <td colspan="5">
              <form method="post" action="/?page=operators&action=save" class="row g-2">
                <input type="hidden" name="id" value="<?= (int)$op['id'] ?>">
                <div class="col-12 col-lg-4">
                  <input class="form-control" name="name" value="<?= htmlspecialchars((string)$op['name']) ?>" required>
                </div>
                <div class="col-6 col-lg-3">
                  <input class="form-control" name="fixed_salary" value="<?= htmlspecialchars((string)$op['fixed_salary']) ?>">
                </div>
                <div class="col-6 col-lg-3">
                  <input class="form-control" name="percent_rate" value="<?= htmlspecialchars((string)$op['percent_rate']) ?>">
                </div>
                <div class="col-12 col-lg-2 d-flex align-items-center gap-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" <?= ((int)$op['is_active'] === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label">Active</label>
                  </div>
                  <button class="btn btn-success btn-sm" type="submit">Saqlash</button>
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

