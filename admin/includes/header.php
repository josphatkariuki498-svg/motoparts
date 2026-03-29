<?php
// Must be called AFTER session_start() and requireAdmin()
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$adminName   = $_SESSION['admin_name'] ?? 'Admin';

// Nav items
$navItems = [
  ['dashboard',  'fas fa-tachometer-alt', 'Dashboard'],
  ['parts',      'fas fa-cog',            'Spare Parts'],
  ['stock',      'fas fa-boxes',          'Inventory'],
  ['categories', 'fas fa-layer-group',    'Categories'],
  ['orders',     'fas fa-shopping-bag',   'Orders'],
  ['customers',  'fas fa-users',          'Customers'],
  ['payments',   'fas fa-credit-card',    'Payments'],
  ['reports',    'fas fa-chart-bar',      'Reports'],
  ['users',      'fas fa-user-shield',    'Admin Users'],
  ['settings',   'fas fa-sliders-h',      'Settings'],
];

// Fetch unread/pending counts for badges
$db = getDB();
$_r = $db->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'"); $pendingOrders = $_r ? (int)$_r->fetch_assoc()["c"] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MotoParts Admin — <?= ucfirst($currentPage) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --red: #e63329;
    --red-dark: #b5261e;
    --red-glow: rgba(230,51,41,0.15);
    --bg: #0a0a0b;
    --surface: #111114;
    --surface-2: #18181d;
    --surface-3: #1e1e24;
    --border: rgba(255,255,255,0.07);
    --border-2: rgba(255,255,255,0.12);
    --text: #f0f0f2;
    --text-2: #a0a0ab;
    --muted: #5a5a68;
    --green: #22c55e;
    --yellow: #eab308;
    --blue: #3b82f6;
    --font-head: 'Barlow Condensed', sans-serif;
    --font-body: 'Barlow', sans-serif;
    --sidebar-w: 240px;
    --topbar-h: 64px;
    --radius: 10px;
  }

  html, body { height: 100%; background: var(--bg); font-family: var(--font-body); color: var(--text); }

  /* ── SIDEBAR ── */
  .sidebar {
    position: fixed; top: 0; left: 0;
    width: var(--sidebar-w); height: 100%;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    z-index: 100;
    transition: transform 0.3s;
  }

  .sidebar-brand {
    height: var(--topbar-h);
    display: flex; align-items: center;
    padding: 0 20px;
    border-bottom: 1px solid var(--border);
    gap: 12px;
    flex-shrink: 0;
  }
  .sidebar-brand .icon {
    width: 36px; height: 36px;
    background: var(--red);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; color: #fff;
    flex-shrink: 0;
  }
  .sidebar-brand .name {
    font-family: var(--font-head);
    font-weight: 800; font-size: 20px;
    letter-spacing: 0.5px;
  }
  .sidebar-brand .name span { color: var(--red); }

  .nav-section {
    padding: 20px 12px 6px;
    font-size: 10px; font-weight: 600;
    letter-spacing: 1.8px; text-transform: uppercase;
    color: var(--muted);
  }

  .nav-link {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px;
    margin: 2px 8px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-2);
    font-size: 14px; font-weight: 500;
    transition: all 0.18s;
    position: relative;
  }
  .nav-link i { width: 18px; text-align: center; font-size: 15px; }
  .nav-link:hover { background: var(--surface-2); color: var(--text); }
  .nav-link.active {
    background: var(--red-glow);
    color: var(--red);
    border: 1px solid rgba(230,51,41,0.2);
  }
  .nav-link.active i { color: var(--red); }

  .badge {
    margin-left: auto;
    background: var(--red);
    color: #fff;
    font-size: 10px; font-weight: 700;
    padding: 2px 6px;
    border-radius: 20px;
    min-width: 18px; text-align: center;
  }

  .sidebar-spacer { flex: 1; }

  .sidebar-user {
    padding: 16px 12px;
    border-top: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
  }
  .user-avatar {
    width: 36px; height: 36px;
    background: var(--red-glow);
    border: 1px solid rgba(230,51,41,0.3);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; color: var(--red);
    flex-shrink: 0;
  }
  .user-info { flex: 1; min-width: 0; }
  .user-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .user-role { font-size: 11px; color: var(--muted); }
  .logout-btn {
    color: var(--muted);
    font-size: 15px;
    background: none; border: none;
    cursor: pointer; padding: 4px;
    transition: color 0.2s;
    text-decoration: none;
  }
  .logout-btn:hover { color: var(--red); }

  /* ── TOPBAR ── */
  .topbar {
    position: fixed;
    top: 0; left: var(--sidebar-w); right: 0;
    height: var(--topbar-h);
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center;
    padding: 0 28px;
    gap: 16px;
    z-index: 90;
  }
  .topbar-title {
    font-family: var(--font-head);
    font-size: 22px; font-weight: 700;
    letter-spacing: 0.5px;
  }
  .topbar-breadcrumb {
    font-size: 13px; color: var(--muted);
    display: flex; align-items: center; gap: 6px;
  }
  .topbar-breadcrumb a { color: var(--text-2); text-decoration: none; }
  .topbar-breadcrumb a:hover { color: var(--red); }
  .topbar-spacer { flex: 1; }
  .topbar-time {
    font-size: 12px; color: var(--muted);
    font-family: monospace;
  }

  /* ── MAIN ── */
  .main {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 28px;
    min-height: calc(100vh - var(--topbar-h));
  }

  /* ── CARDS ── */
  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
  }
  .card-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
  }
  .card-header h3 {
    font-family: var(--font-head);
    font-size: 16px; font-weight: 700;
    letter-spacing: 0.5px;
    flex: 1;
  }
  .card-body { padding: 22px; }

  /* ── STAT CARDS ── */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }
  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px;
    position: relative;
    overflow: hidden;
    transition: border-color 0.2s;
  }
  .stat-card:hover { border-color: var(--border-2); }
  .stat-card::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
  }
  .stat-card.red::after   { background: var(--red); }
  .stat-card.green::after { background: var(--green); }
  .stat-card.blue::after  { background: var(--blue); }
  .stat-card.yellow::after{ background: var(--yellow); }

  .stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 14px;
  }
  .stat-icon.red    { background: var(--red-glow); color: var(--red); }
  .stat-icon.green  { background: rgba(34,197,94,0.12); color: var(--green); }
  .stat-icon.blue   { background: rgba(59,130,246,0.12); color: var(--blue); }
  .stat-icon.yellow { background: rgba(234,179,8,0.12); color: var(--yellow); }

  .stat-value {
    font-family: var(--font-head);
    font-size: 34px; font-weight: 800;
    line-height: 1; margin-bottom: 4px;
  }
  .stat-label { font-size: 13px; color: var(--text-2); }
  .stat-change { font-size: 12px; margin-top: 6px; display: flex; align-items: center; gap: 4px; }
  .stat-change.up   { color: var(--green); }
  .stat-change.down { color: var(--red); }

  /* ── TABLE ── */
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  thead tr { border-bottom: 1px solid var(--border-2); }
  th {
    padding: 10px 14px;
    text-align: left;
    font-size: 11px; font-weight: 600;
    letter-spacing: 1.2px; text-transform: uppercase;
    color: var(--muted);
    white-space: nowrap;
  }
  td { padding: 13px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tbody tr:hover { background: var(--surface-2); }

  /* ── BADGES ── */
  .tag {
    display: inline-flex; align-items: center;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px; font-weight: 600;
    white-space: nowrap;
  }
  .tag-pending   { background: rgba(234,179,8,0.12); color: var(--yellow); }
  .tag-confirmed { background: rgba(59,130,246,0.12); color: var(--blue); }
  .tag-processing{ background: rgba(168,85,247,0.12); color: #c084fc; }
  .tag-shipped   { background: rgba(6,182,212,0.12);  color: #22d3ee; }
  .tag-delivered { background: rgba(34,197,94,0.12);  color: var(--green); }
  .tag-cancelled { background: rgba(239,68,68,0.12);  color: #f87171; }
  .tag-active    { background: rgba(34,197,94,0.12);  color: var(--green); }
  .tag-inactive  { background: rgba(100,100,100,0.15);color: var(--muted); }
  .tag-low       { background: rgba(234,179,8,0.12);  color: var(--yellow); }
  .tag-out       { background: rgba(239,68,68,0.12);  color: #f87171; }

  /* ── BUTTONS ── */
  .btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 16px;
    border-radius: 7px;
    font-family: var(--font-body);
    font-size: 13px; font-weight: 600;
    cursor: pointer; border: none;
    text-decoration: none;
    transition: all 0.18s;
    white-space: nowrap;
  }
  .btn-primary { background: var(--red); color: #fff; }
  .btn-primary:hover { background: var(--red-dark); box-shadow: 0 4px 16px var(--red-glow); }
  .btn-outline {
    background: transparent;
    border: 1px solid var(--border-2);
    color: var(--text-2);
  }
  .btn-outline:hover { background: var(--surface-2); color: var(--text); border-color: var(--text-2); }
  .btn-ghost { background: transparent; color: var(--text-2); padding: 6px 10px; }
  .btn-ghost:hover { color: var(--red); }
  .btn-sm { padding: 5px 11px; font-size: 12px; }
  .btn-danger { background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
  .btn-danger:hover { background: rgba(239,68,68,0.2); }
  .btn-success { background: rgba(34,197,94,0.12); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
  .btn-success:hover { background: rgba(34,197,94,0.2); }

  /* ── FORM ELEMENTS ── */
  .form-group { margin-bottom: 20px; }
  .form-label {
    display: block;
    font-size: 11px; font-weight: 600;
    letter-spacing: 1.2px; text-transform: uppercase;
    color: var(--muted); margin-bottom: 8px;
  }
  .form-control {
    width: 100%;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 7px;
    padding: 10px 14px;
    font-family: var(--font-body);
    font-size: 14px; color: var(--text);
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  .form-control:focus { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-glow); }
  .form-control::placeholder { color: var(--muted); }
  select.form-control { cursor: pointer; }
  textarea.form-control { resize: vertical; min-height: 90px; }

  /* ── MODAL ── */
  .modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.7);
    z-index: 200;
    align-items: center; justify-content: center;
    backdrop-filter: blur(4px);
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: var(--surface);
    border: 1px solid var(--border-2);
    border-radius: 14px;
    width: 90%; max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalIn 0.25s ease;
  }
  @keyframes modalIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
  }
  .modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
  }
  .modal-header h3 { font-family: var(--font-head); font-size: 20px; font-weight: 700; flex: 1; }
  .modal-close { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 18px; }
  .modal-close:hover { color: var(--text); }
  .modal-body { padding: 24px; }
  .modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex; gap: 10px; justify-content: flex-end;
  }

  /* ── ALERT FLASH ── */
  .flash {
    padding: 12px 18px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
  }
  .flash-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.25); color: #4ade80; }
  .flash-error   { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #f87171; }

  /* ── SEARCH ── */
  .search-box {
    position: relative;
  }
  .search-box i {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: 13px; pointer-events: none;
  }
  .search-box input {
    padding-left: 36px;
  }

  /* ── GRID ── */
  .grid-2 { display: grid; grid-template-columns: repeat(2,1fr); gap: 16px; }
  .grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; }

  /* ── UTILITIES ── */
  .flex { display: flex; }
  .items-center { align-items: center; }
  .justify-between { justify-content: space-between; }
  .gap-2 { gap: 8px; }
  .gap-3 { gap: 12px; }
  .mt-1 { margin-top: 4px; }
  .mt-2 { margin-top: 8px; }
  .mt-3 { margin-top: 16px; }
  .mb-4 { margin-bottom: 16px; }
  .mb-6 { margin-bottom: 24px; }
  .text-muted { color: var(--muted); }
  .text-sm { font-size: 13px; }
  .text-xs { font-size: 11px; }
  .font-bold { font-weight: 700; }
  .text-red { color: var(--red); }
  .text-green { color: var(--green); }
  .w-full { width: 100%; }

  /* ── HAMBURGER (mobile) ── */
  .hamburger { display: none; background: none; border: none; color: var(--text); font-size: 20px; cursor: pointer; }

  @media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main { margin-left: 0; }
    .topbar { left: 0; }
    .hamburger { display: block; }
    .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }
    .overlay.open { display: block; }
  }
</style>
</head>
<body>

<!-- Mobile overlay -->
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="icon"><i class="fas fa-motorcycle"></i></div>
    <div class="name">Moto<span>Parts</span></div>
  </div>

  <?php
  $sectionMap = ['dashboard'=>'Main','parts'=>'Main','stock'=>'Main','categories'=>'Main','orders'=>'Sales','customers'=>'Sales','payments'=>'Sales','reports'=>'Sales','users'=>'Admin','settings'=>'Admin'];
  $shownSections = [];
  foreach ($navItems as [$page, $icon, $label]):
    $sec = $sectionMap[$page] ?? '';
    if ($sec && !in_array($sec,$shownSections)) {
      echo "<div class='nav-section'>$sec</div>";
      $shownSections[] = $sec;
    }
  ?>
  <a href="<?= $page ?>.php" class="nav-link <?= $currentPage === $page ? 'active' : '' ?>">
    <i class="<?= $icon ?>"></i> <?= $label ?>
    <?php if ($page === 'orders' && $pendingOrders > 0): ?>
    <span class="badge"><?= $pendingOrders ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>

  <div class="sidebar-spacer"></div>

  <div class="sidebar-user">
    <a href="profile.php" style="display:flex;align-items:center;gap:10px;flex:1;text-decoration:none;min-width:0;" title="My Profile">
      <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($adminName) ?></div>
        <div class="user-role">Administrator</div>
      </div>
    </a>
    <a href="logout.php" class="logout-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
  <button class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
  <div>
    <div class="topbar-title"><?= ucfirst($currentPage) ?></div>
  </div>
  <div class="topbar-spacer"></div>
  <div class="topbar-time" id="clock"></div>
  <a href="logout.php" class="btn btn-outline btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
</header>

<!-- MAIN -->
<main class="main">

<script>
function pad(n){ return String(n).padStart(2,'0'); }
function tick(){
  const d = new Date();
  document.getElementById('clock').textContent =
    pad(d.getHours())+':'+pad(d.getMinutes())+':'+pad(d.getSeconds())+
    '  '+d.toLocaleDateString('en-KE',{weekday:'short',day:'numeric',month:'short'});
}
tick(); setInterval(tick, 1000);

function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('open');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
}
</script>
