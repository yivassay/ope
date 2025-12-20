<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var bool $saved */
/** @var bool $wiped */
/** @var string|null $wipeError */
/** @var array $smartomato */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="card">
  <div class="card-body">
    <h1 class="h4 mb-3"><?= htmlspecialchars($t->t('settings.title', 'Sozlamalar')) ?></h1>

    <?php if ($saved): ?>
      <div class="alert alert-success"><?= htmlspecialchars($t->t('settings.saved', 'Saqlandi')) ?></div>
    <?php endif; ?>
    <?php if ($wiped): ?>
      <div class="alert alert-success"><?= htmlspecialchars($t->t('settings.wiped', 'Baza tozalandi (analitika ma’lumotlari)')) ?></div>
    <?php endif; ?>
    <?php if ($wipeError): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($wipeError) ?></div>
    <?php endif; ?>

    <h2 class="h6 mt-3"><?= htmlspecialchars($t->t('settings.section.smartomato', 'Smartomato API')) ?></h2>
    <form method="post" action="?page=settings">
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.smartomato.base_url', 'Base URL')) ?></label>
          <input class="form-control" name="smartomato_base_url" value="<?= htmlspecialchars((string)$smartomato['base_url']) ?>">
          <div class="form-text"><?= htmlspecialchars($t->t('settings.smartomato.base_url_hint', 'Odatda: https://smartomato.ru')) ?></div>
        </div>
        <div class="col-12 col-lg-6">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.smartomato.delivered_status', 'Delivered status')) ?></label>
          <input class="form-control" name="smartomato_delivered_status" value="<?= htmlspecialchars((string)$smartomato['delivered_status']) ?>">
          <div class="form-text"><?= htmlspecialchars($t->t('settings.smartomato.delivered_status_hint', 'Dokda “complete” (dostavleno) ko‘rsatilgan.')) ?></div>
        </div>
        <div class="col-12 col-lg-6">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.smartomato.login', 'Login')) ?></label>
          <input class="form-control" name="smartomato_login" value="<?= htmlspecialchars((string)$smartomato['login']) ?>">
        </div>
        <div class="col-12 col-lg-6">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.smartomato.password', 'Password')) ?></label>
          <input class="form-control" type="password" name="smartomato_password" value="<?= htmlspecialchars((string)$smartomato['password']) ?>">
        </div>
        <div class="col-12 col-lg-6">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.smartomato.per_page', 'per_page')) ?></label>
          <input class="form-control" name="smartomato_per_page" value="<?= htmlspecialchars((string)$smartomato['per_page']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.smartomato.channel_map', 'Channel map (ixtiyoriy)')) ?></label>
          <textarea class="form-control" name="smartomato_channel_map" rows="6" placeholder="marketplace=web&#10;marketplace_mobile=web&#10;mobile_application_android=app&#10;mobile_application_ios=app&#10;foodfox=yandex&#10;wolt=wolt&#10;board=board"><?= htmlspecialchars((string)$smartomato['channel_map']) ?></textarea>
          <div class="form-text"><?= htmlspecialchars($t->t('settings.smartomato.channel_map_hint', 'Format: har qatorda source=channel. Channel: app/web/yandex/wolt/board/other.')) ?></div>
        </div>
      </div>

      <h2 class="h6 mt-4"><?= htmlspecialchars($t->t('settings.section.commission', 'Komissiya (%)')) ?></h2>
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.commission.yandex', 'Yandex Eda')) ?></label>
          <input class="form-control" name="commission_yandex" value="<?= htmlspecialchars((string)$smartomato['commission_yandex']) ?>">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.commission.wolt', 'Wolt')) ?></label>
          <input class="form-control" name="commission_wolt" value="<?= htmlspecialchars((string)$smartomato['commission_wolt']) ?>">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.commission.uzum', 'Uzum')) ?></label>
          <input class="form-control" name="commission_uzum" value="<?= htmlspecialchars((string)$smartomato['commission_uzum']) ?>">
        </div>
      </div>

      <h2 class="h6 mt-4"><?= htmlspecialchars($t->t('settings.section.taxi', 'Taxi (restaurant adres keyword)')) ?></h2>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.taxi.mapping', 'Restaurant mapping')) ?></label>
          <textarea class="form-control" name="taxi_restaurant_keywords" rows="6" placeholder="Mahmood Kebab #1|хадра, массив хадра, 1&#10;Mahmood Kebab #2|кадирий, абдуллы кадыри"><?= htmlspecialchars((string)$smartomato['taxi_restaurant_keywords']) ?></textarea>
          <div class="form-text"><?= htmlspecialchars($t->t('settings.taxi.mapping_hint', 'Format: har qatorda Restaurant nomi|keyword1, keyword2. Keyword “Адрес отправителя” ichida chiqsa shu restaurantga yoziladi.')) ?></div>
        </div>
      </div>

      <h2 class="h6 mt-4"><?= htmlspecialchars($t->t('settings.section.restaurants_map', 'Restaurantlar (ID → nom)')) ?></h2>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.restaurants_map.label', 'Restaurant list')) ?></label>
          <textarea class="form-control" name="restaurants_map" rows="4" placeholder="50206|Mahmood Kebab Hadra&#10;50213|Mahmood Kebab Qodiriy"><?= htmlspecialchars((string)$smartomato['restaurants_map']) ?></textarea>
          <div class="form-text"><?= htmlspecialchars($t->t('settings.restaurants_map.hint', 'Format: har qatorda restaurant_id|restaurant_name.')) ?></div>
        </div>
      </div>

      <h2 class="h6 mt-4"><?= htmlspecialchars($t->t('settings.section.telegram', 'Telegram (hisobot yuborish)')) ?></h2>
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.telegram.token', 'Bot token')) ?></label>
          <input class="form-control" name="telegram_bot_token" value="<?= htmlspecialchars((string)$smartomato['telegram_bot_token']) ?>">
        </div>
        <div class="col-12 col-lg-6">
          <label class="form-label"><?= htmlspecialchars($t->t('settings.telegram.chat_id', 'Group chat_id')) ?></label>
          <input class="form-control" name="telegram_chat_id" value="<?= htmlspecialchars((string)$smartomato['telegram_chat_id']) ?>">
          <div class="form-text"><?= htmlspecialchars($t->t('settings.telegram.chat_id_hint', 'Masalan: -1001234567890')) ?></div>
        </div>
      </div>

      <div class="mt-3">
        <button class="btn btn-primary" type="submit"><?= htmlspecialchars($t->t('common.save', 'Saqlash')) ?></button>
      </div>
    </form>

    <hr class="my-4">
    <h2 class="h6 text-danger"><?= htmlspecialchars($t->t('settings.danger', 'Xavfli amal: Bazani tozalash')) ?></h2>
    <p class="text-muted small mb-2">
      <?= htmlspecialchars($t->t('settings.wipe.desc', "Bu tugma barcha analitika ma’lumotlarini o‘chiradi (Smartomato, Operator savdo, Boshqalar, Taxi).")) ?>
      <strong><?= htmlspecialchars($t->t('settings.wipe.keep', 'Foydalanuvchilar va Sozlamalar')) ?></strong> <?= htmlspecialchars($t->t('settings.wipe.keep2', "o‘chirilmaydi.")) ?>
    </p>
    <form method="post" action="?page=settings&action=wipe" class="row g-2 align-items-end">
      <div class="col-12 col-lg-4">
        <label class="form-label"><?= htmlspecialchars($t->t('settings.confirm', 'Tasdiqlash')) ?></label>
        <input class="form-control" name="confirm_text" placeholder="DELETE" required>
        <div class="form-text"><?= htmlspecialchars($t->t('settings.wipe.confirm_hint', 'Davom etish uchun aniq DELETE deb yozing.')) ?></div>
      </div>
      <div class="col-12 col-lg-3">
        <button class="btn btn-danger w-100" type="submit" onclick="return confirm('Haqiqatan ham barcha analitika ma\\'lumotlarini o\\'chirmoqchimisiz?');">
          <?= htmlspecialchars($t->t('settings.wipe_btn', 'Bazani tozalash')) ?>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

