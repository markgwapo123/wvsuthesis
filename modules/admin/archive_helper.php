<?php
require_once __DIR__ . '/db_connect.php';

function archive_record($table, $recordId, $deletedBy)
{
    global $conn;

    // Fetch record
    $recordId = (int)$recordId;
    $deletedBy = (int)$deletedBy;
    $table = $conn->real_escape_string($table);

    $res = $conn->query("SELECT * FROM `$table` WHERE " . get_primary_key($table) . " = $recordId LIMIT 1");
    if (!$res || $res->num_rows == 0) {
        return false;
    }

    $row = $res->fetch_assoc();
    $jsonData = $conn->real_escape_string(json_encode($row, JSON_UNESCAPED_UNICODE));

    $sql = "
        INSERT INTO archive_all (table_name, record_id, data, deleted_by, deleted_at, purge_at)
        VALUES ('$table', $recordId, '$jsonData', $deletedBy, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY))
    ";
    $ok = $conn->query($sql);

    // Delete original record
    if ($ok) {
        $conn->query("DELETE FROM `$table` WHERE " . get_primary_key($table) . " = $recordId");
    }

    return $ok;
}

function get_primary_key($table)
{
    static $map = [
        'inventory' => 'inventory_id',
        'items'     => 'item_id',
        'suppliers' => 'supplier_id',
        'users'     => 'id',
        'orders'    => 'order_id',
    ];
    return $map[$table] ?? 'id';
}
?>
