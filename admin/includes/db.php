<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
function getDB() {
    $host = getenv('MYSQLHOST');
    $user = getenv('MYSQLUSER');
    $pass = getenv('MYSQLPASSWORD');
    $db   = getenv('MYSQLDATABASE');
    $port = getenv('MYSQLPORT') ?: 3306;

    $conn = new mysqli($host, $user, $pass, $db, (int)$port);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>

// Create connection — use global $conn so login.php can use $conn->prepare()
if (!isset($GLOBALS['_moto_conn'])) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die('
        <style>body{font-family:sans-serif;background:#0a0a0b;color:#f0f0f2;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
        .box{background:#111;border:1px solid #333;border-radius:10px;padding:32px;max-width:480px;text-align:center;}
        h2{color:#e63329;margin-bottom:8px;}pre{background:#1a1a1a;padding:12px;border-radius:6px;text-align:left;font-size:12px;color:#aaa;}</style>
        <div class="box">
          <h2>&#9888; Database Connection Failed</h2>
          <p style="color:#aaa;margin-bottom:16px;">Could not connect to <strong>' . DB_NAME . '</strong> on <strong>' . DB_HOST . '</strong>.</p>
          <pre>' . htmlspecialchars($conn->connect_error) . '</pre>
          <p style="color:#555;font-size:13px;margin-top:16px;">Edit DB_USER / DB_PASS in <code>admin/includes/db.php</code></p>
        </div>');
    }
    $conn->set_charset('utf8mb4');
    $GLOBALS['_moto_conn'] = $conn;
} else {
    $conn = $GLOBALS['_moto_conn'];
}

if (!function_exists('getDB')) {
    function getDB() {
        return $GLOBALS['_moto_conn'];
    }
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return 'KSh ' . number_format((float)$price, 2);
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}
