<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';   // make sure this filename matches exactly

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=All fields are required");
    exit();
}

// Query user by email
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // Verify password (supports hashed and plain)
    if ($user['password'] === $password || password_verify($password, $user['password'])) {

        // Set session values
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['role']       = $user['role'];        // admin | subadmin | procurement | end_user
        $_SESSION['username']   = $user['username'] ?? $user['email'];
        $_SESSION['fullname']   = trim(
            ($user['first_name']  ?? '') . ' ' .
            (!empty($user['middle_name']) ? $user['middle_name'].' ' : '') .
            ($user['last_name']   ?? '')
        );
        $_SESSION['department'] = $user['department'] ?? null;  // only set for end_user

        // Log login activity
        log_activity(
            $_SESSION['user_id'],
            "User logged in",
            "Authentication",
            "Success",
            $conn
        );

        // Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: /SUPPLY_OFFICE_first/Admindashboard.php");
        } elseif ($user['role'] === 'procurement') {
            header("Location: /SUPPLY_OFFICE_first/procurementdashboard.php");
        } elseif ($user['role'] === 'subadmin') {
            header("Location: /SUPPLY_OFFICE_first/userdashboard.php");
        } elseif ($user['role'] === 'end_user') {
            header("Location: /SUPPLY_OFFICE_first/endUserDashboard.php");
        } elseif ($user['role'] === 'DAA') {
            header("Location: /SUPPLY_OFFICE_first/daa_dashboard.php");
        } elseif ($user['role'] === 'inst_admin') {
            header("Location: /SUPPLY_OFFICE_first/daa_dashboard.php");
        } else {
            // Unknown role fallback
            header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
        }
        exit();

    } else {
        // Wrong password
        log_activity(
            $user['id'],
            "Failed login attempt (wrong password)",
            "Authentication",
            "Failed",
            $conn
        );
        header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Invalid password");
        exit();
    }

} else {
    // No such user
    log_activity(
        0,
        "Failed login attempt (unknown credentials)",
        "Authentication",
        "Failed",
        $conn
    );
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Invalid credentials");
    exit();
}
