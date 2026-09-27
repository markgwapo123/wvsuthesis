<?php
require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Guard: Ensure only end_users can execute or render this component
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'end_user') {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

$user_id    = $_SESSION['user_id'];
$department = $_SESSION['department'] ?? null;

if (!$department) {
    exit('No department assigned to this End User. Contact the administrator.');
}

// 1. Safe profile fallback setup
$defaultImage = "/SUPPLY_OFFICE_first/assets/img/default_profile.svg";
$profileImage = $defaultImage;

// 2. Fetch fresh user profile details straight from the database
$stmtUser = $conn->prepare("SELECT profile_img FROM users WHERE id = ? LIMIT 1");
$stmtUser->bind_param("i", $user_id);
if ($stmtUser->execute()) {
    $resUser = $stmtUser->get_result();
    if ($userRow = $resUser->fetch_assoc()) {
        if (!empty($userRow['profile_img'])) {
            $profileImage = htmlspecialchars($userRow['profile_img'], ENT_QUOTES);
        }
    }
}
$stmtUser->close();

// 3. Fetch unread updates badge count specifically for this user/department
$user_unread_count = 0;
$userRes = $conn->query("SELECT COUNT(*) AS c FROM system_notifications 
                         WHERE target_role = 'end_user' 
                         AND (target_user_id IS NULL OR target_user_id = " . (int)$user_id . ") 
                         AND is_read = 0");
if ($userRes && $ur = $userRes->fetch_assoc()) {
    $user_unread_count = (int)$ur['c'];
}
?>
<style>
    .sidebar {
        width: 220px;
        min-height: 100vh;
        background: #003366;
        color: white;
        padding: 20px;
        box-sizing: border-box;
    }

    .sidebar-profile-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding-bottom: 20px;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar h2 {
        margin: 10px 0 5px 0;
        font-size: 16px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .sidebar .dept-tag {
        font-size: 11px;
        background: rgba(255, 255, 255, 0.15);
        padding: 3px 8px;
        border-radius: 12px;
        color: #e2e8f0;
    }

    .sidebar-avatar-wrapper {
        cursor: pointer;
        transition: transform 0.2s;
    }
    
    .sidebar-avatar-wrapper:hover {
        transform: scale(1.05);
    }

    .sidebar-avatar {
        width: 65px;
        height: 65px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.4);
    }

    .sidebar a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: white;
        text-decoration: none;
        padding: 12px 15px;
        margin-bottom: 8px;
        border-radius: 6px;
        font-size: 14px;
        transition: background 0.2s;
    }

    .sidebar a:hover {
        background: #004aad;
    }

    .sidebar a i {
        margin-right: 10px;
        width: 16px;
        text-align: center;
    }

    .sidebar-link-content {
        display: flex;
        align-items: center;
    }

    .sidebar-badge {
        background: #dc3545;
        color: white;
        border-radius: 10px;
        padding: 1px 7px;
        font-size: 11px;
        font-weight: bold;
        box-shadow: 0 1px 3px rgba(220, 53, 69, 0.4);
    }
</style>

<aside class="sidebar">
    <div class="sidebar-profile-header">
        <div class="sidebar-avatar-wrapper" onclick="window.location.href='/SUPPLY_OFFICE_first/manage_account.php'" title="Manage Account">
            <img src="<?= $profileImage ?>" class="sidebar-avatar" onerror="this.src='<?= $defaultImage ?>';">
        </div>
        <h2><?= htmlspecialchars($_SESSION['fullname'] ?? 'End User'); ?></h2>
        <span class="dept-tag"><?= htmlspecialchars($department); ?></span>
    </div>

    <nav>
        <a href="endUserDashboard.php">
            <span class="sidebar-link-content"><i class="fas fa-th-large"></i> Dashboard</span>
        </a>
        <a href="my_requests.php">
            <span class="sidebar-link-content"><i class="fas fa-paper-plane"></i> My Requests</span>
        </a>
        <a href="order_management.php">
            <span class="sidebar-link-content"><i class="fas fa-shopping-cart"></i> My Orders</span>
        </a>
        <a href="manage_inventory.php">
            <span class="sidebar-link-content"><i class="fas fa-boxes"></i> Stocks</span>
        </a>
        <a href="receive_item.php">
            <span class="sidebar-link-content"><i class="fas fa-file-download"></i> Receive Goods</span>
        </a>
        <a href="user_notifications.php">
            <span class="sidebar-link-content"><i class="fas fa-bell"></i> Notifications</span>
            <?php if ($user_unread_count > 0): ?>
                <span class="sidebar-badge"><?= $user_unread_count ?></span>
            <?php endif; ?>
        </a>
    </nav>
    
</aside>