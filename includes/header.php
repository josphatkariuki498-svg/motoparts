<?php require_once dirname(__DIR__) . '/includes/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - ' : '' ?><?= SITE_NAME ?></title>
  <link rel="stylesheet" href="/spares/motoparts/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-inner">
    <a href="/spares/motoparts/" class="brand">Moto<span>Parts</span></a>
    <ul class="nav-links">
      <li><a href="/spares/motoparts/" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' && dirname($_SERVER['PHP_SELF']) === '/motoparts' ? 'active' : '' ?>">Home</a></li>
      <li><a href="/spares/motoparts/customer/catalog.php" class="<?= basename($_SERVER['PHP_SELF']) === 'catalog.php' ? 'active' : '' ?>">Catalog</a></li>
      <?php if (isLoggedIn()): ?>
      <li><a href="/spares/motoparts/customer/orders.php">My Orders</a></li>
      <?php endif; ?>
    </ul>
    <div class="nav-actions">
      <?php if (isLoggedIn()): ?>
        <a href="/spares/motoparts/customer/cart.php" class="cart-btn">
          <i class="fas fa-shopping-cart"></i> Cart
          <?php $count = getCartCount(); if ($count > 0): ?>
            <span class="cart-badge"><?= $count ?></span>
          <?php endif; ?>
        </a>
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:13px;color:var(--text-secondary)">Hi, <?= sanitize(explode(' ', $_SESSION['name'])[0]) ?></span>
          <a href="/spares/motoparts/customer/logout.php" class="btn btn-outline btn-sm">Logout</a>
        </div>
      <?php else: ?>
        <a href="/spares/motoparts/customer/login.php" class="btn btn-outline btn-sm">Login</a>
        <a href="/spares/motoparts/customer/register.php" class="btn btn-primary btn-sm">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
