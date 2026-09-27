<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Access Control Layer: Ensure only DAA and Admin roles can run this script
allow_roles(['admin', 'DAA']); 

$approverName = trim($_SESSION['requestor_name'] ?? $_SESSION['username'] ?? $_SESSION['fullname'] ?? 'Authorized DAA');

// -------------------------------------------------------------
// ROUTE 1: APPROVAL ACTION (Handled via URL GET Parameters)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && isset($_GET['action'])) {
    $disposalId = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        // Escaping inputs for strict statement integration safety strings
        $safeApprover = $conn->real_escape_string($approverName);

        // Advance workflow status state flag target configuration
        $updateSql = "UPDATE disposal_requests 
                      SET status = 'Approved by DAA', 
                          daa_approved_by = '$safeApprover' 
                      WHERE disposal_id = $disposalId";

        if ($conn->query($updateSql)) {
            // SILENT REDIRECT: No window popups. Passes success flag contextually via URL.
            header("Location: daa_process_disposals.php?tab=pending&status=success_approved");
            exit;
        } else {
            die("Database Error during authorization routine execution: " . $conn->error);
        }
    }
}

// -------------------------------------------------------------
// ROUTE 2: REJECTION ACTION (Handled via Virtual Form POST Parameters)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['action'])) {
    $disposalId = (int)$_POST['id'];
    $action = $_POST['action'];
    $rejectionReason = trim($_POST['reason'] ?? '');

    if ($action === 'reject') {
        if (empty($rejectionReason)) {
            // SILENT ERROR REDIRECT: Redirects with a dedicated missing parameter string
            header("Location: daa_process_disposals.php?tab=pending&status=error_missing_reason");
            exit;
        }

        // Context mapping combining authorization identity metrics alongside notes
        $formattedReason = "REJECTED BY DAA ($approverName) REASON: " . $rejectionReason;
        
        $safeReason = $conn->real_escape_string($formattedReason);
        $safeApprover = $conn->real_escape_string($approverName);

        // Turn request state over to Rejected, documenting who took action and why
        $updateSql = "UPDATE disposal_requests 
                      SET status = 'Rejected', 
                          reason = '$safeReason',
                          daa_approved_by = '$safeApprover' 
                      WHERE disposal_id = $disposalId";

        if ($conn->query($updateSql)) {
            // SILENT REDIRECT: Passes rejection notice state variables to the frontend view.
            header("Location: daa_process_disposals.php?tab=pending&status=success_rejected");
            exit;
        } else {
            die("Database Error during rejection routine execution: " . $conn->error);
        }
    }
}

// Fallback protection: If the page is accessed mistakenly without parameters, return to base dashboard
header("Location: daa_process_disposals.php");
exit;
?>