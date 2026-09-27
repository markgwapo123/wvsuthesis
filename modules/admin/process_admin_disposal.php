<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Restrict access to Admins/Sub-admins
allow_roles(['admin', 'subadmin']);

$adminIdentifier = $_SESSION['username'] ?? ($_SESSION['email'] ?? 'System Admin');

// -------------------------------------------------------------
// ROUTE 1: CONFIRM/APPROVE DISPOSAL (GET Action)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id'])) {
    $disposal_id = (int)$_GET['id'];

    // Prepare update query statement
    $stmt = $conn->prepare("UPDATE disposal_requests SET status = 'Disposed', admin_disposed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE disposal_id = ?");
    $stmt->bind_param("si", $adminIdentifier, $disposal_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: admin_execute_disposals.php?tab=pending_action&status=success_disposed");
        exit();
    } else {
        die("Error logging processing state adjustment execution: " . $conn->error);
    }
}

// -------------------------------------------------------------
// ROUTE 2: REJECT DISPOSAL REQUEST (POST Action)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject' && isset($_POST['id'])) {
    $disposal_id = (int)$_POST['id'];
    $rejectionReason = trim($_POST['reason'] ?? '');

    if (empty($rejectionReason)) {
        header("Location: admin_execute_disposals.php?tab=pending_action&status=error_missing_reason");
        exit();
    }

    $formattedReason = "REJECTED BY ADMIN ($adminIdentifier) REASON: " . $rejectionReason;

    // Update status to 'Rejected by Admin' and attach the note log text
    $stmt = $conn->prepare("UPDATE disposal_requests SET status = 'Rejected by Admin', reason = ?, admin_disposed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE disposal_id = ?");
    $stmt->bind_param("ssi", $formattedReason, $adminIdentifier, $disposal_id);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: admin_execute_disposals.php?tab=pending_action&status=success_rejected");
        exit();
    } else {
        die("Error logging rejection routine exception: " . $conn->error);
    }
}

// Fallback safety redirect
header("Location: admin_execute_disposals.php");
exit();
?>