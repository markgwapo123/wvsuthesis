<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = current_user();
if (!$user) exit("Unauthorized");

$user_id = $user['id'];
$current = $_POST['current_password'] ?? "";

// Simple rate limit
if (!isset($_SESSION['pw_attempts'])) {
    $_SESSION['pw_attempts'] = 0;
}
if ($_SESSION['pw_attempts'] > 10) {
    exit("INVALID"); // or "LOCKED"
}

$stmt = $conn->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    exit("INVALID");
}

$dbPassword = $row['password'];

if (password_verify($current, $dbPassword) || $current === $dbPassword) {
    $_SESSION['pw_attempts'] = 0; // reset on success
    exit("OK");
}

// wrong password
$_SESSION['pw_attempts']++;

if (function_exists('log_activity')) {
    log_activity($user_id, "Incorrect current password attempt", "Account", "Failed", $conn);
}

exit("INVALID");
