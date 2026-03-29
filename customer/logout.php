<?php
require_once dirname(__DIR__) . '/includes/config.php';
session_destroy();
header('Location: /spares/motoparts/customer/login.php');
exit;
