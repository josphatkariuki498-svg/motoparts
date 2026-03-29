<?php
$pageTitle = 'Parts Catalog';
require_once dirname(__DIR__) . '/includes/header.php';
$db = getDB();

// Filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$where = "WHERE sp.is_active = 1";
$params = [];
if ($search) $where .= " AND (sp.part_name LIKE '%$search%' OR sp.brand LIKE '%$search%' OR sp.sku LIKE '%$search%' OR sp.description LIKE '%$search%')";
if ($category_id) $where .= " AND sp.category_id = $category_id";

$order_by = match($sort) {
  'price_asc' => 'sp.price ASC',
  'price_desc' => 'sp.price DESC',
  'name_asc' => 'sp.part_name ASC',
  default => 'sp.created_at DESC'
};

$count_result = $db->query("SELECT COUNT(*) as total FROM spare_parts sp $where");
$total = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

$parts = $db->query("SELECT sp.*, c.category_name FROM spare_parts sp LEFT JOIN categories c ON sp.category_id = c.category_id $where ORDER BY $order_by LIMIT $per_page OFFSET $offset");
$categories = $db->query("SELECT * FROM categories ORDER BY category_name");
?>

<div class="container section">
  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <a href="/motoparts/">Home</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <span>Catalog</span>
    <?php if ($search): ?>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right" style="font-size:10px;"></i></span>
    <span>Search: "<?= $search ?>"</span>
    <?php endif; ?>
  </div>

  <div style="display:flex;gap:32px;align-items:flex-start;">
    <!-- SIDEBAR FILTERS -->
    <div style="width:220px;flex-shrink:0;">
      <div class="card p-20 mb-16">
        <div style="font-family:var(--font-display);font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:16px;color:var(--text-secondary);">Categories</div>
        <a href="/spares/motoparts/motoparts/customer/catalog.php<?= $search ? '?search='.$search : '' ?>" class="category-pill<?= !$category_id ? ' active' : '' ?>" style="display:block;margin-bottom:4px;border-radius:4px;">All Parts</a>
        <?php while($cat = $categories->fetch_assoc()): ?>
        <a href="?category=<?= $cat['category_id'] ?><?= $search ? '&search='.$search : '' ?>" class="category-pill<?= $category_id == $cat['category_id'] ? ' active' : '' ?>" style="display:block;margin-bottom:4px;border-radius:4px;"><?= sanitize($cat['category_name']) ?></a>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div style="flex:1;">
      <!-- Top Bar -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
          <div style="font-family:var(--font-display);font-size:22px;font-weight:700;text-transform:uppercase;">
            <?php 
  if ($category_id) {
    $_cr = $db->query("SELECT category_name FROM categories WHERE category_id=$category_id");
    echo $_cr ? sanitize($_cr->fetch_assoc()['category_name']) : 'All Parts';
  } else {
    echo $search ? 'Search Results' : 'All Parts';
  }
?>
          </div>
          <div style="font-size:13px;color:var(--text-secondary);"><?= $total ?> parts found</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <form class="search-bar" method="GET" style="max-width:280px;">
            <?php if ($category_id): ?><input type="hidden" name="category" value="<?= $category_id ?>"><?php endif; ?>
            <input type="text" name="search" placeholder="Search parts..." value="<?= $search ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
          </form>
          <select class="form-control" onchange="window.location='?sort='+this.value+'<?= $category_id ? '&category='.$category_id : '' ?><?= $search ? '&search='.$search : '' ?>'" style="width:auto;">
            <option value="newest" <?= $sort=='newest'?'selected':''?>>Newest</option>
            <option value="price_asc" <?= $sort=='price_asc'?'selected':''?>>Price: Low to High</option>
            <option value="price_desc" <?= $sort=='price_desc'?'selected':''?>>Price: High to Low</option>
            <option value="name_asc" <?= $sort=='name_asc'?'selected':''?>>Name A-Z</option>
          </select>
        </div>
      </div>

      <!-- PARTS GRID -->
      <?php if ($parts->num_rows > 0): ?>
      <div class="grid-3">
        <?php while($part = $parts->fetch_assoc()): ?>
        <div class="card part-card">
          <div class="part-card-img-placeholder">
            <i class="fas fa-cog"></i>
            <?php if ($part['stock'] == 0): ?>
              <span class="stock-badge out-stock">Out of Stock</span>
            <?php elseif ($part['stock'] <= 10): ?>
              <span class="stock-badge low-stock">Only <?= $part['stock'] ?> left</span>
            <?php else: ?>
              <span class="stock-badge in-stock">In Stock</span>
            <?php endif; ?>
          </div>
          <div class="part-card-body">
            <div class="part-category"><?= sanitize($part['category_name'] ?? '') ?></div>
            <div class="part-name"><?= sanitize($part['part_name']) ?></div>
            <div class="part-brand"><i class="fas fa-tag" style="font-size:10px;color:var(--text-muted);"></i> <?= sanitize($part['brand'] ?? 'Generic') ?> &bull; SKU: <?= sanitize($part['sku'] ?? '-') ?></div>
            <div class="part-price"><?= formatPrice($part['price']) ?></div>
            <div style="display:flex;gap:8px;margin-top:auto;">
              <a href="/spares/motoparts/customer/part-detail.php?id=<?= $part['part_id'] ?>" class="btn btn-outline btn-sm" style="flex:1;justify-content:center;">View Details</a>
              <?php if ($part['stock'] > 0): ?>
              <?php if (isLoggedIn()): ?>
              <form method="POST" action="cart-action.php" style="flex:1;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="part_id" value="<?= $part['part_id'] ?>">
                <input type="hidden" name="part_name" value="<?= $part['part_name'] ?>">
                <input type="hidden" name="price" value="<?= $part['price'] ?>">
                <input type="hidden" name="quantity" value="1">
                <input type="hidden" name="redirect" value="<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                <button type="submit" class="btn btn-primary btn-sm w-full" style="justify-content:center;width:100%;"><i class="fas fa-cart-plus"></i> Add</button>
              </form>
              <?php else: ?>
              <a href="login.php" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;"><i class="fas fa-cart-plus"></i> Add</a>
              <?php endif; ?>
              <?php else: ?>
              <button class="btn btn-outline btn-sm" style="flex:1;justify-content:center;opacity:.5;" disabled>Unavailable</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

      <!-- PAGINATION -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?><?= $search ? '&search='.$search : '' ?><?= $category_id ? '&category='.$category_id : '' ?>&sort=<?= $sort ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div style="text-align:center;padding:80px 20px;">
        <i class="fas fa-search" style="font-size:48px;color:var(--text-muted);margin-bottom:16px;display:block;"></i>
        <div style="font-family:var(--font-display);font-size:24px;font-weight:700;margin-bottom:8px;">No Parts Found</div>
        <p style="color:var(--text-secondary);">Try a different search term or browse all categories.</p>
        <a href="/spares/motoparts/customer/catalog.php" class="btn btn-primary" style="margin-top:20px;">Browse All Parts</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
