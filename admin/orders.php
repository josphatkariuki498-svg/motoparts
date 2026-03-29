<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = getDB();

$flash = ''; $flashType = 'success';

// ── UPDATE STATUS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $oid    = (int)$_POST['order_id'];
        $status = $db->real_escape_string($_POST['status']);
        $valid  = ['pending','confirmed','processing','shipped','delivered','cancelled'];
        if (in_array($status, $valid)) {
            $db->query("UPDATE orders SET status='$status' WHERE order_id=$oid");
            $flash = "Order #$oid status updated to " . ucfirst($status) . ".";
        }
    }
}

// ── FILTERS ──
$search    = trim($_GET['search'] ?? '');
$status    = trim($_GET['status'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;
$offset    = ($page - 1) * $perPage;

$where = "1=1";
if ($search) $where .= " AND (u.name LIKE '%$search%' OR o.order_id LIKE '%$search%')";
if ($status) $where .= " AND o.status='$status'";

$_r = $db->query("SELECT COUNT(*) c FROM orders o JOIN users u ON o.user_id=u.user_id WHERE $where"); $total = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$pages = ceil($total / $perPage);

$orders = $db->query("
    SELECT o.*, u.name as customer_name, u.email, u.phone,
           p.status as pay_status, p.payment_method,
           (SELECT COUNT(*) FROM order_details WHERE order_id=o.order_id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN payments p ON p.order_id = o.order_id
    WHERE $where
    ORDER BY o.order_date DESC
    LIMIT $perPage OFFSET $offset
");

// View single order detail
$viewOrder = null; $orderItems = null;
if (isset($_GET['id'])) {
    $oid = (int)$_GET['id'];
    $viewOrder = $db->query("
        SELECT o.*, u.name as customer_name, u.email, u.phone, u.address,
               p.status as pay_status, p.payment_method, p.amount as pay_amount, p.transaction_id
        FROM orders o
        JOIN users u ON o.user_id=u.user_id
        LEFT JOIN payments p ON p.order_id=o.order_id
        WHERE o.order_id=$oid
    ")->fetch_assoc();
    $orderItems = $db->query("
        SELECT od.*, sp.part_name, sp.brand, sp.sku
        FROM order_details od
        JOIN spare_parts sp ON od.part_id=sp.part_id
        WHERE od.order_id=$oid
    ");
}

$statusList = ['pending','confirmed','processing','shipped','delivered','cancelled'];

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flashType ?>">
  <i class="fas fa-check-circle"></i> <?= sanitize($flash) ?>
</div>
<?php endif; ?>

<!-- Order Detail Modal (if ?id is set) -->
<?php if ($viewOrder): ?>
<div class="modal-overlay open" id="orderDetailModal">
  <div class="modal" style="max-width:680px;">
    <div class="modal-header">
      <i class="fas fa-receipt" style="color:var(--red);"></i>
      <h3>Order #<?= $viewOrder['order_id'] ?></h3>
      <a href="orders.php" class="modal-close"><i class="fas fa-times"></i></a>
    </div>
    <div class="modal-body">
      <div class="grid-2 mb-4">
        <div>
          <div class="text-xs text-muted" style="margin-bottom:4px;">Customer</div>
          <div style="font-weight:600;"><?= sanitize($viewOrder['customer_name']) ?></div>
          <div class="text-sm text-muted"><?= sanitize($viewOrder['email']) ?></div>
          <div class="text-sm text-muted"><?= sanitize($viewOrder['phone'] ?? '') ?></div>
        </div>
        <div>
          <div class="text-xs text-muted" style="margin-bottom:4px;">Order Info</div>
          <div class="text-sm">Date: <strong><?= date('d M Y H:i', strtotime($viewOrder['order_date'])) ?></strong></div>
          <div class="text-sm">Status: <span class="tag tag-<?= $viewOrder['status'] ?>"><?= ucfirst($viewOrder['status']) ?></span></div>
          <div class="text-sm">Payment: <span class="tag tag-<?= $viewOrder['pay_status'] ?? 'pending' ?>"><?= ucfirst($viewOrder['pay_status'] ?? 'Unpaid') ?></span></div>
        </div>
      </div>

      <?php if (!empty($viewOrder['shipping_address'] ?? '')): ?>
      <div class="mb-4">
        <div class="text-xs text-muted" style="margin-bottom:4px;">Shipping Address</div>
        <div class="text-sm" style="background:var(--surface-2);padding:10px;border-radius:6px;"><?= sanitize($viewOrder['shipping_address']) ?></div>
      </div>
      <?php endif; ?>

      <table style="width:100%;font-size:13px;border-collapse:collapse;">
        <thead>
          <tr style="border-bottom:1px solid var(--border);">
            <th style="padding:8px 0;text-align:left;color:var(--muted);font-size:11px;letter-spacing:1px;">PART</th>
            <th style="padding:8px;text-align:right;color:var(--muted);font-size:11px;">QTY</th>
            <th style="padding:8px;text-align:right;color:var(--muted);font-size:11px;">UNIT</th>
            <th style="padding:8px 0;text-align:right;color:var(--muted);font-size:11px;">SUBTOTAL</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($item = $orderItems->fetch_assoc()): ?>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:10px 0;">
              <div style="font-weight:600;"><?= sanitize($item['part_name']) ?></div>
              <div class="text-xs text-muted"><?= sanitize($item['sku']) ?></div>
            </td>
            <td style="padding:10px 8px;text-align:right;"><?= $item['quantity'] ?></td>
            <td style="padding:10px 8px;text-align:right;">KSh <?= number_format($item['unit_price']) ?></td>
            <td style="padding:10px 0;text-align:right;font-weight:600;">KSh <?= number_format($item['subtotal']) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="padding:12px 0;text-align:right;font-weight:700;font-family:var(--font-head);font-size:16px;">TOTAL</td>
            <td style="padding:12px 0;text-align:right;font-weight:800;font-family:var(--font-head);font-size:18px;color:var(--red);">KSh <?= number_format($viewOrder['total_amount']) ?></td>
          </tr>
        </tfoot>
      </table>

      <!-- Update status form -->
      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
        <form method="POST" class="flex gap-2 items-center">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="order_id" value="<?= $viewOrder['order_id'] ?>">
          <label class="form-label" style="white-space:nowrap;margin:0;">Update Status:</label>
          <select name="status" class="form-control" style="width:auto;">
            <?php foreach ($statusList as $s): ?>
            <option value="<?= $s ?>" <?= $viewOrder['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Update</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- TOOLBAR -->
<div class="flex items-center justify-between mb-4">
  <form class="flex gap-2 items-center" method="GET">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input class="form-control" style="width:220px;" name="search" placeholder="Search by name or ID..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select class="form-control" name="status" style="width:150px;">
      <option value="">All Statuses</option>
      <?php foreach ($statusList as $s): ?>
      <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline btn-sm" type="submit"><i class="fas fa-filter"></i></button>
    <?php if ($search||$status): ?>
    <a href="orders.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    <?php endif; ?>
  </form>
  <div class="text-muted text-sm"><?= number_format($total) ?> orders</div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-shopping-bag" style="color:var(--red);"></i>
    <h3>Orders</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#ID</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($o = $orders->fetch_assoc()): ?>
        <tr>
          <td><a href="orders.php?id=<?= $o['order_id'] ?>" style="color:var(--red);font-weight:700;">#<?= $o['order_id'] ?></a></td>
          <td>
            <div style="font-weight:500;"><?= sanitize($o['customer_name']) ?></div>
            <div class="text-xs text-muted"><?= sanitize($o['email']) ?></div>
          </td>
          <td class="text-muted"><?= $o['item_count'] ?> item<?= $o['item_count']!=1?'s':'' ?></td>
          <td style="font-weight:700;">KSh <?= number_format($o['total_amount']) ?></td>
          <td>
            <span class="tag tag-<?= $o['pay_status'] ?? 'pending' ?> ">
              <?= $o['pay_status'] ? ucfirst($o['pay_status']) : 'Unpaid' ?>
            </span>
            <?php if ($o['payment_method']): ?>
            <div class="text-xs text-muted"><?= ucfirst(str_replace('_',' ',$o['payment_method'])) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" class="flex gap-1 items-center" style="min-width:160px;">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
              <select name="status" class="form-control" style="padding:4px 8px;font-size:12px;width:120px;">
                <?php foreach ($statusList as $s): ?>
                <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-ghost btn-sm" title="Save"><i class="fas fa-check"></i></button>
            </form>
          </td>
          <td class="text-sm text-muted"><?= date('d M Y', strtotime($o['order_date'])) ?></td>
          <td>
            <a href="orders.php?id=<?= $o['order_id'] ?>" class="btn btn-ghost btn-sm" title="View"><i class="fas fa-eye"></i></a>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($total === 0): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--muted);">No orders found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:6px;align-items:center;">
    <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
       class="btn btn-sm <?= $i==$page?'btn-primary':'btn-outline' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
