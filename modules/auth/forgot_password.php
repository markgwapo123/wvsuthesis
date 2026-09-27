<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';

// Load PHPMailer classes
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Message variable to hold error/success messages
$message = "";

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);

    // Check if the email exists in the users table
    $check = $conn->query("SELECT id, first_name FROM users WHERE email='$email' LIMIT 1");

    if ($check && $check->num_rows > 0) {
        // Email exists, proceed with OTP generation
        $row = $check->fetch_assoc();
        $user_first = $row['first_name'];  // Get the user's first name for the email body
        $_SESSION['reset_email'] = $email;  // Store the email in session

        // Generate a 6-digit OTP
        $otp = rand(100000, 999999);
        $expiry = time() + (5 * 60);  // OTP valid for 5 minutes

        // Store OTP and expiry time in the session
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = $expiry;

        // Use PHPMailer to send the OTP to the user's email
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
    $mail->addAddress($email, $user_first);

            // Email content
            $mail->isHTML(true);
            $mail->Subject = 'SOIMS Password Reset OTP';
            $mail->Body    = "Your OTP code is: <strong>$otp</strong><br>This OTP is valid for 5 minutes.";
            $mail->AltBody = "Your OTP code is: $otp (valid for 5 minutes)";

            // Send email
            $mail->send();

            // Success message
            $message = "OTP sent to your email. Please check your inbox.";

                        // Redirect to OTP verification page
            header("Location: verifying_otp.php");
            exit();

        } catch (Exception $e) {
            // Error sending OTP
            $message = "Error sending OTP: " . $mail->ErrorInfo;
        }

    } else {
        // Email not found in database
        $message = "Incorrect email. Not found in the system.";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Forgot Password</title>
<link rel="stylesheet" href="style.css">
<style>
.containerBox { max-width:400px; margin:80px auto; padding:20px; background:#fff; border-radius:8px; }
input { width:100%; padding:10px; margin-top:10px; }
button { margin-top:15px; padding:10px 20px; width:100%; }
</style>
</head>

<body>
<div class="containerBox">
<h2>Forgot Password</h2>

<?php if ($message): ?>
<p style="color:red;"><?= $message ?></p>
<?php endif; ?>

<form method="POST">
    <label>Enter your registered email:</label>
    <input type="email" name="email" required>
    <button type="submit">Send OTP</button>
</form>

</div>
</body>
</html>
