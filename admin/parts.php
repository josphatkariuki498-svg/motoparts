<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = getDB();

$flash = ''; $flashType = 'success';

// ── HANDLE ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id    = (int)($_POST['part_id'] ?? 0);
        $name  = trim($_POST['part_name'] ?? '');
        $cat   = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $desc  = trim($_POST['description'] ?? '');
        $active= isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || $price <= 0) {
            $flash = 'Part name and price are required.'; $flashType = 'error';
        } elseif ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO parts (part_name, category_id, price, stock, description, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("siidsi", $name, $cat, $price, $stock, $desc, $active);
            if ($stmt->execute()) { $flash = "Part '$name' added successfully!"; }
            else { $flash = 'Error: ' . $stmt->error; $flashType = 'error'; }
        } else {
            $stmt = $db->prepare("UPDATE parts SET part_name=?, category_id=?, price=?, stock=?, description=?, is_active=? WHERE part_id=?");
            $stmt->bind_param("siidsii", $name, $cat, $price, $stock, $desc, $active, $id);
            if ($stmt->execute()) { $flash = "Part updated successfully!"; }
            else { $flash = 'Error: ' . $stmt->error; $flashType = 'error'; }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['part_id'];
        $db->query("UPDATE parts SET is_active=0 WHERE part_id=$id");
        $flash = 'Part deactivated successfully.';
    }

    if ($action === 'restore') {
        $id = (int)$_POST['part_id'];
        $db->query("UPDATE parts SET is_active=1 WHERE part_id=$id");
        $flash = 'Part restored successfully.';
    }
}

// ── FILTERS ──
$search    = trim($_GET['search'] ?? '');
$catFilter = (int)($_GET['category'] ?? 0);
$filter    = $_GET['filter'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;
$offset    = ($page - 1) * $perPage;

$where = "1=1";
if ($search)    $where .= " AND p.part_name LIKE '%" . $db->real_escape_string($search) . "%'";
if ($catFilter) $where .= " AND p.category_id=" . $catFilter;

if ($filter === 'low')      $where .= " AND p.stock<=10 AND p.stock>0 AND p.is_active=1";
elseif ($filter === 'out')  $where .= " AND p.stock=0 AND p.is_active=1";
elseif ($filter === 'inactive') $where .= " AND p.is_active=0";
else                        $where .= " AND p.is_active=1";

$_r   = $db->query("SELECT COUNT(*) c FROM spare_parts p WHERE $where");
$total = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$pages = ceil($total / $perPage);

$parts = $db->query("
    SELECT p.*, c.category_name
    FROM spare_parts p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE $where
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");

$categories = $db->query("SELECT * FROM categories ORDER BY category_name");
$catList = [];
if ($categories) while ($c = $categories->fetch_assoc()) $catList[] = $c;

// Edit mode
$editPart = null;
if (isset($_GET['edit_id'])) {
    $eid = (int)$_GET['edit_id'];
    $r   = $db->query("SELECT * FROM spare_parts WHERE part_id=$eid");
    if ($r) $editPart = $r->fetch_assoc();
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flashType ?>">
  <i class="fas fa-<?= $flashType==='success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
  <?= sanitize($flash) ?>
</div>
<?php endif; ?>

<!-- TOOLBAR -->
<div class="flex items-center justify-between mb-4">
  <form class="flex gap-2 items-center" method="GET">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input class="form-control" style="width:240px;" name="search"
             placeholder="Search parts..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select class="form-control" name="category" style="width:160px;">
      <option value="">All Categories</option>
      <?php foreach ($catList as $cat): ?>
      <option value="<?= $cat['category_id'] ?>" <?= $catFilter==$cat['category_id']?'selected':'' ?>>
        <?= sanitize($cat['category_name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <select class="form-control" name="filter" style="width:130px;">
      <option value="">All Active</option>
      <option value="low"      <?= $filter==='low'     ?'selected':'' ?>>Low Stock</option>
      <option value="out"      <?= $filter==='out'     ?'selected':'' ?>>Out of Stock</option>
      <option value="inactive" <?= $filter==='inactive'?'selected':'' ?>>Inactive</option>
    </select>
    <button class="btn btn-outline btn-sm" type="submit"><i class="fas fa-filter"></i> Filter</button>
    <?php if ($search || $catFilter || $filter): ?>
    <a href="parts.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    <?php endif; ?>
  </form>
  <button class="btn btn-primary" onclick="openModal('addModal')">
    <i class="fas fa-plus"></i> Add Part
  </button>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-cog" style="color:var(--red);"></i>
    <h3>Spare Parts <span class="text-muted text-sm">(<?= $total ?>)</span></h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Part Name</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($parts && $parts->num_rows > 0):
          while ($p = $parts->fetch_assoc()): ?>
        <tr>
          <td style="font-weight:600;max-width:220px;"><?= sanitize($p['part_name']) ?></td>
          <td class="text-sm"><?= sanitize($p['category_name'] ?? '-') ?></td>
          <td style="font-weight:600;">KSh <?= number_format($p['price']) ?></td>
          <td>
            <?php if ($p['stock'] == 0): ?>
              <span class="tag tag-out">Out</span>
            <?php elseif ($p['stock'] <= 10): ?>
              <span class="tag tag-low"><?= $p['stock'] ?></span>
            <?php else: ?>
              <span style="color:var(--green);font-weight:600;"><?= $p['stock'] ?></span>
            <?php endif; ?>
          </td>
          <td>
            <span class="tag <?= $p['is_active'] ? 'tag-active' : 'tag-inactive' ?>">
              <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-sm" title="Edit"
                      onclick='editPart(<?= json_encode($p) ?>)'>
                <i class="fas fa-edit"></i>
              </button>
              <?php if ($p['is_active']): ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Deactivate this part?')">
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="part_id" value="<?= $p['part_id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">
                  <i class="fas fa-ban"></i>
                </button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"  value="restore">
                <input type="hidden" name="part_id" value="<?= $p['part_id'] ?>">
                <button class="btn btn-success btn-sm" type="submit">
                  <i class="fas fa-check"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--muted);">
          <i class="fas fa-search" style="font-size:24px;display:block;margin-bottom:8px;"></i>
          No parts found.
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:6px;align-items:center;">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $catFilter ?>&filter=<?= $filter ?>"
       class="btn btn-sm <?= $i==$page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <span class="text-muted text-sm" style="margin-left:8px;">
      Page <?= $page ?> of <?= $pages ?>
    </span>
  </div>
  <?php endif; ?>
</div>

<!-- ── ADD MODAL ── -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <i class="fas fa-plus-circle" style="color:var(--red);"></i>
      <h3>Add New Part</h3>
      <button class="modal-close" onclick="closeModal('addModal')">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Part Name *</label>
          <input type="text" name="part_name" class="form-control" required
                 placeholder="e.g. Piston Kit 100cc">
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-control">
              <option value="">-- Select --</option>
              <?php foreach ($catList as $cat): ?>
              <option value="<?= $cat['category_id'] ?>">
                <?= sanitize($cat['category_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Price (KSh) *</label>
            <input type="number" name="price" class="form-control"
                   step="0.01" min="0" required placeholder="0.00">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Stock Quantity</label>
          <input type="number" name="stock" class="form-control" min="0" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control"
                    placeholder="Part description..."></textarea>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" checked>
            <span class="form-label" style="margin:0;">Active (visible to customers)</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline"
                onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Save Part
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── EDIT MODAL ── -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <i class="fas fa-edit" style="color:var(--blue);"></i>
      <h3>Edit Part</h3>
      <button class="modal-close" onclick="closeModal('editModal')">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST" id="editForm">
      <input type="hidden" name="action"  value="edit">
      <input type="hidden" name="part_id" id="edit_part_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Part Name *</label>
          <input type="text" name="part_name" id="edit_part_name"
                 class="form-control" required>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" id="edit_category_id" class="form-control">
              <option value="">-- Select --</option>
              <?php foreach ($catList as $cat): ?>
              <option value="<?= $cat['category_id'] ?>">
                <?= sanitize($cat['category_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Price (KSh) *</label>
            <input type="number" name="price" id="edit_price"
                   class="form-control" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Stock</label>
          <input type="number" name="stock" id="edit_stock"
                 class="form-control" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit_description"
                    class="form-control"></textarea>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" id="edit_is_active">
            <span class="form-label" style="margin:0;">Active</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline"
                onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Update Part
        </button>
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

function editPart(p) {
  document.getElementById('edit_part_id').value      = p.part_id;
  document.getElementById('edit_part_name').value    = p.part_name;
  document.getElementById('edit_price').value        = p.price;
  document.getElementById('edit_stock').value        = p.stock;
  document.getElementById('edit_description').value  = p.description || '';
  document.getElementById('edit_is_active').checked  = p.is_active == 1;
  const sel = document.getElementById('edit_category_id');
  for (let o of sel.options) { o.selected = (o.value == p.category_id); }
  openModal('editModal');
}

<?php if ($editPart): ?>
editPart(<?= json_encode($editPart) ?>);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
