<?php
require_once __DIR__ . '/../../auth.php';
allow_roles(['admin']);   // only admin can send to procurement
require_once __DIR__ . '/../../db_connect.php';

$inventory_id = (int)($_GET['inventory_id'] ?? 0);
$qty          = (int)($_GET['qty'] ?? 0);

if ($inventory_id <= 0 || $qty <= 0) {
    header("Location: manage_inventory.php?error=Invalid+procurement+request");
    exit();
}

// Get inventory item info
$stmt = $conn->prepare("SELECT name, description FROM inventory WHERE inventory_id = ?");
$stmt->bind_param("i", $inventory_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: manage_inventory.php?error=Inventory+item+not+found");
    exit();
}

$request_code = 'PR-' . date('Ymd-His');

$stmt = $conn->prepare("
    INSERT INTO procurement_requests
        (request_code, inventory_id, description, quantity, requested_by)
    VALUES (?, ?, ?, ?, ?)
");
$desc = $item['name'] . ' - ' . ($item['description'] ?? '');
$stmt->bind_param(
    "sisii",
    $request_code,
    $inventory_id,
    $desc,
    $qty,
    $_SESSION['user_id']
);
$stmt->execute();

header("Location: manage_inventory.php?success=Sent+to+procurement");
exit();
