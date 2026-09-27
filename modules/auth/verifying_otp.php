<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';

// Check if OTP is in session
if (!isset($_SESSION['otp']) || !isset($_SESSION['reset_email'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/forgot_password.php");
    exit();
}

$message = "";

// Handle OTP submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp = $_POST['otp'];

    // Check if the entered OTP matches the stored OTP
    if ($otp == $_SESSION['otp']) {
        // OTP is correct, proceed to password reset
        header("Location: reset_password.php");
        exit();
    } else {
        // Invalid OTP
        $message = "Invalid OTP. Please try again.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .containerBox { max-width:400px; margin:80px auto; padding:20px; background:#fff; border-radius:8px; }
        input { width:100%; padding:10px; margin-top:10px; }
        button { margin-top:15px; padding:10px 20px; width:100%; }
    </style>
</head>
<body>
<div class="containerBox">
    <h2>Verify OTP</h2>

    <?php if ($message): ?>
        <p style="color:red;"><?= $message ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Enter the OTP sent to your email:</label>
        <input type="text" name="otp" required>
        <button type="submit">Verify OTP</button>
    </form>
</div>
</body>
</html>
