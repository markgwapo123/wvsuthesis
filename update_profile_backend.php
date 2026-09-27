<?php
require_once "auth.php";
require_once "db_connect.php";

$user = current_user();
if (!$user) {
    exit("Unauthorized access.");
}

$user_id = $user['id'];

// ==============================
// HANDLE PROFILE IMAGE UPLOAD
// ==============================
if (!empty($_FILES['profile_img']['name'])) {
    $uploadError = $_FILES['profile_img']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadError !== UPLOAD_ERR_OK) {
        exit("Upload failed: " . $uploadError);
    }

    $targetDir = __DIR__ . "/uploads/profile/";
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        exit("Failed to create upload folder.");
    }

    $fileName   = $user_id . "_" . time() . "_" . basename($_FILES["profile_img"]["name"]);
    $targetFile = $targetDir . DIRECTORY_SEPARATOR . $fileName;
    $dbPath     = "/SUPPLY_OFFICE_first/uploads/profile/" . $fileName;

    $allowed = ["jpg","jpeg","png"];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        exit("Only JPG and PNG are allowed.");
    }

    $tmpName = $_FILES['profile_img']['tmp_name'] ?? null;
    if (!$tmpName || (!is_uploaded_file($tmpName) && !file_exists($tmpName))) {
        exit("Upload file is missing or invalid.");
    }

    $moved = false;
    if (is_uploaded_file($tmpName)) {
        $moved = move_uploaded_file($tmpName, $targetFile);
    }
    if (!$moved && file_exists($tmpName)) {
        $moved = copy($tmpName, $targetFile);
    }

    if ($moved) {
        $stmt = $conn->prepare("UPDATE users SET profile_img = ? WHERE id = ?");
        $stmt->bind_param("si", $dbPath, $user_id);
        if ($stmt->execute()) {
            if (function_exists('log_activity')) {
                log_activity($user_id, "Updated profile picture", "Account", "Success", $conn);
            }
            exit("success: Profile picture updated successfully!");
        } else {
            exit("Upload succeeded but saving to database failed.");
        }
    }

    exit("Failed to upload image.");
}

// ==============================
// HANDLE INFORMATION UPDATE
// ==============================
if (!empty($_POST['first_name'])) {

    $first   = $conn->real_escape_string($_POST['first_name']);
    $middle  = $conn->real_escape_string($_POST['middle_name'] ?? "");
    $last    = $conn->real_escape_string($_POST['last_name']);
    $dob     = $conn->real_escape_string($_POST['dob'] ?? null);
    $contact = $conn->real_escape_string($_POST['contact']);
    $email   = $conn->real_escape_string($_POST['email']);

    // Validate email is not used by another user
    $emailCheck = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $emailCheck->bind_param("si", $email, $user_id);
    $emailCheck->execute();
    $emailCheckRes = $emailCheck->get_result();

    if ($emailCheckRes->num_rows > 0) {
        exit("Email already in use by another account.");
    }

    $stmt = $conn->prepare("
        UPDATE users 
        SET first_name=?, middle_name=?, last_name=?, dob=?, contact=?, email=? 
        WHERE id=?
    ");
    $stmt->bind_param("ssssssi", $first, $middle, $last, $dob, $contact, $email, $user_id);
    $stmt->execute();

    if (function_exists('log_activity')) {
        log_activity($user_id, "Updated account information", "Account", "Success", $conn);
    }

    exit("success: Account information updated.");
}

exit("No changes detected.");
