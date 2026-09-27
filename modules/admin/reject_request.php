<?php
session_start();
require_once __DIR__ . "/db_connect.php";

if (($_SESSION['role'] ?? '') !== 'admin') {
    exit("Unauthorized");
}

$request_id = (int)($_GET['request_id'] ?? 0);
if ($request_id <= 0) {
    exit("Invalid request ID");
}

/* Reject only if still pending */
$stmt = $conn->prepare("
    UPDATE requests
    SET 
        status = 'Rejected',
        processed_by_user_id = ?,
        processed_by_name = ?,
        processed_at = NOW()
    WHERE request_id = ? AND status = 'Pending'
");
$stmt->bind_param(
    "isi",
    $_SESSION['user_id'],
    $_SESSION['fullname'],
    $request_id
);
$stmt->execute();

header("Location: admin_requests.php?success=Request+Rejected");
exit();
