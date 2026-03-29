<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = getDB();

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_payment') {
    $pid    = (int)$_POST['payment_id'];
    $status = $db->real_escape_string($_POST['status']);
    $txn    = $db->real_escape_string(trim($_POST['transaction_id'] ?? ''));
    $db->query("UPDATE payments SET status='$status', transaction_id='$txn' WHERE payment_id=$pid");
    $flash = 'Payment updated.';
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$perPage= 15;
$offset = ($page-1)*$perPage;

$where = "1=1";
if ($search) $where .= " AND (u.name LIKE '%$search%' OR p.transaction_id LIKE '%$search%' OR o.order_id LIKE '%$search%')";
if ($status) $where .= " AND p.status='$status'";

$_r = $db->query("SELECT COUNT(*) c FROM payments p JOIN orders o ON p.order_id=o.order_id JOIN users u ON o.user_id=u.user_id WHERE $where"); $total = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$pages = ceil($total/$perPage);

$payments = $db->query("
    SELECT p.*, o.total_amount, o.status as order_status,
           u.name as customer_name, u.email
    FROM payments p
    JOIN orders o ON p.order_id=o.order_id
    JOIN users u ON o.user_id=u.user_id
    WHERE $where
    ORDER BY p.payment_date DESC
    LIMIT $perPage OFFSET $offset
");

// Summary
$summary = $db->query("SELECT status, COUNT(*) c, COALESCE(SUM(amount),0) total FROM payments GROUP BY status")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= sanitize($flash) ?></div>
<?php endif; ?>

<!-- Summary chips -->
<div class="flex gap-3 mb-6" style="flex-wrap:wrap;">
  <?php
  $colors = ['completed'=>'green','pending'=>'yellow','failed'=>'red','refunded'=>'blue'];
  foreach ($summary as $s):
    $col = $colors[$s['status']] ?? 'muted';
  ?>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 20px;min-width:140px;">
    <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:1px;"><?= ucfirst($s['status']) ?></div>
    <div style="font-family:var(--font-head);font-size:24px;font-weight:800;margin:4px 0;"><?= $s['c'] ?></div>
    <div class="text-sm" style="color:var(--<?= $col ?>);">KSh <?= number_format($s['total']) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="flex items-center justify-between mb-4">
  <form class="flex gap-2" method="GET">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input class="form-control" style="width:230px;" name="search" placeholder="Customer, order or TXN..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select class="form-control" name="status" style="width:140px;">
      <option value="">All Statuses</option>
      <?php foreach (['pending','completed','failed','refunded'] as $s): ?>
      <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline btn-sm" type="submit"><i class="fas fa-filter"></i></button>
    <?php if ($search||$status): ?><a href="payments.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-credit-card" style="color:var(--green);"></i>
    <h3>Payments <span class="text-muted text-sm">(<?= $total ?>)</span></h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#Pay</th>
          <th>Order</th>
          <th>Customer</th>
          <th>Method</th>
          <th>Amount</th>
          <th>TXN ID</th>
          <th>Status</th>
          <th>Date</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php while ($p = $payments->fetch_assoc()): ?>
        <tr>
          <td class="text-muted text-xs"><?= $p['payment_id'] ?></td>
          <td><a href="orders.php?id=<?= $p['order_id'] ?>" style="color:var(--red);font-weight:600;">#<?= $p['order_id'] ?></a></td>
          <td>
            <div style="font-weight:500;"><?= sanitize($p['customer_name']) ?></div>
            <div class="text-xs text-muted"><?= sanitize($p['email']) ?></div>
          </td>
          <td class="text-sm"><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
          <td style="font-weight:700;">KSh <?= number_format($p['amount']) ?></td>
          <td class="text-xs text-muted" style="font-family:monospace;"><?= sanitize($p['transaction_id'] ?? '—') ?></td>
          <td><span class="tag tag-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
          <td class="text-sm text-muted"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
          <td>
            <button class="btn btn-ghost btn-sm" onclick='editPayment(<?= json_encode($p) ?>)'><i class="fas fa-edit"></i></button>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($total===0): ?>
        <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--muted);">No payments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal-overlay" id="editPayModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <i class="fas fa-edit" style="color:var(--green);"></i>
      <h3>Update Payment</h3>
      <button class="modal-close" onclick="closeModal('editPayModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_payment">
      <input type="hidden" name="payment_id" id="ep_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" id="ep_status" class="form-control">
            <?php foreach (['pending','completed','failed','refunded'] as $s): ?>
            <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Transaction ID</label>
          <input type="text" name="transaction_id" id="ep_txn" class="form-control" placeholder="Optional reference">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editPayModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o=>{
  o.addEventListener('click', e=>{ if(e.target===o) o.classList.remove('open'); });
});
function editPayment(p) {
  document.getElementById('ep_id').value = p.payment_id;
  document.getElementById('ep_txn').value = p.transaction_id || '';
  const sel = document.getElementById('ep_status');
  for(let o of sel.options) o.selected = o.value===p.status;
  openModal('editPayModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
