<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="items.csv"');
$out = fopen('php://output', 'w');
$res = $conn->query("SELECT item_id,code,barcode,name,category,quantity,unit,condition_label,status_label,location,updated_at FROM items");
$first = true;
while($r=$res->fetch_assoc()){ if($first){ fputcsv($out, array_keys($r)); $first=false; } fputcsv($out, $r); }
fclose($out); exit; ?>