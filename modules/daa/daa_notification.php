<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

allow_roles(['admin', 'DAA']);

$currentUserId = $_SESSION['user_id'] ?? 0;

// Fetch Theme
$settingsQuery = $conn->query("SELECT theme FROM user_settings WHERE user_id = " . (int)$currentUserId . " LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0) ? $settingsQuery->fetch_assoc() : ['theme' => 'light'];

// Fetch unread AND read persistent notifications for DAA
$notifQuery = $conn->query("SELECT * FROM system_notifications 
                            WHERE target_role = 'DAA' 
                            ORDER BY is_read ASC, created_at DESC 
                            LIMIT 50");
$notifications = [];
if ($notifQuery) {
    while($r = $notifQuery->fetch_assoc()) {
        $notifications[] = $r;
    }
}

// Automatically mark unread alerts as read since DAA is viewing this screen
$conn->query("UPDATE system_notifications SET is_read = 1 WHERE target_role = 'DAA' AND is_read = 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DAA Alerts & Notifications</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        .notification-box { max-width: 800px; margin-top: 20px; }
        .alert-card { background: #eff6ff; border-left: 5px solid #3b82f6; padding: 15px 20px; border-radius: 0 8px 8px 0; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .alert-card.read-state { background: #f8fafc; border-left-color: #cbd5e1; opacity: 0.85; }
        .alert-details h3 { margin: 0 0 5px 0; font-size: 15px; color: #1e3a8a; }
        .alert-card.read-state .alert-details h3 { color: #475569; }
        .alert-details p { margin: 0; font-size: 13px; color: #1e40af; }
        .alert-card.read-state .alert-details p { color: #64748b; }
        .alert-time { font-size: 11px; color: #60a5fa; text-align: right; }
        .alert-card.read-state .alert-time { color: #94a3b8; }
        .btn-view { background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 12px; }
        .alert-card.read-state .btn-view { background: #64748b; }
        .unread-badge { background: #dc2626; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 5px; text-transform: uppercase; }
        .empty-notifications { text-align: center; background: white; padding: 40px; border-radius: 12px; border: 1px dashed #cbd5e1; color: #64748b; }
    </style>
</head>
<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">
<div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>
    <main class="main-content">
        <header class="header">
            <h1>DAA Notification Hub</h1>
            <p style="color: #64748b;">Real-time actionable alerts for incoming operational requests from department users.</p>
        </header>

        <div class="notification-box">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                    <?php 
                    $isRead = (int)$notif['is_read'] === 1; 
                    
                    // Route to matching workflow file based on context type
                    $redirectPage = ($notif['module_type'] === 'disposal') ? 'daa_process_disposals.php' : 'daa_process_request.php';
                    ?>
                    <div class="alert-card <?= $isRead ? 'read-state' : '' ?>">
                        <div class="alert-details">
                            <h3>
                                <?= htmlspecialchars($notif['title']) ?>
                                <?php if(!$isRead): ?><span class="unread-badge">New</span><?php endif; ?>
                            </h3>
                            <p><?= htmlspecialchars($notif['message']) ?></p>
                        </div>
                        <div style="text-align: right;">
                            <div class="alert-time"><?= date('h:i A', strtotime($notif['created_at'])) ?></div>
                            <div class="alert-time" style="margin-bottom: 10px;"><?= date('M d, Y', strtotime($notif['created_at'])) ?></div>
                            <a href="<?= $redirectPage ?>?ref_id=<?= $notif['reference_id'] ?>" class="btn-view">
                                <?= $isRead ? 'Review Log' : 'Go to Process' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-notifications">
                    <div style="font-size: 40px; margin-bottom: 10px;">🔔</div>
                    <h3>No Alerts Found</h3>
                    <p>There are currently no persistent system logs addressed to your role profile status.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
<script src="admindashboard.js" defer></script>
</html>