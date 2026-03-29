<?php
$pageTitle = 'My Cart';
require_once dirname(__DIR__) . '/includes/header.php';
requireLogin();
$db = getDB();
$user_id = $_SESSION['user_id'];

// Flash messages
$cartSuccess = $_SESSION['cart_success'] ?? '';
$cartError   = $_SESSION['cart_error']   ?? '';
unset($_SESSION['cart_success'], $_SESSION['cart_error']);

// ✅ FIX 1: Changed JOIN from "spare_parts" to "parts" (correct table name)
// ✅ FIX 2: Using prepared statement instead of raw $user_id interpolation
$stmt = $db->prepare("
    SELECT c.cart_id, c.part_id, c.quantity,
           sp.part_name, sp.price, sp.stock
    FROM cart c
    JOIN parts sp ON c.part_id = sp.part_id
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$total = 0;
if ($result) {
    while ($item = $result->fetch_assoc()) {
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $total += $item['subtotal'];
        $items[] = $item;
    }
}
?>

<div class="container section" style="max-width:900px;">
  <div class="breadcrumb">
    <a href="/spares/motoparts/">Home</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <span>My Cart</span>
  </div>

  <div style="font-family:var(--font-display);font-size:28px;font-weight:800;text-transform:uppercase;margin-bottom:24px;">
    Shopping Cart
  </div>

  <?php if ($cartSuccess): ?>
  <div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($cartSuccess) ?></div>
  <?php endif; ?>
  <?php if ($cartError): ?>
  <div class="alert alert-danger" style="margin-bottom:16px;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($cartError) ?></div>
  <?php endif; ?>

  <?php if (empty($items)): ?>
  <div style="text-align:center;padding:80px 20px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);">
    <i class="fas fa-shopping-cart" style="font-size:48px;color:var(--text-muted);display:block;margin-bottom:16px;"></i>
    <div style="font-family:var(--font-display);font-size:24px;font-weight:700;margin-bottom:8px;">Your Cart is Empty</div>
    <p style="color:var(--text-secondary);margin-bottom:20px;">Browse our catalog and add some parts!</p>
    <a href="/spares/motoparts/customer/catalog.php" class="btn btn-primary"><i class="fas fa-search"></i> Browse Parts</a>
  </div>

  <?php else: ?>
  <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">
    <!-- CART ITEMS -->
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Part</th>
              <th>Price</th>
              <th>Qty</th>
              <th>Subtotal</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div style="font-weight:600;font-size:14px;"><?= sanitize($item['part_name']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= sanitize($item['brand'] ?? '') ?> &bull; SKU: <?= sanitize($item['sku'] ?? '') ?></div>
                <?php if ($item['stock'] < $item['quantity']): ?>
                <div style="font-size:11px;color:#f87171;margin-top:2px;"><i class="fas fa-exclamation-triangle"></i> Only <?= $item['stock'] ?> in stock</div>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap;"><?= formatPrice($item['price']) ?></td>
              <td><?= $item['quantity'] ?></td>
              <td style="font-weight:700;white-space:nowrap;"><?= formatPrice($item['subtotal']) ?></td>
              <td>
                <a href="cart-action.php?action=remove&id=<?= $item['part_id'] ?>"
                   onclick="return confirm('Remove this item?')"
                   class="btn btn-ghost btn-sm" style="color:#f87171;">
                  <i class="fas fa-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:12px;">
        <a href="/spares/motoparts/customer/catalog.php" class="btn btn-outline btn-sm">
          <i class="fas fa-arrow-left"></i> Continue Shopping
        </a>
        <a href="cart-action.php?action=clear"
           onclick="return confirm('Clear your entire cart?')"
           class="btn btn-ghost btn-sm" style="color:#f87171;">
          <i class="fas fa-trash"></i> Clear Cart
        </a>
      </div>
    </div>

    <!-- ORDER SUMMARY -->
    <div class="card" style="padding:24px;position:sticky;top:80px;">
      <div style="font-family:var(--font-display);font-size:16px;font-weight:700;text-transform:uppercase;margin-bottom:16px;">Order Summary</div>
      <div style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:14px;">
        <span style="color:var(--text-secondary);">Items (<?= count($items) ?>)</span>
        <span><?= formatPrice($total) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:16px;font-size:14px;">
        <span style="color:var(--text-secondary);">Shipping</span>
        <span style="color:var(--success);">Free</span>
      </div>
      <hr style="border:none;border-top:1px solid var(--border);margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:20px;">
        <span style="font-family:var(--font-display);font-size:18px;font-weight:700;">Total</span>
        <span style="font-family:var(--font-display);font-size:22px;font-weight:800;color:var(--primary);"><?= formatPrice($total) ?></span>
      </div>
      <a href="/spares/motoparts/customer/checkout.php" class="btn btn-primary w-full" style="justify-content:center;">
        <i class="fas fa-check-circle"></i> Proceed to Checkout
      </a>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
