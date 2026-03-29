<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = getDB();

$flash = ''; $flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['category_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-cog');
        if ($name) {
            $stmt = $db->prepare("INSERT INTO categories (category_name, description, icon) VALUES (?,?,?)");
            $stmt->bind_param("sss", $name, $desc, $icon);
            $stmt->execute();
            $flash = "Category '$name' added.";
        }
    } elseif ($action === 'edit') {
        $id   = (int)$_POST['category_id'];
        $name = trim($_POST['category_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-cog');
        $stmt = $db->prepare("UPDATE categories SET category_name=?, description=?, icon=? WHERE category_id=?");
        $stmt->bind_param("sssi", $name, $desc, $icon, $id);
        $stmt->execute();
        $flash = "Category updated.";
    } elseif ($action === 'delete') {
        $id = (int)$_POST['category_id'];
        $_r = $db->query("SELECT COUNT(*) c FROM spare_parts WHERE category_id=$id"); $count = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
        if ($count > 0) {
            $flash = "Cannot delete: $count parts use this category."; $flashType = 'error';
        } else {
            $db->query("DELETE FROM categories WHERE category_id=$id");
            $flash = "Category deleted.";
        }
    }
}

$categories = $db->query("
    SELECT c.*, COUNT(sp.part_id) as part_count
    FROM categories c
    LEFT JOIN spare_parts sp ON c.category_id=sp.category_id AND sp.is_active=1
    GROUP BY c.category_id ORDER BY c.category_name
");

$iconOptions = ['fa-circle-notch','fa-circle','fa-bolt','fa-arrows-alt-v','fa-cogs','fa-car','fa-filter','fa-cog','fa-wrench','fa-tools','fa-oil-can','fa-tachometer-alt'];

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flashType ?>"><i class="fas fa-<?= $flashType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= sanitize($flash) ?></div>
<?php endif; ?>

<div class="flex items-center justify-between mb-4">
  <h2 style="font-family:var(--font-head);font-size:18px;">Part Categories</h2>
  <button class="btn btn-primary" onclick="openModal('addCatModal')"><i class="fas fa-plus"></i> Add Category</button>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
  <?php while ($cat = $categories->fetch_assoc()): ?>
  <div class="card" style="padding:22px;position:relative;">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
      <div style="width:46px;height:46px;background:var(--red-glow);border:1px solid rgba(230,51,41,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;">
        <i class="fas <?= sanitize($cat['icon']) ?>" style="color:var(--red);font-size:20px;"></i>
      </div>
      <div>
        <div style="font-weight:700;font-family:var(--font-head);font-size:18px;"><?= sanitize($cat['category_name']) ?></div>
        <div class="text-xs text-muted"><?= $cat['part_count'] ?> active part<?= $cat['part_count']!=1?'s':'' ?></div>
      </div>
    </div>
    <?php if ($cat['description']): ?>
    <p class="text-sm text-muted" style="margin-bottom:14px;"><?= sanitize($cat['description']) ?></p>
    <?php endif; ?>
    <div class="flex gap-2">
      <button class="btn btn-outline btn-sm" onclick='editCat(<?= json_encode($cat) ?>)'><i class="fas fa-edit"></i> Edit</button>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
        <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
      </form>
      <a href="parts.php?category=<?= $cat['category_id'] ?>" class="btn btn-ghost btn-sm"><i class="fas fa-cog"></i> Parts</a>
    </div>
  </div>
  <?php endwhile; ?>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addCatModal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <i class="fas fa-layer-group" style="color:var(--red);"></i>
      <h3>Add Category</h3>
      <button class="modal-close" onclick="closeModal('addCatModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Category Name *</label>
          <input type="text" name="category_name" class="form-control" required placeholder="e.g. Engine Parts">
        </div>
        <div class="form-group">
          <label class="form-label">Icon (Font Awesome class)</label>
          <select name="icon" class="form-control">
            <?php foreach ($iconOptions as $ic): ?>
            <option value="<?= $ic ?>"><?= $ic ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" placeholder="Short description..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addCatModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editCatModal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <i class="fas fa-edit" style="color:var(--blue);"></i>
      <h3>Edit Category</h3>
      <button class="modal-close" onclick="closeModal('editCatModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="category_id" id="edit_cat_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Category Name *</label>
          <input type="text" name="category_name" id="edit_cat_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Icon</label>
          <select name="icon" id="edit_cat_icon" class="form-control">
            <?php foreach ($iconOptions as $ic): ?>
            <option value="<?= $ic ?>"><?= $ic ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit_cat_desc" class="form-control"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editCatModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
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
function editCat(c) {
  document.getElementById('edit_cat_id').value   = c.category_id;
  document.getElementById('edit_cat_name').value = c.category_name;
  document.getElementById('edit_cat_desc').value = c.description || '';
  const sel = document.getElementById('edit_cat_icon');
  for(let o of sel.options) o.selected = o.value===c.icon;
  openModal('editCatModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
