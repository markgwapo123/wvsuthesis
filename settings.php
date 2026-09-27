<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php");
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 1);
