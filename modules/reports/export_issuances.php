<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="issuances.csv"');
$out = fopen('php://output', 'w');
$res = $conn->query("SELECT issuance_id,item_id,qty,action,user_id,created_at FROM issuances");
$first = true;
while($r=$res->fetch_assoc()){ if($first){ fputcsv($out, array_keys($r)); $first=false; } fputcsv($out, $r); }
fclose($out); exit; ?>