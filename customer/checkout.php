<?php
$pageTitle = 'Checkout';
require_once dirname(__DIR__) . '/includes/header.php';
requireLogin();
$db = getDB();
$user_id = $_SESSION['user_id'];

// ✅ FIX 1: Changed JOIN from "spare_parts" to "parts" (correct table name)
// ✅ FIX 2: Using prepared statement instead of raw $user_id interpolation
$stmt = $db->prepare("
    SELECT c.cart_id, c.quantity, sp.part_id, sp.part_name, sp.price, sp.stock
    FROM cart c
    JOIN parts sp ON c.part_id = sp.part_id
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();

$items = [];
$total = 0;
while ($item = $cart_items->fetch_assoc()) {
    $item['subtotal'] = $item['price'] * $item['quantity'];
    $total += $item['subtotal'];
    $items[] = $item;
}

if (empty($items)) { header('Location: /spares/motoparts/customer/cart.php'); exit; }

// Get user info (prepared statement)
$stmt2 = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$user = $stmt2->get_result()->fetch_assoc() ?? [];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = sanitize($_POST['shipping_address'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? '');
    
    if (!in_array($payment_method, ['cash', 'card', 'mobile_money', 'bank_transfer'])) { $error = 'Please select a valid payment method.'; }
    else {
        $db->begin_transaction();
        try {
            // Create order
            $stmt = $db->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param('id', $user_id, $total);
            $stmt->execute();
            $order_id = $db->insert_id;
            
            // Create order details
            foreach ($items as $item) {
                $stmt = $db->prepare("INSERT INTO order_details (order_id, part_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('iiid', $order_id, $item['part_id'], $item['quantity'], $item['price']);
                $stmt->execute();

                // ✅ FIX 3: Changed "spare_parts" to "parts" for stock decrement
                $upd = $db->prepare("UPDATE parts SET stock = stock - ? WHERE part_id = ? AND stock >= ?");
                $upd->bind_param("iii", $item['quantity'], $item['part_id'], $item['quantity']);
                $upd->execute();
            }
            
            // Create payment record
            $txn_id = 'TXN-' . strtoupper(uniqid());
            $stmt = $db->prepare("INSERT INTO payments (order_id, amount, payment_method, transaction_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('idss', $order_id, $total, $payment_method, $txn_id);
            $stmt->execute();
            
            // Clear cart (prepared statement)
            $clr = $db->prepare("DELETE FROM cart WHERE user_id = ?");
            $clr->bind_param("i", $user_id);
            $clr->execute();
            
            $db->commit();
            header("Location: /spares/motoparts/customer/order-success.php?order_id=$order_id");
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Order failed: ' . $e->getMessage();
        }
    }
}
?>

<div class="container section">
  <div class="breadcrumb">
    <a href="/spares/motoparts/">Home</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <a href="/spares/motoparts/customer/cart.php">Cart</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <span>Checkout</span>
  </div>

  <div style="font-family:var(--font-display);font-size:28px;font-weight:800;text-transform:uppercase;margin-bottom:28px;">Checkout</div>

  <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>

  <form method="POST">
  <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;">
    <!-- LEFT: FORM -->
    <div>
      <!-- Shipping -->
      <div class="card p-24 mb-16">
        <div style="font-family:var(--font-display);font-size:16px;font-weight:700;text-transform:uppercase;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Shipping Address
        </div>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" class="form-control" value="<?= sanitize($user['name']) ?>" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" class="form-control" value="<?= sanitize($user['phone'] ?? '') ?>" disabled>
        </div>
        <div class="form-group mb-0">
          <label class="form-label">Delivery Address *</label>
          <textarea name="shipping_address" class="form-control" placeholder="Street address, City, County..." rows="3" required><?= sanitize($user['address'] ?? '') ?></textarea>
        </div>
      </div>
      
      <!-- Payment -->
      <div class="card p-24 mb-16">
        <div style="font-family:var(--font-display);font-size:16px;font-weight:700;text-transform:uppercase;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-credit-card" style="color:var(--primary);"></i> Payment Method
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <?php $methods = ['cash'=>['fas fa-money-bill-wave','Cash on Delivery'],'mobile_money'=>['fas fa-mobile-alt','Mobile Money'],'card'=>['fas fa-credit-card','Card Payment'],'bank_transfer'=>['fas fa-university','Bank Transfer']]; ?>
          <?php foreach ($methods as $val => [$icon, $label]): ?>
          <label style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:all 0.2s;" class="payment-option">
            <input type="radio" name="payment_method" value="<?= $val ?>" style="accent-color:var(--primary);" onchange="document.querySelectorAll('.payment-option').forEach(el=>el.style.borderColor='var(--border)');this.closest('.payment-option').style.borderColor='var(--primary)'">
            <i class="<?= $icon ?>" style="color:var(--primary);width:20px;text-align:center;"></i>
            <span style="font-size:14px;font-weight:500;"><?= $label ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      
      <!-- Notes -->
      <div class="card p-24">
        <div style="font-family:var(--font-display);font-size:16px;font-weight:700;text-transform:uppercase;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-sticky-note" style="color:var(--primary);"></i> Order Notes <span style="font-weight:400;font-size:12px;color:var(--text-muted);">(optional)</span>
        </div>
        <textarea name="notes" class="form-control" placeholder="Any special instructions for delivery..." rows="3"></textarea>
      </div>
    </div>

    <!-- RIGHT: ORDER SUMMARY -->
    <div>
      <div class="card p-24" style="position:sticky;top:80px;">
        <div style="font-family:var(--font-display);font-size:16px;font-weight:700;text-transform:uppercase;margin-bottom:16px;">Order Summary</div>
        <?php foreach ($items as $item): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:14px;">
          <div>
            <div style="font-weight:500;"><?= sanitize($item['part_name']) ?></div>
            <div style="color:var(--text-muted);font-size:12px;"><?= CURRENCY ?> <?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></div>
          </div>
          <div style="font-weight:600;"><?= formatPrice($item['subtotal']) ?></div>
        </div>
        <?php endforeach; ?>
        <hr class="divider">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;">
          <span style="color:var(--text-secondary);">Shipping</span>
          <span style="color:var(--success);">Free</span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:24px;">
          <span style="font-family:var(--font-display);font-size:18px;font-weight:700;">Total</span>
          <span style="font-family:var(--font-display);font-size:24px;font-weight:800;color:var(--primary);"><?= formatPrice($total) ?></span>
        </div>
        <button type="submit" class="btn btn-primary w-full btn-lg" style="justify-content:center;">
          <i class="fas fa-check-circle"></i> Place Order
        </button>
        <div style="text-align:center;margin-top:12px;font-size:12px;color:var(--text-muted);">
          <i class="fas fa-shield-alt"></i> Your order is secure and encrypted
        </div>
      </div>
    </div>
  </div>
  </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
