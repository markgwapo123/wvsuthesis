<?php
// ===================================================
// SESSION START (Safe)
// ===================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===================================================
// DATABASE CONNECTION
// ===================================================
$host = "localhost";   // XAMPP default
$user = "root";        // XAMPP default
$pass = "";            // No password by default
$db   = "soims";       // Your database name

// Create a new connection and throw an exception on failure
$conn = new mysqli($host, $user, $pass, $db);

// Connection error handling
if ($conn->connect_error) {
    // Log the error
    error_log("Database connection failed: " . $conn->connect_error);
    // Throw an exception instead of using exit
    throw new Exception("Database connection failed.");
}

// ===================================================
// UNIVERSAL ACTIVITY LOGGING FUNCTION (Prepared Statements)
// ===================================================
if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $module, $status = 'Success', $dbConn = null) {

        // Choose which DB connection to use
        if ($dbConn === null) {
            // fallback to global $conn if nothing passed
            if (!isset($GLOBALS['conn'])) {
                return; // no connection available, just skip logging
            }
            $dbConn = $GLOBALS['conn'];
        }

        // Prepare the SQL statement to prevent SQL injection
        $sql = "
            INSERT INTO activity_logs 
                (user_id, action, module, status, ip_address, device, created_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, NOW())
        ";

        // Prepare the statement
        if ($stmt = $dbConn->prepare($sql)) {
            // Bind the parameters
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $device = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            // Bind user input to the statement
            $stmt->bind_param("isssss", 
                $user_id, 
                $action, 
                $module, 
                $status, 
                $ip, 
                $device
            );

            // Execute the statement
            $stmt->execute();

            // Close the statement
            $stmt->close();
        } else {
            error_log("Error preparing SQL statement: " . $dbConn->error);
        }
    }
}

?>
