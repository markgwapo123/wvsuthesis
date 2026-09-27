<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId     = $_SESSION['user_id'] ?? 0;
$name       = trim($_POST['name'] ?? '');
$department = trim($_POST['department'] ?? '');
$mode       = $_POST['mode'] ?? 'all'; // "all" or "one"

// Department fallback for "No Department" label
$deptDB = ($department === 'No Department') ? '' : $department;

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Missing item name']);
    exit;
}

try {
    // 1) Ensure archive_all exists (same structure as other archive code)
    $conn->query("
        CREATE TABLE IF NOT EXISTS archive_all (
          archive_id INT AUTO_INCREMENT PRIMARY KEY,
          table_name VARCHAR(100) NOT NULL,
          record_id INT NOT NULL,
          data LONGTEXT NOT NULL,
          deleted_by INT DEFAULT NULL,
          deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          purge_at DATETIME GENERATED ALWAYS AS (deleted_at + INTERVAL 30 DAY) STORED,
          INDEX idx_table_record (table_name, record_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    if ($conn->error) {
        throw new Exception('Failed to ensure archive_all: ' . $conn->error);
    }

    // 2) Fetch records to delete
    if ($mode === 'one') {
        // delete only the last (or first) matching record
        $stmt = $conn->prepare("
            SELECT * FROM inventory
            WHERE name = ?
              AND COALESCE(department,'') = ?
            ORDER BY inventory_id DESC
            LIMIT 1
        ");
        $stmt->bind_param("ss", $name, $deptDB);
    } else {
        // delete all items in the group
        $stmt = $conn->prepare("
            SELECT * FROM inventory
            WHERE name = ?
              AND COALESCE(department,'') = ?
        ");
        $stmt->bind_param("ss", $name, $deptDB);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();

    if (empty($rows)) {
        throw new Exception("No matching inventory records found for deletion.");
    }

    // 3) Archive each selected row to archive_all
    $deletedCount = 0;
    foreach ($rows as $row) {
        $invId   = (int)$row['inventory_id'];
        $json    = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonEsc = $conn->real_escape_string($json);
        $delBy   = (int)$userId;

        $conn->query("
            INSERT INTO archive_all (table_name, record_id, data, deleted_by)
            VALUES ('inventory', {$invId}, '{$jsonEsc}', {$delBy})
        ");
        if ($conn->error) {
            throw new Exception("Archiving failed: " . $conn->error);
        }

        $deletedCount++;
    }

    // 4) Delete from inventory
    if ($mode === 'one') {
        $invId = (int)$rows[0]['inventory_id'];
        $stmt = $conn->prepare("DELETE FROM inventory WHERE inventory_id = ?");
        $stmt->bind_param("i", $invId);
        $stmt->execute();
        $stmt->close();

        log_activity(
            $userId,
            "Deleted ONE inventory item (ID {$invId}) for '{$name}' / '{$department}'",
            "Inventory Group Delete",
            "Success",
            $conn
        );
    } else {
        $stmt = $conn->prepare("
            DELETE FROM inventory
            WHERE name = ?
              AND COALESCE(department,'') = ?
        ");
        $stmt->bind_param("ss", $name, $deptDB);
        $stmt->execute();
        $stmt->close();

        log_activity(
            $userId,
            "Deleted ALL inventory items ({$deletedCount}) for '{$name}' / '{$department}'",
            "Inventory Group Delete",
            "Success",
            $conn
        );
    }

    echo json_encode([
        'success' => true,
        'deleted' => $deletedCount,
        'mode'    => $mode
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
