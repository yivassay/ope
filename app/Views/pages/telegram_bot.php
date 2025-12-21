<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $date */
/** @var array|null $row */
require __DIR__ . '/../partials/layout_top.php';

$orderCount = $row ? (int)$row['order_count'] : 0;
$sumFinal = $row ? (float)$row['sum_final'] : 0.0;
$note = $row ? (string)$row['note'] : '';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 m-0"><?= htmlspecialchars($t->t('telegram_bot.title', "Telegram bot (qo‘lda)")) ?></h1>
    <div class="text-muted small"><?= htmlspecialchars($t->t('common.day', 'Kun')) ?>: <?= htmlspecialchars($date) ?> — <?= htmlspecialchars($t->t('telegram_bot.hint', "bu qiymatlar Smartomato summalariga qo‘shilmaydi (double-count bo‘lmasin).")) ?></div>
  </div>
  <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="telegram_bot">
    <input class="form-control form-control-sm" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button class="btn btn-outline-secondary btn-sm" type="submit"><?= htmlspecialchars($t->t('common.show', 'Ko‘rsatish')) ?></button>
  </form>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" action="?page=telegram_bot&action=save&date=<?= urlencode($date) ?>" class="row g-3">
      <div class="col-12 col-lg-4">
        <label class="form-label"><?= htmlspecialchars($t->t('common.orders_count', 'Buyurtma soni')) ?></label>
        <input class="form-control" name="order_count" value="<?= htmlspecialchars((string)$orderCount) ?>" required>
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label"><?= htmlspecialchars($t->t('telegram_bot.sum', "Summa (so'm)")) ?></label>
        <input class="form-control" name="sum_final" value="<?= htmlspecialchars((string)$sumFinal) ?>" required>
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label"><?= htmlspecialchars($t->t('telegram_bot.note', 'Izoh (ixtiyoriy)')) ?></label>
        <input class="form-control" name="note" value="<?= htmlspecialchars($note) ?>">
      </div>
      <div class="col-12">
        <button class="btn btn-success" type="submit"><?= htmlspecialchars($t->t('common.save', 'Saqlash')) ?></button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

