<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Secure Check: Only Admin and Subadmin roles are allowed here
allow_roles(['admin', 'subadmin']);

$currentUserId = $_SESSION['user_id'] ?? 0;

// Fetch Theme Configuration Settings
$settingsQuery = $conn->query("SELECT theme FROM user_settings WHERE user_id = " . (int)$currentUserId . " LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0) ? $settingsQuery->fetch_assoc() : ['theme' => 'light'];

// Fetch unread AND read persistent notifications for Admin/Subadmin roles
$notifQuery = $conn->query("SELECT * FROM system_notifications 
                            WHERE target_role = 'admin' 
                            ORDER BY is_read ASC, created_at DESC 
                            LIMIT 50");
$notifications = [];
if ($notifQuery) {
    while($r = $notifQuery->fetch_assoc()) {
        $notifications[] = $r;
    }
}

// Automatically mark unread alerts as read since the administrator is reviewing this screen
$conn->query("UPDATE system_notifications SET is_read = 1 WHERE target_role = 'admin' AND is_read = 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Action & Stock Alerts Hub</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        .notification-box { max-width: 900px; margin-top: 20px; }
        .alert-card { background: #f8fafc; border-left: 5px solid #64748b; padding: 18px 22px; border-radius: 0 8px 8px 0; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        
        /* 🚨 Red Highlight Theme for Urgent Inventory Stock alerts */
        .alert-card.type-inventory { background: #fef2f2; border-left-color: #ef4444; }
        .alert-card.type-inventory h3 { color: #991b1b; }
        .alert-card.type-inventory p { color: #7f1d1d; }
        
        /* 📋 Blue Highlight Theme for Request Orders or Issuances */
        .alert-card.type-order, .alert-card.type-issuance { background: #eff6ff; border-left-color: #3b82f6; }
        .alert-card.type-order h3, .alert-card.type-issuance h3 { color: #1e3a8a; }
        .alert-card.type-order p, .alert-card.type-issuance p { color: #1e40af; }

        /* ♻️ Purple Highlight Theme for Post-DAA Disposal Pipelines */
        .alert-card.type-disposal { background: #faf5ff; border-left-color: #a855f7; }
        .alert-card.type-disposal h3 { color: #6b21a8; }
        .alert-card.type-disposal p { color: #581c87; }

        /* Grey styling configuration for viewed entries */
        .alert-card.read-state { background: #f1f5f9 !important; border-left-color: #cbd5e1 !important; opacity: 0.8; }
        .alert-card.read-state h3 { color: #475569 !important; }
        .alert-card.read-state p { color: #64748b !important; }

        .alert-time { font-size: 11px; color: #94a3b8; text-align: right; }
        .btn-action { background: #0f172a; color: white; border: none; padding: 8px 14px; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 12px; display: inline-block; margin-top: 8px; transition: background 0.2s; }
        .btn-action:hover { background: #334155; }
        .alert-card.read-state .btn-action { background: #64748b; }
        
        .unread-indicator { background: #dc2626; color: white; padding: 2px 5px; border-radius: 3px; font-size: 9px; margin-left: 6px; text-transform: uppercase; letter-spacing: 0.5px; vertical-align: middle; }
        .empty-state { text-align: center; background: white; padding: 50px; border-radius: 12px; border: 1px dashed #cbd5e1; color: #64748b; }
    </style>
</head>
<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">
<div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>
    <main class="main-content">
        <header class="header">
            <h1>Admin Notification Control Center</h1>
            <p style="color: #64748b;">Overview of required manual approvals, final physical logistics execution updates, and critical stock balance warnings.</p>
        </header>

        <div class="notification-box">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                    <?php 
                    $isRead = (int)$notif['is_read'] === 1; 
                    $module = strtolower($notif['module_type']); // 'order', 'issuance', 'disposal', 'inventory'
                    $refId = $notif['reference_id'];
                    
                    // ROUTING TO THE CORRECT FILES YOU HAVE IN YOUR SYSTEM
if ($module === 'order') {
    $destination = "admin_process_orders.php?ref_id=" . $refId;
} elseif ($module === 'issuance') {
    $destination = "admin_manage_issuances.php?issuance_id=" . $refId;
} elseif ($module === 'disposal') {
    // ROUTE TO YOUR ACTUAL DISPOSAL FILE
    // If the item needs action, we pass the disposal ID as 'id'
    $destination = "admin_execute_disposals.php?id=" . $refId;
} elseif ($module === 'inventory') {
    $destination = "admin_inventory_stocks.php?ref_id=" . $refId;
} else {
    $destination = "admin_dashboard.php";
}
                    ?>
                    
                    <div class="alert-card type-<?= $module ?> <?= $isRead ? 'read-state' : '' ?>">
                        <div class="alert-details">
                            <h3>
                                <?= htmlspecialchars($notif['title']) ?>
                                <?php if(!$isRead): ?><span class="unread-indicator">New Action</span><?php endif; ?>
                            </h3>
                            <p><?= htmlspecialchars($notif['message']) ?></p>
                        </div>
                        <div style="text-align: right; min-width: 140px;">
                            <div class="alert-time">📅 <?= date('M d, Y', strtotime($notif['created_at'])) ?></div>
                            <div class="alert-time" style="margin-bottom: 5px;">⏰ <?= date('h:i A', strtotime($notif['created_at'])) ?></div>
                            
                            <a href="<?= $destination ?>" class="btn-action">
                                <?= $isRead ? 'View Records' : 'Resolve Task &rarr;' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div style="font-size: 45px; margin-bottom: 12px;">🛡️</div>
                    <h3>System Action Logs Clear</h3>
                    <p>No operational tasks or stock balance alerts currently require administrator or subadmin triage decisions.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="admindashboard.js" defer></script>
</body>
</html>