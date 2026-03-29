<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = getDB();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Sanitize dates
$from = $db->real_escape_string($from);
$to   = $db->real_escape_string($to);

// Revenue by day in range
$dailyRevenue = $db->query("
    SELECT DATE(payment_date) d, COUNT(*) txn, SUM(amount) rev
    FROM payments
    WHERE status='completed'
      AND DATE(payment_date) BETWEEN '$from' AND '$to'
    GROUP BY DATE(payment_date)
    ORDER BY d
");
$revenueData = [];
if ($dailyRevenue) { while ($r = $dailyRevenue->fetch_assoc()) $revenueData[] = $r; }

// Top categories by sales — use parts table, calculate subtotal from quantity * price
$topCats = $db->query("
    SELECT c.category_name,
           COUNT(od.order_detail_id) AS items_sold,
           SUM(od.quantity * od.price) AS revenue
    FROM order_details od
    JOIN parts p ON od.part_id = p.part_id
    JOIN categories c ON p.category_id = c.category_id
    JOIN orders o ON od.order_id = o.order_id
    JOIN payments py ON py.order_id = o.order_id
    WHERE py.status = 'completed'
      AND DATE(py.payment_date) BETWEEN '$from' AND '$to'
    GROUP BY c.category_id
    ORDER BY revenue DESC
    LIMIT 8
");
$catData = [];
if ($topCats) { while ($r = $topCats->fetch_assoc()) $catData[] = $r; }

// Summary stats
$summaryResult = $db->query("
    SELECT COUNT(*) AS total_orders,
           COALESCE(SUM(p.amount), 0) AS total_rev,
           COUNT(DISTINCT o.user_id) AS unique_customers
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.order_id AND p.status = 'completed'
    WHERE DATE(o.order_date) BETWEEN '$from' AND '$to'
");
$summary = $summaryResult
    ? $summaryResult->fetch_assoc()
    : ['total_orders' => 0, 'total_rev' => 0, 'unique_customers' => 0];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Date range filter -->
<form class="flex gap-2 items-center mb-6" method="GET" style="flex-wrap:wrap;">
  <label class="form-label" style="margin:0;white-space:nowrap;">Date Range:</label>
  <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control" style="width:160px;">
  <span class="text-muted">to</span>
  <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control" style="width:160px;">
  <button type="submit" class="btn btn-primary btn-sm">
    <i class="fas fa-chart-bar"></i> Generate Report
  </button>
  <a href="reports.php" class="btn btn-outline btn-sm">This Month</a>
</form>

<!-- Summary Stats -->
<div class="stats-grid mb-6">
  <div class="stat-card green">
    <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <div class="stat-value">KSh <?= number_format($summary['total_rev'] / 1000, 1) ?>K</div>
    <div class="stat-label">Revenue in Period</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon red"><i class="fas fa-shopping-bag"></i></div>
    <div class="stat-value"><?= $summary['total_orders'] ?></div>
    <div class="stat-label">Orders in Period</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
    <div class="stat-value"><?= $summary['unique_customers'] ?></div>
    <div class="stat-label">Unique Customers</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon yellow"><i class="fas fa-receipt"></i></div>
    <div class="stat-value">KSh <?= $summary['total_orders'] > 0
        ? number_format($summary['total_rev'] / $summary['total_orders'])
        : '0' ?></div>
    <div class="stat-label">Avg. Order Value</div>
  </div>
</div>

<!-- Charts -->
<div class="grid-2 mb-6">
  <div class="card">
    <div class="card-header">
      <i class="fas fa-chart-line" style="color:var(--green);"></i>
      <h3>Daily Revenue</h3>
    </div>
    <div class="card-body">
      <?php if (empty($revenueData)): ?>
      <div style="text-align:center;padding:32px;color:var(--muted);">No revenue data for this period.</div>
      <?php else: ?>
      <canvas id="dailyChart" height="200"></canvas>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <i class="fas fa-chart-bar" style="color:var(--blue);"></i>
      <h3>Revenue by Category</h3>
    </div>
    <div class="card-body">
      <?php if (empty($catData)): ?>
      <div style="text-align:center;padding:32px;color:var(--muted);">No category data for this period.</div>
      <?php else: ?>
      <canvas id="catChart" height="200"></canvas>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Category table -->
<?php if (!empty($catData)): ?>
<div class="card">
  <div class="card-header">
    <i class="fas fa-layer-group" style="color:var(--red);"></i>
    <h3>Category Performance</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Category</th>
          <th>Items Sold</th>
          <th>Revenue</th>
          <th>% Share</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $totalCatRev = array_sum(array_column($catData, 'revenue'));
        foreach ($catData as $cat):
            $pct = $totalCatRev > 0 ? round($cat['revenue'] / $totalCatRev * 100, 1) : 0;
        ?>
        <tr>
          <td style="font-weight:600;"><?= sanitize($cat['category_name']) ?></td>
          <td><?= number_format($cat['items_sold']) ?></td>
          <td style="font-weight:700;color:var(--green);">KSh <?= number_format($cat['revenue']) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="background:var(--surface-2);border-radius:4px;height:6px;width:100px;overflow:hidden;">
                <div style="background:var(--red);height:100%;width:<?= $pct ?>%;"></div>
              </div>
              <span class="text-sm"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.color = '#6b6b78';
Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';

<?php if (!empty($revenueData)): ?>
new Chart(document.getElementById('dailyChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($r) => date('d M', strtotime($r['d'])), $revenueData)) ?>,
    datasets: [{
      label: 'Revenue',
      data: <?= json_encode(array_map(fn($r) => (float)$r['rev'], $revenueData)) ?>,
      backgroundColor: 'rgba(230,51,41,0.6)',
      borderColor: '#e63329',
      borderWidth: 1,
      borderRadius: 4,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: {
        ticks: { callback: v => 'KSh ' + Number(v / 1000).toFixed(1) + 'K' },
        grid: { color: 'rgba(255,255,255,0.05)' }
      },
      x: { grid: { display: false } }
    }
  }
});
<?php endif; ?>

<?php if (!empty($catData)): ?>
const catColors = ['#e63329','#3b82f6','#22c55e','#eab308','#a855f7','#06b6d4','#f97316','#ec4899'];
new Chart(document.getElementById('catChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($catData, 'category_name')) ?>,
    datasets: [{
      label: 'Revenue',
      data: <?= json_encode(array_map(fn($r) => (float)$r['revenue'], $catData)) ?>,
      backgroundColor: catColors,
      borderRadius: 4,
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: {
        ticks: { callback: v => 'KSh ' + Number(v / 1000).toFixed(1) + 'K' },
        grid: { color: 'rgba(255,255,255,0.05)' }
      },
      y: { grid: { display: false } }
    }
  }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
