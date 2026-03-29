<?php
$pageTitle = 'My Orders';
require_once dirname(__DIR__) . '/includes/header.php';
requireLogin();
$db = getDB();
$user_id = $_SESSION['user_id'];

$orders = $db->query("SELECT o.*, p.payment_method, COUNT(od.order_detail_id) as item_count FROM orders o LEFT JOIN payments p ON o.order_id = p.order_id LEFT JOIN order_details od ON o.order_id = od.order_id WHERE o.user_id = $user_id GROUP BY o.order_id ORDER BY o.order_date DESC");
?>

<div class="container section">
  <div class="breadcrumb">
    <a href="/spares/motoparts/">Home</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <span>My Orders</span>
  </div>
  
  <div style="font-family:var(--font-display);font-size:28px;font-weight:800;text-transform:uppercase;margin-bottom:28px;">My Orders</div>

  <?php if ($orders->num_rows === 0): ?>
  <div style="text-align:center;padding:80px 20px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);">
    <i class="fas fa-box-open" style="font-size:48px;color:var(--text-muted);display:block;margin-bottom:16px;"></i>
    <div style="font-family:var(--font-display);font-size:24px;font-weight:700;margin-bottom:8px;">No Orders Yet</div>
    <a href="/spares/motoparts/customer/catalog.php" class="btn btn-primary" style="margin-top:16px;"><i class="fas fa-search"></i> Start Shopping</a>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Order #</th>
          <th>Date</th>
          <th>Items</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($order = $orders->fetch_assoc()): ?>
        <tr>
          <td><strong>#<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
          <td style="color:var(--text-secondary);font-size:13px;"><?= date('d M Y', strtotime($order['order_date'])) ?><br><span style="font-size:11px;"><?= date('H:i', strtotime($order['order_date'])) ?></span></td>
          <td><?= $order['item_count'] ?> item<?= $order['item_count'] != 1 ? 's' : '' ?></td>
          <td style="font-family:var(--font-display);font-weight:700;color:var(--primary);"><?= formatPrice($order['total_amount']) ?></td>
          <td style="text-transform:capitalize;font-size:13px;"><?= str_replace('_',' ',$order['payment_method'] ?? 'N/A') ?></td>
          <td><span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
          <td>
            <a href="/spares/motoparts/customer/order-detail.php?id=<?= $order['order_id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
