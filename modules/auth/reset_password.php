<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';

// Check if OTP is verified
if (!isset($_SESSION['otp']) || !isset($_SESSION['reset_email'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/forgot_password.php");
    exit();
}

$message = "";

// Handle password reset form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in both password fields.";
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters.";
    } elseif ($new_password != $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password in the database
        $email = $_SESSION['reset_email'];
        $query = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $query->bind_param("ss", $hashed_password, $email);

        if ($query->execute()) {
            // Password reset successful, clear session data
            unset($_SESSION['otp']);
            unset($_SESSION['reset_email']);
            $message = "Your password has been reset successfully. You can now log in.";
        } else {
            $message = "Failed to reset your password. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .containerBox { max-width:400px; margin:80px auto; padding:20px; background:#fff; border-radius:8px; }
        input { width:100%; padding:10px; margin-top:10px; }
        button { margin-top:15px; padding:10px 20px; width:100%; }
    </style>
</head>
<body>
<div class="containerBox">
    <h2>Reset Your Password</h2>

    <?php if ($message): ?>
        <p style="color:red;"><?= $message ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>New Password</label>
        <input type="password" name="new_password" required>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Reset Password</button>
    </form>
</div>
</body>
</html>
