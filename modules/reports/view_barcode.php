<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';

$barcode = '';
$resultData = null;
$errorMsg = '';

if (isset($_GET['barcode']) && !empty(trim($_GET['barcode']))) {
    $barcode = $conn->real_escape_string(trim($_GET['barcode']));

    // 1. Check if the barcode exists
    $barcodeQuery = $conn->query("SELECT * FROM barcodes WHERE barcode_code = '$barcode'");
    
    if ($barcodeQuery->num_rows > 0) {
        $barcodeInfo = $barcodeQuery->fetch_assoc();
        $itemId = $barcodeInfo['item_id'];
        $isScannedInInventory = $barcodeInfo['is_scanned'];

        // 2. Look for item details (check inventory first, fall back to staging items)
        $itemQuery = $conn->query("SELECT name, department, category, unit FROM inventory WHERE item_id = $itemId");
        if ($itemQuery->num_rows === 0) {
            $itemQuery = $conn->query("SELECT name, department, category, unit FROM items WHERE item_id = $itemId");
        }

        if ($itemQuery->num_rows > 0) {
            $itemDetails = $itemQuery->fetch_assoc();
            
            // 3. Collect all tracking info into an array for viewing
            $resultData = [
                'barcode' => $barcode,
                'name' => $itemDetails['name'],
                'category' => $itemDetails['category'],
                'assigned_dept' => $itemDetails['department'] ?: 'Not Assigned / General Stock',
                'unit' => $itemDetails['unit'],
                'status' => $isScannedInInventory ? "✅ Verified & Stored in Inventory" : "⏳ Staging Area (Ready for add_item.php)"
            ];
        } else {
            $errorMsg = "❌ Core item details missing from the database.";
        }
    } else {
        $errorMsg = "❌ Barcode not found in the system registry.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SOIMS | Barcode Verification Lookup</title>
    <link href="assets/bootstrap-5.3.2-dist/css/bootstrap.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #fafaff; padding: 40px 20px; }
        .lookup-card { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .data-display { background: #eef5ff; border-left: 5px solid #007bff; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .error-display { background: #fdf2f2; border-left: 5px solid #dc3545; color: #dc3545; padding: 15px; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>

<div class="lookup-card">
    <h3 class="text-center text-primary mb-4">🔍 Live Barcode Verification Engine</h3>
    
    <form method="GET" action="view_barcode.php">
        <div class="mb-3">
            <input type="text" name="barcode" class="form-control form-control-lg" 
                   placeholder="Scan item label here..." value="<?= htmlspecialchars($barcode) ?>" autofocus autocomplete="off" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Verify Asset Info</button>
    </form>

    <?php if ($errorMsg): ?>
        <div class="error-display"><?= $errorMsg ?></div>
    <?php endif; ?>

    <?php if ($resultData): ?>
        <div class="data-display">
            <h4>📋 Asset Profile Details</h4>
            <hr>
            <p><strong>Barcode String:</strong> <span class="badge bg-dark"><?= $resultData['barcode'] ?></span></p>
            <p><strong>Item Name:</strong> <?= htmlspecialchars($resultData['name']) ?></p>
            <p><strong>Category:</strong> <?= htmlspecialchars($resultData['category']) ?></p>
            <p><strong>Owning Department:</strong> <span class="text-primary font-weight-bold"><?= htmlspecialchars($resultData['assigned_dept']) ?></span></p>
            <p><strong>Unit Context:</strong> <?= htmlspecialchars($resultData['unit']) ?></p>
            <p><strong>System Storage State:</strong> <b><?= $resultData['status'] ?></b></p>
        </div>
    <?php endif; ?>

    <a href="manage_inventory.php" class="btn btn-secondary w-100 mt-3">← Back </a>
</div>

<script>
    // Keep focus on input for fast hardware scanning gun use
    document.addEventListener("DOMContentLoaded", () => {
        const input = document.querySelector('input[name="barcode"]');
        if(input) {
            setInterval(() => { if (document.activeElement !== input) input.focus(); }, 3000);
        }
    });
</script>
</body>
</html>