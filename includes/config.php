<?php
// ============================================
// Database Configuration
// ============================================
if (!defined('DB_HOST')) define('DB_HOST', getenv('MYSQL_HOST')     ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('MYSQL_USER')     ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('MYSQL_PASSWORD') ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'motoparts_db');
if (!defined('DB_PORT')) define('DB_PORT', getenv('MYSQL_PORT')     ?: 3306);
if (!defined('SITE_NAME')) define('SITE_NAME', 'MotoParts Kenya');
if (!defined('CURRENCY'))  define('CURRENCY',  'KSh');

// Start session only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create connection — singleton pattern prevents redeclaration errors
if (!function_exists('getDB')) {
    function getDB() {
        static $conn = null;
        if ($conn === null) {
          $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            if ($conn->connect_error) {
                http_response_code(500);
                die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                    <h2 style="color:#e63329;">Database Connection Failed</h2>
                    <p>Could not connect to <strong>' . DB_NAME . '</strong>. Check your credentials in <code>includes/config.php</code>.</p>
                    <pre style="text-align:left;background:#f4f4f4;padding:12px;border-radius:6px;">' . htmlspecialchars($conn->connect_error) . '</pre>
                </div>');
            }
            $conn->set_charset('utf8mb4');
        }
        return $conn;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header('Location: /spares/motoparts/customer/login.php' . ($redirect ? '?redirect=' . $redirect : ''));
            exit;
        }
    }
}

// NOTE: requireAdmin() here redirects to CUSTOMER login (for front-end pages).
// The admin panel uses its OWN requireAdmin() in admin/includes/auth.php.
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!isAdmin()) {
            header('Location: /spares/motoparts/customer/login.php');
            exit;
        }
    }
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return CURRENCY . ' ' . number_format((float)$price, 2);
    }
}

if (!function_exists('getCartCount')) {
    function getCartCount() {
        if (!isLoggedIn()) return 0;
        $db  = getDB();
        $uid = (int)$_SESSION['user_id'];
        $r   = $db->query("SELECT COALESCE(SUM(quantity),0) as total FROM cart WHERE user_id = $uid");
        return $r ? (int)$r->fetch_assoc()['total'] : 0;
    }
}
