<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Access Control
allow_roles(['admin', 'DAA', 'inst_admin']); 

$currentUserId = $_SESSION['user_id'] ?? 0;
$currentUserRole = $_SESSION['role'] ?? ''; // Capture user role ('DAA', 'inst_admin', or 'admin')

// Theme Configuration
$settingsQuery = $conn->query("SELECT theme FROM user_settings WHERE user_id = " . (int)$currentUserId . " LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0) ? $settingsQuery->fetch_assoc() : ['theme' => 'light'];

// Determine active tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// 1. Base status & role filtering built dynamically in PHP
$whereConditions = [];

if ($tab === 'approved') {
    $whereConditions[] = "dr.status = 'Approved by DAA'";
} elseif ($tab === 'history') {
    $whereConditions[] = "(dr.status = 'Disposed' OR dr.status = 'Rejected')";
} else {
    // Tab is 'pending'
    if ($currentUserRole === 'DAA') {
        $whereConditions[] = "dr.status = 'Pending DAA'";
    } elseif ($currentUserRole === 'inst_admin') {
        $whereConditions[] = "dr.status = 'Pending Admin'";
    } else {
        // Master admin can view either pipeline variant
        $whereConditions[] = "(dr.status = 'Pending DAA' OR dr.status = 'Pending Admin')";
    }
}

// 2. Strict global role-based isolation mapping to user table affiliation
if ($currentUserRole === 'DAA') {
    $whereConditions[] = "LOWER(TRIM(u.affiliation)) = 'academic'";
} elseif ($currentUserRole === 'inst_admin') {
    $whereConditions[] = "LOWER(TRIM(u.affiliation)) = 'non-academic'";
}

// Stitch conditions into query string safely
$whereSQL = "WHERE " . implode(' AND ', $whereConditions);

// Fetch filtered logs using a smart fuzzy match to tolerate middle names/initials safely
$disposal_sql = "SELECT dr.disposal_id, dr.requestor_name, dr.department, dr.item_name, dr.qty, dr.unit, dr.reason, dr.status, dr.created_at 
                 FROM disposal_requests dr
                 INNER JOIN users u ON (
                    dr.requestor_name LIKE CONCAT('%', TRIM(u.first_name), '%') 
                    AND dr.requestor_name LIKE CONCAT('%', TRIM(u.last_name), '%')
                 )
                 $whereSQL 
                 ORDER BY dr.disposal_id DESC";
                 
$disposal_result = $conn->query($disposal_sql);
$disposal_count = ($disposal_result) ? $disposal_result->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Disposal Logs</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        .z-main-wrapper {
            padding: 30px !important;
            background-color: #f8fafc !important;
            min-height: 100vh !important;
            box-sizing: border-box !important;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        }
        .z-page-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 24px !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
        }
        .z-nav-tabs {
            display: flex !important;
            gap: 8px !important;
            margin-bottom: 20px !important;
            border-bottom: 2px solid #e2e8f0 !important;
            padding-bottom: 8px !important;
        }
        .z-tab-btn {
            text-decoration: none !important;
            padding: 10px 18px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            color: #64748b !important;
            border-radius: 6px !important;
            background: transparent !important;
        }
        .z-tab-btn:hover {
            background: #f1f5f9 !important;
            color: #0f172a !important;
        }
        .z-tab-btn.z-active {
            background: #eff6ff !important;
            color: #2563eb !important;
            border-bottom: 2px solid #2563eb !important;
        }
        
        .z-table-container {
            width: 100% !important;
            overflow-x: auto !important;
            margin-top: 15px !important;
        }
        table.z-table {
            width: 100% !important;
            border-collapse: collapse !important;
            display: table !important;
        }
        table.z-table th {
            background-color: #f1f5f9 !important;
            color: #475569 !important; 
            font-weight: 700 !important;
            text-align: left !important;
            padding: 14px 12px !important;
            font-size: 13px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #cbd5e1 !important;
        }
        table.z-table td {
            padding: 14px 12px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            color: #1e293b !important; 
            font-size: 14px !important;
            background-color: #ffffff !important;
            vertical-align: middle !important;
        }
        
        .clickable-items-box {
            background: #ffffff; 
            border: 1px solid #cbd5e1; 
            border-radius: 6px; 
            padding: 8px 12px; 
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .clickable-items-box:hover {
            border-color: #3b82f6;
            background-color: #f8fafc;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .inline-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .inline-item-row:last-child {
            border-bottom: none;
        }

        .more-badge {
            display: block;
            text-align: center;
            font-size: 11px;
            color: #2563eb;
            font-weight: bold;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .z-badge {
            padding: 4px 10px !important;
            border-radius: 9999px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            display: inline-block !important;
            background-color: #fef3c7 !important;
            color: #92400e !important;
        }
        .z-btn {
            border: none !important;
            padding: 7px 14px !important;
            border-radius: 6px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            font-size: 13px !important;
            display: inline-block !important;
        }
        .z-btn-approve { background-color: #dcfce7 !important; color: #166534 !important; }
        .z-btn-reject { background-color: #fee2e2 !important; color: #991b1b !important; margin-left: 6px !important; }

        .z-alert-msg { padding: 12px 16px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .z-alert-success { background: #dcfce7; color: #14532d; border: 1px solid #bbf7d0; }
        
        .inline-reject-wrap { display: none; margin-top: 10px; padding: 10px; background: #fff5f5; border: 1px solid #fee2e2; border-radius: 6px; }
        .inline-reject-input { padding: 6px; font-size: 12px; width: 170px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        .inline-reject-submit { background-color: #dc3545; color: white; border: none; padding: 6px 10px; font-size: 12px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 5px; display: block; width: 100%; }
        .inline-reject-submit:hover { background-color: #bd2130; }
        .error-hint { color: #dc3545; font-size: 11px; font-weight: bold; display: none; margin-top: 4px; }

        .items-modal {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
        }
        .items-modal-content {
            background-color: #ffffff;
            margin: 10% auto;
            padding: 24px;
            border: 1px solid #cbd5e1;
            width: 90%;
            max-width: 550px;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalFadeIn 0.25s ease-out;
        }
        @keyframes modalFadeIn {
            from { transform: translateY(-15px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #0f172a;
            font-weight: 700;
        }
        .modal-close-btn {
            background: none;
            border: none;
            font-size: 24px;
            font-weight: bold;
            color: #64748b;
            cursor: pointer;
            line-height: 1;
        }
        .modal-close-btn:hover {
            color: #0f172a;
        }
        .modal-list {
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 4px;
        }
        .modal-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 6px;
            margin-bottom: 6px;
            border: 1px solid #f1f5f9;
        }
        .modal-item-row:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body class="<?php echo ($settings['theme'] == 'dark') ? 'dark-mode' : ''; ?>">

<div class="dashboard" style="display: flex !important;">
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="z-main-wrapper" style="flex: 1 !important; display: block !important;">
        <header style="margin-bottom: 25px; display: block !important;">
            <h1 style="font-size: 26px; font-weight: 800; color: #1e293b; margin: 0 0 5px 0;">Global Disposal Logs</h1>
            <p style="color: #64748b; margin: 0; font-size: 14px;">Reviewing and authorizing stock asset disposal pipelines across all system modules.</p>
        </header>

        <div class="z-page-card" style="display: block !important;">
            <div class="z-nav-tabs">
                <a href="daa_process_disposals.php?tab=pending" class="z-tab-btn <?php echo ($tab === 'pending') ? 'z-active' : ''; ?>">
                    📥 Pending <?php echo ($currentUserRole === 'inst_admin') ? 'Admin' : 'DAA'; ?> Approval
                </a>
                <a href="daa_process_disposals.php?tab=approved" class="z-tab-btn <?php echo ($tab === 'approved') ? 'z-active' : ''; ?>">🕒 Routing to Admin Office</a>
                <a href="daa_process_disposals.php?tab=history" class="z-tab-btn <?php echo ($tab === 'history') ? 'z-active' : ''; ?>">🗄️ Completed History Logs</a>
            </div>

            <?php if (isset($_GET['status']) && $_GET['status'] === 'success_approved'): ?>
                <div class="z-alert-msg z-alert-success">✅ Disposal request authorized successfully! It has been forwarded to the Admin/Sub Admin dashboard.</div>
            <?php elseif (isset($_GET['status']) && $_GET['status'] === 'success_rejected'): ?>
                <div class="z-alert-msg z-alert-success">✅ Disposal request has been successfully denied.</div>
            <?php endif; ?>

            <div class="z-table-container">
                <table class="z-table">
                    <thead>
                        <tr>
                            <th width="10%">ID</th>
                            <th width="15%">Date Submitted</th>
                            <th width="20%">Requestor Info</th>
                            <th>Disposal Items List (Item Description | Qty | Unit)</th>
                            <th width="20%">Reason Given</th>
                            <th width="12%">Status</th>
                            <?php if($tab === 'pending'): ?><th width="15%">Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($disposal_count > 0) {
                            $disposal_result->data_seek(0);
                            while($disposal_row = $disposal_result->fetch_assoc()) {
                                
                                $disposal_id  = $disposal_row['disposal_id'];
                                $requestor    = $disposal_row['requestor_name'];
                                $department   = $disposal_row['department'];
                                $item_name    = $disposal_row['item_name'];
                                $qty          = $disposal_row['qty'];
                                $unit         = $disposal_row['unit'];
                                $reason       = $disposal_row['reason'];
                                $status       = $disposal_row['status'];
                                $created_at   = $disposal_row['created_at'];

                                $createdDate = date("M d, Y h:i A", strtotime($created_at));

                                $arrNames = array_values(array_filter(explode(', ', $item_name)));
                                $arrQtys  = array_values(array_filter(explode(', ', $qty)));
                                $arrUnits = array_values(array_filter(explode(', ', $unit)));
                                $totalItemsCount = count($arrNames);

                                $fullDataArray = [];
                                for ($i = 0; $i < $totalItemsCount; $i++) {
                                    $fullDataArray[] = [
                                        'name' => htmlspecialchars(trim($arrNames[$i] ?? '')),
                                        'qty' => htmlspecialchars(trim($arrQtys[$i] ?? '1')),
                                        'unit' => htmlspecialchars(trim($arrUnits[$i] ?? ($arrUnits[0] ?? 'pcs')))
                                    ];
                                }
                                $jsonData = json_encode($fullDataArray, JSON_HEX_APOS | JSON_HEX_QUOT);
                                ?>
                                <tr>
                                    <td><code>#<?php echo (int)$disposal_id; ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($createdDate); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($requestor); ?></strong><br>
                                        <small style="color: #64748b;">Dept: <?php echo htmlspecialchars($department); ?></small>
                                    </td>
                                    <td>
                                        <div class="clickable-items-box" onclick='openItemsModal(<?php echo $jsonData; ?>, "<?php echo htmlspecialchars($requestor); ?>")' title="Click to view all items">
                                            <?php 
                                            $displayLimit = 2;
                                            for ($i = 0; $i < min($totalItemsCount, $displayLimit); $i++): 
                                                $currName = trim($arrNames[$i]);
                                                $currQty  = isset($arrQtys[$i])  ? trim($arrQtys[$i])  : '1';
                                                $currUnit = isset($arrUnits[$i]) ? trim($arrUnits[$i]) : (isset($arrUnits[0]) ? trim($arrUnits[0]) : 'pcs');
                                            ?>
                                                <div class="inline-item-row">
                                                    <span style="font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;">
                                                        📂 <?php echo htmlspecialchars($currName); ?>
                                                    </span>
                                                    <span style="font-size: 11px; color: #991b1b; font-weight: 700; background: #fef2f2; padding: 2px 6px; border-radius: 4px;">
                                                        <?php echo htmlspecialchars($currQty); ?> <?php echo htmlspecialchars($currUnit); ?>
                                                    </span>
                                                </div>
                                            <?php endfor; ?>

                                            <?php if ($totalItemsCount > $displayLimit): ?>
                                                <span class="more-badge">+ <?php echo ($totalItemsCount - $displayLimit); ?> more items (View All)</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; max-width: 260px; word-wrap: break-word; color: #475569; font-size: 13px;">
                                            <?php echo htmlspecialchars($reason); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="z-badge"><?php echo htmlspecialchars($status); ?></span>
                                    </td>
                                    <?php if($tab === 'pending'): ?>
                                    <td>
                                        <div style="display: flex; gap: 4px;">
                                            <button class="z-btn z-btn-approve" onclick="processAsset(event, <?php echo (int)$disposal_id; ?>, 'approve')">Authorize</button>
                                            <button class="z-btn z-btn-reject" onclick="processAsset(event, <?php echo (int)$disposal_id; ?>, 'reject')">Deny</button>
                                        </div>

                                        <div id="inline-reject-box-<?php echo (int)$disposal_id; ?>" class="inline-reject-wrap">
                                            <input type="text" id="reject-reason-<?php echo (int)$disposal_id; ?>" class="inline-reject-input" placeholder="Justification message...">
                                            <div id="error-hint-<?php echo (int)$disposal_id; ?>" class="error-hint">Reason string required!</div>
                                            <button class="inline-reject-submit" onclick="submitInlineReject(event, <?php echo (int)$disposal_id; ?>)">Confirm Deny</button>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="<?php echo ($tab === 'pending') ? '7' : '6'; ?>" style="padding: 45px; text-align: center; color: #94a3b8; font-weight: 500;">
                                    No records found matching this status pipeline.
                                </td>
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

<div id="itemsDetailModal" class="items-modal">
    <div class="items-modal-content">
        <div class="modal-header">
            <h3>Disposal Items - <span id="modalRequestorName"></span></h3>
            <button class="modal-close-btn" onclick="closeItemsModal()">&times;</button>
        </div>
        <div class="modal-list" id="modalItemsWrapper"></div>
    </div>
</div>

<script src="admindashboard.js?v=<?= time(); ?>" defer></script>
<script>
function openItemsModal(items, requestor) {
    document.getElementById('modalRequestorName').textContent = requestor;
    const wrapper = document.getElementById('modalItemsWrapper');
    wrapper.innerHTML = '';

    items.forEach(item => {
        const itemRow = document.createElement('div');
        itemRow.className = 'modal-item-row';
        itemRow.innerHTML = `
            <span style="font-weight: 600; color: #1e293b; font-size: 14px;">
                📂 ${item.name}
            </span>
            <span style="font-size: 13px; color: #991b1b; font-weight: 700; background: #fef2f2; padding: 4px 10px; border-radius: 6px;">
                ${item.qty} ${item.unit}
            </span>
        `;
        wrapper.appendChild(itemRow);
    });

    document.getElementById('itemsDetailModal').style.display = "block";
}

function closeItemsModal() {
    document.getElementById('itemsDetailModal').style.display = "none";
}

window.onclick = function(event) {
    const modal = document.getElementById('itemsDetailModal');
    if (event.target === modal) {
        modal.style.display = "none";
    }
}

function processAsset(e, id, action) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
    }

    if (action === 'approve') {
        window.location.href = `process_daa_disposal.php?id=${id}&action=approve`;
    } else if (action === 'reject') {
        const container = document.getElementById(`inline-reject-box-${id}`);
        if (container) {
            container.style.display = container.style.display === 'block' ? 'none' : 'block';
            if (container.style.display === 'block') {
                document.getElementById(`reject-reason-${id}`).focus();
            }
        }
    }
}

function submitInlineReject(e, id) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
    }

    const reasonInput = document.getElementById(`reject-reason-${id}`);
    const errorHint = document.getElementById(`error-hint-${id}`);
    const reason = reasonInput.value.trim();

    if (reason === "") {
        if (errorHint) errorHint.style.display = 'block';
        reasonInput.style.borderColor = '#dc3545';
        return;
    }

    if (errorHint) errorHint.style.display = 'none';

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'process_daa_disposal.php';
    
    const fields = { id: id, action: 'reject', reason: reason };
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