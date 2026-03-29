<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$db = getDB();

// ── Stats
$_r = $db->query("SELECT COUNT(*) c FROM spare_parts WHERE is_active=1"); $totalParts = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$_r = $db->query("SELECT COUNT(*) c FROM orders"); $totalOrders = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$_r = $db->query("SELECT COUNT(*) c FROM users WHERE role='customer'"); $totalCustomers = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$_r = $db->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed'"); $totalRevenue = $_r ? (float)$_r->fetch_assoc()['s'] : 0;
$_r = $db->query("SELECT COUNT(*) c FROM orders WHERE status='pending'"); $pendingOrders = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$_r = $db->query("SELECT COUNT(*) c FROM spare_parts WHERE stock<=10 AND is_active=1"); $lowStock = $_r ? (int)$_r->fetch_assoc()['c'] : 0;

// ── Recent Orders (10)
$recentOrders = $db->query("
    SELECT o.order_id, o.order_date, o.total_amount, o.status,
           u.name as customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    ORDER BY o.order_date DESC LIMIT 10
");

// ── Top-selling parts
$topParts = $db->query("
    SELECT sp.part_name, sp.brand, SUM(od.quantity) as sold, SUM(od.subtotal) as revenue
    FROM order_details od
    JOIN spare_parts sp ON od.part_id = sp.part_id
    GROUP BY od.part_id
    ORDER BY sold DESC LIMIT 5
");

// ── Revenue last 7 days (for sparkline)
$last7 = $db->query("
    SELECT DATE(payment_date) as d, SUM(amount) as rev
    FROM payments WHERE status='completed'
      AND payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(payment_date)
    ORDER BY d
");
$sparkData = [];
while ($r = $last7->fetch_assoc()) {
    $sparkData[] = ['date' => $r['d'], 'rev' => (float)$r['rev']];
}

// ── Order status breakdown
$statusBreakdown = $db->query("SELECT status, COUNT(*) c FROM orders GROUP BY status");
$statusData = [];
while ($r = $statusBreakdown->fetch_assoc()) {
    $statusData[$r['status']] = (int)$r['c'];
}

// ── Low stock parts
$lowStockParts = $db->query("
    SELECT part_name, brand, stock, sku
    FROM spare_parts WHERE stock<=10 AND is_active=1
    ORDER BY stock ASC LIMIT 6
");

require_once __DIR__ . '/includes/header.php';
?>

<!-- Flash from redirects -->
<?php if (isset($_GET['msg'])): ?>
<div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stats-grid">
  <div class="stat-card red">
    <div class="stat-icon red"><i class="fas fa-shopping-bag"></i></div>
    <div class="stat-value"><?= number_format($totalOrders) ?></div>
    <div class="stat-label">Total Orders</div>
    <?php if ($pendingOrders): ?>
    <div class="stat-change"><i class="fas fa-clock"></i> <?= $pendingOrders ?> pending</div>
    <?php endif; ?>
  </div>

  <div class="stat-card green">
    <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <div class="stat-value">KSh <?= number_format($totalRevenue/1000, 1) ?>K</div>
    <div class="stat-label">Total Revenue</div>
    <div class="stat-change up"><i class="fas fa-arrow-up"></i> Completed payments</div>
  </div>

  <div class="stat-card blue">
    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
    <div class="stat-value"><?= number_format($totalCustomers) ?></div>
    <div class="stat-label">Registered Customers</div>
  </div>

  <div class="stat-card yellow">
    <div class="stat-icon yellow"><i class="fas fa-cog"></i></div>
    <div class="stat-value"><?= number_format($totalParts) ?></div>
    <div class="stat-label">Active Parts</div>
    <?php if ($lowStock): ?>
    <div class="stat-change down"><i class="fas fa-exclamation-triangle"></i> <?= $lowStock ?> low stock</div>
    <?php endif; ?>
  </div>
</div>

<!-- CHARTS ROW -->
<div class="grid-2 mb-6">

  <!-- Revenue Sparkline -->
  <div class="card">
    <div class="card-header">
      <i class="fas fa-chart-line" style="color:var(--green);"></i>
      <h3>Revenue — Last 7 Days</h3>
    </div>
    <div class="card-body" style="padding-bottom:12px;">
      <canvas id="revenueChart" height="130"></canvas>
    </div>
  </div>

  <!-- Order Status Doughnut -->
  <div class="card">
    <div class="card-header">
      <i class="fas fa-chart-pie" style="color:var(--blue);"></i>
      <h3>Order Status Breakdown</h3>
    </div>
    <div class="card-body" style="display:flex;align-items:center;gap:24px;">
      <canvas id="statusChart" width="140" height="140" style="flex-shrink:0;"></canvas>
      <div style="flex:1;display:flex;flex-direction:column;gap:8px;">
        <?php
        $statusColors = [
          'pending'    => '#eab308',
          'confirmed'  => '#3b82f6',
          'processing' => '#a855f7',
          'shipped'    => '#06b6d4',
          'delivered'  => '#22c55e',
          'cancelled'  => '#ef4444',
        ];
        foreach ($statusColors as $s => $c): $cnt = $statusData[$s] ?? 0; if($cnt===0) continue; ?>
        <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $c ?>;flex-shrink:0;"></span>
          <span style="flex:1;text-transform:capitalize;color:var(--text-2);"><?= $s ?></span>
          <span style="font-weight:600;"><?= $cnt ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- BOTTOM ROW -->
<div class="grid-2">

  <!-- Recent Orders -->
  <div class="card">
    <div class="card-header">
      <i class="fas fa-receipt" style="color:var(--red);"></i>
      <h3>Recent Orders</h3>
      <a href="orders.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#ID</th>
            <th>Customer</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($o = $recentOrders->fetch_assoc()): ?>
          <tr>
            <td><a href="orders.php?id=<?= $o['order_id'] ?>" style="color:var(--red);font-weight:600;">#<?= $o['order_id'] ?></a></td>
            <td><?= sanitize($o['customer_name']) ?></td>
            <td style="font-weight:600;">KSh <?= number_format($o['total_amount']) ?></td>
            <td><span class="tag tag-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
            <td class="text-muted text-sm"><?= date('d M', strtotime($o['order_date'])) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Low Stock Alert -->
  <div class="card">
    <div class="card-header">
      <i class="fas fa-exclamation-triangle" style="color:var(--yellow);"></i>
      <h3>Low Stock Alert</h3>
      <a href="parts.php?filter=low" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Part</th><th>Brand</th><th>Stock</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php while ($p = $lowStockParts->fetch_assoc()): ?>
          <tr>
            <td style="font-size:13px;"><?= sanitize($p['part_name']) ?></td>
            <td class="text-muted text-xs"><?= sanitize($p['brand'] ?? '-') ?></td>
            <td>
              <span class="tag <?= $p['stock']==0 ? 'tag-out' : 'tag-low' ?>">
                <?= $p['stock']==0 ? 'Out' : $p['stock'] ?>
              </span>
            </td>
            <td><a href="parts.php?edit=<?= urlencode($p['sku']) ?>" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i></a></td>
          </tr>
          <?php endwhile; ?>
          <?php if ($lowStock === 0): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:24px;">
            <i class="fas fa-check-circle" style="color:var(--green);margin-right:6px;"></i> All parts are well-stocked!
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Top selling -->
    <div class="card-header" style="border-top:1px solid var(--border);">
      <i class="fas fa-trophy" style="color:var(--yellow);"></i>
      <h3>Top Selling Parts</h3>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Part</th><th>Sold</th><th>Revenue</th></tr></thead>
        <tbody>
          <?php while ($tp = $topParts->fetch_assoc()): ?>
          <tr>
            <td style="font-size:13px;"><?= sanitize($tp['part_name']) ?></td>
            <td><span style="font-weight:700;color:var(--red);"><?= $tp['sold'] ?></span></td>
            <td class="text-sm">KSh <?= number_format($tp['revenue']) ?></td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$db->query("SELECT 1 FROM order_details LIMIT 1")->num_rows): ?>
          <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:24px;">No sales data yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.color = '#6b6b78';
Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';

// Revenue chart
const sparkData = <?= json_encode($sparkData) ?>;
const labels = sparkData.map(d => new Date(d.date).toLocaleDateString('en-KE', {weekday:'short', day:'numeric', month:'short'}));
const values = sparkData.map(d => d.rev);

new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: labels.length ? labels : ['No data'],
    datasets: [{
      label: 'Revenue (KSh)',
      data: values.length ? values : [0],
      borderColor: '#22c55e',
      backgroundColor: 'rgba(34,197,94,0.08)',
      borderWidth: 2,
      tension: 0.4,
      fill: true,
      pointBackgroundColor: '#22c55e',
      pointRadius: 4,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: v => 'KSh ' + (v/1000).toFixed(1) + 'K' } },
      x: { grid: { display: false } }
    }
  }
});

// Status donut
const statusData = <?= json_encode($statusData) ?>;
const statusColors = {
  pending: '#eab308', confirmed: '#3b82f6', processing: '#a855f7',
  shipped: '#06b6d4', delivered: '#22c55e', cancelled: '#ef4444'
};
const sLabels = Object.keys(statusData).filter(k => statusData[k] > 0);
const sValues = sLabels.map(k => statusData[k]);
const sColors = sLabels.map(k => statusColors[k] || '#555');

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: sLabels.map(l => l.charAt(0).toUpperCase() + l.slice(1)),
    datasets: [{ data: sValues, backgroundColor: sColors, borderWidth: 0, hoverOffset: 6 }]
  },
  options: {
    cutout: '72%',
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.raw } }
    }
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
