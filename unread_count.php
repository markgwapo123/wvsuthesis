<?php
require_once __DIR__ . '/db_connect.php';
$res = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0");
$count = ($res && $r=$res->fetch_assoc()) ? (int)$r['c'] : 0;
header('Content-Type: application/json');
echo json_encode(['count'=>$count]);
