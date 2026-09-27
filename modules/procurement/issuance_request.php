<?php 
session_start();
require_once __DIR__ . '/../../db_connect.php'; 

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['fullname'])) {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$issued_by = $conn->real_escape_string($_SESSION['fullname']);

// --- GATHER REDIRECT MESSAGES FROM SESSION ---
$alert_message = $_SESSION['issuance_alert'] ?? "";
$receipt_id = $_SESSION['receipt_id'] ?? null; 

unset($_SESSION['issuance_alert']);
unset($_SESSION['receipt_id']);

// --- FETCH ORIGINAL REQUEST PARAMETERS ---
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

if ($request_id === 0) {
    die("Error: Request ID parameter is missing from the URL string.");
}

$req = $conn->query("SELECT * FROM requests WHERE request_id=$request_id")->fetch_assoc();

if (!$req) {
    die("Request row data not found for ID: " . $request_id);
}

// Explode request strings into working arrays
$arrNames   = explode(', ', $req['item_name']);
$arrQtys    = explode(', ', $req['qty_requested']);
$arrUnits   = explode(', ', $req['unit']);
$arrInvIds  = isset($req['inventory_id']) ? explode(', ', $req['inventory_id']) : [];
$department = $conn->real_escape_string($req['requester_department']);

$totalCount = count($arrNames);

// --- PROCESS POST FORM ACTION (SINGLE-ROW INSERT LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $issued_qtys = $_POST['issued_qtys'] ?? [];
    $receiver = $conn->real_escape_string($_POST['receiver']);
    $requestor_name = $conn->real_escape_string($req['requester_name']);
    $approved_by = $conn->real_escape_string($req['approved_by'] ?? 'DAA Approved');

    $conn->begin_transaction();

    try {
        $new_req_qtys = [];
        $all_zero = true;
        
        // Arrays to collect items so we can save them in ONE row
        $final_barcodes = [];
        $final_item_names = [];
        $final_issued_qtys = [];

        for ($i = 0; $i < $totalCount; $i++) {
            $issued_q = isset($issued_qtys[$i]) ? (int)$issued_qtys[$i] : 0;
            $req_q    = isset($arrQtys[$i]) ? (int)trim($arrQtys[$i]) : 0;
            $inv_id   = isset($arrInvIds[$i]) ? (int)trim($arrInvIds[$i]) : 0;
            $item_n   = trim($arrNames[$i]);
            
            if ($req_q <= 0) {
                $new_req_qtys[] = 0;
                continue; 
            }

            if ($issued_q > 0) {
                if ($issued_q > $req_q) {
                    throw new Exception("Action Denied: You cannot issue more than requested ({$req_q}) for {$item_n}.");
                }

                // Check stock levels
                $stmtInv = $conn->prepare("SELECT quantity, barcode FROM inventory WHERE inventory_id = ? LIMIT 1");
                $stmtInv->bind_param("i", $inv_id);
                $stmtInv->execute();
                $resInv = $stmtInv->get_result();
                $invRow = $resInv->fetch_assoc();
                $stmtInv->close();

                if (!$invRow || (int)$invRow['quantity'] < $issued_q) {
                    throw new Exception("❌ Out of Stock: Not enough inventory for '{$item_n}'.");
                }

                $barcode_used = $invRow['barcode'] ?? 'Manual';

                // Deduct inventory stock balances
                $updateInv = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE inventory_id = ?");
                $updateInv->bind_param("ii", $issued_q, $inv_id);
                $updateInv->execute();
                $updateInv->close();

                // COLLECT DATA IN ARRAYS INSTEAD OF RUNNING INSERT HERE
                $final_barcodes[] = $barcode_used;
                $final_item_names[] = $item_n;
                $final_issued_qtys[] = $issued_q;

                $remaining_req_q = $req_q - $issued_q;
                $new_req_qtys[] = $remaining_req_q;
            } else {
                $remaining_req_q = $req_q;
                $new_req_qtys[] = $remaining_req_q;
            }

            if ($remaining_req_q > 0) {
                $all_zero = false;
            }
        }

        // --- SINGLE INSERT EXECUTION ---
        $last_issuance_id = null;
        if (!empty($final_issued_qtys)) {
            // Combine collected data items into comma-separated strings
            $str_barcodes = implode(', ', $final_barcodes);
            $str_item_names = implode(', ', $final_item_names);
            $str_issued_qtys = implode(', ', $final_issued_qtys);

            $order_id_placeholder = 0; 
            $sql = "INSERT INTO issuances (
                        order_id, request_id, barcode, item_name, issued_qty, 
                        issued_by, approved_by, issued_at, department, requestor_name, receiver
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
            
            $stmtIssue = $conn->prepare($sql);
            $stmtIssue->bind_param(
                "iissssssss", 
                $order_id_placeholder, $request_id, $str_barcodes, $str_item_names, $str_issued_qtys, 
                $issued_by, $approved_by, $department, $requestor_name, $receiver          
            );
            $stmtIssue->execute();
            $last_issuance_id = $conn->insert_id; // Grabs the single unique issuance_id generated
            $stmtIssue->close();
        } else {
            throw new Exception("Please specify a valid quantity to issue.");
        }

        // Update the Requests table tracking state
        $new_qtys_str = implode(', ', $new_req_qtys);
        $new_status = $all_zero ? 'Issued' : 'Approved'; 

        $updateReq = $conn->prepare("UPDATE requests SET qty_requested = ?, status = ? WHERE request_id = ?");
        $updateReq->bind_param("ssi", $new_qtys_str, $new_status, $request_id);
        $updateReq->execute();
        $updateReq->close();

        $conn->commit();

        $_SESSION['issuance_alert'] = "✅ SUCCESS: All selected items issued successfully! Saved under a single transaction ID.";
        $_SESSION['receipt_id'] = $last_issuance_id;
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?request_id=" . $request_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['issuance_alert'] = $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF'] . "?request_id=" . $request_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Issue Request Verification</title>
    <style>
        body { font-family: 'Poppins', Arial, sans-serif; background: #f4f7fc; padding: 10px; }
        form { background: #fff; max-width: 650px; margin: 40px auto; padding: 30px; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.06); border-top: 5px solid #003366; }
        h2 { color: #003366; margin-top: 0; margin-bottom: 20px; font-size: 22px; border-bottom: 2px solid #f0f4f8; padding-bottom: 10px;}
        input { width: 100%; padding: 11px; margin: 6px 0 16px; border-radius: 6px; border: 1px solid #cbd5e1; box-sizing: border-box; font-size: 14px; color: #334155; }
        input[readonly] { background: #f8fafc; color: #475569; font-weight: 500; border-color: #e2e8f0; }
        .form-footer-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 25px; gap: 12px; }
        .btn-back { display: inline-block; padding: 12px 20px; background: #64748b; color: white; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600; text-align: center; transition: background 0.2s; }
        .btn-back:hover { background: #475569; }
        .btn-confirm { flex-grow: 1; padding: 12px 20px; background: #17a2b8; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: bold; transition: opacity 0.2s; }
        .btn-confirm:hover { opacity: 0.9; }
        .btn-receipt { display: block; background: #22c55e; color: white; text-align: center; padding: 11px; margin-bottom: 20px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; }
        .btn-receipt:hover { background: #16a34a; }
        label { font-weight: 600; font-size: 13px; color: #334155; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
        .ui-alert-banner { padding: 14px 16px; margin-bottom: 20px; border-radius: 6px; font-size: 14px; font-weight: 600; line-height: 1.4; }
        .ui-alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .ui-alert-success { background-color: #dcfce7; color: #14532d; border: 1px solid #bbf7d0; }
        .item-list-container { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 5px 20px 20px; margin-bottom: 20px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);}
        .item-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #cbd5e1; padding-top: 15px; padding-bottom: 15px; }
        .item-row:last-child { border-bottom: none; padding-bottom: 0; }
        .item-name-block { font-size: 16px; font-weight: 700; color: #0f172a; }
        .item-qty-input { text-align: right; }
        .item-qty-input input { width: 120px; margin: 0; text-align: center; font-size: 16px; font-weight: bold; padding: 8px; }
        .item-qty-input label { font-size: 11px; color: #64748b; margin-bottom: 4px; }
    </style>
</head>
<body>

<form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . "?request_id=" . $request_id) ?>">
    <h2>Issue Requested Items</h2>

    <?php if (!empty($alert_message)): ?>
        <?php 
            $isSuccess = (strpos($alert_message, 'SUCCESS') !== false); 
            $bannerClass = $isSuccess ? 'ui-alert-success' : 'ui-alert-danger';
        ?>
        <div class="ui-alert-banner <?= $bannerClass; ?>">
            <?= nl2br(htmlspecialchars($alert_message)) ?>
        </div>
        
        <?php if ($isSuccess && $receipt_id): ?>
            <a href="print_receipt.php?issuance_id=<?= $receipt_id ?>" target="_blank" class="btn-receipt">
                🖨️ Print Fresh Receipt Record
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <label>Receiver (Person claiming item):</label>
    <input name="receiver" value="<?= htmlspecialchars($req['requester_name']) ?>" required>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div>
            <label>Requestor:</label>
            <input value="<?= htmlspecialchars($req['requester_name']) ?>" readonly>
        </div>
        <div>
            <label>Department:</label>
            <input value="<?= htmlspecialchars($req['requester_department']) ?>" readonly>
        </div>
    </div>

    <div class="item-list-container">
        <label style="margin-top: 10px; color: #003366; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px;">Item Details & Issuance Quantities</label>
        
        <?php 
        $all_issued = true;
        for ($i = 0; $i < $totalCount; $i++): 
            $q = isset($arrQtys[$i]) ? (int)trim($arrQtys[$i]) : 0;
            $u = isset($arrUnits[$i]) ? trim($arrUnits[$i]) : 'pcs';
            $n = isset($arrNames[$i]) ? trim($arrNames[$i]) : 'Unknown Item';
            $displayString = "{$q}{$u} {$n}";

            if ($q > 0) $all_issued = false; 
        ?>
            <?php if ($q > 0): ?> 
                <div class="item-row">
                    <div class="item-name-block">
                         <?= htmlspecialchars($displayString) ?>
                    </div>
                    <div class="item-qty-input">
                        <label>Qty to Issue</label>
                        <input type="number" name="issued_qtys[<?= $i ?>]" min="1" max="<?= $q ?>" value="<?= $q ?>" required>
                    </div>
                </div>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($all_issued): ?>
            <div style="padding: 20px 0; text-align: center; color: #16a34a; font-weight: bold;">
                All items in this request have been fully issued.
            </div>
        <?php endif; ?>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div>
            <label>Approved By:</label>
            <input value="<?= htmlspecialchars($req['approved_by'] ?? 'DAA Approved') ?>" readonly style="color: #16a34a;">
        </div>
        <div>
            <label>Issued By:</label>
            <input value="<?= htmlspecialchars($issued_by) ?>" readonly>
        </div>
    </div>

    <div class="form-footer-actions">
        <a href="admin_requests.php" class="btn-back">⬅ Back</a>
        <?php if (!$all_issued): ?>
            <button type="submit" class="btn-confirm">Confirm Issuance</button>
        <?php else: ?>
            <button type="button" class="btn-confirm" style="background: #94a3b8; cursor: not-allowed;" disabled>Fully Issued</button>
        <?php endif; ?>
    </div>
</form>

</body>
</html>