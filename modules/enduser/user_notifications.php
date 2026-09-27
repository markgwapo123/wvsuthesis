<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'end_user') {
    die("Access Denied.");
}

$myUserId = $_SESSION['user_id'] ?? 0;

// Pull global end-user notifications OR items targeted explicitly to this logged-in account ID reference
$res = $conn->query("SELECT * FROM system_notifications 
                     WHERE target_role = 'end_user' AND (target_user_id IS NULL OR target_user_id = " . (int)$myUserId . ")
                     ORDER BY is_read ASC, created_at DESC LIMIT 30");

// Mark viewed entries as read cleanly
$conn->query("UPDATE system_notifications SET is_read = 1 WHERE target_role = 'end_user' AND (target_user_id IS NULL OR target_user_id = " . (int)$myUserId . ") AND is_read = 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications | SOIMS</title>
    <link rel="stylesheet" href="enduser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f4f7fc; 
            margin: 0;
            padding: 0;
        }
        
        /* Layout Grid System wrapper */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-workspace {
            flex: 1;
            padding: 30px;
            box-sizing: border-box;
        }

        .wrapper { 
            max-width: 1100px; 
            margin: 0 auto; 
            background: transparent;
        }

        .header-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: #fff;
            padding: 20px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .header-area h2 {
            margin: 0;
            font-size: 22px;
            color: #003366;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Scattered / Grid Notification Cards Matrix */
        .notif-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .notif-item { 
            background: #ffffff;
            padding: 20px; 
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border-top: 4px solid #10b981; 
            transition: all 0.25s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .notif-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        }

        /* Unread layout accent variations */
        .notif-item.unread { 
            border-top-color: #ef4444; 
            background: #fffcfc; 
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.08);
        }

        .unread-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: pulseGlow 2s infinite;
        }

        .notif-title { 
            font-size: 15px; 
            margin: 0 0 10px 0; 
            color: #1e293b; 
            font-weight: 700;
            padding-right: 50px;
        }
        
        .notif-item.unread .notif-title {
            color: #991b1b;
        }

        .notif-msg { 
            font-size: 13.5px; 
            color: #475569; 
            margin: 0 0 15px 0; 
            line-height: 1.5;
            font-weight: normal; 
        }

        .notif-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
            margin-top: auto;
        }

        .notif-date { 
            font-size: 11.5px; 
            color: #94a3b8; 
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            color: #003366;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #003366;
            color: white;
            border-color: #003366;
        }

        @keyframes pulseGlow {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style> 
</head>
<body>

    <div class="dashboard-container">
        <?php include __DIR__ . '/endusersidebar.php'; ?>

        <main class="main-workspace">
            <div class="wrapper">
                
                <div class="header-area">
                    <h2><i class="fas fa-bell" style="color: #f59e0b;"></i> Supply Notifications Matrix</h2>
                    <a href="receive_item.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Return to Workspace
                    </a>
                </div>
                
                <?php if ($res && $res->num_rows > 0): ?>
                    <div class="notif-grid">
                        <?php while($n = $res->fetch_assoc()): ?>
                            <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                                <?php if (!$n['is_read']): ?>
                                    <span class="unread-badge">New</span>
                                <?php endif; ?>
                                
                                <div>
                                    <h4 class="notif-title"><?= htmlspecialchars($n['title']) ?></h4>
                                    <p class="notif-msg"><?= htmlspecialchars($n['message']) ?></p>
                                </div>

                                <div class="notif-footer">
                                    <div class="notif-date">
                                        <i class="far fa-clock"></i> 
                                        <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?>
                                    </div>
                                    <span style="font-size: 14px;">
                                        <?= $n['is_read'] ? '◯' : '🔴' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="background: white; text-align: center; padding: 50px 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                        <i class="far fa-bell-slash" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                        <p style="color: #64748b; font-size: 15px; margin: 0;">You have no active log records or stock updates at this time.</p>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

</body>
</html>