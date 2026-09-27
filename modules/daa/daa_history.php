<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Secure the route - allow access to Admin and DAA accounts
allow_roles(['inst_admin', 'daa', 'DAA']);

$currentUserId = $_SESSION['user_id'] ?? 0;
$currentUserRole = $_SESSION['role'] ?? '';

// Theme Settings
$settingsQuery = $conn->query("SELECT theme FROM user_settings WHERE user_id = " . (int)$currentUserId . " LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0) ? $settingsQuery->fetch_assoc() : ['theme' => 'light'];

// --- DETERMINING USER AFFILIATION ACCESS VISIBILITY TARGETS ---
$targetAffiliation = ($currentUserRole === 'DAA' || $currentUserRole === 'daa') ? 'Academic' : 'Non-Academic';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DAA Action History</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        .table-container { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 20px; }
        .table { width: 100%; border-collapse: collapse; text-align: left; }
        .table th { background: #475569; color: white; padding: 12px; font-size: 14px; }
        .table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; color: #334155; vertical-align: middle; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; display: inline-block; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-success { background: #e0e7ff; color: #3730a3; }
        .reason-box { font-size: 12px; color: #dc2626; background: #fff5f5; padding: 6px 10px; border-radius: 6px; border: 1px solid #fee2e2; font-weight: 500; display: inline-block; }

        /* --- Clickable Compact Item Preview Card --- */
        .clickable-item-box {
            background: #ffffff; 
            border: 1px solid #cbd5e1; 
            border-radius: 6px; 
            padding: 8px 12px; 
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
            cursor: pointer;
            min-width: 240px;
            max-width: 280px;
            transition: all 0.2s ease;
        }
        .clickable-item-box:hover {
            border-color: #3b82f6;
            background-color: #f8fafc;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .inline-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-text-truncated {
            font-weight: 600; 
            color: #1e293b;
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis;
            max-width: 170px;
        }

        .qty-badge-pill {
            font-size: 11px; 
            color: #0369a1; 
            font-weight: 700; 
            background: #e0f2fe; 
            padding: 2px 6px; 
            border-radius: 4px;
            white-space: nowrap;
        }

        .view-more-hint {
            display: block;
            text-align: center;
            font-size: 10px;
            color: #2563eb;
            font-weight: bold;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- Modal Layout Structures --- */
        .items-modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
        .items-modal-content { background-color: #ffffff; margin: 12% auto; padding: 24px; border: 1px solid #cbd5e1; width: 90%; max-width: 520px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); animation: modalFadeIn 0.2s ease-out; }
        @keyframes modalFadeIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 16px; }
        .modal-header h3 { margin: 0; font-size: 17px; color: #0f172a; font-weight: 700; }
        .modal-close-btn { background: none; border: none; font-size: 24px; font-weight: bold; color: #64748b; cursor: pointer; line-height: 1; }
        .modal-close-btn:hover { color: #0f172a; }
        
        /* --- Matches the uploaded screenshot stylings perfectly --- */
        .modal-list-wrapper {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .modal-item-row-card { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 14px 16px; 
            background: #ffffff; 
            border-radius: 8px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .modal-item-left {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        .modal-qty-red-badge {
            font-size: 12px; 
            color: #991b1b; 
            font-weight: 700; 
            background: #fef2f2; 
            padding: 4px 12px; 
            border-radius: 6px; 
            white-space: nowrap;
        }
    </style>
</head>
<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">
<div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>
    <main class="main-content">
        <header class="header">
            <h1>Processing History</h1>
            <p style="color: #64748b;">Archived logs of processed supply records under your <strong><?= htmlspecialchars($targetAffiliation) ?> Scope</strong>.</p>
        </header>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tracking ID</th>
                        <th>Requestor Name</th>
                        <th>Department</th>
                        <th>Item Details (Description | Qty | Unit)</th>
                        <th>Status Verdict</th>
                        <th>Reason for Rejection / Remarks</th>
                        <th>Processed By</th>
                        <th>Date Processed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "
                        (SELECT 
                            r.request_id AS tracking_id, 
                            r.requester_name AS name_of_requestor, 
                            r.requester_department AS dept_name, 
                            r.item_name, 
                            r.qty_requested AS quantity, 
                            'pcs' AS unit_name,
                            r.status, 
                            r.remarks AS rej_reason,
                            'Authorized Reviewer' AS reviewer,
                            r.requested_at AS processed_date
                         FROM requests r
                         JOIN users u ON (
                            r.requester_name = CONCAT(u.first_name, ' ', u.last_name) OR 
                            r.requester_name = CONCAT(u.first_name, ' ', u.middle_name, ' ', u.last_name) OR 
                            r.requester_name = u.email
                         )
                         WHERE LOWER(r.status) IN ('approved', 'rejected', 'in house', 'delivered') AND u.affiliation = ?)
                        
                        UNION ALL
                        
                        (SELECT 
                            i.issuance_id AS tracking_id, 
                            i.requestor_name AS name_of_requestor, 
                            i.department AS dept_name, 
                            i.item_name, 
                            i.issued_qty AS quantity, 
                            'pcs' AS unit_name,
                            'Approved' AS status, 
                            i.remarks AS rej_reason,
                            COALESCE(i.approved_by, i.issued_by) AS reviewer,
                            i.issued_at AS processed_date
                         FROM issuances i
                         JOIN users u ON (
                            i.requestor_name = CONCAT(u.first_name, ' ', u.last_name) OR 
                            i.requestor_name = CONCAT(u.first_name, ' ', u.middle_name, ' ', u.last_name) OR 
                            i.requestor_name = u.email
                         )
                         WHERE u.affiliation = ?)
                        
                        ORDER BY tracking_id DESC";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ss", $targetAffiliation, $targetAffiliation);
                    $stmt->execute();
                    $queryResult = $stmt->get_result();
                                                                                                        
                    if ($queryResult && $queryResult->num_rows > 0) {
                        while ($row = $queryResult->fetch_assoc()) {
                            $rawStatus = strtolower(trim($row['status'] ?? ''));
                            
                            if ($rawStatus === 'rejected') {
                                $badgeClass = 'badge-rejected';
                                $displayStatus = 'Rejected';
                            } elseif ($rawStatus === 'in house') {
                                $badgeClass = 'badge-info';
                                $displayStatus = 'In House';
                            } elseif ($rawStatus === 'delivered') {
                                $badgeClass = 'badge-success';
                                $displayStatus = 'Delivered';
                            } else {
                                $badgeClass = 'badge-approved';
                                $displayStatus = 'Approved';
                            }
                            
                            $trackingId = $row['tracking_id'];
                            $dept       = $row['dept_name'] ?? 'N/A';
                            $qty        = $row['quantity'] ?? 0;
                            $unit       = $row['unit_name'] ?? 'pcs';
                            $reqName    = $row['name_of_requestor'] ?? 'Unknown User';
                            $itemName   = $row['item_name'] ?? 'Unknown Item';
                            $processor  = !empty($row['reviewer']) ? $row['reviewer'] : 'Authorized Reviewer';
                            
                            $dateProcessed = 'N/A';
                            if (!empty($row['processed_date'])) {
                                $dateProcessed = date('M d, Y h:i A', strtotime($row['processed_date']));
                            }
                            
                            // Safe injection values for modal transmission strings
                            $escapedItemName = htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8');
                            $escapedUnit = htmlspecialchars($unit, ENT_QUOTES, 'UTF-8');
                            $escapedQty = htmlspecialchars($qty, ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td>#<?php echo $trackingId; ?></td>
                                <td><strong><?php echo htmlspecialchars($reqName); ?></strong></td>
                                <td><?php echo htmlspecialchars($dept); ?></td>
                                <td>
                                    <div class="clickable-item-box" onclick="openItemModal('<?php echo $escapedItemName; ?>', '<?php echo $escapedQty; ?>', '<?php echo $escapedUnit; ?>')" title="Click to view full description">
                                        <div class="inline-item-row">
                                            <span class="item-text-truncated">📂 <?php echo htmlspecialchars($itemName); ?></span>
                                            <span class="qty-badge-pill"><?php echo htmlspecialchars($qty . ' ' . $unit); ?></span>
                                        </div>
                                        <span class="view-more-hint">(Expand Details)</span>
                                    </div>
                                </td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $displayStatus; ?></span></td>
                                <td>
                                    <?php
                                    if (!empty($row['rej_reason'])) {
                                        $isRejected = ($rawStatus === 'rejected');
                                        echo "<div class='" . ($isRejected ? 'reason-box' : '') . "' style='" . (!$isRejected ? 'font-size:12px; color:#475569;' : '') . "'>" . htmlspecialchars($row['rej_reason']) . "</div>";
                                    } else {
                                        echo "<span style='color: #94a3b8;'>—</span>";
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($processor); ?></td>
                                <td><?php echo $dateProcessed; ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align:center; padding: 40px; color:#94a3b8;'>No processed historical logs found under your division filter.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="itemDetailModal" class="items-modal">
    <div class="items-modal-content">
        <div class="modal-header">
            <h3>Item Allocation Specifications</h3>
            <button class="modal-close-btn" onclick="closeItemModal()">&times;</button>
        </div>
        <div id="modalItemsContainer" class="modal-list-wrapper">
            </div>
    </div>
</div>

<script>
function openItemModal(itemNamesString, qtyString, unitNamesString) {
    const container = document.getElementById('modalItemsContainer');
    container.innerHTML = ''; // Reset modal view stream

    // Split items if comma-delimited strings are stored in database columns
    const namesArray = itemNamesString.split(',').map(s => s.trim());
    const qtyArray   = qtyString.split(',').map(s => s.trim());
    const unitArray  = unitNamesString.split(',').map(s => s.trim());

    namesArray.forEach((name, index) => {
        if (name === '') return;

        // Ensure array alignment pairings fall back safely if values don't match index counts
        const exactQty  = qtyArray[index]  || qtyArray[0] || '1';
        const exactUnit = unitArray[index] || unitArray[0] || 'pcs';

        // Build individual element cards to match the uploaded screenshot layout
        const rowCard = document.createElement('div');
        rowCard.className = 'modal-item-row-card';

        rowCard.innerHTML = `
            <div class="modal-item-left">
                <span>📁</span>
                <span>${name}</span>
            </div>
            <div class="modal-qty-red-badge">
                ${exactQty} ${exactUnit}
            </div>
        `;
        container.appendChild(rowCard);
    });

    document.getElementById('itemDetailModal').style.display = "block";
}

function closeItemModal() {
    document.getElementById('itemDetailModal').style.display = "none";
}

window.onclick = function(event) {
    const modal = document.getElementById('itemDetailModal');
    if (event.target === modal) {
        modal.style.display = "none";
    }
}
</script>
</body>
<script src="admindashboard.js" defer></script>
</html>