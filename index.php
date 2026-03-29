<?php
// Root index.php — redirects to the customer-facing homepage
require_once __DIR__ . '/includes/config.php';

// If admin is logged in, go to admin dashboard
if (isLoggedIn() && isAdmin()) {
    header('Location: /spares/motoparts/admin/dashboard.php');
    exit();
}

// Otherwise serve the main homepage
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
$db = getDB();

// Featured parts
$featured = $db->query("SELECT sp.*, c.category_name FROM parts sp LEFT JOIN categories c ON sp.category_id = c.category_id WHERE sp.is_active = 1 ORDER BY sp.created_at DESC LIMIT 8");

// Categories with part count
$categories = $db->query("SELECT c.*, COUNT(sp.part_id) as part_count FROM categories c LEFT JOIN parts sp ON c.category_id = sp.category_id AND sp.is_active=1 GROUP BY c.category_id ORDER BY part_count DESC LIMIT 8");

// Stats
$r = $db->query("SELECT COUNT(*) as c FROM parts WHERE is_active=1");
$totalParts = $r ? (int)$r->fetch_assoc()['c'] : 0;

$r = $db->query("SELECT COUNT(*) as c FROM orders");
$totalOrders = $r ? (int)$r->fetch_assoc()['c'] : 0;

$r = $db->query("SELECT COUNT(*) as c FROM users WHERE role='customer'");
$totalCustomers = $r ? (int)$r->fetch_assoc()['c'] : 0;
?>

<!-- HERO -->
<section class="hero">
  <div class="hero-grid"></div>
  <div class="hero-content">
    <div class="hero-label">Kenya's #1 Motorcycle Parts Store</div>
    <h1>Genuine <em>Spare Parts</em> Delivered Fast</h1>
    <p>Browse thousands of quality motorcycle spare parts. Engine, brakes, electrical, suspension — all in one place.</p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      <a href="/spares/motoparts/customer/catalog.php" class="btn btn-primary btn-lg">
        <i class="fas fa-search"></i> Browse Catalog
      </a>
      <?php if (!isLoggedIn()): ?>
      <a href="/spares/motoparts/customer/register.php" class="btn btn-outline btn-lg">Create Account</a>
      <?php endif; ?>
    </div>
    <div class="hero-stats">
      <div class="stat-item"><span class="stat-number"><?= $totalParts ?>+</span><span class="stat-label">Parts Available</span></div>
      <div class="stat-item"><span class="stat-number"><?= $totalOrders ?>+</span><span class="stat-label">Orders Fulfilled</span></div>
      <div class="stat-item"><span class="stat-number"><?= $totalCustomers ?>+</span><span class="stat-label">Happy Customers</span></div>
    </div>
  </div>
</section>

<!-- SEARCH -->
<div style="background:var(--surface-2);border-bottom:1px solid var(--border);padding:20px 24px;">
  <div style="max-width:1280px;margin:0 auto;">
    <form class="search-bar" action="/spares/motoparts/customer/catalog.php" method="GET">
      <input type="text" name="search" placeholder="Search by part name, brand or SKU..." value="<?= isset($_GET['search']) ? sanitize($_GET['search']) : '' ?>">
      <button type="submit"><i class="fas fa-search"></i> Search</button>
    </form>
  </div>
</div>

<!-- CATEGORIES -->
<?php if ($categories && $categories->num_rows > 0): ?>
<section class="section">
  <div class="container">
    <div class="section-header">
      <div class="section-label">Browse By</div>
      <div class="section-title">Categories</div>
    </div>
    <div class="grid-4">
      <?php while($cat = $categories->fetch_assoc()): ?>
      <a href="/spares/motoparts/customer/catalog.php?category=<?= $cat['category_id'] ?>" class="card" style="padding:24px;text-decoration:none;display:flex;align-items:center;gap:16px;transition:all 0.3s;">
        <div style="width:52px;height:52px;background:var(--primary-glow);border:1px solid var(--border-accent);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fas <?= sanitize($cat['icon'] ?? 'fa-cog') ?>" style="color:var(--primary);font-size:22px;"></i>
        </div>
        <div>
          <div style="font-family:var(--font-display);font-weight:700;font-size:16px;color:var(--text-primary);margin-bottom:2px;"><?= sanitize($cat['category_name']) ?></div>
          <div style="font-size:12px;color:var(--text-secondary);"><?= $cat['part_count'] ?> parts</div>
        </div>
      </a>
      <?php endwhile; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- FEATURED PARTS -->
<?php if ($featured && $featured->num_rows > 0): ?>
<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-header flex justify-between items-center">
      <div>
        <div class="section-label">New Arrivals</div>
        <div class="section-title">Latest Parts</div>
      </div>
      <a href="/spares/motoparts/customer/catalog.php" class="btn btn-outline">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="grid-4">
      <?php while($part = $featured->fetch_assoc()): ?>
      <div class="card part-card animate-fadeup">
        <div class="part-card-img-placeholder">
          <i class="fas fa-cog"></i>
          <?php if ($part['stock'] == 0): ?>
            <span class="stock-badge out-stock">Out of Stock</span>
          <?php elseif ($part['stock'] <= 10): ?>
            <span class="stock-badge low-stock">Low Stock</span>
          <?php else: ?>
            <span class="stock-badge in-stock">In Stock</span>
          <?php endif; ?>
        </div>
        <div class="part-card-body">
          <div class="part-category"><?= sanitize($part['category_name'] ?? 'Uncategorized') ?></div>
          <div class="part-name"><?= sanitize($part['part_name']) ?></div>
          <div class="part-brand"><i class="fas fa-tag" style="font-size:10px;"></i> <?= sanitize($part['brand'] ?? 'Generic') ?></div>
          <div class="part-price"><?= formatPrice($part['price']) ?></div>
          <div style="display:flex;gap:8px;">
            <a href="/spares/motoparts/customer/part-detail.php?id=<?= $part['part_id'] ?>" class="btn btn-outline btn-sm" style="flex:1;justify-content:center;">Details</a>
            <?php if ($part['stock'] > 0 && isLoggedIn()): ?>
            <form method="POST" action="/spares/motoparts/customer/cart-action.php" style="flex:1;">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="part_id" value="<?= $part['part_id'] ?>">
              <input type="hidden" name="quantity" value="1">
              <button type="submit" class="btn btn-primary btn-sm w-full" style="justify-content:center;width:100%;"><i class="fas fa-cart-plus"></i> Add</button>
            </form>
            <?php elseif ($part['stock'] > 0): ?>
            <a href="/spares/motoparts/customer/login.php" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;"><i class="fas fa-cart-plus"></i> Add</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- FEATURES -->
<section style="background:var(--surface);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:48px 24px;">
  <div class="container">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:32px;text-align:center;">
      <?php $features = [
        ['fas fa-shipping-fast','Fast Delivery','Nairobi same-day, nationwide within 3 days'],
        ['fas fa-shield-alt','Genuine Parts','Verified authentic spare parts only'],
        ['fas fa-undo','Easy Returns','7-day return policy, no questions asked'],
        ['fas fa-headset','Expert Support','Mechanic-grade advice from our team'],
      ]; foreach($features as $f): ?>
      <div>
        <div style="width:56px;height:56px;background:var(--surface-2);border:1px solid var(--border);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
          <i class="<?= $f[0] ?>" style="color:var(--primary);font-size:22px;"></i>
        </div>
        <div style="font-family:var(--font-display);font-weight:700;font-size:16px;margin-bottom:6px;"><?= $f[1] ?></div>
        <div style="font-size:13px;color:var(--text-secondary);"><?= $f[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
