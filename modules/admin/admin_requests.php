<?php
session_start();
require_once __DIR__ . "/db_connect.php";

/* ======================
   AUTH CHECK (Admin & Subadmin)
====================== */
if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'subadmin'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

/* ======================
   FETCH REQUESTS (FILTERED: Excluded fully issued items)
   We check for status != 'Issued' (case-insensitive fallback) 
   AND ensure it doesn't show rows that are entirely made of '0' quantities.
====================== */
$sql = "
    SELECT
        request_id,
        requester_name,
        requester_department,
        item_name,
        unit,
        qty_requested,
        remarks,
        status,
        requested_at
    FROM requests
    WHERE LOWER(status) NOT IN ('issued', 'fully issued')
      AND qty_requested NOT REGEXP '^[0[:space:][:punct:]]+$'
    ORDER BY requested_at DESC
";

$requests = $conn->query($sql);
if (!$requests) {
    die("Database Error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Request Management</title>

<style>
body {
    font-family: Poppins, Arial, sans-serif;
    background: #f4f7fc;
    padding: 30px;
}

h2 {
    margin-bottom: 15px;
    color: #003366;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}

th {
    background: #003366;
    color: white;
    text-transform: uppercase;
    font-size: 13px;
    padding: 14px;
}

td {
    padding: 12px;
    border-bottom: 1px solid #e6e6e6;
    font-size: 14px;
    vertical-align: top;
}

tr:hover {
    background: #f0f6ff;
}

.action-container {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.btn-group {
    display: flex;
    gap: 5px;
}

a, .btn-action-reject {
    text-decoration: none;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 13px;
    display: inline-block;
    text-align: center;
    border: none;
}

/* Button Styles */
.issue { background: #17a2b8; color: white; }
.issue:hover { opacity: 0.85; }

.reject { background: #dc3545; color: white; cursor: pointer; }
.reject:hover { opacity: 0.85; }

/* Disabled State for Buttons */
.btn-disabled {
    background: #cbd5e1 !important;
    color: #64748b !important;
    cursor: not-allowed !important;
    pointer-events: none;
    opacity: 0.6;
}

.status-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.status-Pending { background: #fff3cd; color: #856404; }
.status-Approved { background: #d4edda; color: #155724; }
.status-Rejected { background: #f8d7da; color: #721c24; }
.status-Issued { background: #cbd5e1; color: #475569; }

.empty-row { text-align: center; font-weight: bold; padding: 20px; color: #777; }
.waiting-text { font-size: 11px; color: #64748b; display: block; font-style: italic; margin-top: 4px; }

/* Interactive Item Cell Formatting */
.clickable-items { 
    color: #003366; 
    cursor: pointer; 
    font-weight: 500; 
    text-decoration: underline dotted;
}
.clickable-items:hover { 
    color: #0056b3; 
    background-color: #f0f4f8;
    border-radius: 4px;
}

/* Inline Rejection Box */
.reject-box {
    display: none;
    margin-top: 5px;
    background: #fff5f5;
    padding: 8px;
    border: 1px solid #feb2b2;
    border-radius: 6px;
}
.reject-input {
    padding: 6px;
    font-size: 12px;
    width: 150px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    margin-right: 4px;
}
.btn-submit-reject {
    background: #dc3545;
    color: white;
    border: none;
    padding: 6px 10px;
    font-size: 12px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}

/* Modal Pop-up Styles */
.custom-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
.custom-modal-content { background-color: #fff; margin: 15% auto; padding: 22px; border: 1px solid #cbd5e1; width: 85%; max-width: 480px; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.15); }
.close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 20px; }
.close-modal:hover { color: #000; }
.modal-item-list { list-style: none; padding: 0; margin: 15px 0 0 0; }
.modal-item-list li { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }
.modal-item-list li:last-child { border-bottom: none; }
</style>
</head>

<body>

<h2>Request Management</h2>

<table>
<thead>
<tr>
    <th>Request ID</th>
    <th>Requester</th>
    <th>Department</th>
    <th>Item Detailed (Click to view)</th>
    <th>Status</th>
    <th>Requested At</th>
    <th>Action</th>
</tr>
</thead>

<tbody>
<?php if ($requests->num_rows > 0): ?>
    <?php while ($r = $requests->fetch_assoc()): 
        $requestId = $r['request_id'];
        $status    = $r['status'];

        // Split items out safely
        $arrNames = explode(', ', $r['item_name']);
        $arrQtys  = explode(', ', $r['qty_requested']);
        $arrUnits = explode(', ', $r['unit']);

        $displayItems = [];
        $totalCount   = count($arrNames);
        
        for ($i = 0; $i < $totalCount; $i++) {
            $q = isset($arrQtys[$i]) ? trim($arrQtys[$i]) : '0';
            $u = isset($arrUnits[$i]) ? trim($arrUnits[$i]) : 'pcs';
            $n = isset($arrNames[$i]) ? trim($arrNames[$i]) : 'Unknown Item';
            
            $displayItems[] = "{$q}{$u} {$n}";
        }

        $summaryText = implode(', ', $displayItems);
        if (strlen($summaryText) > 65) {
            $summaryText = substr($summaryText, 0, 62) . '...';
        }

        $jsonItems = json_encode($displayItems);
    ?>
        <tr>
            <td><strong>#<?= $requestId ?></strong></td>
            <td><strong><?= htmlspecialchars($r['requester_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['requester_department']) ?></td>
            
            <td class="clickable-items" onclick="openItemsModal(<?= $requestId ?>, <?= htmlspecialchars($jsonItems) ?>)">
                <?= htmlspecialchars($summaryText) ?>
            </td>

            <td>
                <span class="status-badge status-<?= htmlspecialchars($status) ?>">
                    <?= htmlspecialchars($status) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($r['requested_at']) ?></td>
            <td>
                <div class="action-container">
                    <div class="btn-group">
                        <?php if ($status === 'Approved'): ?>
                            <a class="issue" href="issuance_request.php?request_id=<?= $requestId ?>">Issue Item</a>
                            <button class="btn-action-reject reject" onclick="toggleRejectBox(<?= $requestId ?>)">Reject</button>
                        <?php else: ?>
                            <a class="issue btn-disabled" href="#" onclick="return false;">Issue Item</a>
                            <button class="btn-action-reject reject btn-disabled" disabled>Reject</button>
                        <?php endif; ?>
                    </div>

                    <div id="reject-box-<?= $requestId ?>" class="reject-box">
                        <input type="text" id="reason-<?= $requestId ?>" class="reject-input" placeholder="Type rejection reason...">
                        <button class="btn-submit-reject" onclick="submitReject(<?= $requestId ?>)">Submit</button>
                    </div>

                    <?php if ($status === 'Pending'): ?>
                        <span class="waiting-text">Waiting for DAA/Admin Approval</span>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="7" class="empty-row">No active requests found</td>
    </tr>
<?php endif; ?>
</tbody>
</table>

<div id="itemDetailsModal" class="custom-modal">
    <div class="custom-modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3 style="margin-top: 0; color: #003366;" id="modalHeaderTitle">Detailed Inventory Request</h3>
        <p style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Below are the structural items for this requisition slip line:</p>
        <ul id="modalListContainer" class="modal-item-list"></ul>
    </div>
</div>

<script>
function openItemsModal(id, itemsArray) {
    const modal = document.getElementById('itemDetailsModal');
    const title = document.getElementById('modalHeaderTitle');
    const container = document.getElementById('modalListContainer');
    
    title.innerText = `Request #${id} Item Details`;
    container.innerHTML = ""; 

    itemsArray.forEach(itemLine => {
        const li = document.createElement('li');
        li.innerHTML = `ðŸ“¦ <strong>${itemLine}</strong>`;
        container.appendChild(li);
    });
    modal.style.display = "block";
}

function closeModal() {
    document.getElementById('itemDetailsModal').style.display = "none";
}

function toggleRejectBox(id) {
    const box = document.getElementById(`reject-box-${id}`);
    if (box) {
        box.style.display = box.style.display === 'block' ? 'none' : 'block';
        if (box.style.display === 'block') {
            document.getElementById(`reason-${id}`).focus();
        }
    }
}

function submitReject(id) {
    const reasonInput = document.getElementById(`reason-${id}`);
    const reason = reasonInput.value.trim();

    if (reason === "") {
        alert("Please provide a reason for rejecting this request.");
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'reject_request.php';
    
    const data = { request_id: id, action: 'reject', reason: reason };
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

window.onclick = function(event) {
    const modal = document.getElementById('itemDetailsModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>
</body>
</html>
