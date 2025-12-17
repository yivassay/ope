<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $yesterday */
/** @var bool $smartomatoConfigured */
/** @var string|null $message */
/** @var string|null $error */
/** @var array $runs */
/** @var array $stats */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="card">
  <div class="card-body">
    <h1 class="h4">Smartomato</h1>
    <p class="text-muted">
      Bu modul har kuni 01:30 (Toshkent vaqti) da “dostavleno” buyurtmalarni yig‘adi va kunlik agregatlarni bazaga yozadi.
      Keyingi bosqichda bu yerda real statistikalar va filtrlar chiqadi.
    </p>

    <?php if (($auth->role() ?? '') === 'admin' && !$smartomatoConfigured): ?>
      <div class="alert alert-warning mb-3">
        Smartomato login/parol sozlanmagan. Avval <a href="?page=settings">Sozlamalar</a> ga kiring.
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (($auth->role() ?? '') === 'admin'): ?>
      <div class="card bg-light border-0 mb-3">
        <div class="card-body">
          <h2 class="h6 mb-2">Qo‘lda yig‘ish (test)</h2>
          <form method="post" action="?page=smartomato&action=fetch" class="row g-2 align-items-end">
            <div class="col-12 col-lg-3">
              <label class="form-label">Sana</label>
              <input class="form-control" type="date" name="date" value="<?= htmlspecialchars($yesterday) ?>" required>
              <div class="form-text">Odatda kechagi sana</div>
            </div>
            <div class="col-12 col-lg-3">
              <button class="btn btn-primary w-100" type="submit">Yig‘ish</button>
            </div>
          </form>
          <div class="form-text mt-2">Bu tugma cron/SSH bo‘lmasa ham ishlaydi (admin only).</div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mt-0">
  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6">Oxirgi ishga tushirishlar</h2>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
            <tr>
              <th>Sana</th>
              <th>Status</th>
              <th>Xabar</th>
              <th>Vaqt</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($runs as $r): ?>
              <tr>
                <td><?= htmlspecialchars((string)$r['run_date']) ?></td>
                <td><?= htmlspecialchars((string)$r['status']) ?></td>
                <td class="text-muted small"><?= htmlspecialchars((string)$r['message']) ?></td>
                <td class="text-muted small"><?= htmlspecialchars((string)$r['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$runs): ?>
              <tr><td colspan="4" class="text-muted">Hozircha run yo‘q (cron ishlamagan yoki qo‘lda bosilmagan).</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6">Oxirgi statistikalar (kun/restoran)</h2>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
            <tr>
              <th>Sana</th>
              <th>Restaurant</th>
              <th class="text-end">Buyurtma</th>
              <th class="text-end">Summa</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($stats as $s): ?>
              <tr>
                <td><?= htmlspecialchars((string)$s['stat_date']) ?></td>
                <td><?= htmlspecialchars((string)$s['restaurant_id']) ?></td>
                <td class="text-end"><?= (int)$s['cnt'] ?></td>
                <td class="text-end"><?= number_format((float)$s['sum_final'], 2, '.', ' ') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$stats): ?>
              <tr><td colspan="4" class="text-muted">Hozircha statistika yo‘q.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

