<?php
// request_disposal.php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../auth.php'; // Ensure session is active

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'end_user') {
    die("Access Denied.");
}

$issuance_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issuance_id'])) {
    $issuance_id = (int)$_POST['issuance_id'];
} elseif (isset($_GET['issuance_id'])) {
    $issuance_id = (int)$_GET['issuance_id'];
}

if ($issuance_id === 0) {
    die("Error: No valid issuance tracking reference was received.");
}

// 1. FIRST, FIND THE DEPARTMENT OF THIS ISSUANCE
$deptQuery = $conn->query("SELECT department FROM issuances WHERE issuance_id = $issuance_id LIMIT 1");
if ($deptQuery && $deptQuery->num_rows > 0) {
    $department = $deptQuery->fetch_assoc()['department'];
} else {
    die("Error: Issuance record not found.");
}

// 2. FETCH ALL ISSUANCES FOR THIS DEPARTMENT TO AGGREGATE TOTALS
$allIssuances = $conn->prepare("SELECT item_name, issued_qty, issuance_id FROM issuances WHERE department = ?");
$allIssuances->bind_param("s", $department);
$allIssuances->execute();
$res = $allIssuances->get_result();

$aggregatedIssued = [];
$issuanceIdsMapping = []; 

while ($row = $res->fetch_assoc()) {
    $items = explode(', ', $row['item_name']);
    $qtys  = explode(', ', $row['issued_qty']);
    $u_id  = $row['issuance_id'];

    for ($i = 0; $i < count($items); $i++) {
        $name = trim($items[$i]);
        if (empty($name)) continue;

        $qty = isset($qtys[$i]) && trim($qtys[$i]) !== '' ? (int)trim($qtys[$i]) : 1;

        if (!isset($aggregatedIssued[$name])) {
            $aggregatedIssued[$name] = 0;
            $issuanceIdsMapping[$name] = [];
        }
        $aggregatedIssued[$name] += $qty;
        $issuanceIdsMapping[$name][] = $u_id; 
    }
}
$allIssuances->close();

// 3. COMPUTE DISPOSAL DEDUCTIONS USING POSITION-BASED ARRAY EXTRACTION
$itemsMatrix = [];
$has_available_items = false;

foreach ($aggregatedIssued as $itemName => $totalIssued) {
    // Fetch all disposal records for this department that might contain this item
    $stmtHistory = $conn->prepare("SELECT item_name, qty FROM disposal_requests 
                                   WHERE department = ? 
                                   AND status IN ('Pending DAA', 'Approved by DAA', 'Disposed')");
    $stmtHistory->bind_param("s", $department);
    $stmtHistory->execute();
    $historyRes = $stmtHistory->get_result();
    
    $total_locked = 0;
    while ($histRow = $historyRes->fetch_assoc()) {
        $histItems = explode(', ', $histRow['item_name']);
        $histQtys  = explode(', ', $histRow['qty']);
        
        // Find if our specific item is inside this multi-item row
        for ($j = 0; $j < count($histItems); $j++) {
            if (trim($histItems[$j]) === $itemName) {
                $total_locked += isset($histQtys[$j]) ? (int)trim($histQtys[$j]) : 0;
            }
        }
    }
    $stmtHistory->close();

    $available_qty = $totalIssued - $total_locked;
    if ($available_qty < 0) {
        $available_qty = 0;
    }

    // Only show items with a remaining balance greater than 0
    if ($available_qty > 0) {
        $has_available_items = true;
        $itemsMatrix[] = [
            'name' => $itemName,
            'issued' => $totalIssued,
            'locked' => $total_locked,
            'available' => $available_qty,
            'sources' => implode(',', $issuanceIdsMapping[$itemName])
        ];
    }
}

if (!$has_available_items && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Error: No items available for disposal. All inventory balances for this department have already been completely requested or exhausted.");
}

// 4. FORM SUBMISSION PROCESSING
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestor = trim($_SESSION['requestor_name'] ?? $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'End User');
    $dept      = trim($_POST['department']);
    $reason    = trim($_POST['reason']);
    $unit      = trim($_POST['unit'] ?? 'pcs');

    $posted_items   = $_POST['items'] ?? []; 
    $posted_qtys    = $_POST['qtys'] ?? [];  
    $posted_sources = $_POST['sources'] ?? []; 

    if (empty($posted_items) || empty($reason)) {
        die("Error: Please select an item and provide a reason.");
    }

    $final_selected_items = [];
    $final_selected_qtys  = [];
    $associated_issuance_ids = [];

    foreach ($posted_items as $selected_item) {
        $qty_to_dispose = isset($posted_qtys[$selected_item]) ? (int)$posted_qtys[$selected_item] : 0;
        if ($qty_to_dispose <= 0) continue;

        // Validation check against our backend matrix limits
        $availablePoolLimit = 0;
        foreach ($itemsMatrix as $matrixRow) {
            if ($matrixRow['name'] === $selected_item) {
                $availablePoolLimit = $matrixRow['available'];
                break;
            }
        }

        if ($qty_to_dispose > $availablePoolLimit) {
            die("Error: The requested quantity for '" . htmlspecialchars($selected_item) . "' exceeds your current department balance.");
        }

        $final_selected_items[] = $selected_item;
        $final_selected_qtys[]  = $qty_to_dispose;
        
        $item_sources = isset($posted_sources[$selected_item]) ? explode(',', $posted_sources[$selected_item]) : [$issuance_id];
        $associated_issuance_ids[] = (int)($item_sources[0] ?? $issuance_id);
    }

    if (empty($final_selected_items)) {
        die("Error: Disposal request quantities must be greater than zero.");
    }

    // Combine arrays into tidy comma-separated text blocks
    $db_item_names = implode(', ', $final_selected_items);
    $db_quantities = implode(', ', $final_selected_qtys);
    $primary_issuance_id = isset($associated_issuance_ids[0]) ? $associated_issuance_ids[0] : $issuance_id;

    // 5. INSERT ONE SINGLE UNIQUE DISPOSAL RECORD ROW
    $stmt = $conn->prepare("INSERT INTO disposal_requests 
        (issuance_id, requestor_name, department, item_name, qty, unit, reason, status, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending DAA', NOW(), NOW())");

    $stmt->bind_param("issssss", $primary_issuance_id, $requestor, $dept, $db_item_names, $db_quantities, $unit, $reason);
    
    if ($stmt->execute()) {
        $last_id = $stmt->insert_id; // Single unique ID for this entire request bundle
        $msg = "Aggregated disposal request #" . $last_id . " filed for: " . $db_item_names . " (" . $db_quantities . " " . $unit . ") by " . $requestor . " (" . $dept . ").";

        // Notify DAA
        $notif1 = $conn->prepare("INSERT INTO system_notifications (target_role, module_type, reference_id, title, message) VALUES ('DAA', 'disposal', ?, 'New Disposal Request Filed', ?)");
        $notif1->bind_param("is", $last_id, $msg);
        $notif1->execute(); 
        $notif1->close();

        // Notify Admin
        $notif2 = $conn->prepare("INSERT INTO system_notifications (target_role, module_type, reference_id, title, message) VALUES ('admin', 'disposal', ?, 'Disposal Alert: Pending DAA Action', ?)");
        $notif2->bind_param("is", $last_id, $msg);
        $notif2->execute(); 
        $notif2->close();
        
        $stmt->close();
        header("Location: receive_item.php?success=1");
        exit;
    } else {
        echo "Database submission failed: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Department Inventory Disposal Selection</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 40px; }
        .form-card { max-width: 650px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 6px; color: #4b5563; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .form-group textarea { height: 100px; resize: none; }
        .item-selection-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .item-selection-table th { background: #f8fafc; text-align: left; padding: 8px; font-size: 13px; color: #64748b; border-bottom: 2px solid #e2e8f0; }
        .item-selection-table td { padding: 10px 8px; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        .qty-input { width: 80px !important; padding: 5px !important; text-align: center; }
        .btn-submit { background: #dc3545; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-size: 15px; font-weight: bold; width: 100%; }
        .btn-submit:hover { background: #bd2130; }
        .cancel-link { display: block; text-align: center; margin-top: 15px; color: #6b7280; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>

<div class="form-card">
    <h2>📋 Request Item Disposal</h2>
    <p style="color: #6b7280; font-size: 13px; margin-bottom: 20px;">
        Showing available inventory options for department: <strong><?= htmlspecialchars($department) ?></strong>
    </p>

    <form action="request_disposal.php" method="POST">
        <input type="hidden" name="issuance_id" value="<?php echo $issuance_id; ?>">
        <input type="hidden" name="department" value="<?= htmlspecialchars($department) ?>">

        <div class="form-group">
            <label>Select Items to Dispose</label>
            <table class="item-selection-table">
                <thead>
                    <tr>
                        <th width="5%">Select</th>
                        <th>Item Description</th>
                        <th width="25%">Total Dept Bal</th>
                        <th width="25%">Qty to Dispose</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itemsMatrix as $index => $item): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="items[]" value="<?= htmlspecialchars($item['name']) ?>" 
                                       id="chk_<?= $index ?>" checked onchange="toggleQtyInput(this, 'qty_<?= $index ?>')">
                                <input type="hidden" name="sources[<?= htmlspecialchars($item['name']) ?>]" value="<?= $item['sources'] ?>">
                            </td>
                            <td>
                                <label for="chk_<?= $index ?>"><strong><?= htmlspecialchars($item['name']) ?></strong></label>
                                <div style="font-size: 11px; color:#94a3b8;">Total Received: <?= $item['issued'] ?> | Disposed/Locked: <?= $item['locked'] ?></div>
                            </td>
                            <td><span style="font-weight:bold; color: #16a34a;"><?= $item['available'] ?> items left</span></td>
                            <td>
                                <input type="number" name="qtys[<?= htmlspecialchars($item['name']) ?>]" 
                                       id="qty_<?= $index ?>" class="qty-input"
                                       value="<?= $item['available'] ?>" min="1" max="<?= $item['available'] ?>" required>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-group">
            <label>Unit Measurement</label>
            <input type="text" name="unit" value="pcs" required>
        </div>

        <div class="form-group">
            <label>Reason for Scrapping / Disposal</label>
            <textarea name="reason" placeholder="Explain explicitly why this selected batch needs to be scrapped or disposed..." required></textarea>
        </div>

        <button type="submit" class="btn-submit">🚀 Send Batch to DAA</button>
        <a href="receive_item.php" class="cancel-link">Cancel and return</a>
    </form>
</div>

<script>
function toggleQtyInput(checkbox, inputId) {
    const inputField = document.getElementById(inputId);
    if(checkbox.checked) {
        inputField.removeAttribute('disabled');
        inputField.setAttribute('required', 'required');
    } else {
        inputField.setAttribute('disabled', 'disabled');
        inputField.removeAttribute('required');
    }
}
</script>
</body>
</html>