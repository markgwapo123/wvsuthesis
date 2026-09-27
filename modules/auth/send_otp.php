<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';

// Load PHPMailer
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Must be logged in OR manually submitting email
$user_email = "";
$user_first = "";

// OPTION A — email provided from AJAX:
if (isset($_POST['email'])) {
    $email = $conn->real_escape_string($_POST['email']);

    $q = $conn->query("SELECT id, first_name FROM users WHERE email='$email' LIMIT 1");
    if ($q->num_rows == 0) exit("ERROR: Email not found.");

    $row = $q->fetch_assoc();
    $user_email = $email;
    $user_first = $row['first_name'];
}
// OPTION B — from logged-in session:
else if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $q = $conn->query("SELECT email, first_name FROM users WHERE id=$uid LIMIT 1");
    $row = $q->fetch_assoc();

    $user_email = $row['email'];
    $user_first = $row['first_name'];
}
else {
    exit("ERROR: No email available.");
}

// Generate OTP
$otp = rand(100000, 999999);
$expiry = time() + (5 * 60); // valid for 5 minutes

$input_otp = $_POST['otp'] ?? "";

$_SESSION['otp'] = $otp;
$_SESSION['otp_email'] = $email;
$_SESSION['otp_expiry'] = $expiry;


$mail = new PHPMailer(true);

try {
    // SMTP settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'supplyofficehimamaylan@gmail.com';
    $mail->Password   = 'uybtwaiqstdsocir'; // APP PASSWORD
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Sender & Recipient
    $mail->setFrom('supplyofficehimamaylan@gmail.com','SOIMS');
    $mail->addAddress($user_email, $user_first);

    // Email content
    $mail->isHTML(true);
    $mail->Subject = 'SOIMS OTP Code';
    $mail->Body = "
        <h2>Your OTP Code</h2>
        <p>Hello <b>$user_first</b>, your OTP is:</p>
        <h1 style='font-size:32px;'>$otp</h1>
        <p>This OTP is valid for <b>5 minutes</b>.</p>
    ";
    $mail->AltBody = "Your OTP is $otp (valid for 5 minutes).";

    $mail->send();

    echo "OTP_SENT";

} catch (Exception $e) {
    exit("ERROR: " . $mail->ErrorInfo);
}
?>
