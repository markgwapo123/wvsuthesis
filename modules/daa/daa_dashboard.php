<?php
// Safe session initialization to prevent "session already active" notice
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';
allow_roles(['inst_admin', 'DAA']); 

$currentUserId = $_SESSION['user_id'] ?? 0;
$currentUserRole = $_SESSION['role'] ?? '';

// Theme Settings
$settingsQuery = $conn->query("SELECT theme FROM user_settings WHERE user_id = " . (int)$currentUserId . " LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0) ? $settingsQuery->fetch_assoc() : ['theme' => 'light'];

// --- DETERMINING USER AFFILIATION ACCESS VISIBILITY TARGETS ---
// DAA sees 'Academic' users. inst_admin sees 'Non-Academic' users.
$targetAffiliation = ($currentUserRole === 'DAA') ? 'Academic' : 'Non-Academic';

// --- FETCH DASHBOARD STATS WITH FILTERED ACCESS CONTROL ---

// 1. Filtered Pending Supply Requests from 'orders' table joined with 'users'
$stmtPending = $conn->prepare("SELECT COUNT(*) as total FROM orders o JOIN users u ON (o.requestor_name = CONCAT(u.first_name, ' ', u.last_name) OR o.requestor_name = CONCAT(u.first_name, ' ', u.middle_name, ' ', u.last_name) OR o.requestor_name = u.email) WHERE o.status = 'Pending' AND u.affiliation = ?");
$stmtPending->bind_param("s", $targetAffiliation);
$stmtPending->execute();
$pendingCount = $stmtPending->get_result()->fetch_assoc()['total'];

// 2. Filtered Pending Disposals from 'disposal_requests' table joined with 'users'
$stmtDisposal = $conn->prepare("SELECT COUNT(*) as total FROM disposal_requests d JOIN users u ON (d.requestor_name = CONCAT(u.first_name, ' ', u.last_name) OR d.requestor_name = CONCAT(u.first_name, ' ', u.middle_name, ' ', u.last_name) OR d.requestor_name = u.email) WHERE d.status = 'Pending DAA' AND u.affiliation = ?");
$stmtDisposal->bind_param("s", $targetAffiliation);
$stmtDisposal->execute();
$disposalCount = $stmtDisposal->get_result()->fetch_assoc()['total'];

// 3. Expiring Items (within 30 days) - System Global
$expiringCount = $conn->query("SELECT COUNT(*) as total FROM inventory WHERE expiration_date IS NOT NULL AND DATEDIFF(expiration_date, CURDATE()) <= 30 AND DATEDIFF(expiration_date, CURDATE()) >= 0")->fetch_assoc()['total'];

// 4. Expired Items - System Global
$expiredCount = $conn->query("SELECT COUNT(*) as total FROM inventory WHERE expiration_date IS NOT NULL AND expiration_date < CURDATE()")->fetch_assoc()['total'];

// 5. All Stocks (Total Quantity) - System Global
$stockCount = $conn->query("SELECT SUM(quantity) as total FROM inventory")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DAA Dashboard | Supply Office</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        :root {
            --primary: #2563eb;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
            --purple: #7c3aed;
            --bg: #f8fafc;
            --card: #ffffff;
        }

        .main-content { background: var(--bg); padding: 30px; font-family: 'Inter', sans-serif; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card);
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }

        .stat-card h3 { font-size: 13px; color: #64748b; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .value { font-size: 26px; font-weight: 800; color: #1e293b; margin: 10px 0; }
        .stat-card .indicator { font-size: 11px; font-weight: 600; }

        .section-container {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            margin-bottom: 30px;
        }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-size: 17px; margin: 0; color: #1e293b; }

        .btn-approve { background: #dcfce7; color: #166534; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-reject { background: #fee2e2; color: #991b1b; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; margin-left: 5px; }
        
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 14px; }
        .reason-text { color: #64748b; font-size: 12px; display: block; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">

<div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header" style="margin-bottom: 25px;">
            <h1 style="font-size: 26px; font-weight: 800; color: #1e293b; margin:0;">DAA Control Center</h1>
            <p style="color: #64748b; margin: 5px 0 0 0;">Viewing requests for: <strong><?= htmlspecialchars($targetAffiliation) ?> Scope</strong></p>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Pending Orders</h3>
                <div class="value"><?= number_format($pendingCount) ?></div>
                <div class="indicator" style="color: var(--primary);">Filtered Requests</div>
            </div>
            <div class="stat-card">
                <h3>Pending Disposals</h3>
                <div class="value" style="color: var(--purple);"><?= number_format($disposalCount) ?></div>
                <div class="indicator" style="color: var(--purple);">Awaiting Sign-off</div>
            </div>
            <div class="stat-card">
                <h3>Expiring Items</h3>
                <div class="value"><?= number_format($expiringCount) ?></div>
                <div class="indicator" style="color: var(--warning);">Within 30 Days</div>
            </div>
            <div class="stat-card">
                <h3>Total Inventory</h3>
                <div class="value"><?= number_format($stockCount) ?></div>
                <div class="indicator" style="color: var(--success);">Current Stocks</div>
            </div>
            <div class="stat-card">
                <h3>Expired</h3>
                <div class="value" style="color: var(--danger);"><?= number_format($expiredCount) ?></div>
                <div class="indicator" style="color: var(--danger);">Action Required</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
            
            <div style="display: flex; flex-direction: column;">
                <div class="section-container">
                    <div class="section-header">
                        <h2>Recent Pending Supply Orders (<?= htmlspecialchars($targetAffiliation) ?>)</h2>
                        <a href="daa_process_request.php" style="font-size: 13px; color: var(--primary); text-decoration: none; font-weight: 600;">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Requestor</th>
                                <th>Department</th>
                                <th>Item Name</th>
                                <th>Qty</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Updated robust string join matching check to evaluate spacing variations, middle names, or unique username strings
                            $stmtOrderList = $conn->prepare("SELECT o.order_id, o.requestor_name, o.department, o.item_name, o.qty FROM orders o JOIN users u ON (o.requestor_name = CONCAT(u.first_name, ' ', u.last_name) OR o.requestor_name = CONCAT(u.first_name, ' ', u.middle_name, ' ', u.last_name) OR o.requestor_name = u.email) WHERE o.status = 'Pending' AND u.affiliation = ? ORDER BY o.order_id DESC LIMIT 5");
                            $stmtOrderList->bind_param("s", $targetAffiliation);
                            $stmtOrderList->execute();
                            $orderResult = $stmtOrderList->get_result();
                            
                            if ($orderResult && $orderResult->num_rows > 0) {
                                while($ord = $orderResult->fetch_assoc()){
                                    echo "
                                    <tr>
                                        <td style='font-weight: 600;'>".htmlspecialchars($ord['requestor_name'])."</td>
                                        <td><span style='color: #64748b; font-size: 13px;'>".htmlspecialchars($ord['department'])."</span></td>
                                        <td>".htmlspecialchars($ord['item_name'])."</td>
                                        <td><strong>{$ord['qty']}</strong></td>
                                        <td>
                                            <button class='btn-approve' onclick='handleRequest({$ord['order_id']}, \"approve\")'>Approve</button>
                                            <button class='btn-reject' onclick='handleRequest({$ord['order_id']}, \"reject\")'>Reject</button>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' style='padding: 20px; text-align: center; color: #94a3b8;'>No pending supply orders found for your department scope.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="section-container">
                    <div class="section-header">
                        <h2>Recent Pending Disposal Requests (<?= htmlspecialchars($targetAffiliation) ?>)</h2>
                        <a href="daa_process_disposals.php" style="font-size: 13px; color: var(--purple); text-decoration: none; font-weight: 600;">View All Disposals</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Requestor</th>
                                <th>Department</th>
                                <th>Item Name</th>
                                <th>Qty</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Updated safe connection string validation check fallback
                            $stmtDispList = $conn->prepare("SELECT d.disposal_id, d.requestor_name, d.department, d.item_name, d.qty, d.reason FROM disposal_requests d JOIN users u ON (d.requestor_name = CONCAT(u.first_name, ' ', u.last_name) OR d.requestor_name = CONCAT(u.first_name, ' ', u.middle_name, ' ', u.last_name) OR d.requestor_name = u.email) WHERE d.status = 'Pending DAA' AND u.affiliation = ? ORDER BY d.disposal_id DESC LIMIT 5");
                            $stmtDispList->bind_param("s", $targetAffiliation);
                            $stmtDispList->execute();
                            $dispResult = $stmtDispList->get_result();
                            
                            if ($dispResult && $dispResult->num_rows > 0) {
                                while($disp = $dispResult->fetch_assoc()){
                                    echo "
                                    <tr>
                                        <td style='font-weight: 600;'>".htmlspecialchars($disp['requestor_name'])."</td>
                                        <td><span style='color: #64748b; font-size: 13px;'>".htmlspecialchars($disp['department'])."</span></td>
                                        <td>".htmlspecialchars($disp['item_name'])."</td>
                                        <td><span style='color: #dc3545; font-weight:bold;'>{$disp['qty']}</span></td>
                                        <td><span class='reason-text' title='".htmlspecialchars($disp['reason'])."'>".htmlspecialchars($disp['reason'])."</span></td>
                                        <td>
                                            <button class='btn-approve' style='background:#f3e8ff; color:#6b21a8;' onclick='handleDisposal({$disp['disposal_id']}, \"approve\")'>Authorize</button>
                                            <button class='btn-reject' onclick='handleDisposal({$disp['disposal_id']}, \"reject\")'>Deny</button>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='padding: 20px; text-align: center; color: #94a3b8;'>No pending disposal requests found for your department scope.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-container" style="align-self: start;">
                <div class="section-header">
                    <h2>Stock Expiry Alerts</h2>
                </div>
                <?php
                $alertQuery = $conn->query("SELECT name, expiration_date FROM inventory WHERE expiration_date IS NOT NULL AND DATEDIFF(expiration_date, CURDATE()) <= 30 ORDER BY expiration_date ASC LIMIT 6");
                if ($alertQuery && $alertQuery->num_rows > 0) {
                    while($item = $alertQuery->fetch_assoc()){
                        $days = ceil((strtotime($item['expiration_date']) - time()) / 86400);
                        $statusCls = ($days < 0) ? 'background: #fee2e2; color: #991b1b;' : 'background: #fef3c7; color: #92400e;';
                        echo "
                        <div style='padding: 10px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;'>
                            <div style='font-size: 13px; font-weight: 600;'>".htmlspecialchars($item['name'])."</div>
                            <div class='badge' style='$statusCls'>".($days < 0 ? 'Expired' : $days.' Days')."</div>
                        </div>";
                    }
                } else {
                    echo "<p style='font-size: 13px; color: #64748b; text-align: center;'>All items are stable.</p>";
                }
                ?>
                <button onclick="location.href='expiring_item.php'" style="width: 100%; padding: 10px; margin-top: 10px; border: 1px dashed #cbd5e1; background: transparent; cursor: pointer; color: #64748b; font-size: 12px; border-radius: 8px;">Generate Detailed Report</button>
            </div>
        </div>

    </main>
</div>
<script src="admindashboard.js" defer></script>
<script>
function handleRequest(id, action) {
    if (action === 'approve') {
        if (confirm("Are you sure you want to approve and forward this order request to Admin?")) {
            window.location.href = `process_daa_request.php?id=${id}&action=approve`;
        }
    } else if (action === 'reject') {
        let reason = prompt("Please state your reason for rejecting this supply order:");
        if (reason === null) return; 
        if (reason.trim() === "") {
            alert("You must provide a reason for rejection.");
            return;
        }
        submitHiddenForm('process_daa_request.php', id, 'reject', reason);
    }
}

function handleDisposal(id, action) {
    if (action === 'approve') {
        if (confirm("Verify Asset Condition? Approving routes this item directly to Admin/Sub Admin control for complete disposal removal.")) {
            window.location.href = `process_daa_disposal.php?id=${id}&action=approve`;
        }
    } else if (action === 'reject') {
        let reason = prompt("State reason for denying this asset disposal request:");
        if (reason === null) return;
        if (reason.trim() === "") {
            alert("A reason is required to reject asset disposal.");
            return;
        }
        submitHiddenForm('process_daa_disposal.php', id, 'reject', reason);
    }
}

function submitHiddenForm(targetUrl, id, action, reason) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = targetUrl;
    
    const fields = { id: id, action: action, reason: reason };
    for (const key in fields) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>