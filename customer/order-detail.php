<?php
$pageTitle = 'Order Detail';
require_once dirname(__DIR__) . '/includes/header.php';
requireLogin();
$db = getDB();
$user_id  = $_SESSION['user_id'];
$order_id = (int)($_GET['id'] ?? 0);

// ✅ FIX 1: Use prepared statements instead of raw variables
$stmt = $db->prepare("SELECT o.*, p.payment_method, p.transaction_id, p.status as payment_status FROM orders o LEFT JOIN payments p ON o.order_id = p.order_id WHERE o.order_id = ? AND o.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { header('Location: /spares/motoparts/customer/orders.php'); exit; }

// ✅ FIX 2: Removed sp.brand (doesn't exist), calculate subtotal from price * quantity
$stmt2 = $db->prepare("SELECT od.*, sp.part_name, (od.price * od.quantity) as subtotal FROM order_details od JOIN parts sp ON od.part_id = sp.part_id WHERE od.order_id = ?");
$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$details = $stmt2->get_result();
?>

<div class="container section" style="max-width:820px;">
  <div class="breadcrumb">
    <a href="/spares/motoparts/">Home</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <a href="/spares/motoparts/customer/orders.php">My Orders</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <span>Order #<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></span>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div style="font-family:var(--font-display);font-size:28px;font-weight:800;text-transform:uppercase;">Order #<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></div>
    <span class="badge badge-<?= $order['status'] ?>" style="font-size:13px;padding:6px 16px;"><?= ucfirst($order['status']) ?></span>
  </div>

  <div class="card p-24 mb-16">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:24px;">
      <div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Order Date</div><div style="font-weight:600;"><?= date('d M Y', strtotime($order['order_date'])) ?></div></div>
      <div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Payment Method</div><div style="font-weight:600;text-transform:capitalize;"><?= str_replace('_', ' ', $order['payment_method'] ?? '—') ?></div></div>
      <div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Payment Status</div><span class="badge badge-<?= $order['payment_status'] === 'completed' ? 'delivered' : 'pending' ?>"><?= ucfirst($order['payment_status'] ?? 'Pending') ?></span></div>
    </div>

    <div style="border-top:1px solid var(--border);padding-top:20px;">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-bottom:14px;">Items</div>
      <?php while ($d = $details->fetch_assoc()): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
        <div>
          <div style="font-weight:500;font-size:14px;"><?= sanitize($d['part_name']) ?></div>
          <!-- ✅ FIX 3: Removed brand (doesn't exist), changed unit_price to price -->
          <div style="font-size:12px;color:var(--text-muted);"><?= CURRENCY ?> <?= number_format($d['price'], 2) ?> × <?= $d['quantity'] ?></div>
        </div>
        <!-- ✅ FIX 4: subtotal is now calculated in the query as price * quantity -->
        <div style="font-weight:700;font-family:var(--font-display);"><?= formatPrice($d['subtotal']) ?></div>
      </div>
      <?php endwhile; ?>
      <div style="display:flex;justify-content:space-between;padding-top:16px;">
        <span style="font-family:var(--font-display);font-size:18px;font-weight:700;">Total</span>
        <span style="font-family:var(--font-display);font-size:24px;font-weight:800;color:var(--primary);"><?= formatPrice($order['total_amount']) ?></span>
      </div>
    </div>
  </div>

  <div class="card p-24">
    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-bottom:12px;">Transaction ID</div>
    <div style="font-size:15px;line-height:1.8;"><?= sanitize($order['transaction_id'] ?? '—') ?></div>
  </div>

  <div style="margin-top:20px;display:flex;gap:12px;">
    <a href="/spares/motoparts/customer/orders.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> All Orders</a>
    <a href="/spares/motoparts/customer/catalog.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> Continue Shopping</a>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
