<?php
/** @var \App\I18n $t */
/** @var string|null $error */
?>
<!doctype html>
<html lang="<?= htmlspecialchars($_SESSION['locale'] ?? 'uz') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($t->t('login.title')) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 480px">
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h4 mb-3"><?= htmlspecialchars($t->t('login.title')) ?></h1>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" action="?page=login">
        <div class="mb-3">
          <label class="form-label"><?= htmlspecialchars($t->t('login.username')) ?></label>
          <input class="form-control" name="username" autocomplete="username" required>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= htmlspecialchars($t->t('login.password')) ?></label>
          <input class="form-control" type="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary w-100" type="submit"><?= htmlspecialchars($t->t('login.submit')) ?></button>
      </form>
    </div>
  </div>
</main>
</body>
</html>

