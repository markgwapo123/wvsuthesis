<?php
require_once __DIR__ . '/../../auth.php';
allow_roles(['procurement', 'admin']); // allow both if you want
require_once __DIR__ . '/../../db_connect.php';

$id     = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

$allowed = ['for_procurement','on_process','purchased','delivered','cancelled'];
if ($id <= 0 || !in_array($status, $allowed, true)) {
    header("Location: /SUPPLY_OFFICE_first/modules/procurement/procurementdashboard.php?error=Invalid+data");
    exit();
}

$stmt = $conn->prepare("UPDATE procurement_requests SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);
$stmt->execute();

header("Location: /SUPPLY_OFFICE_first/modules/procurement/procurementdashboard.php?success=Status+updated");
exit();
