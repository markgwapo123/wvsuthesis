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
// DAA only sees 'Academic' users. inst_admin sees 'Non-Academic' users.
$targetAffiliation = ($currentUserRole === 'DAA' || $currentUserRole === 'daa') ? 'Academic' : 'Non-Academic';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Process Supply Requests</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        .table-container { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 20px; }
        .table { width: 100%; border-collapse: collapse; text-align: left; }
        .table th { background: #007bff; color: white; padding: 12px; font-size: 14px; }
        .table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; color: #334155; vertical-align: top; }
        .btn-approve { background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-reject { background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-left: 5px; }
        .btn-approve:hover, .btn-reject:hover { opacity: 0.9; }
        .remarks-text { font-style: italic; color: #64748b; font-size: 12px; }
        .alert-msg { padding: 12px; margin-bottom: 15px; border-radius: 6px; font-weight: bold; text-align: center; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        
        /* Interactive Clickable Item Cell */
        .clickable-items { 
            color: #007bff; 
            cursor: pointer; 
            font-weight: 500; 
            text-decoration: underline dotted;
        }
        .clickable-items:hover { 
            color: #0056b3; 
            background-color: #f1f5f9; 
            border-radius: 4px; 
        }

        /* Inline rejection form styling */
        .reject-action-container { display: none; margin-top: 10px; background: #fff5f5; padding: 8px; border: 1px solid #feb2b2; border-radius: 6px; }
        .reject-input { padding: 6px; font-size: 12px; width: 160px; border: 1px solid #cbd5e1; border-radius: 4px; margin-right: 4px; }
        .btn-submit-reject { background: #dc3545; color: white; border: none; padding: 6px 10px; font-size: 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-submit-reject:hover { background: #c53030; }

        /* Custom Modal / Pop-up Overlay for Multi-items Preview */
        .custom-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .custom-modal-content { background-color: #fff; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 20px; }
        .close-modal:hover { color: #000; }
        .modal-item-list { list-style: none; padding: 0; margin: 15px 0 0 0; }
        .modal-item-list li { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .modal-item-list li:last-child { border-bottom: none; }
    </style>
</head>
<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">
<div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>
    <main class="main-content">
        <header class="header">
            <h1>Pending Supply Requests</h1>
            <p style="color: #64748b;">Reviewing active incoming requests assigned under your <strong><?= htmlspecialchars($targetAffiliation) ?> Scope</strong>.</p>
        </header>

        <div class="table-container">
            <?php if (isset($_GET['status']) && $_GET['status'] === 'success_approved'): ?>
                <div class="alert-msg alert-success">Request successfully approved and updated!</div>
            <?php elseif (isset($_GET['status']) && $_GET['status'] === 'success_rejected'): ?>
                <div class="alert-msg alert-success">Request successfully rejected.</div>
            <?php endif; ?>

            <table class="table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Requestor</th>
                        <th>Department</th>
                        <th>Item Details (Click to view all)</th>
                        <th>User Remarks</th>
                        <th>Date Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Prepared SQL statement matching correct request entries dynamically against explicit user structural profiles
                    $stmt = $conn->prepare("SELECT r.* FROM requests r 
                        JOIN users u ON (
                            r.requester_name = CONCAT(u.first_name, ' ', u.last_name) OR 
                            r.requester_name = CONCAT(u.first_name, ' ', u.middle_name, ' ', u.last_name) OR 
                            r.requester_name = u.email
                        ) 
                        WHERE LOWER(r.status) = 'pending' AND u.affiliation = ? 
                        ORDER BY r.request_id DESC");
                    
                    $stmt->bind_param("s", $targetAffiliation);
                    $stmt->execute();
                    $queryResult = $stmt->get_result();
                                                                                                                        
                    if ($queryResult && $queryResult->num_rows > 0) {
                        while ($row = $queryResult->fetch_assoc()) {
                            $requestId = $row['request_id'];
                            $requester = htmlspecialchars($row['requester_name'] ?? 'Unknown');
                            $dept      = htmlspecialchars($row['requester_department'] ?? $row['department'] ?? 'N/A');
                            $remarks   = htmlspecialchars($row['remarks'] ?? 'None');
                            $dateString = !empty($row['requested_at']) ? $row['requested_at'] : 'now';
                            $requestDate = date('M d, Y h:i A', strtotime($dateString));

                            // Parsing records safely
                            $arrNames = !empty($row['item_name']) ? explode(', ', $row['item_name']) : [];
                            $arrQtys  = !empty($row['qty_requested']) ? explode(', ', $row['qty_requested']) : [];
                            $arrUnits = !empty($row['unit']) ? explode(', ', $row['unit']) : [];

                            $displayItems = [];
                            $totalCount = count($arrNames);
                            
                            for ($i = 0; $i < $totalCount; $i++) {
                                $q = isset($arrQtys[$i]) ? trim($arrQtys[$i]) : '0';
                                $u = isset($arrUnits[$i]) ? trim($arrUnits[$i]) : 'pcs';
                                $n = isset($arrNames[$i]) ? trim($arrNames[$i]) : 'Unknown Item';
                                $displayItems[] = "{$q}{$u} {$n}";
                            }

                            $summaryText = implode(', ', $displayItems);
                            
                            if (strlen($summaryText) > 60) {
                                $summaryText = substr($summaryText, 0, 57) . '...';
                            }

                            $jsonItems = json_encode($displayItems);

                            echo "
                            <tr>
                                <td>#{$requestId}</td>
                                <td><strong>{$requester}</strong></td>
                                <td>{$dept}</td>
                                <td class='clickable-items' onclick='showAllItemsModal({$requestId}, {$jsonItems})' title='Click to view complete item lines'>
                                    {$summaryText}
                                </td>
                                <td class='remarks-text'>{$remarks}</td>
                                <td>{$requestDate}</td>
                                <td>
                                    <div class='action-buttons'>
                                        <button class='btn-approve' onclick='handleAction({$requestId}, \"approve\")'>Approve</button>
                                        <button class='btn-reject' onclick='handleAction({$requestId}, \"reject\")'>Reject</button>
                                    </div>
                                    
                                    <div id='reject-container-{$requestId}' class='reject-action-container'>
                                        <input type='text' id='reject-reason-{$requestId}' class='reject-input' placeholder='Type rejection reason...'>
                                        <button class='btn-submit-reject' onclick='submitInlineReject({$requestId})'>Submit</button>
                                    </div>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align:center; padding: 40px; color:#94a3b8;'>There are no pending supply requests found under your department scope.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="itemsDisplayModal" class="custom-modal">
    <div class="custom-modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3 style="margin-top: 0; color: #004085;" id="modalTitle">Request Breakdown</h3>
        <p style="font-size: 12px; color: #64748b; margin-bottom: 10px;">Full structural breakdown list for this combined slip order lines:</p>
        <ul id="modalItemList" class="modal-item-list"></ul>
    </div>
</div>

<script src="admindashboard.js" defer></script>
<script>
function showAllItemsModal(id, itemsArray) {
    const modal = document.getElementById('itemsDisplayModal');
    const modalTitle = document.getElementById('modalTitle');
    const listContainer = document.getElementById('modalItemList');
    
    modalTitle.innerText = `Request #${id} Item Breakdown`;
    listContainer.innerHTML = ""; 

    itemsArray.forEach(item => {
        const li = document.createElement('li');
        li.innerHTML = `📌 <strong>${item}</strong>`;
        listContainer.appendChild(li);
    });

    modal.style.display = "block";
}

function closeModal() {
    document.getElementById('itemsDisplayModal').style.display = "none";
}

window.onclick = function(event) {
    const modal = document.getElementById('itemsDisplayModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

function handleAction(id, action) {
    if (action === 'approve') {
        window.location.href = `process_daa_request.php?id=${id}&action=approve`;
    } else if (action === 'reject') {
        const container = document.getElementById(`reject-container-${id}`);
        if (container) {
            container.style.display = container.style.display === 'block' ? 'none' : 'block';
            if (container.style.display === 'block') {
                document.getElementById(`reject-reason-${id}`).focus();
            }
        }
    }
}

function submitInlineReject(id) {
    const reasonInput = document.getElementById(`reject-reason-${id}`);
    const reason = reasonInput.value.trim();

    if (reason === "") {
        alert("A rejection reason is required."); 
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'process_daa_request.php';
    
    const data = { id: id, action: 'reject', reason: reason };
    for (const key in data) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = data[key];
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>