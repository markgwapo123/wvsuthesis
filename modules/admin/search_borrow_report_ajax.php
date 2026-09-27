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
    $where[] = "(i.name LIKE '%$s%' OR b.borrower_name LIKE '%$s%' OR b.borrower_department LIKE '%$s%' OR b.status LIKE '%$s%')";
}

/* DATE FILTERS */
if (!empty($_POST["from"])) {
    $from = $conn->real_escape_string($_POST["from"]);
    $where[] = "DATE(b.created_at) >= '$from'";
}
if (!empty($_POST["to"])) {
    $to = $conn->real_escape_string($_POST["to"]);
    $where[] = "DATE(b.created_at) <= '$to'";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

/* COUNT ROWS */
$countRes = $conn->query("
    SELECT COUNT(*) AS total
    FROM borrow_item b
    LEFT JOIN inventory i ON b.inventory_id = i.inventory_id
    $whereSQL
");
$totalRows = $countRes->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalRows / $limit));

/* MAIN QUERY */
$sql = "
SELECT 
  b.borrow_id,
  b.inventory_id,
  i.name AS item_name,
  b.borrower_name,
  b.borrower_department,
  b.quantity_borrowed,
  b.quantity_returned,
  b.status,
  b.remarks,
  b.created_at
FROM borrow_item b
LEFT JOIN inventory i ON b.inventory_id = i.inventory_id
$whereSQL
ORDER BY b.created_at DESC
LIMIT $limit OFFSET $offset
";

$res = $conn->query($sql);

/* OUTPUT TABLE */
echo "
<table class='table'>
<thead>
<tr>
  <th>Borrow ID</th>
  <th>Inventory ID</th>
  <th>Item Name</th>
  <th>Borrower</th>
  <th>Department</th>
  <th>Borrowed Qty</th>
  <th>Returned Qty</th>
  <th>Status</th>
  <th>Remarks</th>
  <th>Date</th>
</tr>
</thead>
<tbody>
";

if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        echo "
        <tr>
          <td>{$r['borrow_id']}</td>
          <td>{$r['inventory_id']}</td>
          <td>" . htmlspecialchars($r['item_name']) . "</td>
          <td>" . htmlspecialchars($r['borrower_name']) . "</td>
          <td>" . htmlspecialchars($r['borrower_department']) . "</td>
          <td>{$r['quantity_borrowed']}</td>
          <td>{$r['quantity_returned']}</td>
          <td>{$r['status']}</td>
          <td>" . htmlspecialchars($r['remarks']) . "</td>
          <td>{$r['created_at']}</td>
        </tr>
        ";
    }
} else {
    echo "<tr><td colspan='10'>No borrowed records found.</td></tr>";
}

echo "</tbody></table>";

/* PAGINATION OUTPUT */
echo "<div class='pagination'>";
if ($page > 1) echo "<span class='page-link' data-page='".($page-1)."'>Previous</span>";

for ($i=1; $i<=$totalPages; $i++) {
    $active = $i == $page ? "active" : "";
    echo "<span class='page-link $active' data-page='$i'>$i</span>";
}

if ($page < $totalPages) echo "<span class='page-link' data-page='".($page+1)."'>Next</span>";
echo "</div>";
