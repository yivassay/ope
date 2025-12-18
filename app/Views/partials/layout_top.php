<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
?>
<!doctype html>
<html lang="uz">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($t->t('app.title')) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="?page=dashboard"><?= htmlspecialchars($t->t('app.title')) ?></a>
    <div class="collapse navbar-collapse show">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="?page=dashboard"><?= htmlspecialchars($t->t('nav.dashboard')) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="?page=smartomato"><?= htmlspecialchars($t->t('nav.smartomato')) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="?page=others"><?= htmlspecialchars($t->t('nav.others', 'Boshqalar')) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="?page=taxi"><?= htmlspecialchars($t->t('nav.taxi', 'Taxi')) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="?page=operator_sales"><?= htmlspecialchars($t->t('nav.operator_sales', 'Operator savdo')) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="?page=operators"><?= htmlspecialchars($t->t('nav.operators')) ?></a></li>
        <?php if (($auth->role() ?? '') === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="?page=settings"><?= htmlspecialchars($t->t('nav.settings')) ?></a></li>
        <?php endif; ?>
      </ul>
      <div class="d-flex">
        <a class="btn btn-outline-light btn-sm me-2" href="?page=profile">Profil</a>
        <a class="btn btn-outline-light btn-sm" href="?page=logout"><?= htmlspecialchars($t->t('nav.logout')) ?></a>
      </div>
    </div>
  </div>
</nav>
<main class="container py-4">

