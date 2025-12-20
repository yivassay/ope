<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string $yesterday */
/** @var bool $smartomatoConfigured */
/** @var string|null $message */
/** @var string|null $error */
/** @var array|null $debug */
/** @var array $runs */
/** @var array $stats */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="card">
  <div class="card-body">
    <h1 class="h4"><?= htmlspecialchars($t->t('smartomato.title', 'Smartomato')) ?></h1>
    <p class="text-muted"><?= htmlspecialchars($t->t('smartomato.desc', 'Bu modul har kuni 01:30 (Toshkent vaqti) da “dostavleno” buyurtmalarni yig‘adi va kunlik agregatlarni bazaga yozadi.')) ?></p>

    <?php if (($auth->role() ?? '') === 'admin' && !$smartomatoConfigured): ?>
      <div class="alert alert-warning mb-3">
        <?= htmlspecialchars($t->t('smartomato.not_configured', 'Smartomato login/parol sozlanmagan.')) ?>
        <a href="?page=settings"><?= htmlspecialchars($t->t('nav.settings', 'Sozlamalar')) ?></a>
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
          <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('smartomato.manual', "Qo‘lda yig‘ish (test)")) ?></h2>
          <form method="post" action="?page=smartomato&action=fetch" class="row g-2 align-items-end">
            <div class="col-12 col-lg-3">
              <label class="form-label"><?= htmlspecialchars($t->t('common.date', 'Sana')) ?></label>
              <input class="form-control" type="date" name="date" value="<?= htmlspecialchars($yesterday) ?>" required>
              <div class="form-text"><?= htmlspecialchars($t->t('smartomato.usually_yesterday', 'Odatda kechagi sana')) ?></div>
            </div>
            <div class="col-12 col-lg-3">
              <button class="btn btn-primary w-100" type="submit"><?= htmlspecialchars($t->t('smartomato.fetch', "Yig‘ish")) ?></button>
            </div>
          </form>
          <div class="form-text mt-2"><?= htmlspecialchars($t->t('smartomato.manual_hint', "Bu tugma cron/SSH bo‘lmasa ham ishlaydi (admin only).")) ?></div>
        </div>
      </div>

      <div class="card bg-light border-0 mb-3">
        <div class="card-body">
          <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('smartomato.debug', 'Debug: source / payment_source')) ?></h2>
          <form method="post" action="?page=smartomato&action=debug" class="row g-2 align-items-end">
            <div class="col-12 col-lg-3">
              <label class="form-label"><?= htmlspecialchars($t->t('common.date', 'Sana')) ?></label>
              <input class="form-control" type="date" name="date" value="<?= htmlspecialchars($yesterday) ?>" required>
            </div>
            <div class="col-12 col-lg-3">
              <button class="btn btn-outline-primary w-100" type="submit"><?= htmlspecialchars($t->t('common.show', 'Ko‘rsatish')) ?></button>
            </div>
          </form>
          <div class="form-text mt-2">
            <?= htmlspecialchars($t->t('smartomato.debug_hint', "Bu yerda real qiymatlar chiqadi: source va payment_source qanday kelishini tekshiramiz.")) ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (is_array($debug ?? null)): ?>
  <div class="row g-3 mt-0">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <h2 class="h6 mb-2"><?= htmlspecialchars($t->t('smartomato.debug_result', 'Debug natija')) ?>: <?= htmlspecialchars((string)$debug['date']) ?> (orders_seen=<?= (int)$debug['orders_seen'] ?>)</h2>
          <div class="row g-3">
            <div class="col-12 col-lg-4">
              <h3 class="h6">Delivery / Pickup</h3>
              <ul class="mb-0">
                <li>delivery: <?= (int)($debug['takeaway']['delivery'] ?? 0) ?></li>
                <li>pickup: <?= (int)($debug['takeaway']['pickup'] ?? 0) ?></li>
              </ul>
            </div>
            <div class="col-12 col-lg-4">
              <h3 class="h6">delivery/shipping keys (examples)</h3>
              <?php
                $dk = $debug['delivery_keys_top'] ?? [];
                if (is_array($dk)) arsort($dk);
              ?>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead><tr><th>key</th><th class="text-end">cnt</th></tr></thead>
                  <tbody>
                  <?php foreach (array_slice($dk, 0, 12, true) as $k => $v): ?>
                    <tr><td><code><?= htmlspecialchars((string)$k) ?></code></td><td class="text-end"><?= (int)$v ?></td></tr>
                  <?php endforeach; ?>
                  <?php if (!$dk): ?>
                    <tr><td colspan="2" class="text-muted">no delivery/shipping keys in listOrders examples</td></tr>
                  <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <h3 class="h6">source (top)</h3>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead><tr><th>source</th><th class="text-end">cnt</th></tr></thead>
                  <tbody>
                  <?php foreach (($debug['sources'] ?? []) as $k => $v): ?>
                    <tr><td><code><?= htmlspecialchars((string)$k) ?></code></td><td class="text-end"><?= (int)$v ?></td></tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="col-12 col-lg-4">
              <h3 class="h6">payment_source (top)</h3>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead><tr><th>payment_source</th><th class="text-end">cnt</th></tr></thead>
                  <tbody>
                  <?php foreach (($debug['payments'] ?? []) as $k => $v): ?>
                    <tr><td><code><?= htmlspecialchars((string)$k) ?></code></td><td class="text-end"><?= (int)$v ?></td></tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <hr>
          <h3 class="h6"><?= htmlspecialchars($t->t('smartomato.examples', 'Misollar (5 ta)')) ?></h3>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
              <tr>
                <th>ID</th>
                <th>created_at</th>
                <th>restaurant_id</th>
                <th>takeaway</th>
                <th>source</th>
                <th>payment_source</th>
                <th class="text-end">final_sum</th>
                <th>delivery_like(list)</th>
                <th>payment_id(list)</th>
              </tr>
              </thead>
              <tbody>
              <?php foreach (($debug['examples'] ?? []) as $ex): ?>
                <tr>
                  <td><?= (int)$ex['id'] ?></td>
                  <td class="text-muted small"><?= htmlspecialchars((string)$ex['created_at']) ?></td>
                  <td><?= (int)$ex['restaurant_id'] ?></td>
                  <td><?= (int)$ex['takeaway'] ?></td>
                  <td><code><?= htmlspecialchars((string)$ex['source']) ?></code></td>
                  <td><code><?= htmlspecialchars((string)$ex['payment_source']) ?></code></td>
                  <td class="text-end"><?= number_format((float)$ex['final_sum'], 2, '.', ' ') ?></td>
                  <td class="text-muted small"><code><?= htmlspecialchars(json_encode($ex['delivery_like'] ?? [], JSON_UNESCAPED_UNICODE)) ?></code></td>
                  <td class="text-muted small"><?= htmlspecialchars(is_scalar($ex['payment_id']) ? (string)$ex['payment_id'] : '') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <hr>
          <h3 class="h6"><?= htmlspecialchars($t->t('smartomato.order_details', 'Order details (payments array tekshiruvi)')) ?></h3>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>ID</th><th>source</th><th>payment_source</th><th>delivery_like(detail)</th><th>payment_id(detail)</th><th>payments_count</th></tr></thead>
              <tbody>
              <?php foreach (($debug['details'] ?? []) as $d): ?>
                <tr>
                  <td><?= (int)$d['id'] ?></td>
                  <td><code><?= htmlspecialchars((string)$d['source']) ?></code></td>
                  <td><code><?= htmlspecialchars((string)$d['payment_source']) ?></code></td>
                  <td class="text-muted small"><code><?= htmlspecialchars(json_encode($d['delivery_like'] ?? [], JSON_UNESCAPED_UNICODE)) ?></code></td>
                  <td class="text-muted small"><?= htmlspecialchars(is_scalar($d['payment_id']) ? (string)$d['payment_id'] : '') ?></td>
                  <td><?= htmlspecialchars(is_null($d['payments_count']) ? '-' : (string)$d['payments_count']) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3 mt-0">
  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6"><?= htmlspecialchars($t->t('smartomato.runs', 'Oxirgi ishga tushirishlar')) ?></h2>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
            <tr>
              <th><?= htmlspecialchars($t->t('common.date', 'Sana')) ?></th>
              <th><?= htmlspecialchars($t->t('common.status', 'Status')) ?></th>
              <th><?= htmlspecialchars($t->t('smartomato.message', 'Xabar')) ?></th>
              <th><?= htmlspecialchars($t->t('smartomato.time', 'Vaqt')) ?></th>
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
              <tr><td colspan="4" class="text-muted"><?= htmlspecialchars($t->t('smartomato.runs_empty', "Hozircha run yo‘q (cron ishlamagan yoki qo‘lda bosilmagan).")) ?></td></tr>
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
        <h2 class="h6"><?= htmlspecialchars($t->t('smartomato.stats', 'Oxirgi statistikalar (kun/restoran)')) ?></h2>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
            <tr>
              <th><?= htmlspecialchars($t->t('common.date', 'Sana')) ?></th>
              <th><?= htmlspecialchars($t->t('common.restaurant', 'Restaurant')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('common.orders', 'Buyurtma')) ?></th>
              <th class="text-end"><?= htmlspecialchars($t->t('common.sum', 'Summa')) ?></th>
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
              <tr><td colspan="4" class="text-muted"><?= htmlspecialchars($t->t('smartomato.stats_empty', "Hozircha statistika yo‘q.")) ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

