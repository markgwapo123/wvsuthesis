<?php
require_once __DIR__ . '/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';

$role   = $_SESSION['role'] ?? 'guest';
$userId = $_SESSION['user_id'] ?? null;

if ($role === 'end_user') {
    return; // stop executing sidebar for end users
}

// 1. Generic System Notifications Count (For Admin / Subadmin)
$unread_count = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0");
if ($res && $r = $res->fetch_assoc()) {
    $unread_count = (int)$r['c'];
}

// 2. DAA-Specific Notifications Count (Pending Restock Requests)
$daa_unread_count = 0;
if ($role === 'DAA' || $role === 'admin') {
    $daaRes = $conn->query("SELECT COUNT(*) AS c FROM requests WHERE LOWER(status) = 'pending' AND is_read = 0");
    if ($daaRes && $dr = $daaRes->fetch_assoc()) {
        $daa_unread_count = (int)$dr['c'];
    }
}

// Profile image setup
$profileImage = '/SUPPLY_OFFICE_first/assets/img/default_profile.svg'; // fallback image

if ($userId) {
    if ($stmt = $conn->prepare("SELECT profile_img FROM users WHERE id = ? LIMIT 1")) {
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result && $userRow = $result->fetch_assoc()) {
                if (!empty($userRow['profile_img'])) {
                    $profileImage = $userRow['profile_img']; 
                }
            }
        }
        $stmt->close();
    }
}

$profileImage = htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8');

// --- VISUAL DISPLAY ROLE OVERRIDES ---
if ($role === 'admin') {
    $displayRole = 'Supply Admin';
} elseif ($role === 'inst_admin') {
    $displayRole = 'Admin';
} else {
    $displayRole = htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8');
}
?>
<style>
  .sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
  }

  .sidebar-header .logo {
    margin: 0;
    font-size: 24px; /* Slightly adjusted down from 30px to perfectly fit "Supply Admin" layout cleanly */
    font-weight: 700;
  }

  .profile-avatar-btn {
    border: none;
    background: transparent;
    padding: 0;
    cursor: pointer;
  }

  .profile-avatar {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.3);
  }

  .profile-avatar:hover {
    filter: brightness(1.05);
  }

  .input-group:not(.has-validation) > :not(:last-child):not(.dropdown-toggle):not(.dropdown-menu):not(.form-floating),
  .input-group:not(.has-validation) > .dropdown-toggle:nth-last-child(n+3),
  .input-group:not(.has-validation) > .form-floating:not(:last-child) > .form-control,
  .input-group:not(.has-validation) > .form-floating:not(:last-child) > .form-select {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
  }

  .notif-badge {
    background: #dc3545;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 11px;
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: bold;
  }
</style>

<aside class="sidebar">
  <div class="sidebar-header">
    <h2 class="logo"><?= $displayRole ?></h2>
    <button
      class="profile-avatar-btn"
      type="button"
      onclick="window.location.href='/SUPPLY_OFFICE_first/manage_account.php'">
      <img src="<?= $profileImage ?>" alt="Profile" class="profile-avatar">
    </button>
  </div>

  <ul class="menu">
    <?php if ($role === 'admin'): ?>
      <li onclick="window.location.href='Admindashboard.php'">📊 Overview</li>
    <?php elseif ($role === 'subadmin'): ?>
      <li onclick="window.location.href='userdashboard.php'">📊 Overview</li>
    <?php elseif ($role === 'DAA' || $role==='inst_admin'): ?>
      <li onclick="window.location.href='daa_dashboard.php'">📊 DAA Overview</li>
    <?php endif; ?>

    <?php if ($role === 'admin' || $role === 'subadmin'): ?>
    <li class="has-submenu">
      📦 Inventory Management <span class="arrow">▸</span>
      <div class="submenu">
        <p onclick="window.location.href='manage_inventory.php'">Manage Inventory</p>
        <p onclick="window.location.href='admin_requests.php'">Manage Requests</p>
        <p onclick="window.location.href='admin_execute_disposals.php'">Manage Disposal</p>       
        <p onclick="window.location.href='manage_suppliers.php'">Suppliers List</p>
        <p onclick="window.location.href='generate_barcode.php'">Barcode Management</p>
      </div>
    </li>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <li class="has-submenu">
      👥 User Management <span class="arrow">▸</span>
      <div class="submenu">
        <p onclick="window.location.href='users.php'">Users</p>
        <p onclick="window.location.href='assign_roles_permissions.php'">Assign Roles & Permissions</p>
        <p onclick="window.location.href='view_user_activity.php'">View User Activity Log</p>
      </div>
    </li>
    <?php endif; ?>

    <?php if ($role === 'admin' || $role === 'subadmin'): ?>
    <li class="has-submenu">
      📑 Reports <span class="arrow">▸</span>
      <div class="submenu">
        <p onclick="window.location.href='inventory_summary_report.php'">Inventory Summary Report</p>
        <p onclick="window.location.href='issuance_report.php'">Issuance Report</p>
        <p onclick="window.location.href='stock_levels.php'">Stock In Report</p>
        <p onclick="window.location.href='disposed_items.php'">Disposed Item Report</p>
        <p onclick="window.location.href='expiring_item.php'">Expiring Item Report</p>
      </div>
    </li>
    <?php endif; ?>

    <?php if ($role === 'DAA' || $role==='inst_admin'): ?>
      <li onclick="window.location.href='daa_process_request.php'">📝 Requests to Process</li>
      <li onclick="window.location.href='daa_process_disposals.php'">🗑️ Disposal to Process</li>
      <li onclick="window.location.href='daa_history.php'">📋 Request History</li>
      <li onclick="window.location.href='daa_notification.php'" style="position:relative;">
        🔔 Alerts
        <?php if ($daa_unread_count > 0): ?>
          <span class="notif-badge"><?= $daa_unread_count ?></span>
        <?php endif; ?>
      </li>
    <?php endif; ?>

    <?php if ($role !== 'DAA'): ?>
      <li onclick="window.location.href='admin_notification.php'" style="position:relative;">
        🔔 System Notification
        <?php if ($unread_count > 0): ?>
          <span class="notif-badge"><?= $unread_count ?></span>
        <?php endif; ?>
      </li>  
    <?php endif; ?>

    <?php if ($role === 'admin' || $role === 'subadmin'): ?>
      <li onclick="window.location.href='archive_all.php'">🗄️ Archive</li>
      <li onclick="window.location.href='support.php'">🆘 Support</li>
    <?php endif; ?>
  </ul>

  <form action="/SUPPLY_OFFICE_first/modules/auth/logout.php" method="post">
    <button class="logout" type="submit">⬅ Logout</button>
  </form>
</aside>