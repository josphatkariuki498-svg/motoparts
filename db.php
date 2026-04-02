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