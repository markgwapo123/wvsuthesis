<?php
require_once __DIR__ . '/../../db_connect.php';

/* Pagination */
$page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where = [];

/* LIVE SEARCH */
if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(r.item_name LIKE '%$s%' 
                 OR r.borrower_name LIKE '%$s%'
                 OR r.department LIKE '%$s%'
                 OR r.remarks LIKE '%$s%')";
}

/* DATE FILTERS */
if (!empty($_POST["from"])) {
    $from = $conn->real_escape_string($_POST["from"]);
    $where[] = "DATE(r.return_date) >= '$from'";
}
if (!empty($_POST["to"])) {
    $to = $conn->real_escape_string($_POST["to"]);
    $where[] = "DATE(r.return_date) <= '$to'";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

/* COUNT ROWS */
$count = $conn->query("SELECT COUNT(*) AS total FROM return_item r $whereSQL")->fetch_assoc()['total'];
$totalPages = max(1, ceil($count / $limit));

/* MAIN QUERY */
$sql = "
SELECT 
    r.return_id,
    r.borrow_id,
    r.inventory_id,
    r.item_name,
    r.quantity_returned,
    r.condition_label,
    r.remarks,
    r.processed_by,
    r.department,
    r.borrower_name,
    r.return_date
FROM return_item r
$whereSQL
ORDER BY r.return_date DESC
LIMIT $limit OFFSET $offset
";

$res = $conn->query($sql);

/* OUTPUT TABLE */
echo "
<table class='table'>
<thead>
<tr>
  <th>Return ID</th>
  <th>Borrow ID</th>
  <th>Inventory ID</th>
  <th>Item Name</th>
  <th>Returned Qty</th>
  <th>Condition</th>
  <th>Department</th>
  <th>Borrower</th>
  <th>Processed By</th>
  <th>Remarks</th>
  <th>Date Returned</th>
</tr>
</thead>
<tbody>
";

if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        echo "
        <tr>
          <td>{$r['return_id']}</td>
          <td>{$r['borrow_id']}</td>
          <td>{$r['inventory_id']}</td>
          <td>" . htmlspecialchars($r['item_name']) . "</td>
          <td>{$r['quantity_returned']}</td>
          <td>" . htmlspecialchars($r['condition_label']) . "</td>
          <td>" . htmlspecialchars($r['department']) . "</td>
          <td>" . htmlspecialchars($r['borrower_name']) . "</td>
          <td>" . htmlspecialchars($r['processed_by']) . "</td>
          <td>" . htmlspecialchars($r['remarks']) . "</td>
          <td>{$r['return_date']}</td>
        </tr>
        ";
    }
} else {
    echo "<tr><td colspan='11'>No return records found.</td></tr>";
}

echo "</tbody></table>";

/* PAGINATION */
echo "<div class='pagination'>";
if ($page > 1) echo "<span class='page-link' data-page='".($page-1)."'>Previous</span>";

for ($i = 1; $i <= $totalPages; $i++) {
    $active = $i == $page ? "active" : "";
    echo "<span class='page-link $active' data-page='$i'>$i</span>";
}

if ($page < $totalPages) echo "<span class='page-link' data-page='".($page+1)."'>Next</span>";
echo "</div>";
