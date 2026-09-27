<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Access Control - Only allow Admin and Sub-Admin roles
allow_roles(['admin', 'subadmin']); 

$currentUserId = $_SESSION['user_id'] ?? 0;

// Theme Configuration
$settingsQuery = $conn->query("SELECT theme FROM user_settings WHERE user_id = " . (int)$currentUserId . " LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0) ? $settingsQuery->fetch_assoc() : ['theme' => 'light'];

// Determine active view tab
$admin_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending_action';

if ($admin_tab === 'completed') {
    $admin_where = "WHERE status = 'Disposed'";
} elseif ($admin_tab === 'rejected') {
    $admin_where = "WHERE status = 'Rejected by Admin'";
} else {
    $admin_where = "WHERE status = 'Approved by DAA'"; 
}

// Fetch records for Admin action
$admin_sql = "SELECT disposal_id, requestor_name, department, item_name, qty, unit, reason, status, daa_approved_by, created_at 
              FROM disposal_requests 
              $admin_where 
              ORDER BY disposal_id DESC";
$admin_result = $conn->query($admin_sql);
$admin_count = ($admin_result) ? $admin_result->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Asset Disposal Execution</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        :root {
            --bg-workspace: #f8fafc;
            --text-title: #0f172a;
            --text-subtitle: #475569;
            --card-bg: #ffffff;
            --border: #e2e8f0;
        }

        .dark-mode {
            --bg-workspace: #0f172a;
            --text-title: #f8fafc;
            --text-subtitle: #94a3b8;
            --card-bg: #1e293b;
            --border: #334155;
        }

        .adm-wrapper { 
            padding: 30px !important; 
            background-color: var(--bg-workspace) !important; 
            min-height: 100vh !important; 
            font-family: 'Inter', sans-serif !important; 
        }
        
        .adm-title { color: var(--text-title) !important; font-weight: 800; font-size: 26px; margin: 0; }
        .adm-subtitle { color: var(--text-subtitle) !important; margin: 5px 0 0 0; font-size: 14px; }

        .adm-card { 
            background: var(--card-bg) !important; 
            border: 1px solid var(--border) !important; 
            border-radius: 12px !important; 
            padding: 24px !important; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important; 
        }
        
        .adm-tabs { display: flex !important; gap: 8px !important; margin-bottom: 20px !important; border-bottom: 2px solid var(--border) !important; padding-bottom: 8px !important; }
        .adm-tab-btn { text-decoration: none !important; padding: 10px 18px !important; font-weight: 600; font-size: 14px !important; color: #64748b !important; border-radius: 6px !important; }
        .adm-tab-btn.active { background: #eff6ff !important; color: #2563eb !important; border-bottom: 2px solid #2563eb !important; }
        
        table.adm-table { width: 100% !important; border-collapse: collapse !important; display: table !important; }
        table.adm-table th { background-color: #f1f5f9 !important; color: #475569 !important; font-weight: 700 !important; text-align: left !important; padding: 14px 12px !important; font-size: 13px !important; border-bottom: 2px solid #cbd5e1 !important; }
        table.adm-table td { padding: 14px 12px !important; border-bottom: 1px solid var(--border) !important; color: var(--text-title) !important; background-color: var(--card-bg) !important; }
        
        .action-btn-group { display: flex; gap: 6px; }
        .btn-dispose { border: none !important; padding: 8px 12px !important; border-radius: 6px !important; font-weight: 600 !important; cursor: pointer !important; background-color: #10b981 !important; color: #ffffff !important; font-size: 12px !important; transition: background-color 0.15s; text-decoration: none; }
        .btn-dispose:hover { background-color: #059669 !important; }

        .btn-reject-trigger { border: none !important; padding: 8px 12px !important; border-radius: 6px !important; font-weight: 600 !important; cursor: pointer !important; background-color: #ef4444 !important; color: #ffffff !important; font-size: 12px !important; transition: background-color 0.15s; }
        .btn-reject-trigger:hover { background-color: #dc2626 !important; }
        
        .btn-print-receipt { border: 1px solid #cbd5e1 !important; padding: 8px 14px !important; border-radius: 6px !important; font-weight: 600 !important; cursor: pointer !important; background-color: #ffffff !important; color: #475569 !important; font-size: 13px !important; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s; }
        .btn-print-receipt:hover { background-color: #f1f5f9 !important; color: #0f172a !important; border-color: #94a3b8 !important; }

        .badge-status { padding: 4px 10px !important; border-radius: 9999px !important; font-size: 12px !important; font-weight: 700 !important; background-color: #dcfce7 !important; color: #15803d !important; }
        .badge-pending { padding: 4px 10px !important; border-radius: 9999px !important; font-size: 12px !important; font-weight: 700 !important; background-color: #fef3c7 !important; color: #92400e !important; }
        .badge-rejected { padding: 4px 10px !important; border-radius: 9999px !important; font-size: 12px !important; font-weight: 700 !important; background-color: #fee2e2 !important; color: #991b1b !important; }

        .adm-ui-banner { padding: 12px 16px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; font-weight: 600; border: 1px solid transparent; }
        .adm-banner-success { background-color: #dcfce7; color: #14532d; border-color: #bbf7d0; }
        .adm-banner-danger { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5; }

        .reject-tray { background-color: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 5px; }
        .dark-mode .reject-tray { background-color: #1e293b; border-color: #334155; }
        .reject-input { width: 70%; padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px; margin-right: 8px; }
        .btn-reject-submit { background-color: #991b1b; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; }
    </style>
</head>
<body class="<?php echo ($settings['theme'] == 'dark') ? 'dark-mode' : ''; ?>">

<div class="dashboard" style="display: flex !important;">
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="adm-wrapper" style="flex: 1 !important;">
        <header style="margin-bottom: 25px;">
            <h1 class="adm-title">Inventory Disposal Execution</h1>
            <p class="adm-subtitle">Finalize physical inventory scrap protocols for items verified by Department Heads.</p>
        </header>

        <div class="adm-card">
            <div class="adm-tabs">
                <a href="admin_execute_disposals.php?tab=pending_action" class="adm-tab-btn <?php echo ($admin_tab === 'pending_action') ? 'active' : ''; ?>">📦 Awaiting Final Disposal</a>
                <a href="admin_execute_disposals.php?tab=completed" class="adm-tab-btn <?php echo ($admin_tab === 'completed') ? 'active' : ''; ?>">✅ Completed Disposals Archive</a>
                <a href="admin_execute_disposals.php?tab=rejected" class="adm-tab-btn <?php echo ($admin_tab === 'rejected') ? 'active' : ''; ?>">❌ Rejected by Admin</a>
            </div>

            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] === 'success_disposed'): ?>
                    <div class="adm-ui-banner adm-banner-success">
                        ✅ Success: Item has been physically scrapped and removed completely from active inventory stocks.
                    </div>
                <?php elseif ($_GET['status'] === 'success_rejected'): ?>
                    <div class="adm-ui-banner adm-banner-success">
                        🛑 Disposal request has been formally denied and moved to the Rejection Archive.
                    </div>
                <?php elseif ($_GET['status'] === 'error_missing_reason'): ?>
                    <div class="adm-ui-banner adm-banner-danger">
                        ⚠️ Error: You must supply an explicit rejection reason statement before denying an operational request.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="overflow-x: auto;">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Date Requested</th>
                            <th>Origin Department</th>
                            <th>Asset Details</th>
                            <th>Qty To Remove</th>
                            <th>DAA Authorized By</th>
                            <th>Status State</th>
                            <th>Management Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($admin_count > 0) {
                            while($row = $admin_result->fetch_assoc()) {
                                $dateStr = date("M d, Y h:i A", strtotime($row['created_at']));
                                $statusClass = 'badge-pending';
                                if ($row['status'] === 'Disposed') { $statusClass = 'badge-status'; }
                                if ($row['status'] === 'Rejected by Admin') { $statusClass = 'badge-rejected'; }
                                ?>
                                <tr>
                                    <td><strong><?php echo $dateStr; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['department']); ?></strong><br>
                                        <small style="color: #64748b;">By: <?php echo htmlspecialchars($row['requestor_name']); ?></small>
                                    </td>
                                    <td>
                                        <code style="background: #f1f5f9; padding: 3px 6px; border-radius: 4px; font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($row['item_name']); ?></code>
                                        <?php if (!empty($row['reason']) && $admin_tab === 'rejected'): ?>
                                            <br><small style="color:#dc2626; display:block; margin-top:4px;"><strong>Reason:</strong> <?php echo htmlspecialchars($row['reason']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="color: #dc2626; font-weight: 700;"><?php echo htmlspecialchars($row['qty']); ?></span> <small><?php echo htmlspecialchars($row['unit']); ?></small></td>
                                    <td><small>Verified by:</small><br><strong><?php echo htmlspecialchars($row['daa_approved_by'] ?? 'N/A'); ?></strong></td>
                                    <td>
                                        <span class="<?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($admin_tab === 'pending_action'): ?>
                                            <div class="action-btn-group">
                                                <a href="process_admin_disposal.php?action=approve&id=<?php echo $row['disposal_id']; ?>" class="btn-dispose">Confirm</a>
                                                <button class="btn-reject-trigger" onclick="toggleRejectRow(<?php echo $row['disposal_id']; ?>)">Reject</button>
                                            </div>
                                        <?php else: ?>
                                            <a href="print_disposed.php?id=<?php echo $row['disposal_id']; ?>" target="_blank" class="btn-print-receipt">🖨️ Print Receipt</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr id="reject-row-<?php echo $row['disposal_id']; ?>" style="display: none;">
                                    <td colspan="7" style="background-color: rgba(241,245,249,0.5); padding: 10px 20px;">
                                        <form method="POST" action="process_admin_disposal.php" class="reject-tray">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?php echo $row['disposal_id']; ?>">
                                            <input type="text" name="reason" class="reject-input" placeholder="Type structural reason for denying this disposal request..." required>
                                            <button type="submit" class="btn-reject-submit">Submit Rejection Rollback</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="7" style="padding: 40px; text-align: center; color: #94a3b8;">No entries waiting layout pipeline execution.</td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
function toggleRejectRow(id) {
    const targetRow = document.getElementById(`reject-row-${id}`);
    if (targetRow.style.display === 'none') {
        targetRow.style.display = 'table-row';
    } else {
        targetRow.style.display = 'none';
    }
}
</script>
<script src="admindashboard.js?v=<?= time(); ?>" defer></script>
</body>
</html>