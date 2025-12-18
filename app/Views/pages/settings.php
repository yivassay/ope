<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var bool $saved */
/** @var array $smartomato */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="card">
  <div class="card-body">
    <h1 class="h4 mb-3">Sozlamalar</h1>

    <?php if ($saved): ?>
      <div class="alert alert-success">Saqlandi</div>
    <?php endif; ?>

    <h2 class="h6 mt-3">Smartomato API</h2>
    <form method="post" action="?page=settings">
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <label class="form-label">Base URL</label>
          <input class="form-control" name="smartomato_base_url" value="<?= htmlspecialchars((string)$smartomato['base_url']) ?>">
          <div class="form-text">Odatda: https://smartomato.ru</div>
        </div>
        <div class="col-12 col-lg-6">
          <label class="form-label">Delivered status</label>
          <input class="form-control" name="smartomato_delivered_status" value="<?= htmlspecialchars((string)$smartomato['delivered_status']) ?>">
          <div class="form-text">Dokda “complete” (dostavleno) ko‘rsatilgan.</div>
        </div>
        <div class="col-12 col-lg-6">
          <label class="form-label">Login</label>
          <input class="form-control" name="smartomato_login" value="<?= htmlspecialchars((string)$smartomato['login']) ?>">
        </div>
        <div class="col-12 col-lg-6">
          <label class="form-label">Password</label>
          <input class="form-control" type="password" name="smartomato_password" value="<?= htmlspecialchars((string)$smartomato['password']) ?>">
        </div>
        <div class="col-12 col-lg-6">
          <label class="form-label">per_page</label>
          <input class="form-control" name="smartomato_per_page" value="<?= htmlspecialchars((string)$smartomato['per_page']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Channel map (ixtiyoriy)</label>
          <textarea class="form-control" name="smartomato_channel_map" rows="6" placeholder="marketplace=web&#10;marketplace_mobile=web&#10;mobile_application_android=app&#10;mobile_application_ios=app&#10;foodfox=yandex&#10;wolt=wolt&#10;board=board"><?= htmlspecialchars((string)$smartomato['channel_map']) ?></textarea>
          <div class="form-text">Format: har qatorda <code>source=channel</code>. Channel: app/web/yandex/wolt/board/other.</div>
        </div>
      </div>

      <h2 class="h6 mt-4">Komissiya (%)</h2>
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <label class="form-label">Yandex Eda</label>
          <input class="form-control" name="commission_yandex" value="<?= htmlspecialchars((string)$smartomato['commission_yandex']) ?>">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label">Wolt</label>
          <input class="form-control" name="commission_wolt" value="<?= htmlspecialchars((string)$smartomato['commission_wolt']) ?>">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label">Uzum</label>
          <input class="form-control" name="commission_uzum" value="<?= htmlspecialchars((string)$smartomato['commission_uzum']) ?>">
        </div>
      </div>

      <h2 class="h6 mt-4">Taxi (restaurant adres keyword)</h2>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Restaurant mapping</label>
          <textarea class="form-control" name="taxi_restaurant_keywords" rows="6" placeholder="Mahmood Kebab #1|хадра, массив хадра, 1&#10;Mahmood Kebab #2|кадирий, абдуллы кадыри"><?= htmlspecialchars((string)$smartomato['taxi_restaurant_keywords']) ?></textarea>
          <div class="form-text">Format: har qatorda <code>Restaurant nomi|keyword1, keyword2</code>. Keyword “Адрес отправителя” ichida chiqsa shu restaurantga yoziladi.</div>
        </div>
      </div>

      <div class="mt-3">
        <button class="btn btn-primary" type="submit">Saqlash</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

