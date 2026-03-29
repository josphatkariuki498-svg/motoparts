<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error   = '';
$success = '';

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, name, password, role FROM users WHERE email = ? AND role = 'admin'"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $user['user_id'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['role']       = 'admin';

                $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host    = $_SERVER['HTTP_HOST'];
                $selfDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                header('Location: ' . $scheme . '://' . $host . $selfDir . '/dashboard.php');
                exit();
            } else {
                $error = 'Incorrect password. Please try again.';
            }
        } else {
            $error = 'No admin account found with that email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — MotoParts</title>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --red: #e63329; --red-dark: #b5261e;
      --red-glow: rgba(230,51,41,0.15);
      --bg: #0a0a0b; --surface: #111114; --surface-2: #18181d;
      --border: rgba(255,255,255,0.07);
      --text: #f0f0f2; --muted: #6b6b78;
    }
    html, body { height:100%; background:var(--bg); font-family:'Barlow',sans-serif; color:var(--text); overflow:hidden; }
    .bg-lines { position:fixed; inset:0; z-index:0; background: repeating-linear-gradient(90deg,transparent,transparent 120px,rgba(255,255,255,0.015) 120px,rgba(255,255,255,0.015) 121px), repeating-linear-gradient(0deg,transparent,transparent 80px,rgba(255,255,255,0.015) 80px,rgba(255,255,255,0.015) 81px); }
    .stripe { position:fixed; top:0; left:0; width:6px; height:100%; background:linear-gradient(180deg,var(--red) 0%,transparent 100%); z-index:1; }
    .glow   { position:fixed; width:500px; height:500px; border-radius:50%; background:radial-gradient(circle,rgba(230,51,41,0.07) 0%,transparent 70%); top:-80px; right:-80px; pointer-events:none; z-index:0; }
    .page   { position:relative; z-index:2; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .wrap   { width:100%; max-width:430px; padding:20px; animation:fadeUp 0.45s ease both; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .brand { display:flex; align-items:center; gap:14px; margin-bottom:32px; }
    .brand-icon { width:50px; height:50px; background:var(--red); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px; color:#fff; box-shadow:0 0 24px var(--red-glow); }
    .brand-name { font-family:'Barlow Condensed',sans-serif; font-size:26px; font-weight:800; }
    .brand-name span { color:var(--red); }
    .brand-sub { font-size:11px; color:var(--muted); letter-spacing:2px; text-transform:uppercase; margin-top:2px; }
    .card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:36px; position:relative; overflow:hidden; }
    .card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,var(--red),transparent); }
    h2 { font-family:'Barlow Condensed',sans-serif; font-size:30px; font-weight:800; margin-bottom:4px; }
    .sub { font-size:14px; color:var(--muted); margin-bottom:28px; }
    .alert-error   { background:rgba(230,51,41,0.1); border:1px solid rgba(230,51,41,0.3); border-radius:8px; padding:11px 14px; font-size:14px; color:#ff7070; margin-bottom:20px; display:flex; align-items:center; gap:9px; }
    .alert-success { background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); border-radius:8px; padding:11px 14px; font-size:14px; color:#4ade80; margin-bottom:20px; display:flex; align-items:center; gap:9px; }
    .field { margin-bottom:18px; }
    .field label { display:block; font-size:11px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:var(--muted); margin-bottom:7px; }
    .inp-wrap { position:relative; }
    .inp-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:13px; pointer-events:none; }
    .inp-wrap input { width:100%; background:var(--surface-2); border:1px solid var(--border); border-radius:8px; padding:12px 13px 12px 40px; font-family:'Barlow',sans-serif; font-size:15px; color:var(--text); outline:none; transition:border-color .2s,box-shadow .2s; }
    .inp-wrap input:focus { border-color:var(--red); box-shadow:0 0 0 3px var(--red-glow); }
    .inp-wrap input::placeholder { color:var(--muted); }
    .toggle-pw { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--muted); cursor:pointer; font-size:13px; transition:color .2s; }
    .toggle-pw:hover { color:var(--text); }
    .btn-login { width:100%; padding:13px; background:var(--red); border:none; border-radius:8px; font-family:'Barlow Condensed',sans-serif; font-size:18px; font-weight:700; letter-spacing:1px; color:#fff; cursor:pointer; margin-top:6px; transition:background .2s,transform .15s,box-shadow .2s; display:flex; align-items:center; justify-content:center; gap:9px; }
    .btn-login:hover { background:var(--red-dark); box-shadow:0 4px 20px var(--red-glow); transform:translateY(-1px); }
    hr { border:none; border-top:1px solid var(--border); margin:24px 0; }
    .footer { text-align:center; font-size:12px; color:var(--muted); margin-top:24px; }
  </style>
</head>
<body>
<div class="bg-lines"></div>
<div class="glow"></div>
<div class="stripe"></div>
<div class="page">
  <div class="wrap">
    <div class="brand">
      <div class="brand-icon"><i class="fas fa-motorcycle"></i></div>
      <div>
        <div class="brand-name">Moto<span>Parts</span></div>
        <div class="brand-sub">Admin Portal</div>
      </div>
    </div>
    <div class="card">
      <h2>Welcome Back</h2>
      <p class="sub">Sign in to access the admin dashboard</p>
      <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <form method="POST" id="loginForm">
        <div class="field">
          <label>Email Address</label>
          <div class="inp-wrap">
            <i class="fas fa-envelope inp-icon"></i>
            <input type="email" name="email" placeholder="admin@motoparts.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
          </div>
        </div>
        <div class="field">
          <label>Password</label>
          <div class="inp-wrap">
            <i class="fas fa-lock inp-icon"></i>
            <input type="password" name="password" id="pwInput" placeholder="Enter your password" required>
            <button type="button" class="toggle-pw" onclick="togglePw()"><i class="fas fa-eye" id="pwIcon"></i></button>
          </div>
        </div>
        <button type="submit" class="btn-login" id="loginBtn"><i class="fas fa-sign-in-alt"></i> SIGN IN</button>
      </form>
      <hr>
      <div class="footer"><i class="fas fa-shield-alt" style="color:var(--red);margin-right:4px;"></i>Admin access only &nbsp;|&nbsp; © <?= date('Y') ?> MotoParts Kenya</div>
    </div>
  </div>
</div>
<script>
function togglePw() {
  const i=document.getElementById('pwInput'),ic=document.getElementById('pwIcon');
  i.type==='password'?(i.type='text',ic.className='fas fa-eye-slash'):(i.type='password',ic.className='fas fa-eye');
}
document.getElementById('loginForm').addEventListener('submit',function(){
  const b=document.getElementById('loginBtn');
  b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Signing in...';
  b.style.opacity='0.75'; b.style.pointerEvents='none';
});
</script>
</body>
</html>
