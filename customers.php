<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = getDB();

$flash = ''; $flashType = 'success';

// ── TOGGLE BAN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $db->query("UPDATE users SET password='$pass' WHERE user_id=$uid AND role='customer'");
        $flash = 'Password reset successfully.';
    }
}

$search  = trim($_GET['search'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page-1)*$perPage;

$where = "role='customer'";
if ($search) $where .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";

$_r = $db->query("SELECT COUNT(*) c FROM users WHERE $where"); $total = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$pages  = ceil($total/$perPage);

$customers = $db->query("
    SELECT u.*,
      (SELECT COUNT(*) FROM orders WHERE user_id=u.user_id) as order_count,
      (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=u.user_id) as total_spent,
      (SELECT MAX(order_date) FROM orders WHERE user_id=u.user_id) as last_order
    FROM users u
    WHERE $where
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= sanitize($flash) ?></div>
<?php endif; ?>

<div class="flex items-center justify-between mb-4">
  <form class="flex gap-2" method="GET">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input class="form-control" style="width:260px;" name="search" placeholder="Search by name, email or phone..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <button class="btn btn-outline btn-sm" type="submit"><i class="fas fa-filter"></i></button>
    <?php if ($search): ?><a href="customers.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
  </form>
  <div class="text-muted text-sm"><?= number_format($total) ?> customers</div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-users" style="color:var(--blue);"></i>
    <h3>Customers</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Customer</th>
          <th>Phone</th>
          <th>Orders</th>
          <th>Total Spent</th>
          <th>Last Order</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($c = $customers->fetch_assoc()): ?>
        <tr>
          <td class="text-muted text-xs"><?= $c['user_id'] ?></td>
          <td>
            <div style="font-weight:600;"><?= sanitize($c['name']) ?></div>
            <div class="text-xs text-muted"><?= sanitize($c['email']) ?></div>
          </td>
          <td class="text-sm text-muted"><?= sanitize($c['phone'] ?? '—') ?></td>
          <td>
            <?php if ($c['order_count']>0): ?>
            <a href="orders.php?search=<?= urlencode($c['name']) ?>" style="color:var(--blue);font-weight:600;"><?= $c['order_count'] ?></a>
            <?php else: ?><span class="text-muted">0</span><?php endif; ?>
          </td>
          <td style="font-weight:600;">
            <?php if ($c['total_spent']>0): ?>
            <span style="color:var(--green);">KSh <?= number_format($c['total_spent']) ?></span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td class="text-sm text-muted">
            <?= $c['last_order'] ? date('d M Y', strtotime($c['last_order'])) : '—' ?>
          </td>
          <td class="text-sm text-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
          <td>
            <button class="btn btn-ghost btn-sm" title="Reset Password"
              onclick="resetPw(<?= $c['user_id'] ?>, '<?= sanitize($c['name']) ?>')">
              <i class="fas fa-key"></i>
            </button>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($total===0): ?>
        <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--muted);">No customers found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages>1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:6px;">
    <?php for($i=1;$i<=$pages;$i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $i==$page?'btn-primary':'btn-outline' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Reset password modal -->
<div class="modal-overlay" id="resetModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <i class="fas fa-key" style="color:var(--yellow);"></i>
      <h3>Reset Password</h3>
      <button class="modal-close" onclick="closeModal('resetModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="reset_uid">
      <div class="modal-body">
        <p class="text-sm text-muted mb-4">Set a new password for <strong id="reset_name"></strong>.</p>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Min 6 characters">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('resetModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
function resetPw(id, name) {
  document.getElementById('reset_uid').value = id;
  document.getElementById('reset_name').textContent = name;
  openModal('resetModal');
}
document.querySelectorAll('.modal-overlay').forEach(o=>{
  o.addEventListener('click', e=>{ if(e.target===o) o.classList.remove('open'); });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
