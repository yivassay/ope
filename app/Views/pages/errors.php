<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $date */
/** @var string|null $message */
/** @var string|null $error */
/** @var array $operators */
/** @var array $restaurantMap */
/** @var array $items */
/** @var float $totalSum */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 m-0"><?= htmlspecialchars($t->t('errors.title', 'Xatolar (dostavka)')) ?></h1>
    <div class="text-muted small"><?= htmlspecialchars($t->t('common.day', 'Kun')) ?>: <?= htmlspecialchars($date) ?></div>
  </div>
  <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="errors">
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
    <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('errors.add', "Xato qo‘shish")) ?></h2>
    <form method="post" action="?page=errors&action=add&date=<?= urlencode($date) ?>" class="row g-2" id="errForm">
      <div class="col-12 col-lg-3">
        <label class="form-label"><?= htmlspecialchars($t->t('errors.target', "Kim bo‘yicha")) ?></label>
        <select class="form-select" name="target_type" id="target_type">
          <option value="operator"><?= htmlspecialchars($t->t('errors.target.operator', 'Operator')) ?></option>
          <option value="restaurant"><?= htmlspecialchars($t->t('errors.target.restaurant', 'Filial')) ?></option>
        </select>
      </div>

      <div class="col-12 col-lg-4" id="operator_block">
        <label class="form-label"><?= htmlspecialchars($t->t('errors.operator', 'Operator')) ?></label>
        <select class="form-select" name="operator_id">
          <option value=""><?= htmlspecialchars($t->t('common.select', 'Tanlang')) ?></option>
          <?php foreach ($operators as $op): ?>
            <option value="<?= (int)$op['id'] ?>"><?= htmlspecialchars((string)$op['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-lg-4 d-none" id="restaurant_block">
        <label class="form-label"><?= htmlspecialchars($t->t('errors.restaurant', 'Restaurant')) ?></label>
        <?php if ($restaurantMap): ?>
          <select class="form-select" name="restaurant_id">
            <option value=""><?= htmlspecialchars($t->t('common.select', 'Tanlang')) ?></option>
            <?php foreach ($restaurantMap as $id => $name): ?>
              <option value="<?= htmlspecialchars((string)$id) ?>"><?= htmlspecialchars((string)$name) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input class="form-control" name="restaurant_name" placeholder="<?= htmlspecialchars($t->t('errors.restaurant_name', 'Restaurant nomi')) ?>">
        <?php endif; ?>
      </div>

      <div class="col-12 col-lg-2">
        <label class="form-label"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></label>
        <input class="form-control" name="amount" value="0" required>
      </div>

      <div class="col-12">
        <label class="form-label"><?= htmlspecialchars($t->t('errors.comment', 'Komment')) ?></label>
        <input class="form-control" name="comment" placeholder="<?= htmlspecialchars($t->t('errors.comment_ph', 'Izoh...')) ?>">
      </div>

      <div class="col-12">
        <button class="btn btn-danger" type="submit"><?= htmlspecialchars($t->t('errors.add_btn', "Qo‘shish")) ?></button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small"><?= htmlspecialchars($t->t('errors.total_sum', 'Jami xatolar summasi')) ?></div>
        <div class="fs-5 fw-semibold"><?= number_format((float)$totalSum, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('errors.list', "Ro‘yxat")) ?></h2>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
            <tr>
              <th>ID</th>
              <th><?= htmlspecialchars($t->t('errors.col.type', 'Type')) ?></th>
              <th><?= htmlspecialchars($t->t('errors.col.operator', 'Operator')) ?></th>
              <th><?= htmlspecialchars($t->t('errors.col.restaurant', 'Restaurant')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></th>
              <th><?= htmlspecialchars($t->t('errors.col.comment', 'Komment')) ?></th>
              <th><?= htmlspecialchars($t->t('errors.col.time', 'Vaqt')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><?= (int)$it['id'] ?></td>
                <td><?= htmlspecialchars((string)$it['target_type']) ?></td>
                <td><?= htmlspecialchars((string)($it['operator_id'] ?? '')) ?></td>
                <td>
                  <?= htmlspecialchars((string)($it['restaurant_name'] ?? '')) ?>
                  <?php if (!empty($it['restaurant_id'])): ?>
                    <span class="text-muted small">(<?= (int)$it['restaurant_id'] ?>)</span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= number_format((float)$it['amount'], 2, '.', ' ') ?></td>
                <td class="text-muted small"><?= htmlspecialchars((string)($it['comment'] ?? '')) ?></td>
                <td class="text-muted small"><?= htmlspecialchars((string)$it['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
              <tr><td colspan="7" class="text-muted"><?= htmlspecialchars($t->t('common.empty', "Hozircha yo‘q.")) ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const target = document.getElementById('target_type');
  const op = document.getElementById('operator_block');
  const rs = document.getElementById('restaurant_block');
  function sync() {
    const v = target.value;
    if (v === 'operator') {
      op.classList.remove('d-none');
      rs.classList.add('d-none');
    } else {
      op.classList.add('d-none');
      rs.classList.remove('d-none');
    }
  }
  target.addEventListener('change', sync);
  sync();
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

