<?php 
session_start();
require_once __DIR__ . '/../../db_connect.php'; 

if (!isset($_SESSION['user_id']) || !isset($_SESSION['fullname'])) {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$issued_by = $conn->real_escape_string($_SESSION['fullname']);

$message = $_SESSION['issuance_message'] ?? "";
$remaining_before = $_SESSION['remaining_before'] ?? "";
$remaining_after  = $_SESSION['remaining_after'] ?? "";
$receipt_id = $_SESSION['receipt_id'] ?? null; 

unset($_SESSION['issuance_message']);
unset($_SESSION['remaining_before']);
unset($_SESSION['remaining_after']);
unset($_SESSION['receipt_id']);

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$order = $conn->query("SELECT * FROM orders WHERE order_id=$order_id")->fetch_assoc();

if (!$order) {
    die("Order not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $order_id = (int)$_POST['order_id'];
    $issued_qty = (int)$_POST['issued_qty'];
    $scanned_barcode = trim($_POST['barcode']); 
    
    $item_name = $conn->real_escape_string($order['item_name']);
    $department = $conn->real_escape_string($order['department']);
    $requestor_name = $conn->real_escape_string($order['requestor_name']);
    $receiver = $conn->real_escape_string($_POST['receiver']);

    // Get the DAA Approver from the orders table record
    $approved_by = $conn->real_escape_string($order['approved_by'] ?? 'DAA Approved');

    if ($issued_qty < 1) {
        $_SESSION['issuance_message'] = "<p style='color:red;'>Invalid quantity.</p>";
        header("Location: issuance.php?order_id=$order_id");
        exit();
    }

    $invStmt = $conn->prepare("SELECT * FROM inventory WHERE barcode = ? LIMIT 1");
    $invStmt->bind_param("s", $scanned_barcode);
    $invStmt->execute();
    $invResult = $invStmt->get_result();
    $invRow = $invResult->fetch_assoc();
    $invStmt->close();

    if (!$invRow) {
        $_SESSION['issuance_message'] = "<p style='color:red;'><strong>Error:</strong> Barcode '{$scanned_barcode}' not found in inventory.</p>";
        header("Location: issuance.php?order_id=$order_id");
        exit();
    }

    if (strcasecmp($invRow['name'], $item_name) !== 0) {
        $_SESSION['issuance_message'] = "<p style='color:red;'><strong>Mismatch Error:</strong><br>Scanned Item: " . htmlspecialchars($invRow['name']) . "<br>Requested Item: " . htmlspecialchars($item_name) . "</p>";
        header("Location: issuance.php?order_id=$order_id");
        exit();
    }

    $remaining_before = $invRow['quantity'];
    if ($remaining_before < $issued_qty) {
        $_SESSION['issuance_message'] = "<p style='color:red;'>Not enough stock available. Current Stock: $remaining_before</p>";
        header("Location: issuance.php?order_id=$order_id");
        exit();
    }

    $remaining_after = $remaining_before - $issued_qty;

    $updateInv = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE inventory_id = ?");
    $updateInv->bind_param("ii", $issued_qty, $invRow['inventory_id']);
    $updateInv->execute();
    $updateInv->close();

    $updateOrder = $conn->prepare("UPDATE orders SET qty = qty - ? WHERE order_id = ?");
    $updateOrder->bind_param("ii", $issued_qty, $order_id);
    $updateOrder->execute();
    $updateOrder->close();

    // Direct Order flow uses 0 for request_id placeholder
    $request_id_placeholder = 0; 
    
    $sql = "INSERT INTO issuances (
                order_id, 
                request_id, 
                barcode, 
                item_name, 
                issued_qty, 
                issued_by, 
                approved_by, 
                issued_at, 
                department, 
                requestor_name, 
                receiver
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iississsss", 
        $order_id, 
        $request_id_placeholder, 
        $scanned_barcode, 
        $item_name, 
        $issued_qty, 
        $issued_by, 
        $approved_by, // Saves the DAA name safely
        $department, 
        $requestor_name, 
        $receiver
    );

    if ($stmt->execute()) {
        $last_issuance_id = $conn->insert_id; 

        if (function_exists('log_activity')) {
            log_activity($user_id, "Issued $issued_qty pcs of $item_name (Barcode: $scanned_barcode)", "Issuance", "Success", $conn);
        }

        $_SESSION['issuance_message'] = "<p style='color:green; font-weight:bold;'>✅ Issued successfully!</p>";
        $_SESSION['remaining_before'] = $remaining_before;
        $_SESSION['remaining_after'] = $remaining_after;
        $_SESSION['receipt_id'] = $last_issuance_id; 
    } else {
        $_SESSION['issuance_message'] = "<p style='color:red;'>Error executing query: " . $stmt->error . "</p>";
    }
    $stmt->close();

    header("Location: issuance.php?order_id=$order_id");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Issuance</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6fa; }
        form { background: #fff; max-width: 600px; margin: 30px auto; padding: 25px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,.1); }
        input { width: 100%; padding: 10px; margin: 8px 0 15px; border-radius: 5px; border: 1px solid #ccc; box-sizing: border-box; }
        button { width: 100%; padding: 15px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #0056b3; }
        .info-box { padding: 15px; background: #e9ecef; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #007bff; }
        .btn-receipt { display: block; background: #28a745; color: white; text-align: center; padding: 10px; margin-bottom: 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-receipt:hover { background: #218838; }
        label { font-weight: bold; font-size: 14px; color: #333; }
        .barcode-input { border: 2px solid #007bff; background-color: #f0f8ff; }
    </style>
</head>
<body>

<form method="POST">
    <h2>Issue Item (From Approved Order)</h2>

    <?= $message ?>

    <?php if ($receipt_id): ?>
        <a href="print_receipt.php?issuance_id=<?= $receipt_id ?>" target="_blank" class="btn-receipt">
            🖨️ Print Receipt
        </a>
    <?php endif; ?>

    <?php if ($remaining_before !== ""): ?>
    <div class="info-box">
        <strong>Stock Before:</strong> <?= $remaining_before ?><br>
        <strong>Stock After:</strong> <?= $remaining_after ?>
    </div>
    <?php endif; ?>

    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">

    <label>Scan Barcode:</label>
    <input name="barcode" class="barcode-input" placeholder="Scan or type barcode here..." required autofocus autocomplete="off">

    <label>Receiver:</label>
    <input name="receiver" value="<?= isset($order['receiver']) ? htmlspecialchars($order['receiver']) : ''; ?>" required placeholder="Enter receiver name">

    <label>Requestor:</label>
    <input value="<?= htmlspecialchars($order['requestor_name']) ?>" readonly style="background:#eee;">

    <label>Department:</label>
    <input value="<?= htmlspecialchars($order['department']) ?>" readonly style="background:#eee;">

    <label>Item:</label>
    <input value="<?= htmlspecialchars($order['item_name']) ?>" readonly style="background:#eee;">

    <label>Quantity Ordered:</label>
    <input value="<?= htmlspecialchars($order['qty']) ?>" readonly style="background:#eee;">

    <label>Quantity to Issue:</label>
    <input name="issued_qty" type="number" min="1" max="<?= $order['qty'] ?>" value="<?= $order['qty'] ?>" required>

    <label>Approved By (DAA):</label>
    <input value="<?= htmlspecialchars($order['approved_by'] ?? 'DAA Authorized') ?>" readonly style="background:#eee; font-weight: bold; color: green;">

    <label>Issued By:</label>
    <input value="<?= htmlspecialchars($issued_by) ?>" readonly style="background:#eee;">

    <a href="order_management.php" style="display:inline-block; margin-top:10px; padding:8px 15px; background:#6c757d; color:white; border-radius:5px; text-decoration:none;">
        ⬅ Back
    </a>

    <button type="submit" style="margin-top: 15px;">Confirm Issuance</button>
</form>

</body>
</html>