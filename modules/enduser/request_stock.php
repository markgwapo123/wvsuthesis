<?php
session_start();
require_once __DIR__ . "/db_connect.php";

/* ===============================
   ACCESS CONTROL
================================ */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== "end_user") {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

/* ===============================
   SESSION DATA
================================ */
$user_id    = $_SESSION['user_id'];
$fullname   = $_SESSION['fullname'];
$department = $_SESSION['department']; 

/* ===============================
   FETCH DEPARTMENT-SPECIFIC STOCKS ONLY
================================ */
$stmt_inventory = $conn->prepare("
    SELECT inventory_id, name, quantity, unit, department 
    FROM inventory 
    WHERE quantity > 0 AND LOWER(department) = LOWER(?)
    ORDER BY name ASC
");
$stmt_inventory->bind_param("s", $department);
$stmt_inventory->execute();
$inventory_res = $stmt_inventory->get_result();

/* ===============================
   HANDLE MULTI-ITEM FORM SUBMISSION
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_items = $_POST['items'] ?? [];
    $quantities     = $_POST['qtys'] ?? [];
    $remarks        = trim($_POST['remarks'] ?? '');

    if (empty($selected_items)) {
        header("Location: request_stock.php?error=empty");
        exit();
    }

    $compiled_ids   = [];
    $compiled_names = [];
    $compiled_qtys  = [];
    $compiled_units = [];

    $conn->begin_transaction();

    try {
        foreach ($selected_items as $inv_id) {
            $inv_id = (int)$inv_id;
            
            // Mas gin-siguro naton ang pagkuha sang saktong quantity gamit ang array key
            $req_qty = isset($quantities[$inv_id]) ? (int)$quantities[$inv_id] : 0;

            if ($req_qty < 1) {
                throw new Exception("Please specify a valid quantity for all selected items.");
            }

            // I-verify ang item records halin sa inventory table
            $item_stmt = $conn->prepare("SELECT name, quantity, unit FROM inventory WHERE inventory_id = ? AND LOWER(department) = LOWER(?)");
            $item_stmt->bind_param("is", $inv_id, $department);
            $item_stmt->execute();
            $item_data = $item_stmt->get_result()->fetch_assoc();
            $item_stmt->close();

            if (!$item_data) {
                throw new Exception("Requested item record could not be verified.");
            }
            
            if ($req_qty > (int)$item_data['quantity']) {
                throw new Exception("Requested quantity for '" . $item_data['name'] . "' exceeds limits.");
            }

            // I-salod sa array ang kada unod sang loop
            $compiled_ids[]   = $inv_id;
            $compiled_names[] = $item_data['name'];
            $compiled_qtys[]  = $req_qty; 
            $compiled_units[] = !empty($item_data['unit']) ? $item_data['unit'] : 'pcs';
        }

        // I-implode para mangin comma-separated text string
        $final_ids   = implode(', ', $compiled_ids);
        $final_names = implode(', ', $compiled_names);
        $final_qtys  = implode(', ', $compiled_qtys);  // Resulta: "5, 10, 3"
        $final_units = implode(', ', $compiled_units);

        // SQL Insertion Command
        $insert_stmt = $conn->prepare("
            INSERT INTO requests
            (requester_user_id, requester_name, requester_department,
             inventory_id, item_name, qty_requested, unit, remarks, status, requested_at, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), 0)
        ");

        // "isssssss" -> Ang 'i' para sa user_id, kag ang sunod nga 7 puro na 's' (strings)
        $insert_stmt->bind_param(
            "isssssss",
            $user_id,
            $fullname,
            $department,
            $final_ids,     
            $final_names,   
            $final_qtys,    // Naka-string parameter na para indi ma-cut ukon ma-ignore sang bind_param
            $final_units,   
            $remarks
        );
        
        $insert_stmt->execute();
        $insert_stmt->close();

        $conn->commit();
        header("Location: /SUPPLY_OFFICE_first/modules/enduser/endUserDashboard.php?success=Bulk+Request+Submitted");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: request_stock.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Material Requisition Request (RIS)</title>
    <link href="assets/bootstrap-5.3.2-dist/css/bootstrap.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Roboto, sans-serif; background-color: #f4f6f9; color: #333; padding: 40px 20px; }
        .main-container { max-width: 900px; background: #fff; margin: auto; border-radius: 12px; padding: 35px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header-title { color: #004085; font-size: 24px; font-weight: 600; border-bottom: 2px solid #e9ecef; padding-bottom: 15px; margin-bottom: 25px; }
        .meta-badge { background-color: #e8f0fe; color: #1a73e8; font-size: 13px; font-weight: 500; padding: 6px 12px; border-radius: 20px; display: inline-block; margin-right: 10px; }
        .stock-table th { background-color: #007bff; color: white; font-weight: 500; font-size: 14px; }
        .stock-table td { font-size: 14px; vertical-align: middle; }
        .qty-input { max-width: 110px; padding: 6px 10px; border-radius: 4px; border: 1px solid #ced4da; text-align: center; font-weight: bold; }
        .qty-input:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; }
        .inline-error { color: #dc3545; font-size: 11px; display: none; margin-top: 4px; font-weight: 600; text-align: center; }
        .remarks-box { border-radius: 6px; border: 1px solid #ced4da; font-size: 14px; padding: 10px; min-height: 80px; }
        .submit-btn { background-color: #007bff; color: white; font-weight: 600; padding: 12px; border-radius: 6px; border: none; width: 100%; font-size: 16px; transition: background 0.2s; }
        .submit-btn:hover { background-color: #0056b3; }
        .submit-btn:disabled { background-color: #a0c4ff; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="header-title">ðŸ“‹ Requisition & Issue Slip (RIS) Entry Form</div>

    <div class="mb-4">
        <span class="meta-badge"><strong>Requester:</strong> <?= htmlspecialchars($fullname) ?></span>
        <span class="meta-badge"><strong>Department Office:</strong> <?= htmlspecialchars($department) ?></span>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger text-center">
            âš ï¸ <strong>Error:</strong> <?= htmlspecialchars($_GET['error'] === 'empty' ? 'You must select at least one item line.' : $_GET['error']) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="risForm">
        <h5 class="mb-3 text-secondary" style="font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">Your Department's Available Stock Allocations</h5>
        
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle stock-table border">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">Select</th>
                        <th>Material Item Nomenclature</th>
                        <th style="text-align: center;">Current Storage Availability</th>
                        <th style="width: 160px; text-align: center;">Quantity Needed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($inventory_res && $inventory_res->num_rows > 0): ?>
                        <?php while ($row = $inventory_res->fetch_assoc()): 
                            $id = (int)$row['inventory_id'];
                            $avail = (int)$row['quantity'];
                            $unit_txt = htmlspecialchars($row['unit'] ?? 'pcs');
                        ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="items[]" value="<?= $id ?>" class="form-check-input item-checkbox" onchange="toggleQtyInput(this, <?= $id ?>)">
                                </td>
                                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                <td style="text-align: center;"><span class="text-muted"><?= $avail ?> <?= $unit_txt ?> allocated</span></td>
                                <td style="text-align: center;">
                                    <input type="number" 
                                           name="qtys[<?= $id ?>]" 
                                           id="qty_<?= $id ?>" 
                                           class="qty-input" 
                                           min="1" 
                                           max="<?= $avail ?>" 
                                           placeholder="Enter Qty"
                                           disabled 
                                           required 
                                           oninput="validateMaxLimit(this, <?= $avail ?>, <?= $id ?>)">
                                    
                                    <div class="inline-error" id="error_<?= $id ?>">Max: <?= $avail ?>!</div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No stock assets are currently cataloged under your department's allocation ledger.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mb-4">
            <label class="form-label" style="font-weight: 500; font-size: 14px;">General Purpose / Requisition Remarks</label>
            <textarea name="remarks" class="form-control remarks-box" placeholder="Indicate justification note for this department issuance line request..."></textarea>
        </div>

        <button type="submit" id="submitBtn" class="submit-btn">Submit Combined Requisition Slip</button>
    </form>
</div>

<script>
    function toggleQtyInput(checkbox, id) {
        const qtyField = document.getElementById('qty_' + id);
        const errorLabel = document.getElementById('error_' + id);
        
        if (checkbox.checked) {
            qtyField.disabled = false;
            qtyField.value = ""; 
            qtyField.focus();
        } else {
            qtyField.disabled = true;
            qtyField.value = ""; 
            errorLabel.style.display = "none";
            checkAllInputs();
        }
    }

    function validateMaxLimit(input, maxLimit, id) {
        const errorLabel = document.getElementById('error_' + id);
        const value = parseInt(input.value);

        if (value > maxLimit) {
            errorLabel.style.display = "block";
            input.style.borderColor = "#dc3545";
            input.style.color = "#dc3545";
        } else {
            errorLabel.style.display = "none";
            input.style.borderColor = "#ced4da";
            input.style.color = "#333";
        }
        
        if (value < 1) {
            input.value = 1;
        }

        checkAllInputs();
    }

    function checkAllInputs() {
        const activeErrors = document.querySelectorAll('.inline-error');
        const submitBtn = document.getElementById('submitBtn');
        let hasError = false;

        activeErrors.forEach(err => {
            if (err.style.display === "block") {
                hasError = true;
            }
        });

        submitBtn.disabled = hasError;
    }
</script>
</body>
</html>
