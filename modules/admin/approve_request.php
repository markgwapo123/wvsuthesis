<?php
session_start();
require_once __DIR__ . "/db_connect.php";

/* ======================
   ADMIN AUTH CHECK
====================== */
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

/* ======================
   VALIDATE REQUEST ID
====================== */
$request_id = $_GET['request_id'] ?? null;
if (!$request_id) {
    exit("Invalid request ID.");
}

/* ======================
   FETCH REQUEST
====================== */
$stmt = $conn->prepare("SELECT request_id, status FROM requests WHERE request_id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    exit("Request not found.");
}

if ($request['status'] !== 'Pending') {
    exit("This request has already been processed.");
}

/* ======================
   UPDATE REQUEST STATUS
====================== */
// NOTE: We do NOT deduct stock here. Stock is deducted during Issuance.

try {
    // Check if 'processed_by_user_id' column exists, otherwise fall back to simple update
    // For safety with your provided schema, I'll use a robust update.
    // If your DB has 'processed_by_user_id', use the specific query. 
    // If not, we might need to alter the table or use a simpler query.
    // Assuming you have these columns based on your code snippet:
    
    $stmt = $conn->prepare("
        UPDATE requests
        SET 
            status = 'Approved',
            processed_by_user_id = ?,
            processed_by_name = ?,
            processed_at = NOW()
        WHERE request_id = ?
    ");
    
    if (!$stmt) {
        // Fallback if the specific columns don't exist yet in your DB
        $stmt = $conn->prepare("UPDATE requests SET status = 'Approved' WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
    } else {
        $stmt->bind_param(
            "isi",
            $_SESSION['user_id'],
            $_SESSION['fullname'],
            $request_id
        );
    }

    $stmt->execute();

    // Log to activity_logs (Optional but recommended)
    $logAction = "Approved Request #$request_id";
    $userId = $_SESSION['user_id'];
    $conn->query("INSERT INTO activity_logs (user_id, action, module, status, created_at) 
                  VALUES ('$userId', '$logAction', 'Request Management', 'Success', NOW())");

    header("Location: admin_requests.php?success=Request+Approved");
    exit();

} catch (Exception $e) {
    exit("Approval failed: " . $e->getMessage());
}
?>
