<?php
require_once __DIR__ . '/db.php';

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        $url = getAdminBaseURL() . '/login.php';
        header('Location: ' . $url);
        exit();
    }
}

function getAdminBaseURL() {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $docRoot  = realpath($_SERVER['DOCUMENT_ROOT']);
    $adminDir = realpath(__DIR__ . '/../');
    $rel      = str_replace($docRoot, '', $adminDir);
    $path     = str_replace('\\', '/', $rel);
    return $scheme . '://' . $host . rtrim($path, '/');
}

function getAdminBase() {
    return getAdminBaseURL();
}

function adminLogout() {
    session_unset();
    session_destroy();
    header('Location: ' . getAdminBaseURL() . '/login.php?logout=1');
    exit();
}
