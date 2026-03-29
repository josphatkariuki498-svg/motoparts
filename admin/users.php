<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = getDB();

$flash = ''; $flashType = 'success';
$myId = (int)$_SESSION['admin_id'];

// ── HANDLE ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_admin') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = trim($_POST['password'] ?? '');

        if (empty($name) || empty($email) || empty($pass)) {
            $flash = 'All fields are required.'; $flashType = 'error';
        } elseif (strlen($pass) < 6) {
            $flash = 'Password must be at least 6 characters.'; $flashType = 'error';
        } else {
            $existing = $db->query("SELECT user_id FROM users WHERE email='".$db->real_escape_string($email)."'")->num_rows;
            if ($existing) {
                $flash = 'Email already in use.'; $flashType = 'error';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,'admin')");
                $stmt->bind_param("sss", $name, $email, $hash);
                $stmt->execute();
                $flash = "Admin account for '$name' created successfully.";
            }
        }
    }

    if ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = trim($_POST['new_password'] ?? '');
        if (strlen($pass) < 6) {
            $flash = 'Password must be at least 6 characters.'; $flashType = 'error';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password='$hash' WHERE user_id=$uid");
            $flash = 'Password reset successfully.';
        }
    }

    if ($action === 'delete_admin') {
        $uid = (int)$_POST['user_id'];
        if ($uid === $myId) {
            $flash = 'You cannot delete your own account.'; $flashType = 'error';
        } else {
            $db->query("DELETE FROM users WHERE user_id=$uid AND role='admin'");
            $flash = 'Admin account deleted.';
        }
    }
}

// ── FETCH ADMINS ──
$admins = $db->query("
    SELECT u.*,
      (SELECT COUNT(*) FROM orders WHERE user_id=u.user_id) as order_count
    FROM users u
    WHERE u.role='admin'
    ORDER BY u.created_at ASC
");

// ── FETCH CUSTOMERS (summary) ──
$custStats = $db->query("
    SELECT COUNT(*) total,
      SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) new_30d,
      SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) new_7d
    FROM users WHERE role='customer'
")->fetch_assoc();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flashType ?>">
  <i class="fas fa-<?= $flashType==='success'?'check-circle':'exclamation-circle' ?>"></i>
  <?= sanitize($flash) ?>
</div>
<?php endif; ?>

<!-- Customer Overview Cards -->
<div class="stats-grid mb-6">
  <div class="stat-card blue">
    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
    <div class="stat-value"><?= number_format($custStats['total']) ?></div>
    <div class="stat-label">Total Customers</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green"><i class="fas fa-user-plus"></i></div>
    <div class="stat-value"><?= $custStats['new_30d'] ?></div>
    <div class="stat-label">New (Last 30 Days)</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon yellow"><i class="fas fa-user-clock"></i></div>
    <div class="stat-value"><?= $custStats['new_7d'] ?></div>
    <div class="stat-label">New (Last 7 Days)</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon red"><i class="fas fa-user-shield"></i></div>
    <div class="stat-value"><?= $admins->num_rows ?></div>
    <div class="stat-label">Admin Accounts</div>
  </div>
</div>

<div class="flex items-center justify-between mb-4">
  <h2 style="font-family:var(--font-head);font-size:20px;font-weight:700;">
    <i class="fas fa-user-shield" style="color:var(--red);margin-right:8px;"></i>Administrator Accounts
  </h2>
  <button class="btn btn-primary" onclick="openModal('addAdminModal')">
    <i class="fas fa-plus"></i> Add Admin
  </button>
</div>

<!-- ADMIN ACCOUNTS TABLE -->
<div class="card mb-6">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Joined</th>
          <th>Last Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php $admins->data_seek(0); while ($adm = $admins->fetch_assoc()): ?>
        <tr <?= $adm['user_id']==$myId ? 'style="background:rgba(230,51,41,0.04);"' : '' ?>>
          <td class="text-muted text-xs"><?= $adm['user_id'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:34px;height:34px;background:var(--red-glow);border:1px solid rgba(230,51,41,0.25);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--red);flex-shrink:0;">
                <i class="fas fa-user-shield"></i>
              </div>
              <div>
                <div style="font-weight:600;"><?= sanitize($adm['name']) ?></div>
                <?php if ($adm['user_id']==$myId): ?>
                <div class="text-xs" style="color:var(--red);">You</div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td class="text-sm"><?= sanitize($adm['email']) ?></td>
          <td class="text-sm text-muted"><?= sanitize($adm['phone'] ?? '—') ?></td>
          <td class="text-sm text-muted"><?= date('d M Y', strtotime($adm['created_at'])) ?></td>
          <td class="text-sm text-muted"><?= date('d M Y H:i', strtotime($adm['updated_at'])) ?></td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-outline btn-sm" title="Reset Password"
                onclick="resetPw(<?= $adm['user_id'] ?>, '<?= sanitize($adm['name']) ?>')">
                <i class="fas fa-key"></i> Reset PW
              </button>
              <?php if ($adm['user_id'] != $myId): ?>
              <form method="POST" onsubmit="return confirm('Delete admin account for <?= sanitize($adm['name']) ?>? This cannot be undone.')">
                <input type="hidden" name="action" value="delete_admin">
                <input type="hidden" name="user_id" value="<?= $adm['user_id'] ?>">
                <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
              </form>
              <?php else: ?>
              <a href="profile.php" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i> Edit</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- RECENT CUSTOMERS TABLE -->
<div class="flex items-center justify-between mb-4">
  <h2 style="font-family:var(--font-head);font-size:20px;font-weight:700;">
    <i class="fas fa-users" style="color:var(--blue);margin-right:8px;"></i>Recent Customers
  </h2>
  <a href="customers.php" class="btn btn-outline btn-sm">
    View All Customers <i class="fas fa-arrow-right"></i>
  </a>
</div>

<?php
$recentCusts = $db->query("
    SELECT u.*,
      (SELECT COUNT(*) FROM orders WHERE user_id=u.user_id) as order_count,
      (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=u.user_id) as total_spent
    FROM users u WHERE u.role='customer'
    ORDER BY u.created_at DESC LIMIT 10
");
?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Spent</th><th>Joined</th></tr>
      </thead>
      <tbody>
        <?php while ($cu = $recentCusts->fetch_assoc()): ?>
        <tr>
          <td class="text-xs text-muted"><?= $cu['user_id'] ?></td>
          <td style="font-weight:500;"><?= sanitize($cu['name']) ?></td>
          <td class="text-sm text-muted"><?= sanitize($cu['email']) ?></td>
          <td class="text-sm text-muted"><?= sanitize($cu['phone'] ?? '—') ?></td>
          <td><?= $cu['order_count'] ?: '<span class="text-muted">0</span>' ?></td>
          <td class="text-sm"><?= $cu['total_spent']>0 ? '<span style="color:var(--green);font-weight:600;">KSh '.number_format($cu['total_spent']).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td class="text-sm text-muted"><?= date('d M Y', strtotime($cu['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD ADMIN MODAL -->
<div class="modal-overlay" id="addAdminModal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <i class="fas fa-user-plus" style="color:var(--red);"></i>
      <h3>Add Administrator</h3>
      <button class="modal-close" onclick="closeModal('addAdminModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_admin">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Jane Wanjiku">
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" required placeholder="admin@example.com">
        </div>
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 characters">
        </div>
        <div style="background:rgba(230,51,41,0.07);border:1px solid rgba(230,51,41,0.2);border-radius:7px;padding:10px 14px;font-size:13px;color:var(--text-2);">
          <i class="fas fa-info-circle" style="color:var(--red);margin-right:6px;"></i>
          This will create a full admin account with access to all sections of the panel.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addAdminModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create Admin</button>
      </div>
    </form>
  </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-overlay" id="resetPwModal">
  <div class="modal" style="max-width:380px;">
    <div class="modal-header">
      <i class="fas fa-key" style="color:var(--yellow);"></i>
      <h3>Reset Password</h3>
      <button class="modal-close" onclick="closeModal('resetPwModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="rp_uid">
      <div class="modal-body">
        <p class="text-sm text-muted mb-4">
          Set a new password for <strong id="rp_name" style="color:var(--text);"></strong>.
        </p>
        <div class="form-group">
          <label class="form-label">New Password *</label>
          <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Min 6 characters">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('resetPwModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Set Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
function resetPw(uid, name) {
  document.getElementById('rp_uid').value = uid;
  document.getElementById('rp_name').textContent = name;
  openModal('resetPwModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
