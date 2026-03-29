<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$db    = getDB();   // ← ADD THIS LINE
$error   = '';
$success = '';

// If already logged in as admin, go to dashboard
if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

define('SETUP_KEY', 'motoparts2024setup');

$_r         = $db->query("SELECT COUNT(*) c FROM users WHERE role='admin'");
$adminCount = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$setupKey   = trim($_GET['key'] ?? '');
$allowAccess = ($adminCount === 0) || ($setupKey === SETUP_KEY);

if (!$allowAccess) {
    header('Location: login.php?error=access');
    exit();
}

// ── HANDLE FORM SUBMISSION ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']             ?? '');
    $email    = trim($_POST['email']            ?? '');
    $phone    = trim($_POST['phone']            ?? '');
    $password = trim($_POST['password']         ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    // ── Validate ──
    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'All required fields must be filled in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // ── Check email uniqueness ──
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $existing = $stmt->get_result();

        if ($existing->num_rows > 0) {
            $error = 'An account with that email address already exists.';
        } else {
            // ── Create admin account ──
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role   = 'admin';
            $stmt   = $conn->prepare(
                "INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssss", $name, $email, $hashed, $phone, $role);

            if ($stmt->execute()) {
                $success = 'Admin account created successfully! You can now sign in.';
                // Clear POST data so form resets
                $_POST = [];
            } else {
                $error = 'Registration failed. Please try again. (' . $stmt->error . ')';
            }
        }
    }
}

// ── Build key param for links (preserve access if key was used) ──
$keyParam = ($setupKey === SETUP_KEY) ? '?key=' . urlencode(SETUP_KEY) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Registration — MotoParts</title>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --red:      #e63329;
      --red-dark: #b5261e;
      --red-glow: rgba(230,51,41,0.15);
      --bg:       #0a0a0b;
      --surface:  #111114;
      --surface-2:#18181d;
      --border:   rgba(255,255,255,0.07);
      --text:     #f0f0f2;
      --muted:    #6b6b78;
      --green:    #22c55e;
    }

    html, body {
      min-height: 100%;
      background: var(--bg);
      font-family: 'Barlow', sans-serif;
      color: var(--text);
    }

    /* ── Background effects ── */
    .bg-lines {
      position: fixed; inset: 0; z-index: 0;
      background:
        repeating-linear-gradient(90deg, transparent, transparent 120px, rgba(255,255,255,0.015) 120px, rgba(255,255,255,0.015) 121px),
        repeating-linear-gradient(0deg,  transparent, transparent 80px,  rgba(255,255,255,0.015) 80px,  rgba(255,255,255,0.015) 81px);
    }
    .stripe {
      position: fixed; top: 0; left: 0;
      width: 6px; height: 100%;
      background: linear-gradient(180deg, var(--red) 0%, transparent 100%);
      z-index: 1;
    }
    .glow {
      position: fixed; width: 600px; height: 600px; border-radius: 50%;
      background: radial-gradient(circle, rgba(230,51,41,0.06) 0%, transparent 70%);
      top: -120px; right: -120px;
      pointer-events: none; z-index: 0;
    }

    /* ── Layout ── */
    .page {
      position: relative; z-index: 2;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 32px 20px;
    }
    .wrap {
      width: 100%; max-width: 520px;
      animation: fadeUp 0.45s ease both;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Brand ── */
    .brand { display: flex; align-items: center; gap: 14px; margin-bottom: 28px; }
    .brand-icon {
      width: 50px; height: 50px;
      background: var(--red); border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; color: #fff;
      box-shadow: 0 0 24px var(--red-glow);
    }
    .brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 800; }
    .brand-name span { color: var(--red); }
    .brand-sub { font-size: 11px; color: var(--muted); letter-spacing: 2px; text-transform: uppercase; margin-top: 2px; }

    /* ── Card ── */
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 36px;
      position: relative;
      overflow: hidden;
    }
    .card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, var(--red), transparent);
    }

    h2 { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 800; margin-bottom: 4px; }
    .sub { font-size: 14px; color: var(--muted); margin-bottom: 28px; }

    /* ── Alerts ── */
    .alert-error {
      background: rgba(230,51,41,0.1); border: 1px solid rgba(230,51,41,0.3);
      border-radius: 8px; padding: 11px 14px;
      font-size: 14px; color: #ff7070;
      margin-bottom: 20px;
      display: flex; align-items: flex-start; gap: 10px;
    }
    .alert-success {
      background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3);
      border-radius: 8px; padding: 11px 14px;
      font-size: 14px; color: #4ade80;
      margin-bottom: 20px;
      display: flex; align-items: center; gap: 10px;
    }

    /* ── Grid ── */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }

    /* ── Fields ── */
    .field { margin-bottom: 16px; }
    .field label {
      display: block; font-size: 11px; font-weight: 600;
      letter-spacing: 1.5px; text-transform: uppercase;
      color: var(--muted); margin-bottom: 7px;
    }
    .field label .req { color: var(--red); margin-left: 2px; }

    .inp-wrap { position: relative; }
    .inp-icon {
      position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
      color: var(--muted); font-size: 13px; pointer-events: none;
    }
    .inp-wrap input, .inp-wrap select {
      width: 100%;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 12px 13px 12px 40px;
      font-family: 'Barlow', sans-serif;
      font-size: 14px; color: var(--text);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .inp-wrap input:focus, .inp-wrap select:focus {
      border-color: var(--red);
      box-shadow: 0 0 0 3px var(--red-glow);
    }
    .inp-wrap input::placeholder { color: var(--muted); }
    .inp-wrap input.valid   { border-color: rgba(34,197,94,0.5); }
    .inp-wrap input.invalid { border-color: rgba(239,68,68,0.5); }

    .toggle-pw {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--muted);
      cursor: pointer; font-size: 13px; transition: color .2s;
    }
    .toggle-pw:hover { color: var(--text); }

    /* ── Password strength ── */
    .strength-wrap { margin-top: 8px; }
    .strength-bar-bg {
      height: 4px; background: var(--surface-2);
      border-radius: 4px; overflow: hidden; margin-bottom: 5px;
    }
    .strength-bar {
      height: 100%; width: 0;
      border-radius: 4px;
      transition: width 0.3s, background 0.3s;
    }
    .strength-label { font-size: 11px; color: var(--muted); }

    /* ── Requirements checklist ── */
    .req-list {
      margin-top: 10px;
      display: grid; grid-template-columns: 1fr 1fr; gap: 4px 12px;
    }
    .req-item {
      font-size: 11px; color: var(--muted);
      display: flex; align-items: center; gap: 5px;
      transition: color 0.2s;
    }
    .req-item i { font-size: 10px; }
    .req-item.met { color: var(--green); }
    .req-item.unmet { color: var(--muted); }

    /* ── Submit button ── */
    .btn-register {
      width: 100%; padding: 13px;
      background: var(--red); border: none; border-radius: 8px;
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 18px; font-weight: 700; letter-spacing: 1px;
      color: #fff; cursor: pointer; margin-top: 8px;
      transition: background .2s, transform .15s, box-shadow .2s;
      display: flex; align-items: center; justify-content: center; gap: 9px;
    }
    .btn-register:hover {
      background: var(--red-dark);
      box-shadow: 0 4px 20px var(--red-glow);
      transform: translateY(-1px);
    }
    .btn-register:active { transform: translateY(0); }
    .btn-register:disabled { opacity: 0.6; pointer-events: none; }

    /* ── Security notice ── */
    .security-box {
      background: rgba(230,51,41,0.05);
      border: 1px solid rgba(230,51,41,0.15);
      border-radius: 8px; padding: 12px 14px;
      font-size: 12px; color: var(--muted);
      margin-bottom: 20px;
      display: flex; gap: 10px; align-items: flex-start;
    }
    .security-box i { color: var(--red); margin-top: 1px; flex-shrink: 0; }

    /* ── Admin count badge ── */
    .admin-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.25);
      border-radius: 20px; padding: 4px 12px;
      font-size: 12px; color: #4ade80;
      margin-bottom: 20px;
    }

    /* ── Divider / footer ── */
    hr { border: none; border-top: 1px solid var(--border); margin: 24px 0; }
    .footer { text-align: center; font-size: 12px; color: var(--muted); margin-top: 20px; }
    .footer a { color: var(--red); text-decoration: none; }
    .footer a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<div class="bg-lines"></div>
<div class="glow"></div>
<div class="stripe"></div>

<div class="page">
  <div class="wrap">

    <!-- Brand -->
    <div class="brand">
      <div class="brand-icon"><i class="fas fa-motorcycle"></i></div>
      <div>
        <div class="brand-name">Moto<span>Parts</span></div>
        <div class="brand-sub">Admin Portal</div>
      </div>
    </div>

    <div class="card">
      <h2>Create Admin Account</h2>
      <p class="sub">Register a new administrator for the MotoParts panel</p>

      <!-- Admin count info -->
      <?php if ($adminCount === 0): ?>
      <div class="admin-badge">
        <i class="fas fa-info-circle"></i>
        No admins exist yet — first admin setup
      </div>
      <?php else: ?>
      <div class="security-box">
        <i class="fas fa-shield-alt"></i>
        <div>
          This page is protected by a setup key.
          There <?= $adminCount === 1 ? 'is' : 'are' ?> currently
          <strong style="color:var(--text);"><?= $adminCount ?></strong>
          admin<?= $adminCount !== 1 ? 's' : '' ?> registered.
          Remove or disable this page after setup.
        </div>
      </div>
      <?php endif; ?>

      <!-- Alerts -->
      <?php if ($error): ?>
      <div class="alert-error">
        <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:1px;"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($success): ?>
      <div class="alert-success">
        <i class="fas fa-check-circle"></i>
        <span>
          <?= htmlspecialchars($success) ?>
          <a href="login.php" style="color:#4ade80;font-weight:600;margin-left:6px;">Sign in →</a>
        </span>
      </div>
      <?php endif; ?>

      <!-- Registration Form -->
      <form method="POST" id="regForm" novalidate>

        <!-- Name + Phone -->
        <div class="grid-2">
          <div class="field">
            <label>Full Name <span class="req">*</span></label>
            <div class="inp-wrap">
              <i class="fas fa-user inp-icon"></i>
              <input
                type="text"
                name="name"
                id="nameInput"
                placeholder="e.g. Jane Wanjiku"
                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                required
                autocomplete="name"
              >
            </div>
          </div>
          <div class="field">
            <label>Phone Number</label>
            <div class="inp-wrap">
              <i class="fas fa-phone inp-icon"></i>
              <input
                type="tel"
                name="phone"
                placeholder="+254 7XX XXX XXX"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                autocomplete="tel"
              >
            </div>
          </div>
        </div>

        <!-- Email -->
        <div class="field">
          <label>Email Address <span class="req">*</span></label>
          <div class="inp-wrap">
            <i class="fas fa-envelope inp-icon"></i>
            <input
              type="email"
              name="email"
              id="emailInput"
              placeholder="admin@motoparts.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required
              autocomplete="email"
            >
          </div>
        </div>

        <!-- Password -->
        <div class="field">
          <label>Password <span class="req">*</span></label>
          <div class="inp-wrap">
            <i class="fas fa-lock inp-icon"></i>
            <input
              type="password"
              name="password"
              id="pwInput"
              placeholder="Min 8 characters"
              required
              autocomplete="new-password"
              oninput="checkPassword(this.value)"
            >
            <button type="button" class="toggle-pw" onclick="togglePw('pwInput','pwIcon')">
              <i class="fas fa-eye" id="pwIcon"></i>
            </button>
          </div>
          <!-- Strength bar -->
          <div class="strength-wrap">
            <div class="strength-bar-bg">
              <div class="strength-bar" id="strengthBar"></div>
            </div>
            <div class="strength-label" id="strengthLabel">Enter a password</div>
          </div>
          <!-- Requirements -->
          <div class="req-list">
            <div class="req-item unmet" id="req-length"><i class="fas fa-circle"></i> 8+ characters</div>
            <div class="req-item unmet" id="req-upper"><i class="fas fa-circle"></i> Uppercase letter</div>
            <div class="req-item unmet" id="req-number"><i class="fas fa-circle"></i> Number</div>
            <div class="req-item unmet" id="req-special"><i class="fas fa-circle"></i> Special character</div>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="field">
          <label>Confirm Password <span class="req">*</span></label>
          <div class="inp-wrap">
            <i class="fas fa-lock inp-icon"></i>
            <input
              type="password"
              name="confirm_password"
              id="cpInput"
              placeholder="Repeat your password"
              required
              autocomplete="new-password"
              oninput="checkConfirm()"
            >
            <button type="button" class="toggle-pw" onclick="togglePw('cpInput','cpIcon')">
              <i class="fas fa-eye" id="cpIcon"></i>
            </button>
          </div>
          <div id="matchMsg" style="font-size:11px;margin-top:6px;display:none;"></div>
        </div>

        <button type="submit" class="btn-register" id="regBtn">
          <i class="fas fa-user-shield"></i> CREATE ADMIN ACCOUNT
        </button>
      </form>

      <hr>
      <div class="footer">
        Already have an account?
        <a href="login.php">Sign in here</a>
        &nbsp;|&nbsp;
        <i class="fas fa-shield-alt" style="color:var(--red);"></i>
        © <?= date('Y') ?> MotoParts Kenya
      </div>
    </div>

  </div>
</div>

<script>
// ── Toggle password visibility ──
function togglePw(inputId, iconId) {
  const inp = document.getElementById(inputId);
  const ico = document.getElementById(iconId);
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fas fa-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'fas fa-eye';
  }
}

// ── Password strength checker ──
function checkPassword(val) {
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');

  const checks = {
    length:  val.length >= 8,
    upper:   /[A-Z]/.test(val),
    number:  /[0-9]/.test(val),
    special: /[^A-Za-z0-9]/.test(val),
  };

  // Update requirement indicators
  Object.entries(checks).forEach(([key, met]) => {
    const el = document.getElementById('req-' + key);
    if (!el) return;
    el.classList.toggle('met',   met);
    el.classList.toggle('unmet', !met);
    el.querySelector('i').className = met ? 'fas fa-check-circle' : 'fas fa-circle';
  });

  const score = Object.values(checks).filter(Boolean).length;
  const levels = [
    { pct: 0,   color: '#ef4444', text: 'Enter a password' },
    { pct: 25,  color: '#ef4444', text: 'Weak' },
    { pct: 50,  color: '#f59e0b', text: 'Fair' },
    { pct: 75,  color: '#3b82f6', text: 'Good' },
    { pct: 100, color: '#22c55e', text: 'Strong' },
  ];
  const level = val.length === 0 ? levels[0] : levels[score];

  bar.style.width      = level.pct + '%';
  bar.style.background = level.color;
  label.textContent    = level.text;
  label.style.color    = level.color;

  // Re-check confirm match
  checkConfirm();
}

// ── Confirm password match indicator ──
function checkConfirm() {
  const pw  = document.getElementById('pwInput').value;
  const cp  = document.getElementById('cpInput').value;
  const msg = document.getElementById('matchMsg');

  if (cp.length === 0) {
    msg.style.display = 'none';
    return;
  }
  msg.style.display = 'block';
  if (pw === cp) {
    msg.innerHTML = '<i class="fas fa-check-circle" style="color:#22c55e;margin-right:4px;"></i><span style="color:#4ade80;">Passwords match</span>';
  } else {
    msg.innerHTML = '<i class="fas fa-times-circle" style="color:#ef4444;margin-right:4px;"></i><span style="color:#f87171;">Passwords do not match</span>';
  }
}

// ── Form submit: show loading state + client-side validation ──
document.getElementById('regForm').addEventListener('submit', function (e) {
  const pw = document.getElementById('pwInput').value;
  const cp = document.getElementById('cpInput').value;

  if (pw !== cp) {
    e.preventDefault();
    document.getElementById('cpInput').focus();
    return;
  }

  const btn = document.getElementById('regBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
});

// ── Real-time email validation indicator ──
document.getElementById('emailInput').addEventListener('blur', function () {
  const val = this.value.trim();
  const re  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (val) {
    this.classList.toggle('valid',   re.test(val));
    this.classList.toggle('invalid', !re.test(val));
  }
});

// ── Real-time name validation ──
document.getElementById('nameInput').addEventListener('blur', function () {
  const val = this.value.trim();
  this.classList.toggle('valid',   val.length >= 2);
  this.classList.toggle('invalid', val.length > 0 && val.length < 2);
});
</script>

</body>
</html>
