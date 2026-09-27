<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="suppliers.csv"');
$out = fopen('php://output', 'w');
$res = $conn->query("SELECT supplier_id,name,contact_person,contact_email,contact_phone,status,updated_at FROM suppliers");
$first = true;
while($r=$res->fetch_assoc()){ if($first){ fputcsv($out, array_keys($r)); $first=false; } fputcsv($out, $r); }
fclose($out); exit; ?>