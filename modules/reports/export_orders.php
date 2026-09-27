<?php
require_once __DIR__ . 'db_connect.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="orders.csv"');
$out = fopen('php://output', 'w');
$res = $conn->query("SELECT order_id,requestor,department,item_id,qty,order_date,expected_date,status,processed_by,remarks FROM orders");
$first = true;
while($r=$res->fetch_assoc()){ if($first){ fputcsv($out, array_keys($r)); $first=false; } fputcsv($out, $r); }
fclose($out); exit; ?>