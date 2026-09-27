<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../auth.php'; // Ensure the user has the proper role

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_role = $_SESSION['role'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? 0;

// ==========================================
// HANDLE ACTIONS (Delete / Mark Read)
// ==========================================
// Note: These actions only apply to manual notifications in the 'notifications' table.

if (isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    $deleteId = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Notification deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete']);
    }
    exit();
}

if (isset($_POST['mark_read_id']) && is_numeric($_POST['mark_read_id'])) {
    $markId = intval($_POST['mark_read_id']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $markId);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Marked as read']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update']);
    }
    exit();
}

// ==========================================
// SEARCH & FILTER LOGIC
// ==========================================

$page  = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build the Union Query based on Role
// We combine: 
// 1. Manual Notifications
// 2. Pending Requests (System)
// 3. Pending Orders (System)

// Common fields: id, message, type, created_at, is_read, source, link

// 1. Base Notifications Query
$sql_parts = [];

// -- Part A: Manual Notifications table
$sql_parts[] = "SELECT 
                    id, 
                    message, 
                    type, 
                    created_at, 
                    is_read, 
                    'manual' AS source, 
                    '' AS link 
                FROM notifications";

// -- Part B: System Alerts (Role specific)
if ($current_role === 'admin' || $current_role === 'subadmin') {
    // Admin sees Pending Requests
    $sql_parts[] = "SELECT 
                        request_id AS id, 
                        CONCAT('New Request: ', qty_requested, ' ', item_name, ' from ', requester_name) AS message, 
                        'Request' AS type, 
                        requested_at AS created_at, 
                        0 AS is_read, 
                        'system' AS source, 
                        'admin_request.php' AS link 
                    FROM requests 
                    WHERE status = 'Pending'";

    // Admin sees Pending Orders
    $sql_parts[] = "SELECT 
                        order_id AS id, 
                        CONCAT('Pending Order: ', qty, ' ', item_name, ' (', receiver, ')') AS message, 
                        'Order' AS type, 
                        created_at AS created_at, 
                        0 AS is_read, 
                        'system' AS source, 
                        'order_management.php' AS link 
                    FROM orders 
                    WHERE status = 'Pending'";
} elseif ($current_role === 'end_user') {
    // End User sees updates on THEIR requests
    $sql_parts[] = "SELECT 
                        request_id AS id, 
                        CONCAT('Request Update: ', item_name, ' is ', status) AS message, 
                        'Status Update' AS type, 
                        requested_at AS created_at, 
                        0 AS is_read, 
                        'system' AS source, 
                        'request_history.php' AS link 
                    FROM requests 
                    WHERE requester_user_id = $current_user_id 
                    AND status IN ('Approved', 'Rejected', 'Issued')";
}

// Combine queries
$full_query_base = implode(" UNION ALL ", $sql_parts);

// Apply Search Filters to the combined result
$search_condition = "";
if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $search_condition = "WHERE (message LIKE '%$s%' OR type LIKE '%$s%')";
}

// Final Query with Order and Limit
$final_sql = "SELECT * FROM ($full_query_base) AS combined_table $search_condition ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$count_sql = "SELECT COUNT(*) as total FROM ($full_query_base) AS combined_table $search_condition";

// Execute Count
$countRes = $conn->query($count_sql);
$totalRows = $countRes ? (int)$countRes->fetch_assoc()['total'] : 0;
$totalPages = $totalRows > 0 ? ceil($totalRows / $limit) : 1;

// Pagination Logic
$pageRange = 3;
$startPage = max(1, $page - floor($pageRange / 2));
$endPage = min($startPage + $pageRange - 1, $totalPages);
if ($endPage - $startPage + 1 < $pageRange) {
    $startPage = max(1, $endPage - $pageRange + 1);
}

// Execute Data Query
$res = $conn->query($final_sql);

// ==========================================
// HTML OUTPUT
// ==========================================
echo "
<style>
    body { font-family: 'Poppins', sans-serif; background-color: #f4f6f8; margin: 0; padding: 0; }
    .table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    
    th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
    th { background: #007bff; color: white; text-transform: uppercase; font-weight: 600; font-size: 13px; }
    
    tr:nth-child(even) { background-color: #fcfcfc; }
    tr.unread { background-color: #e8f4ff; font-weight: 600; color: #000; }
    
    .action-link { cursor: pointer; text-decoration: none; font-size: 13px; margin-right: 10px; font-weight: normal; }
    .mark-read { color: #28a745; }
    .delete-notif { color: #dc3545; }
    .view-link { color: #17a2b8; font-weight: bold; }
    .action-link:hover { text-decoration: underline; }

    .type-badge { padding: 4px 8px; border-radius: 4px; background: #eee; font-size: 12px; color: #333; font-weight: normal; }
    .type-Request { background: #fff3cd; color: #856404; }
    .type-Order { background: #d1ecf1; color: #0c5460; }

    .pagination { display: flex; justify-content: center; margin-top: 20px; gap: 5px; }
    .page-link { padding: 8px 12px; cursor: pointer; border-radius: 4px; background: #fff; border: 1px solid #ddd; color: #007bff; font-size: 13px; }
    .page-link.active { background: #007bff; color: white; border-color: #007bff; }
    .no-result { text-align: center; color: #888; padding: 30px; }
</style>

<table class='table'>
  <thead>
    <tr>
      <th>Message</th>
      <th>Type</th>
      <th>Date</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
";

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id'];
        $message = htmlspecialchars($row['message']);
        $type = htmlspecialchars($row['type']);
        $source = $row['source'];
        $link = $row['link'];
        $created_at = date("M d, Y h:i A", strtotime($row['created_at']));
        
        // Manual notifications have read/unread state. System ones are always "active" until processed.
        $is_read = ($row['is_read'] || $source == 'system') ? 'read' : 'unread';
        $row_class = ($source == 'system') ? 'unread' : $is_read; 

        echo "<tr class='$row_class'>
                <td>$message</td>
                <td><span class='type-badge type-$type'>$type</span></td>
                <td>$created_at</td>
                <td>";
        
        if ($source === 'manual') {
            // Standard actions for manual notifications
            if (!$row['is_read']) {
                echo "<a href='javascript:void(0);' class='action-link mark-read' onclick='markAsRead($id)'>Mark as Read</a>";
            } else {
                echo "<span style='color:#ccc; font-size:12px; margin-right:10px;'>Read</span>";
            }
            echo "<a href='javascript:void(0);' class='action-link delete-notif' onclick='deleteNotification($id)'>Delete</a>";
        } else {
            // System actions (Link to manage page)
            if (!empty($link)) {
                echo "<a href='$link' class='action-link view-link'>View Details</a>";
            }
        }

        echo "</td></tr>";
    }
} else {
    echo "<tr><td colspan='4' class='no-result'>No notifications found.</td></tr>";
}

echo "</tbody></table>";

// Pagination
echo "<div class='pagination'>";
if ($page > 1) {
    echo "<span class='page-link' data-page='" . ($page - 1) . "'>&laquo; Prev</span>";
}
for ($i = $startPage; $i <= $endPage; $i++) {
    $active = ($i == $page) ? "active" : "";
    echo "<span class='page-link {$active}' data-page='{$i}'>{$i}</span>";
}
if ($page < $totalPages) {
    echo "<span class='page-link' data-page='" . ($page + 1) . "'>Next &raquo;</span>";
}
echo "</div>";
?>