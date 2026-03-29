<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = getDB();

$flash = ''; $flashType = 'success';

// ── SETTINGS TABLE (key-value) ──
// Make sure this table exists in your DB:
// CREATE TABLE IF NOT EXISTS settings (
//   `key` VARCHAR(100) PRIMARY KEY,
//   `value` TEXT,
//   updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
// ) ENGINE=InnoDB;

// Helper: get setting
function getSetting($db, $key, $default='') {
    $k = $db->real_escape_string($key);
    $r = $db->query("SELECT value FROM settings WHERE `key`='$k' LIMIT 1");
    if ($r && $r->num_rows) return $r->fetch_assoc()['value'];
    return $default;
}
// Helper: save setting
function saveSetting($db, $key, $value) {
    $k = $db->real_escape_string($key);
    $v = $db->real_escape_string($value);
    $db->query("INSERT INTO settings (`key`,`value`) VALUES ('$k','$v')
                ON DUPLICATE KEY UPDATE `value`='$v', updated_at=NOW()");
}

// Try create settings table if not exists
$db->query("CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// ── HANDLE SAVES ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    if ($section === 'store') {
        foreach (['store_name','store_email','store_phone','store_address','store_currency','low_stock_threshold','tax_rate'] as $k) {
            saveSetting($db, $k, trim($_POST[$k] ?? ''));
        }
        $flash = 'Store settings saved.';
    }

    if ($section === 'delivery') {
        foreach (['nairobi_fee','county_fee','free_threshold','delivery_note'] as $k) {
            saveSetting($db, $k, trim($_POST[$k] ?? ''));
        }
        $flash = 'Delivery settings saved.';
    }

    if ($section === 'system') {
        foreach (['maintenance_mode','items_per_page','orders_notify_email'] as $k) {
            saveSetting($db, $k, trim($_POST[$k] ?? ''));
        }
        $flash = 'System settings saved.';
    }

    if ($section === 'danger') {
        $dangerAction = $_POST['danger_action'] ?? '';
        if ($dangerAction === 'clear_carts') {
            $db->query("DELETE FROM cart");
            $flash = 'All cart data has been cleared.';
        }
    }
}

// Load all settings
$s = [];
foreach ([
    'store_name'          => 'MotoParts Kenya',
    'store_email'         => 'info@motoparts.com',
    'store_phone'         => '+254700000000',
    'store_address'       => 'Nairobi, Kenya',
    'store_currency'      => 'KSh',
    'low_stock_threshold' => '10',
    'tax_rate'            => '16',
    'nairobi_fee'         => '200',
    'county_fee'          => '400',
    'free_threshold'      => '5000',
    'delivery_note'       => 'Nairobi same-day delivery. Nationwide 1-3 business days.',
    'maintenance_mode'    => '0',
    'items_per_page'      => '15',
    'orders_notify_email' => '',
] as $key => $default) {
    $s[$key] = getSetting($db, $key, $default);
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-success">
  <i class="fas fa-check-circle"></i> <?= sanitize($flash) ?>
</div>
<?php endif; ?>

<div style="max-width:860px;">

  <!-- STORE SETTINGS -->
  <div class="card mb-6">
    <div class="card-header">
      <i class="fas fa-store" style="color:var(--red);"></i>
      <h3>Store Settings</h3>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="section" value="store">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Store Name</label>
            <input type="text" name="store_name" class="form-control" value="<?= sanitize($s['store_name']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Contact Email</label>
            <input type="email" name="store_email" class="form-control" value="<?= sanitize($s['store_email']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="text" name="store_phone" class="form-control" value="<?= sanitize($s['store_phone']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Currency Symbol</label>
            <input type="text" name="store_currency" class="form-control" value="<?= sanitize($s['store_currency']) ?>" placeholder="KSh">
          </div>
          <div class="form-group">
            <label class="form-label">Low Stock Threshold</label>
            <input type="number" name="low_stock_threshold" class="form-control" min="1" value="<?= (int)$s['low_stock_threshold'] ?>">
            <div class="text-xs text-muted mt-1">Alert when stock falls below this number</div>
          </div>
          <div class="form-group">
            <label class="form-label">VAT Rate (%)</label>
            <input type="number" name="tax_rate" class="form-control" min="0" max="100" step="0.1" value="<?= (float)$s['tax_rate'] ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Store Address</label>
          <textarea name="store_address" class="form-control" rows="2"><?= sanitize($s['store_address']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Store Settings</button>
      </form>
    </div>
  </div>

  <!-- DELIVERY SETTINGS -->
  <div class="card mb-6">
    <div class="card-header">
      <i class="fas fa-truck" style="color:var(--blue);"></i>
      <h3>Delivery Settings</h3>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="section" value="delivery">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nairobi Delivery Fee (KSh)</label>
            <input type="number" name="nairobi_fee" class="form-control" min="0" value="<?= (int)$s['nairobi_fee'] ?>">
          </div>
          <div class="form-group">
            <label class="form-label">County/National Fee (KSh)</label>
            <input type="number" name="county_fee" class="form-control" min="0" value="<?= (int)$s['county_fee'] ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Free Delivery Threshold (KSh)</label>
            <input type="number" name="free_threshold" class="form-control" min="0" value="<?= (int)$s['free_threshold'] ?>">
            <div class="text-xs text-muted mt-1">Orders above this amount get free delivery</div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Delivery Policy Note</label>
          <textarea name="delivery_note" class="form-control" rows="2"><?= sanitize($s['delivery_note']) ?></textarea>
          <div class="text-xs text-muted mt-1">Displayed on checkout and order confirmation pages</div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Delivery Settings</button>
      </form>
    </div>
  </div>

  <!-- SYSTEM SETTINGS -->
  <div class="card mb-6">
    <div class="card-header">
      <i class="fas fa-cogs" style="color:var(--yellow);"></i>
      <h3>System Settings</h3>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="section" value="system">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Items Per Page (Tables)</label>
            <input type="number" name="items_per_page" class="form-control" min="5" max="100" value="<?= (int)$s['items_per_page'] ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Order Notification Email</label>
            <input type="email" name="orders_notify_email" class="form-control"
                   value="<?= sanitize($s['orders_notify_email']) ?>"
                   placeholder="Leave blank to disable">
            <div class="text-xs text-muted mt-1">Receive an email when new orders are placed</div>
          </div>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <div style="position:relative;display:inline-block;width:44px;height:24px;">
              <input type="checkbox" name="maintenance_mode" id="maintToggle"
                     value="1" <?= $s['maintenance_mode']=='1'?'checked':'' ?>
                     style="opacity:0;width:0;height:0;" onchange="this.form.maintenance_mode.value=this.checked?'1':'0'">
              <label for="maintToggle" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:var(--surface-2);border:1px solid var(--border);border-radius:24px;transition:0.3s;"></label>
            </div>
            <span>
              <div class="form-label" style="margin:0;">Maintenance Mode</div>
              <div class="text-xs text-muted">Customers will see a maintenance page. Admin panel still accessible.</div>
            </span>
          </label>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save System Settings</button>
      </form>
    </div>
  </div>

  <!-- DANGER ZONE -->
  <div class="card" style="border-color:rgba(239,68,68,0.3);">
    <div class="card-header" style="border-color:rgba(239,68,68,0.2);">
      <i class="fas fa-exclamation-triangle" style="color:#f87171;"></i>
      <h3 style="color:#f87171;">Danger Zone</h3>
    </div>
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:14px;background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15);border-radius:8px;margin-bottom:12px;">
        <div>
          <div style="font-weight:600;">Clear All Cart Data</div>
          <div class="text-sm text-muted">Removes all items currently sitting in customer carts.</div>
        </div>
        <button class="btn btn-danger" onclick="confirm('Clear all cart data? This cannot be undone.') && document.getElementById('clearCartForm').submit()">
          <i class="fas fa-trash"></i> Clear Carts
        </button>
      </div>
      <form id="clearCartForm" method="POST">
        <input type="hidden" name="section" value="danger">
        <input type="hidden" name="danger_action" value="clear_carts">
      </form>
      <div style="font-size:12px;color:var(--muted);">
        <i class="fas fa-shield-alt" style="margin-right:4px;"></i>
        Database and order history are never affected by actions above.
      </div>
    </div>
  </div>

</div>

<script>
// Sync checkbox value to hidden input
const mt = document.getElementById('maintToggle');
if (mt) {
  const style = mt.nextElementSibling;
  function updateToggle() {
    style.style.background = mt.checked ? 'var(--red)' : 'var(--surface-2)';
    style.style.borderColor = mt.checked ? 'var(--red)' : 'var(--border)';
  }
  updateToggle();
  mt.addEventListener('change', updateToggle);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
