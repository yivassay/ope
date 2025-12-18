<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $date */
/** @var array|null $telegram */
/** @var array|null $uzum */
require __DIR__ . '/../partials/layout_top.php';

$tgCount = $telegram ? (int)$telegram['order_count'] : 0;
$tgSum = $telegram ? (float)$telegram['sum_final'] : 0.0;
$tgNote = $telegram ? (string)$telegram['note'] : '';

$uzCount = $uzum ? (int)$uzum['order_count'] : 0;
$uzSum = $uzum ? (float)$uzum['sum_final'] : 0.0;
$uzNote = $uzum ? (string)$uzum['note'] : '';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 m-0">Boshqalar</h1>
    <div class="text-muted small">Kun: <?= htmlspecialchars($date) ?></div>
    <div class="text-muted small">Bu qiymatlar Smartomato umumiy summasiga qo‘shilmaydi (faqat alohida ko‘rsatish uchun).</div>
  </div>
  <form class="d-flex gap-2" method="get" action="">
    <input type="hidden" name="page" value="others">
    <input class="form-control form-control-sm" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button class="btn btn-outline-secondary btn-sm" type="submit">Ko‘rsatish</button>
  </form>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6">Telegram bot (qo‘lda)</h2>
        <form method="post" action="?page=others&action=save&date=<?= urlencode($date) ?>" class="row g-2">
          <input type="hidden" name="kind" value="telegram">
          <div class="col-6">
            <label class="form-label">Buyurtma soni</label>
            <input class="form-control" name="order_count" value="<?= htmlspecialchars((string)$tgCount) ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label">Summa</label>
            <input class="form-control" name="sum_final" value="<?= htmlspecialchars((string)$tgSum) ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Izoh</label>
            <input class="form-control" name="note" value="<?= htmlspecialchars($tgNote) ?>">
          </div>
          <div class="col-12">
            <button class="btn btn-success" type="submit">Saqlash</button>
          </div>
        </form>
        <div class="form-text mt-2">Eslatma: “board=qo‘ng‘iroqlar”ni ajratish uchun Dashboardda board dan Telegram qiymati ayriladi.</div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6">Uzum (qo‘lda)</h2>
        <form method="post" action="?page=others&action=save&date=<?= urlencode($date) ?>" class="row g-2">
          <input type="hidden" name="kind" value="uzum">
          <div class="col-6">
            <label class="form-label">Buyurtma soni</label>
            <input class="form-control" name="order_count" value="<?= htmlspecialchars((string)$uzCount) ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label">Summa</label>
            <input class="form-control" name="sum_final" value="<?= htmlspecialchars((string)$uzSum) ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Izoh</label>
            <input class="form-control" name="note" value="<?= htmlspecialchars($uzNote) ?>">
          </div>
          <div class="col-12">
            <button class="btn btn-success" type="submit">Saqlash</button>
          </div>
        </form>
        <?php if ($uzum === null): ?>
          <div class="form-text mt-2 text-danger">
            Agar bu form saqlanmasa, demak bazada <code>uzum_daily_stats</code> jadvali yo‘q — schema.sql dan qo‘shing.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

