<?php
$pageTitle = 'Part Details';
require_once dirname(__DIR__) . '/includes/header.php';
$db = getDB();

$part_id = (int)($_GET['id'] ?? 0);
if (!$part_id) { header('Location: /spares/motoparts/customer/catalog.php'); exit; }

$result = $db->query("SELECT sp.*, c.category_name FROM spare_parts sp LEFT JOIN categories c ON sp.category_id = c.category_id WHERE sp.part_id = $part_id AND sp.is_active = 1");
$part = $result->fetch_assoc();
if (!$part) { header('Location: /spares/motoparts/customer/catalog.php'); exit; }

$pageTitle = $part['part_name'];

// Related parts
$related = $db->query("SELECT sp.*, c.category_name FROM spare_parts sp LEFT JOIN categories c ON sp.category_id = c.category_id WHERE sp.category_id = {$part['category_id']} AND sp.part_id != $part_id AND sp.is_active=1 LIMIT 4");
?>

<div class="container" style="padding-top:40px;padding-bottom:60px;">
  <div class="breadcrumb">
    <a href="/spares/motoparts/">Home</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <a href="/spares/motoparts/customer/catalog.php">Catalog</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <a href="/spares/motoparts/customer/catalog.php?category=<?= $part['category_id'] ?>"><?= sanitize($part['category_name'] ?? '') ?></a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <span><?= sanitize($part['part_name']) ?></span>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start;">
    <!-- IMAGE -->
    <div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);height:400px;display:flex;align-items:center;justify-content:center;font-size:120px;color:var(--text-muted);position:relative;overflow:hidden;">
        <i class="fas fa-cog"></i>
        <div style="position:absolute;inset:0;background:linear-gradient(135deg,transparent 60%,rgba(255,59,31,0.05));"></div>
      </div>
    </div>
    
    <!-- DETAILS -->
    <div>
      <div style="font-size:12px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--primary);margin-bottom:8px;"><?= sanitize($part['category_name'] ?? '') ?></div>
      <h1 style="font-family:var(--font-display);font-size:32px;font-weight:800;text-transform:uppercase;letter-spacing:-0.01em;margin-bottom:12px;"><?= sanitize($part['part_name']) ?></h1>
      
      <div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
        <span style="font-size:13px;color:var(--text-secondary);"><i class="fas fa-tag" style="color:var(--text-muted);margin-right:6px;"></i>Brand: <strong style="color:var(--text-primary);"><?= sanitize($part['brand'] ?? 'Generic') ?></strong></span>
        <span style="font-size:13px;color:var(--text-secondary);"><i class="fas fa-barcode" style="color:var(--text-muted);margin-right:6px;"></i>SKU: <strong style="color:var(--text-primary);"><?= sanitize($part['sku'] ?? 'N/A') ?></strong></span>
      </div>
      
      <div style="font-family:var(--font-display);font-size:40px;font-weight:800;color:var(--primary);margin-bottom:16px;"><?= formatPrice($part['price']) ?></div>
      
      <div style="margin-bottom:20px;">
        <?php if ($part['stock'] == 0): ?>
          <span class="stock-badge out-stock" style="position:static;display:inline-flex;font-size:13px;">Out of Stock</span>
        <?php elseif ($part['stock'] <= 10): ?>
          <span class="stock-badge low-stock" style="position:static;display:inline-flex;font-size:13px;"><i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>Only <?= $part['stock'] ?> in stock</span>
        <?php else: ?>
          <span class="stock-badge in-stock" style="position:static;display:inline-flex;font-size:13px;"><i class="fas fa-check" style="margin-right:6px;"></i><?= $part['stock'] ?> in stock</span>
        <?php endif; ?>
      </div>
      
      <?php if ($part['description']): ?>
      <p style="color:var(--text-secondary);font-size:15px;line-height:1.8;margin-bottom:24px;padding:16px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);"><?= sanitize($part['description']) ?></p>
      <?php endif; ?>

      <?php if ($part['stock'] > 0): ?>
      <?php if (isLoggedIn()): ?>
      <form method="POST" action="/spares/motoparts/customer/cart-action.php">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="part_id" value="<?= $part['part_id'] ?>">
        <input type="hidden" name="redirect" value="/spares/motoparts/customer/cart.php">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
          <label class="form-label" style="margin:0;white-space:nowrap;">Qty:</label>
          <div class="qty-control">
            <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
            <input type="number" name="quantity" id="qty" class="qty-input" value="1" min="1" max="<?= $part['stock'] ?>">
            <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
          </div>
        </div>
        <div style="display:flex;gap:12px;">
          <button type="submit" class="btn btn-primary btn-lg" style="flex:1;justify-content:center;">
            <i class="fas fa-cart-plus"></i> Add to Cart
          </button>
          <a href="/spares/motoparts/customer/catalog.php" class="btn btn-outline btn-lg">
            <i class="fas fa-arrow-left"></i>
          </a>
        </div>
      </form>
      <?php else: ?>
      <a href="/spares/motoparts/customer/login.php" class="btn btn-primary btn-lg" style="display:flex;justify-content:center;">
        <i class="fas fa-sign-in-alt"></i> Login to Add to Cart
      </a>
      <?php endif; ?>
      <?php else: ?>
      <button class="btn btn-outline btn-lg w-full" disabled style="justify-content:center;opacity:0.5;">Currently Unavailable</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- RELATED PARTS -->
  <?php if ($related->num_rows > 0): ?>
  <div style="margin-top:60px;">
    <div class="section-header">
      <div class="section-label">You May Also Need</div>
      <div class="section-title">Related Parts</div>
    </div>
    <div class="grid-4">
      <?php while($rp = $related->fetch_assoc()): ?>
      <div class="card part-card">
        <div class="part-card-img-placeholder">
          <i class="fas fa-cog"></i>
          <?php if ($rp['stock'] <= 0): ?><span class="stock-badge out-stock">Out of Stock</span><?php elseif ($rp['stock'] <= 10): ?><span class="stock-badge low-stock">Low</span><?php else: ?><span class="stock-badge in-stock">In Stock</span><?php endif; ?>
        </div>
        <div class="part-card-body">
          <div class="part-name"><?= sanitize($rp['part_name']) ?></div>
          <div class="part-price"><?= formatPrice($rp['price']) ?></div>
          <a href="/spares/motoparts/customer/part-detail.php?id=<?= $rp['part_id'] ?>" class="btn btn-outline btn-sm w-full" style="justify-content:center;">View Details</a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function changeQty(delta) {
  const input = document.getElementById('qty');
  const max = parseInt(input.max);
  input.value = Math.max(1, Math.min(max, parseInt(input.value) + delta));
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
