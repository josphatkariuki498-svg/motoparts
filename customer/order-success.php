<?php
$pageTitle = 'Order Confirmed';
require_once dirname(__DIR__) . '/includes/header.php';
requireLogin();
$db = getDB();
$order_id = (int)($_GET['order_id'] ?? 0);
$user_id = $_SESSION['user_id'];

$_r = $db->query("SELECT o.*, p.payment_method, p.transaction_id FROM orders o LEFT JOIN payments p ON o.order_id = p.order_id WHERE o.order_id = $order_id AND o.user_id = $user_id");
$order = $_r ? $_r->fetch_assoc() : null;
if (!$order) { header('Location: /spares/motoparts/'); exit; }

$order_details = $db->query("SELECT od.*, sp.part_name, sp.brand FROM order_details od JOIN spare_parts sp ON od.part_id = sp.part_id WHERE od.order_id = $order_id");
?>

<div class="container" style="padding-top:60px;padding-bottom:80px;text-align:center;max-width:680px;">
  <!-- Success Animation -->
  <div style="width:80px;height:80px;background:rgba(34,197,94,0.15);border:2px solid rgba(34,197,94,0.4);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px;color:var(--success);">
    <i class="fas fa-check"></i>
  </div>
  
  <div style="font-family:var(--font-display);font-size:36px;font-weight:800;text-transform:uppercase;margin-bottom:8px;">Order Placed!</div>
  <p style="color:var(--text-secondary);font-size:16px;margin-bottom:32px;">Thank you, <?= sanitize(explode(' ', $_SESSION['name'])[0]) ?>! Your order has been received and is being processed.</p>

  <!-- Order Card -->
  <div class="card p-24" style="text-align:left;margin-bottom:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <div>
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Order Reference</div>
        <div style="font-family:var(--font-display);font-size:22px;font-weight:700;">#<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></div>
      </div>
      <span class="badge badge-pending" style="font-size:13px;"><?= ucfirst($order['status']) ?></span>
    </div>
    
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
      <div style="padding:14px;background:var(--surface-2);border-radius:var(--radius);">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Order Date</div>
        <div style="font-weight:600;font-size:14px;"><?= date('d M Y, H:i', strtotime($order['order_date'])) ?></div>
      </div>
      <div style="padding:14px;background:var(--surface-2);border-radius:var(--radius);">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Payment Method</div>
        <div style="font-weight:600;font-size:14px;text-transform:capitalize;"><?= str_replace('_',' ',$order['payment_method'] ?? 'N/A') ?></div>
      </div>
    </div>
    
    <div style="border-top:1px solid var(--border);padding-top:16px;">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-bottom:12px;">Items Ordered</div>
      <?php while ($detail = $order_details->fetch_assoc()): ?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:14px;">
        <span><?= sanitize($detail['part_name']) ?> <span style="color:var(--text-muted);">×<?= $detail['quantity'] ?></span></span>
        <span style="font-weight:600;"><?= formatPrice($detail['subtotal']) ?></span>
      </div>
      <?php endwhile; ?>
      <div style="display:flex;justify-content:space-between;padding-top:14px;">
        <span style="font-family:var(--font-display);font-size:18px;font-weight:700;">Total</span>
        <span style="font-family:var(--font-display);font-size:22px;font-weight:800;color:var(--primary);"><?= formatPrice($order['total_amount']) ?></span>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
    <a href="/spares/motoparts/customer/orders.php" class="btn btn-outline btn-lg"><i class="fas fa-list"></i> View All Orders</a>
    <a href="/spares/motoparts/customer/catalog.php" class="btn btn-primary btn-lg"><i class="fas fa-shopping-bag"></i> Continue Shopping</a>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
