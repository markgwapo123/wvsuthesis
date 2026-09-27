<?php
$inputPassword = "Admin@123";
$hashFromDB = '$2y$10$Qe9Eq9g8MZ.7gAmZgyt9WOrXw5XamFZqCkA4vZJnpOB7SxMGBZ3t6';

if (password_verify($inputPassword, $hashFromDB)) {
    echo "✅ Password matches";
} else {
    echo "❌ Password does not match";
}
?>
