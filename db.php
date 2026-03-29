<?php
// Root db.php — delegates to the canonical includes/config.php
// Prevents "Cannot redeclare" errors if included more than once
if (!function_exists('getDB')) {
    require_once __DIR__ . '/includes/config.php';
}
