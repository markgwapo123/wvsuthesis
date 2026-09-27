<?php
require_once __DIR__ . '/../../db_connect.php';

/*-------------------------------------------------------
  LOAD SETTINGS
-------------------------------------------------------*/
$settings = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1")->fetch_assoc();
$lowStock = $settings['low_stock_threshold'] ?? 10;

/*-------------------------------------------------------
  PAGINATION
-------------------------------------------------------*/
$page   = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
$limit  = 20;
$offset = ($page - 1) * $limit;

/*-------------------------------------------------------
  FILTERS + LIVE SEARCH
-------------------------------------------------------*/
$where = [];

if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(name LIKE '%$s%' OR department LIKE '%$s%')";
}

if (!empty($_POST['department'])) {
    $d = $conn->real_escape_string($_POST['department']);
    $where[] = "department LIKE '%$d%'";
}

$whereSQL = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/*-------------------------------------------------------
  COUNT ROWS FOR PAGINATION
-------------------------------------------------------*/
$countRes = $conn->query("
    SELECT COUNT(*) AS total FROM (
        SELECT name FROM inventory 
        $whereSQL
        GROUP BY name, department
    ) AS grouped
");
$totalRows = $countRes->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalRows / $limit));

/*-------------------------------------------------------
  MAIN QUERY — GROUP BY ITEM
-------------------------------------------------------*/
// Adjusted query to pull order counts via correlated subquery matching name and department parameters
$sql = "
SELECT 
    MIN(i.inventory_id) AS inventory_id,
    i.name,
    i.category,
    i.department,
    i.status_label,
    SUM(i.quantity) AS remaining_qty,
    i.min_stock,
    COALESCE((
        SELECT SUM(o.qty) 
        FROM orders o 
        WHERE o.item_name = i.name 
          AND (o.department = i.department OR i.department IS NULL OR i.department = '')
          AND o.status IN ('Approved', 'In House', 'Delivered')
    ), 0) AS order_qty
FROM inventory i
$whereSQL
GROUP BY i.name, i.category, i.department, i.min_stock
ORDER BY i.name ASC
LIMIT $limit OFFSET $offset
";

$res = $conn->query($sql);

/*----------------------------------------------
  OUTPUT TABLE (Total: 6 Columns)
----------------------------------------------*/
echo "
<table class='table'>
<thead>
<tr>
  <th>Item/Goods Name</th>
  <th>Order Qty</th>
  <th>Remaining Qty</th>
  <th>Department</th>
  <th>Status</th>
  <th>Min Stock</th>
</tr>
</thead>
<tbody>
";

if ($res->num_rows > 0) {

    while ($r = $res->fetch_assoc()) {

        $cls = "";
        $remainingQty = (int)$r["remaining_qty"];
        $minStock     = (int)$r["min_stock"];

        // Highlighting evaluation scales directly off actual real-time remaining stock levels
        if ($remainingQty <= $minStock) {
            $cls = "low"; 
        }

        echo "
        <tr class='$cls'>
            <td>" . htmlspecialchars($r['name']) . "</td>
            <td>{$r['order_qty']}</td>
            <td>{$remainingQty}</td>
            <td>" . htmlspecialchars($r['department']) . "</td>
            <td>" . htmlspecialchars($r['status_label']) . "</td>
            <td>{$minStock}</td>
        </tr>";
    }

} else {
    echo "<tr><td colspan='6' style='text-align:center;'>No records found.</td></tr>";
}

echo "</tbody></table>";

/*-------------------------------------------------------
  PAGINATION
-------------------------------------------------------*/
echo "<div class='pagination'>";

if ($page > 1) {
    echo "<span class='page-link' data-page='".($page-1)."'>Previous</span>";
}

for ($i = 1; $i <= $totalPages; $i++) {
    $active = ($i == $page) ? "active" : "";
    echo "<span class='page-link $active' data-page='$i'>$i</span>";
}

if ($page < $totalPages) {
    echo "<span class='page-link' data-page='".($page+1)."'>Next</span>";
}

echo "</div>";
?>