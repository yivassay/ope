<?php
/** @var \App\Auth $auth */
/** @var \App\I18n $t */
/** @var bool $smartomatoConfigured */
require __DIR__ . '/../partials/layout_top.php';
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h1 class="h4 mb-1">Dashboard</h1>
            <div class="text-muted small">
              Role: <?= htmlspecialchars($auth->role() ?? '-') ?>
            </div>
          </div>
          <?php if (($auth->role() ?? '') === 'admin' && !$smartomatoConfigured): ?>
            <a class="btn btn-warning" href="/?page=settings">Smartomato sozlash</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6">Kunlik buyurtmalar (namuna grafik)</h2>
        <canvas id="ordersChart" height="140"></canvas>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card">
      <div class="card-body">
        <h2 class="h6">To‘lov turlari (namuna grafik)</h2>
        <canvas id="paymentsChart" height="140"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
  // Placeholder charts; real data will come from daily aggregates
  new Chart(document.getElementById('ordersChart'), {
    type: 'bar',
    data: {
      labels: ['Dush', 'Sesh', 'Chor', 'Pay', 'Jum', 'Shan', 'Yak'],
      datasets: [{label: 'Buyurtmalar', data: [12, 19, 8, 14, 20, 16, 9]}]
    }
  });
  new Chart(document.getElementById('paymentsChart'), {
    type: 'doughnut',
    data: {
      labels: ['Naqd', 'Karta', 'Kuryer karta'],
      datasets: [{data: [55, 35, 10]}]
    }
  });
</script>

<?php require __DIR__ . '/../partials/layout_bottom.php'; ?>

