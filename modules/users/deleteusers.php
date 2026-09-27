<?php
require_once __DIR__ . '/db_connect.php';

// 1. Session Fix: Check if session is already active before starting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if request expects a JSON API response
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
          ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($isAjax) {
    header('Content-Type: application/json');
}

// Helper function to handle response formats dynamically
function sendResponse($success, $messageOrError, $isAjax) {
    if ($isAjax) {
        echo json_encode([
            'success' => $success,
            $success ? 'message' : 'error' => $messageOrError
        ]);
        exit;
    } else {
        // Form/URL redirect mode: save status to session
        $_SESSION[$success ? 'success_flash' : 'error_flash'] = $messageOrError;
        
        // FIX: Instead of hardcoding user_management.php, send them back to the calling page automatically!
        if (!empty($_SERVER['HTTP_REFERER'])) {
            header("Location: " . $_SERVER['HTTP_REFERER']);
        } else {
            header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php"); // Absolute fallback file if referer string is blank
        }
        exit;
    }
}

// 2. Authorization Check: Only Admin can delete users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    sendResponse(false, 'Unauthorized access.', $isAjax);
}

// 3. Get User ID (Handle POST, GET, JSON, and parameter aliases)
$id = 0;

// FIX: Corrected typo from $REQUEST to $_REQUEST
if (isset($_REQUEST['user_id'])) {
    $id = (int)$_REQUEST['user_id'];
} elseif (isset($_REQUEST['id'])) {
    $id = (int)$_REQUEST['id'];
} else {
    // Fallback: Check if data was sent as raw JSON
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $jsonInput = json_decode($rawInput, true);
        if (isset($jsonInput['user_id'])) {
            $id = (int)$jsonInput['user_id'];
        } elseif (isset($jsonInput['id'])) {
            $id = (int)$jsonInput['id'];
        }
    }
}

if ($id <= 0) {
    sendResponse(false, 'Invalid or missing User ID selection.', $isAjax);
}

// 4. Prevent Self-Deletion
if ($id === (int)($_SESSION['user_id'] ?? 0)) {
    sendResponse(false, 'Security protection triggered: You cannot delete your own account.', $isAjax);
}

try {
    // 5. Check if user exists and fetch data for archiving
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        throw new Exception("The target user records could not be found.");
    }

    // 6. Archive the user data safely before system removal
    $jsonData = $conn->real_escape_string(json_encode($user, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    $deletedBy = (int)$_SESSION['user_id'];
    
    $archiveSql = "INSERT INTO archive_all (table_name, record_id, data, deleted_by) 
                   VALUES ('users', $id, '$jsonData', $deletedBy)";
    $conn->query($archiveSql); 

    // 7. DEEP CLEANUP: Disable Constraints and Clear Dependencies
    $conn->query("SET foreign_key_checks = 0");

    // A. Unlink Activity Logs (Set user_id to NULL)
    $conn->query("UPDATE activity_logs SET user_id = NULL WHERE user_id = $id");

    // B. Delete Requests (User's requests)
    $conn->query("DELETE FROM requests WHERE requester_user_id = $id");

    // 8. FINAL DELETE
    $delStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delStmt->bind_param("i", $id);
    $deleted = $delStmt->execute();
    $error = $delStmt->error;
    $delStmt->close();

    // Re-enable FK checks immediately
    $conn->query("SET foreign_key_checks = 1");
    
    if ($deleted) {
        sendResponse(true, 'User account records have been successfully wiped and archived.', $isAjax);
    } else {
        throw new Exception("Database engine fault details: " . $error);
    }

} catch (Exception $e) {
    // Ensure FK checks are back on even if error occurred
    $conn->query("SET foreign_key_checks = 1");
    sendResponse(false, $e->getMessage(), $isAjax);
}
?>
