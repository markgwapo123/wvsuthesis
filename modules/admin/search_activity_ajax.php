<?php
require_once __DIR__ . '/../../db_connect.php';

$perPage = 10; 
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$offset = ($page - 1) * $perPage;

$where = [];

// Filters
if (!empty($_POST['uid']))    $where[] = "a.user_id = " . intval($_POST['uid']);
if (!empty($_POST['module'])) $where[] = "a.module LIKE '%" . $conn->real_escape_string($_POST['module']) . "%'";
if (!empty($_POST['action'])) $where[] = "a.action LIKE '%" . $conn->real_escape_string($_POST['action']) . "%'";
if (!empty($_POST['from']))   $where[] = "DATE(a.created_at) >= '" . $conn->real_escape_string($_POST['from']) . "'";
if (!empty($_POST['to']))     $where[] = "DATE(a.created_at) <= '" . $conn->real_escape_string($_POST['to']) . "'";

// Live search - Matches name, action, or module
if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(a.action LIKE '%$s%' OR a.module LIKE '%$s%' OR u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%')";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

/**
 * JOIN FIX:
 * We match 'a.user_id' (from activity_logs) with 'u.id' (from users)
 * We also CONCAT the name since your table stores them separately
 */
$countSQL = "SELECT COUNT(*) AS total 
             FROM activity_logs a 
             LEFT JOIN users u ON a.user_id = u.id 
             $whereSQL";

$countQuery = $conn->query($countSQL);
$total = $countQuery->fetch_assoc()['total'];
$totalPages = ceil($total / $perPage);

// Main Query - Joining names together for display
$query = "
    SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS fullname 
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    $whereSQL
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$res = $conn->query($query);
?>

<table id='activityLogsTable'>
  <thead>
    <tr>
      <th class='sortable'>Log ID</th>
      <th class='sortable'>User ID</th>
      <th class='sortable'>Full Name</th>
      <th class='sortable'>Action</th>
      <th class='sortable'>Module</th>
      <th class='sortable'>Status</th>
      <th class='sortable'>IP Address</th>
      <th class='sortable'>Date/Time</th>
      <th>Details</th>
    </tr>
  </thead>
  <tbody>

<?php
if ($res->num_rows == 0) {
    echo "<tr><td colspan='9' style='text-align:center;'>No logs found matching your criteria.</td></tr>";
} else {
    while ($row = $res->fetch_assoc()) {
        // Use 'id' or 'log_id' depending on your activity_logs table
        $logId     = $row['id'] ?? $row['log_id']; 
        $userId    = $row['user_id'];
        $fullName  = htmlspecialchars($row['fullname'] ?? 'System/Unknown');
        $action    = htmlspecialchars($row['action']);
        $module    = htmlspecialchars($row['module']);
        $status    = htmlspecialchars($row['status']);
        $ip        = htmlspecialchars($row['ip_address']);
        $createdAt = htmlspecialchars($row['created_at']);
        $device    = htmlspecialchars($row['user_agent'] ?? $row['device'] ?? 'Unknown');

        echo "
        <tr>
          <td>$logId</td>
          <td>$userId</td>
          <td><strong>$fullName</strong></td>
          <td>$action</td>
          <td>$module</td>
          <td>$status</td>
          <td>$ip</td>
          <td>$createdAt</td>
          <td>
            <button 
              class='view-details-btn'
              data-log-id='$logId'
              data-user-id='$userId'
              data-user-name='$fullName'
              data-action='$action'
              data-module='$module'
              data-status='$status'
              data-ip='$ip'
              data-device='$device'
              data-created-at='$createdAt'>
              View
            </button>
          </td>
        </tr>";
    }
}
?>
  </tbody>
</table>

<div style="margin-top:15px; text-align:center;">
<?php if ($page > 1): ?>
  <button class="pagination-btn" data-page="<?= $page - 1 ?>">⟵ Prev</button>
<?php endif; ?>

<?php
for ($i = 1; $i <= $totalPages; $i++) {
    $active = ($i == $page) ? "style='background:#007bff; color:white; font-weight:bold; border-color:#007bff;'" : "";
    echo "<button class='pagination-btn' data-page='$i' $active>$i</button>";
}
?>

<?php if ($page < $totalPages): ?>
  <button class="pagination-btn" data-page="<?= $page + 1 ?>">Next ⟶</button>
<?php endif; ?>
</div>