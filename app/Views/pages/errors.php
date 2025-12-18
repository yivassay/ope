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
    <h1 class="h4 m-0">Xatolar (dostavka)</h1>
    <div class="text-muted small">Kun: <?= htmlspecialchars($date) ?></div>
  </div>
  <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="errors">
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
    <h2 class="h6 mb-2">Xato qo‘shish</h2>
    <form method="post" action="?page=errors&action=add&date=<?= urlencode($date) ?>" class="row g-2" id="errForm">
      <div class="col-12 col-lg-3">
        <label class="form-label">Kim bo‘yicha</label>
        <select class="form-select" name="target_type" id="target_type">
          <option value="operator">Operator</option>
          <option value="restaurant">Filial</option>
        </select>
      </div>

      <div class="col-12 col-lg-4" id="operator_block">
        <label class="form-label">Operator</label>
        <select class="form-select" name="operator_id">
          <option value="">Tanlang</option>
          <?php foreach ($operators as $op): ?>
            <option value="<?= (int)$op['id'] ?>"><?= htmlspecialchars((string)$op['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-lg-4 d-none" id="restaurant_block">
        <label class="form-label">Restaurant</label>
        <?php if ($restaurantMap): ?>
          <select class="form-select" name="restaurant_id">
            <option value="">Tanlang</option>
            <?php foreach ($restaurantMap as $id => $name): ?>
              <option value="<?= htmlspecialchars((string)$id) ?>"><?= htmlspecialchars((string)$name) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input class="form-control" name="restaurant_name" placeholder="Restaurant nomi">
        <?php endif; ?>
      </div>

      <div class="col-12 col-lg-2">
        <label class="form-label">Summa</label>
        <input class="form-control" name="amount" value="0" required>
      </div>

      <div class="col-12">
        <label class="form-label">Komment</label>
        <input class="form-control" name="comment" placeholder="Izoh...">
      </div>

      <div class="col-12">
        <button class="btn btn-danger" type="submit">Qo‘shish</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-body py-3">
        <div class="text-muted small">Jami xatolar summasi</div>
        <div class="fs-5 fw-semibold"><?= number_format((float)$totalSum, 2, '.', ' ') ?></div>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h6 mb-2">Ro‘yxat</h2>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
            <tr>
              <th>ID</th>
              <th>Type</th>
              <th>Operator</th>
              <th>Restaurant</th>
              <th class="text-end">Summa</th>
              <th>Komment</th>
              <th>Vaqt</th>
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
              <tr><td colspan="7" class="text-muted">Hozircha yo‘q.</td></tr>
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

