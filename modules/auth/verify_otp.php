<?php
session_start();

$input = $_POST['otp'] ?? "";

if (!isset($_SESSION['otp'])) {
    exit("NO_OTP");
}

if (time() > $_SESSION['otp_expiry']) {
    exit("EXPIRED");
}

if ($input == $_SESSION['otp']) {
    exit("OTP_OK");
}

exit("INVALID");
