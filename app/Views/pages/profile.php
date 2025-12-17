<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var string|null $error */
/** @var bool $saved */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="card">
  <div class="card-body">
    <h1 class="h4 mb-3">Profil</h1>

    <?php if ($saved): ?>
      <div class="alert alert-success">Parol yangilandi</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="?page=profile" class="row g-3">
      <div class="col-12 col-lg-4">
        <label class="form-label">Joriy parol</label>
        <input class="form-control" type="password" name="current_password" required>
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label">Yangi parol</label>
        <input class="form-control" type="password" name="new_password" required>
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label">Yangi parol (qayta)</label>
        <input class="form-control" type="password" name="new_password2" required>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" type="submit">Saqlash</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

