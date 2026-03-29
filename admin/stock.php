<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db = getDB();

$flash = ''; $flashType = 'success';

// ── HANDLE STOCK UPDATES ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'adjust') {
        $pid  = (int)$_POST['part_id'];
        $type = $_POST['adjust_type'];  // 'add', 'remove', 'set'
        $qty  = abs((int)$_POST['quantity']);

        if ($qty <= 0 && $type !== 'set') {
            $flash = 'Quantity must be greater than 0.'; $flashType = 'error';
        } else {
            if ($type === 'add') {
                $db->query("UPDATE parts SET stock = stock + $qty WHERE part_id=$pid");
                $flash = "Added $qty units to stock.";
            } elseif ($type === 'remove') {
                $db->query("UPDATE parts SET stock = GREATEST(0, stock - $qty) WHERE part_id=$pid");
                $flash = "Removed $qty units from stock.";
            } elseif ($type === 'set') {
                $qty = abs((int)$_POST['quantity']);
                $db->query("UPDATE parts SET stock = $qty WHERE part_id=$pid");
                $flash = "Stock set to $qty units.";
            }
        }
    }

    if ($action === 'bulk_add') {
        $updates = $_POST['stocks'] ?? [];
        $count = 0;
        foreach ($updates as $pid => $qty) {
            $pid = (int)$pid; $qty = (int)$qty;
            if ($qty >= 0) {
                $db->query("UPDATE parts SET stock=$qty WHERE part_id=$pid");
                $count++;
            }
        }
        $flash = "Updated stock for $count parts.";
    }
}

// ── FILTERS ──
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$catFlt = (int)($_GET['category'] ?? 0);

$where = "p.is_active=1";
if ($search) $where .= " AND p.part_name LIKE '%" . $db->real_escape_string($search) . "%'";
if ($catFlt) $where .= " AND p.category_id=$catFlt";
if ($filter === 'out') $where .= " AND p.stock=0";
if ($filter === 'low') $where .= " AND p.stock>0 AND p.stock<=10";
if ($filter === 'ok')  $where .= " AND p.stock>10";

$parts = $db->query("
    SELECT p.*, c.category_name
    FROM spare_parts p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE $where
    ORDER BY p.stock ASC, p.part_name ASC
");

$cats = $db->query("SELECT * FROM categories ORDER BY category_name");
$catList = [];
if ($cats) while ($c = $cats->fetch_assoc()) $catList[] = $c;

// Summary counts
$_r = $db->query("SELECT COUNT(*) c FROM spare_parts WHERE stock=0 AND is_active=1");
$outCount = $_r ? (int)$_r->fetch_assoc()['c'] : 0;

$_r = $db->query("SELECT COUNT(*) c FROM spare_parts WHERE stock>0 AND stock<=10 AND is_active=1");
$lowCount = $_r ? (int)$_r->fetch_assoc()['c'] : 0;

$_r = $db->query("SELECT COUNT(*) c FROM spare_parts WHERE stock>10 AND is_active=1");
$okCount = $_r ? (int)$_r->fetch_assoc()['c'] : 0;

$_r = $db->query("SELECT COALESCE(SUM(price*stock),0) v FROM spare_parts WHERE is_active=1");
$totalVal = $_r ? (float)$_r->fetch_assoc()['v'] : 0;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="flash flash-<?= $flashType ?>">
  <i class="fas fa-<?= $flashType==='success'?'check-circle':'exclamation-circle' ?>"></i>
  <?= sanitize($flash) ?>
</div>
<?php endif; ?>

<!-- Summary Stats -->
<div class="stats-grid mb-6">
  <a href="?filter=out" style="text-decoration:none;">
    <div class="stat-card red" style="cursor:pointer;<?= $filter==='out'?'border-color:var(--red);':'' ?>">
      <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
      <div class="stat-value"><?= $outCount ?></div>
      <div class="stat-label">Out of Stock</div>
    </div>
  </a>
  <a href="?filter=low" style="text-decoration:none;">
    <div class="stat-card yellow" style="cursor:pointer;<?= $filter==='low'?'border-color:var(--yellow);':'' ?>">
      <div class="stat-icon yellow"><i class="fas fa-exclamation-triangle"></i></div>
      <div class="stat-value"><?= $lowCount ?></div>
      <div class="stat-label">Low Stock (≤10)</div>
    </div>
  </a>
  <a href="?filter=ok" style="text-decoration:none;">
    <div class="stat-card green" style="cursor:pointer;<?= $filter==='ok'?'border-color:var(--green);':'' ?>">
      <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
      <div class="stat-value"><?= $okCount ?></div>
      <div class="stat-label">Well-Stocked</div>
    </div>
  </a>
  <div class="stat-card blue">
    <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
    <div class="stat-value" style="font-size:22px;">KSh <?= number_format($totalVal/1000, 1) ?>K</div>
    <div class="stat-label">Total Inventory Value</div>
  </div>
</div>

<!-- Toolbar -->
<div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:10px;">
  <form class="flex gap-2 items-center" method="GET" style="flex-wrap:wrap;gap:8px;">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input class="form-control" style="width:220px;" name="search"
             placeholder="Search parts..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select class="form-control" name="category" style="width:160px;">
      <option value="">All Categories</option>
      <?php foreach ($catList as $cat): ?>
      <option value="<?= $cat['category_id'] ?>" <?= $catFlt==$cat['category_id']?'selected':'' ?>>
        <?= sanitize($cat['category_name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
    <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-filter"></i></button>
    <?php if ($search || $catFlt): ?>
    <a href="?filter=<?= $filter ?>" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    <?php endif; ?>
  </form>

  <div class="flex gap-2">
    <?php foreach (['all'=>'All','out'=>'Out','low'=>'Low','ok'=>'OK'] as $k=>$v): ?>
    <a href="?filter=<?= $k ?>&search=<?= urlencode($search) ?>&category=<?= $catFlt ?>"
       class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
    <button class="btn btn-outline btn-sm" onclick="openModal('bulkModal')">
      <i class="fas fa-list-ol"></i> Bulk Edit
    </button>
  </div>
</div>

<!-- STOCK TABLE -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-boxes" style="color:var(--yellow);"></i>
    <h3>Inventory
      <span class="text-muted text-sm">&nbsp;
        <?php $cnt = $parts ? $parts->num_rows : 0;
        echo "($cnt part".($cnt!=1?'s':'').")"; ?>
      </span>
    </h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Part</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Value</th>
          <th>Status</th>
          <th>Adjust</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($parts && $parts->num_rows > 0):
          $parts->data_seek(0);
          while ($p = $parts->fetch_assoc()):
            $value = $p['price'] * $p['stock'];
        ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:14px;"><?= sanitize($p['part_name']) ?></div>
          </td>
          <td class="text-sm"><?= sanitize($p['category_name'] ?? '—') ?></td>
          <td class="text-sm">KSh <?= number_format($p['price']) ?></td>
          <td>
            <span style="font-family:var(--font-head);font-size:20px;font-weight:800;color:<?= $p['stock']==0?'var(--red)':($p['stock']<=10?'var(--yellow)':'var(--green)') ?>;">
              <?= $p['stock'] ?>
            </span>
          </td>
          <td class="text-sm <?= $value > 0 ? '' : 'text-muted' ?>">
            <?= $value > 0 ? 'KSh '.number_format($value) : '—' ?>
          </td>
          <td>
            <?php if ($p['stock']==0): ?>
              <span class="tag tag-out">Out</span>
            <?php elseif ($p['stock']<=5): ?>
              <span class="tag tag-out">Critical</span>
            <?php elseif ($p['stock']<=10): ?>
              <span class="tag tag-low">Low</span>
            <?php else: ?>
              <span class="tag tag-active">OK</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-outline btn-sm"
              onclick='openAdjust(<?= json_encode(["id"=>$p['part_id'],"name"=>$p['part_name'],"stock"=>$p['stock']]) ?>)'>
              <i class="fas fa-sliders-h"></i> Adjust
            </button>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="7" style="text-align:center;padding:36px;color:var(--muted);">
          <i class="fas fa-box-open" style="font-size:28px;display:block;margin-bottom:8px;"></i>
          No parts found for this filter.
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADJUST SINGLE PART MODAL -->
<div class="modal-overlay" id="adjustModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <i class="fas fa-sliders-h" style="color:var(--yellow);"></i>
      <h3>Adjust Stock</h3>
      <button class="modal-close" onclick="closeModal('adjustModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="adjust">
      <input type="hidden" name="part_id" id="adj_pid">
      <div class="modal-body">
        <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:20px;">
          <div class="text-xs text-muted">Part</div>
          <div style="font-weight:700;margin-top:2px;" id="adj_name">—</div>
          <div style="margin-top:6px;display:flex;align-items:center;gap:8px;">
            <span class="text-xs text-muted">Current Stock:</span>
            <span style="font-family:var(--font-head);font-size:22px;font-weight:800;color:var(--yellow);" id="adj_stock">0</span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Adjustment Type</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
            <?php foreach ([['add','Add','green','fa-plus'],['remove','Remove','red','fa-minus'],['set','Set To','blue','fa-equals']] as [$v,$l,$c,$i]): ?>
            <label style="cursor:pointer;">
              <input type="radio" name="adjust_type" value="<?= $v ?>"
                     <?= $v==='add'?'checked':'' ?> style="display:none;" class="adj-radio">
              <div class="adj-opt" data-color="<?= $c ?>"
                   style="border:1px solid var(--border);border-radius:7px;padding:10px;text-align:center;transition:all 0.15s;">
                <i class="fas <?= $i ?>" style="color:var(--<?= $c ?>);margin-bottom:4px;display:block;"></i>
                <div style="font-size:12px;font-weight:600;"><?= $l ?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Quantity</label>
          <input type="number" name="quantity" id="adj_qty" class="form-control"
                 min="0" value="1" required placeholder="Enter quantity">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('adjustModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Apply</button>
      </div>
    </form>
  </div>
</div>

<!-- BULK EDIT MODAL -->
<div class="modal-overlay" id="bulkModal">
  <div class="modal" style="max-width:580px;max-height:80vh;">
    <div class="modal-header">
      <i class="fas fa-list-ol" style="color:var(--blue);"></i>
      <h3>Bulk Stock Edit</h3>
      <button class="modal-close" onclick="closeModal('bulkModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="bulk_add">
      <div style="padding:16px 24px;max-height:50vh;overflow-y:auto;">
        <p class="text-sm text-muted mb-4">Set exact stock levels for multiple parts at once.</p>
        <?php
        $allParts = $db->query("SELECT part_id, part_name, stock FROM spare_parts WHERE is_active=1 ORDER BY part_name");
        if ($allParts) while ($p = $allParts->fetch_assoc()):
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border);">
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= sanitize($p['part_name']) ?>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <span class="text-xs text-muted">Current:
              <strong style="color:<?= $p['stock']<=10?'var(--yellow)':'var(--green)' ?>;">
                <?= $p['stock'] ?>
              </strong>
            </span>
            <input type="number" name="stocks[<?= $p['part_id'] ?>]"
                   value="<?= $p['stock'] ?>" min="0"
                   style="width:80px;background:var(--surface-2);border:1px solid var(--border);border-radius:6px;padding:5px 8px;font-size:13px;color:var(--text);outline:none;"
                   onfocus="this.style.borderColor='var(--red)'"
                   onblur="this.style.borderColor='var(--border)'">
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('bulkModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save All Changes</button>
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

function openAdjust(p) {
  document.getElementById('adj_pid').value          = p.id;
  document.getElementById('adj_name').textContent   = p.name;
  document.getElementById('adj_stock').textContent  = p.stock;
  document.getElementById('adj_qty').value          = 1;
  openModal('adjustModal');
}

document.querySelectorAll('.adj-radio').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.adj-opt').forEach(el => {
      el.style.background   = 'transparent';
      el.style.borderColor  = 'var(--border)';
    });
    const opt = radio.nextElementSibling;
    const col = opt.dataset.color;
    opt.style.background  = `rgba(var(--${col}-rgb,100,100,100),0.1)`;
    opt.style.borderColor = `var(--${col})`;
  });
});
document.querySelector('.adj-radio:checked')?.dispatchEvent(new Event('change'));
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
