<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName  = trim($_POST['firstName']);
    $middleName = trim($_POST['middleName']);
    $lastName   = trim($_POST['lastName']);
    $dob        = $_POST['dob'];
    $contact    = trim($_POST['contact']);
    $address    = trim($_POST['address']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $role       = $_POST['role'];

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: signup.php?error=Invalid email format");
        exit();
    }

    // Check duplicate email
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        header("Location: signup.php?error=Email already exists");
        exit();
    }
    $check->close();

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, dob, contact, address, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $firstName, $middleName, $lastName, $dob, $contact, $address, $email, $hashedPassword, $role);

    if ($stmt->execute()) {
        header("Location: signup.php?success=1");
    } else {
        header("Location: signup.php?error=Database error");
    }
    $stmt->close();
    // âœ… Create default settings for this user
$conn->query("INSERT INTO user_settings (user_id) VALUES ($newUserId)");
}
?>

