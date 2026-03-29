<?php
$pageTitle = 'Login';
require_once dirname(__DIR__) . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/admin/dashboard.php' : ''));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email']    ?? '');
    $password = $_POST['password']          ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            // Extra keys needed by the admin panel
            if ($user['role'] === 'admin') {
                $_SESSION['admin_id']   = $user['user_id'];
                $_SESSION['admin_name'] = $user['name'];
                header('Location: /admin/dashboard.php');
            } else {
                $redirect = $_GET['redirect'] ?? '';
                header('Location: ' . ($redirect ?: '/spares/'));
            }
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="/spares/motoparts/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card animate-fadeup">
    <a href="/spares/motoparts/" style="display:inline-block;margin-bottom:28px;font-family:var(--font-display);font-size:22px;font-weight:800;text-transform:uppercase;text-decoration:none;color:var(--text-primary);">Moto<span style="color:var(--primary);">Parts</span></a>
    <div class="auth-title">Welcome Back</div>
    <div class="auth-subtitle">Sign in to your account to continue</div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['registered'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Account created! You can now log in.</div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : '' ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div style="position:relative;">
          <input type="password" name="password" id="passInput" class="form-control" placeholder="••••••••" required>
          <button type="button" onclick="togglePass()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;"><i class="fas fa-eye" id="passIcon"></i></button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="justify-content:center;margin-top:8px;">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </button>
    </form>
    <div class="auth-divider">or</div>
    <div style="text-align:center;">
      <span style="font-size:14px;color:var(--text-secondary);">Don't have an account?</span>
      <a href="/spares/motoparts/customer/register.php" style="color:var(--primary);font-weight:600;text-decoration:none;margin-left:6px;">Create one</a>
    </div>
    <div style="margin-top:24px;padding:16px;background:var(--surface-2);border-radius:var(--radius);border:1px solid var(--border);">
      <div style="font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px;">Demo Credentials</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-bottom:6px;"><strong style="color:var(--text-primary);">Admin:</strong> admin@motoparts.com / admin123</div>
      <div style="font-size:13px;color:var(--text-secondary);"><strong style="color:var(--text-primary);">Customer:</strong> john@example.com / admin123</div>
    </div>
  </div>
</div>
<script>
function togglePass() {
  const input = document.getElementById('passInput');
  const icon  = document.getElementById('passIcon');
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>
