<?php
require_once __DIR__ . '/db.php';

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . getAdminBase() . '/login.php');
        exit();
    }
}

function getAdminBase() {
    // Adjust this to your actual path
    return '/motoparts/admin';
}

function adminLogout() {
    session_destroy();
    header('Location: ' . getAdminBase() . '/login.php?logout=1');
    exit();
}
