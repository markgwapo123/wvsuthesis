<?php
require_once __DIR__ . '/auth.php';
allow_roles(['admin']); // only admin can delete any record
require_once __DIR__ . '/db_connect.php';


header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$department = trim($_POST['department'] ?? '');
$mode = $_POST['mode'] ?? 'all'; // "all" or "one"

// Basic validation
if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Missing item name']);
    exit;
}

// Department fallback for "No Department" label
$deptDB = ($department === 'No Department') ? '' : $department;

try {
    // Ensure archive_all exists (same structure as delete_record.php)
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
        throw new Exception('Failed to ensure archive_all table: ' . $conn->error);
    }

    // Delete ALL items in the group
    if ($mode === 'all') {
        $stmt = $conn->prepare("
            SELECT * 
            FROM inventory 
            WHERE name = ? 
              AND COALESCE(department,'') = ?
        ");
        $stmt->bind_param("ss", $name, $deptDB);
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

        $deletedCount = 0;

        foreach ($rows as $row) {
            $invId = (int)$row['inventory_id'];
            $jsonData = $conn->real_escape_string(
                json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $deletedBy = (int)$userId;

            $conn->query("
                INSERT INTO archive_all (table_name, record_id, data, deleted_by)
                VALUES ('inventory', {$invId}, '{$jsonData}', {$deletedBy})
            ");

            if ($conn->error) {
                throw new Exception("Archiving failed: " . $conn->error);
            }

            $deletedCount++;
        }

        // Delete from inventory
        $del = $conn->prepare("
            DELETE FROM inventory 
            WHERE name = ? 
              AND COALESCE(department,'') = ?
        ");
        $del->bind_param("ss", $name, $deptDB);
        $del->execute();
        $del->close();

        log_activity(
            $userId,
            "Deleted ALL inventory items for '{$name}' / '{$department}'",
            "Inventory Group Delete",
            "Success",
            $conn
        );

        echo json_encode([
            'success' => true,
            'deleted' => $deletedCount,
            'mode'    => 'all'
        ]);
        exit;
    }

    // Delete only ONE item in the group (first match)
    $stmt = $conn->prepare("
        SELECT * 
        FROM inventory 
        WHERE name = ? 
          AND COALESCE(department,'') = ?
        ORDER BY inventory_id ASC
        LIMIT 1
    ");
    $stmt->bind_param("ss", $name, $deptDB);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception("No matching inventory record found to delete.");
    }

    $invId = (int)$row['inventory_id'];

    $jsonData = $conn->real_escape_string(
        json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    $deletedBy = (int)$userId;

    $conn->query("
        INSERT INTO archive_all (table_name, record_id, data, deleted_by)
        VALUES ('inventory', {$invId}, '{$jsonData}', {$deletedBy})
    ");

    if ($conn->error) {
        throw new Exception("Archiving failed: " . $conn->error);
    }

    // Delete that one row
    $del = $conn->prepare("DELETE FROM inventory WHERE inventory_id = ?");
    $del->bind_param("i", $invId);
    $del->execute();
    $del->close();

    log_activity(
        $userId,
        "Deleted ONE inventory item (ID {$invId}) for '{$name}' / '{$department}'",
        "Inventory Group Delete",
        "Success",
        $conn
    );

    echo json_encode([
        'success' => true,
        'deleted' => 1,
        'mode'    => 'one'
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
