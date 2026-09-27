<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Secure the route - allow access to Admin and DAA accounts
allow_roles(['inst_admin', 'daa', 'DAA']); // Included both casings for system safety

$currentUserId = $_SESSION['user_id'] ?? 0;

// 1. Fetch current DAA user's name using first_name and last_name
$userName = "Unknown DAA";
if ($currentUserId) {
    $uStmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param("i", $currentUserId);
        $uStmt->execute();
        $uResult = $uStmt->get_result();
        if ($uRow = $uResult->fetch_assoc()) {
            $userName = trim($uRow['first_name'] . ' ' . $uRow['last_name']);
        }
        $uStmt->close();
    }
}

// 2. HANDLE APPROVAL ACTION (via GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'approve') {
    $requestId = (int)$_GET['id'];

    // UPDATED: Now verifies records exist from the 'requests' table
    $check = $conn->prepare("SELECT request_id FROM requests WHERE request_id = ? AND LOWER(status) = 'pending' LIMIT 1");
    $check->bind_param("i", $requestId);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        die("Workflow operational error: Pending request row missing.");
    }
    $check->close();

    // UPDATED: Set query string parameters to update the target request tracking columns
    $stmt = $conn->prepare("UPDATE requests SET status = 'approved', approved_by = ?, processed_at = NOW() WHERE request_id = ?");
    $stmt->bind_param("si", $userName, $requestId);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: daa_process_request.php?status=success_approved");
        exit;
    } else {
        die("Database Error: Failed to execute operational update.");
    }
}

// 3. HANDLE REJECTION ACTION (via POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['action']) && $_POST['action'] === 'reject') {
    $requestId = (int)$_POST['id'];
    $reason    = trim($_POST['reason'] ?? '');

    // Ensure the user typed in a reason
    if (empty($reason)) {
        die("Operational error: A rejection reason is strictly required.");
    }

    // UPDATED: Check targeted rows against 'requests' table
    $check = $conn->prepare("SELECT request_id FROM requests WHERE request_id = ? AND LOWER(status) = 'pending' LIMIT 1");
    $check->bind_param("i", $requestId);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        die("Workflow operational error: Pending request row missing.");
    }
    $check->close();

    // UPDATED: Update statement targets 'rejection_reason' and saves the process time marker
    $stmt = $conn->prepare("UPDATE requests SET status = 'rejected', approved_by = ?, rejection_reason = ?, processed_at = NOW() WHERE request_id = ?");
    $stmt->bind_param("ssi", $userName, $reason, $requestId);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: daa_process_request.php?status=success_rejected");
        exit;
    } else {
        die("Database Error: Failed to execute operational update.");
    }
}

// Fallback protection: If accessed directly, head back to the UI list view
header("Location: daa_process_request.php");
exit;