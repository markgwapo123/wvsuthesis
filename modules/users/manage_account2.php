<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name  = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name   = trim($_POST['last_name']);
    $contact     = trim($_POST['contact']);
    $address     = trim($_POST['address']);
    $dob         = $_POST['dob'];

    $profile_pic_path = null;

    // Handle file upload if any
    if (!empty($_FILES['profile_pic']['name'])) {
        $target_dir = __DIR__ . "/uploads/profile_pics/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $file_name = basename($_FILES["profile_pic"]["name"]);
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_name  = "user_" . $user_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_name;

        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_ext, $allowed)) {
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                $profile_pic_path = "uploads/profile_pics/" . $new_name;
            }
        }
    }

    // Update user info
    $sql = "UPDATE users SET first_name=?, middle_name=?, last_name=?, contact=?, address=?, dob=?";
    if ($profile_pic_path) $sql .= ", profile_pic=?";
    $sql .= " WHERE id=?";

    $stmt = $conn->prepare($sql);

    if ($profile_pic_path) {
        $stmt->bind_param("sssssssi", $first_name, $middle_name, $last_name, $contact, $address, $dob, $profile_pic_path, $user_id);
    } else {
        $stmt->bind_param("ssssssi", $first_name, $middle_name, $last_name, $contact, $address, $dob, $user_id);
    }

    $stmt->execute();
    $success = "Profile updated successfully!";
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $res = $conn->query("SELECT password FROM users WHERE id = $user_id");
    $row = $res->fetch_assoc();

    if (!password_verify($current_password, $row['password'])) {
        $error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hashed' WHERE id=$user_id");
        $success = "Password changed successfully!";
    }
}

// Fetch user data for display
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Account - SOIMS</title>
  <link rel="stylesheet" href="admindasboard.css">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; }
    .container {
      max-width: 900px; margin: 40px auto; background: #fff; padding: 25px;
      border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.1);
    }
    h2 { color: #004085; margin-bottom: 15px; }
    form { margin-bottom: 30px; }
    input, textarea {
      width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc;
      border-radius: 5px; font-size: 14px;
    }
    button {
      background: #007bff; color: white; border: none; padding: 10px 15px;
      border-radius: 5px; cursor: pointer;
    }
    button:hover { background: #0056b3; }
    .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    .profile-pic {
      text-align: center; margin-bottom: 20px;
    }
    .profile-pic img {
      width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #007bff;
    }
  </style>
</head>
<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">
  <?php include __DIR__ . '/../../sidebar.php'; ?>

  <main class="main-content">
    <div class="container">
      <h2>Manage Account</h2>

      <?php if (!empty($success)): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if (!empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="profile-pic">
        <img src="<?= $user['profile_pic'] ? htmlspecialchars($user['profile_pic']) : '/SUPPLY_OFFICE_first/assets/img/default_profile.svg' ?>" alt="Profile Picture">
      </div>

      <form method="POST" enctype="multipart/form-data">
        <h3>Update Profile Information</h3>
        <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" placeholder="First Name" required>
        <input type="text" name="middle_name" value="<?= htmlspecialchars($user['middle_name']) ?>" placeholder="Middle Name" required>
        <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" placeholder="Last Name" required>
        <input type="date" name="dob" value="<?= htmlspecialchars($user['dob']) ?>" required>
        <input type="tel" name="contact" value="<?= htmlspecialchars($user['contact']) ?>" placeholder="Contact" required>
        <textarea name="address" placeholder="Address" required><?= htmlspecialchars($user['address']) ?></textarea>
        <label>Profile Picture</label>
        <input type="file" name="profile_pic" accept="image/*">
        <button type="submit" name="update_profile">Save Changes</button>
      </form>

      <form method="POST">
        <h3>Change Password</h3>
        <input type="password" name="current_password" placeholder="Current Password" required>
        <input type="password" name="new_password" placeholder="New Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <button type="submit" name="change_password">Update Password</button>
      </form>
    </div>
  </main>
</body>
</html>

