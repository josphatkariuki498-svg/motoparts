<?php
$pageTitle = 'Register';
require_once dirname(__DIR__) . '/includes/config.php';

if (isLoggedIn()) { header('Location: /spares/motoparts/'); exit; }

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, phone, address, role) VALUES (?, ?, ?, ?, ?, 'customer')");
            $stmt->bind_param('sssss', $name, $email, $hashed, $phone, $address);
            if ($stmt->execute()) {
                header('Location: /spares/motoparts/customer/login.php?registered=1');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="/spares/motoparts/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="auth-wrapper" style="align-items:flex-start;padding-top:40px;">
  <div class="auth-card animate-fadeup" style="max-width:500px;">
    <a href="/motoparts/" style="display:inline-block;margin-bottom:28px;font-family:var(--font-display);font-size:22px;font-weight:800;text-transform:uppercase;text-decoration:none;color:var(--text-primary);">Moto<span style="color:var(--primary);">Parts</span></a>
    <div class="auth-title">Create Account</div>
    <div class="auth-subtitle">Join thousands of customers ordering parts online</div>
    
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>
    
    <form method="POST">
      <div class="grid-2" style="gap:16px;">
        <div class="form-group mb-0">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control" placeholder="John Kamau" value="<?= isset($_POST['name']) ? sanitize($_POST['name']) : '' ?>" required>
        </div>
        <div class="form-group mb-0">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="+254 7XX XXX XXX" value="<?= isset($_POST['phone']) ? sanitize($_POST['phone']) : '' ?>">
        </div>
      </div>
      <div class="form-group" style="margin-top:20px;">
        <label class="form-label">Email Address *</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : '' ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Delivery Address</label>
        <textarea name="address" class="form-control" placeholder="Street, City, County" rows="2"><?= isset($_POST['address']) ? sanitize($_POST['address']) : '' ?></textarea>
      </div>
      <div class="grid-2" style="gap:16px;">
        <div class="form-group mb-0">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
        </div>
        <div class="form-group mb-0">
          <label class="form-label">Confirm Password *</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="justify-content:center;margin-top:24px;">
        <i class="fas fa-user-plus"></i> Create Account
      </button>
    </form>
    <div style="text-align:center;margin-top:20px;">
      <span style="font-size:14px;color:var(--text-secondary);">Already have an account?</span>
      <a href="/spares/motoparts/customer/login.php" style="color:var(--primary);font-weight:600;text-decoration:none;margin-left:6px;">Sign In</a>
    </div>
  </div>
</div>
</body>
</html>
