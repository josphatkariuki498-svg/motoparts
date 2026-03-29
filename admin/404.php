<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';
?>

<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;text-align:center;">
  <div style="font-family:var(--font-head);font-size:120px;font-weight:800;line-height:1;color:var(--surface-3);letter-spacing:-4px;user-select:none;">404</div>
  <div style="font-family:var(--font-head);font-size:32px;font-weight:700;margin-top:-10px;margin-bottom:12px;">Page Not Found</div>
  <p style="color:var(--text-2);max-width:400px;margin-bottom:28px;">
    The page you're looking for doesn't exist or may have been moved.
  </p>
  <div class="flex gap-3">
    <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i> Go to Dashboard</a>
    <a href="javascript:history.back()" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Go Back</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
