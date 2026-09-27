<?php
session_start();
require_once "db_connect.php";

// Get logged in user id from session
$user_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? null);

if (!$user_id) {
    exit("ERROR: Unauthorized access.");
}

/* ============================================================
   INPUTS
   ============================================================ */
$mode = $_POST['mode'] ?? ""; // direct OR otp

$otp              = $_POST['otp'] ?? "";
$new_password     = $_POST['new_password'] ?? "";
$confirm_password = $_POST['confirm_password'] ?? "";

/* ============================================================
   COMMON VALIDATIONS
   ============================================================ */
if (empty($new_password) || empty($confirm_password)) {
    exit("ERROR: Password fields cannot be empty.");
}

if (strlen($new_password) < 8) {
    exit("ERROR: Password must be at least 8 characters.");
}

if ($new_password !== $confirm_password) {
    exit("ERROR: Passwords do not match.");
}

/* ============================================================
   DIRECT PASSWORD CHANGE (password verified earlier)
   ============================================================ */
if ($mode === "direct") {

    $current_password = $_POST['current_password'] ?? "";

    if (empty($current_password)) {
        exit("ERROR: Current password required.");
    }

    $qry = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $qry->bind_param("i", $user_id);
    $qry->execute();
    $row = $qry->get_result()->fetch_assoc();

    if (!$row) {
        exit("ERROR: User not found.");
    }

    // Verify password
    if (!password_verify($current_password, $row['password']) &&
        $current_password !== $row['password']) {
        exit("ERROR: Invalid current password.");
    }

    // Update password
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $user_id);

    if ($stmt->execute()) {
        exit("SUCCESS: Password changed successfully.");
    } else {
        exit("ERROR: Failed to update password.");
    }
}

/* ============================================================
   OTP PASSWORD RESET
   ============================================================ */
if ($mode === "otp") {

    // Required: OTP must exist
    if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expiry'])) {
        exit("ERROR: No OTP found. Please request a new OTP.");
    }

    // Expired?
    if (time() > $_SESSION['otp_expiry']) {
        unset($_SESSION['otp'], $_SESSION['otp_expiry']);
        exit("ERROR: OTP has expired. Request a new one.");
    }

    // Check OTP
    if ($otp != $_SESSION['otp']) {
        exit("ERROR: Invalid OTP.");
    }

    // Update password
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $user_id);

    if ($stmt->execute()) {
        // Remove OTP after use
        unset($_SESSION['otp'], $_SESSION['otp_expiry']);
        exit("SUCCESS: Password changed successfully via OTP.");
    } else {
        exit("ERROR: Failed to update password.");
    }
}

/* ============================================================
   INVALID MODE
   ============================================================ */
exit("ERROR: Invalid password change mode.");
