<?php
require_once __DIR__ . '/../../db_connect.php';

$today = date("Y-m-d");

/* Pagination */
$page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where = [];

/* 1. Live Search */
if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(name LIKE '%$s%' OR category LIKE '%$s%' OR department LIKE '%$s%')";
}

/* 2. Filters (Category, Status, Condition) */
$filters = [
    "category" => "category",
    "status_label" => "status_label",
    "condition_label" => "condition_label"
];

foreach ($filters as $key => $col) {
    if (!empty($_POST[$key])) {
        $v = $conn->real_escape_string($_POST[$key]);
        $where[] = "$col LIKE '%$v%'";
    }
}

/* 3. Days filter (FIXED: Should be <= to include expired items) */
$daysThreshold = !empty($_POST["days"]) ? intval($_POST["days"]) : 30;
$where[] = "DATEDIFF(expiration_date, CURDATE()) <= $daysThreshold";

/* 4. Must have expiration date */
$where[] = "expiration_date IS NOT NULL";

// Combine filters
$whereSQL = "WHERE " . implode(" AND ", $where);

/* 5. Count rows for pagination */
$countResult = $conn->query("SELECT COUNT(*) AS total FROM inventory $whereSQL");
$count = ($countResult) ? $countResult->fetch_assoc()['total'] : 0;
$totalPages = max(1, ceil($count / $limit));

/* 6. Main Query (FIXED: Now uses the $whereSQL filters) */
$sql = "
SELECT 
    inventory_id,
    name,
    category,
    expiration_date,
    DATEDIFF(expiration_date, CURDATE()) AS days_left,
    condition_label,
    status_label
FROM inventory
$whereSQL 
ORDER BY expiration_date ASC
LIMIT $limit OFFSET $offset
";

$res = $conn->query($sql);

/* 7. Output Table */
echo "
<table class='table'>
<thead>
<tr>
  <th>ID</th>
  <th>Name</th>
  <th>Category</th>
  <th>Expiration Date</th>
  <th>Days Left</th>
  <th>Condition</th>
  <th>Status</th>
</tr>
</thead>
<tbody>
";

if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        $days = intval($r["days_left"]);
        $cls = "";
        $status_text = "";

        // Logic for Row Highlighting
        if ($days < 0) {
            $cls = "expired";       // Red background
            $status_text = "EXPIRED";
        } elseif ($days <= 30) {
            $cls = "soon";          // Yellow background
            $status_text = "Expiring Soon";
        } else {
            $status_text = "Good";
        }

        echo "
        <tr class='$cls'>
            <td>{$r['inventory_id']}</td>
            <td>".htmlspecialchars($r['name'])."</td>
            <td>".htmlspecialchars($r['category'])."</td>
            <td>" . date('M d, Y', strtotime($r['expiration_date'])) . "</td>
            <td>" . ($days < 0 ? "Expired (".abs($days)." days ago)" : $days . " days") . "</td>
            <td>" . htmlspecialchars($r['condition_label']) . "</td>
            <td><strong>$status_text</strong></td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='7' style='padding:20px;'>No items found matching the current filters.</td></tr>";
}

echo "</tbody></table>";

/* 8. Pagination Links */
echo "<div class='pagination' style='margin-top:20px; display:flex; justify-content:center; gap:5px;'>";
if ($page > 1) {
    echo "<span class='page-link' style='cursor:pointer; padding:5px 10px; border:1px solid #ccc;' data-page='".($page-1)."'>&laquo; Prev</span>";
}

for ($i=1; $i <= $totalPages; $i++) {
    $activeStyle = ($i == $page) ? "background:#007bff; color:white; border-color:#007bff;" : "";
    echo "<span class='page-link' style='cursor:pointer; padding:5px 10px; border:1px solid #ccc; $activeStyle' data-page='$i'>$i</span>";
}

if ($page < $totalPages) {
    echo "<span class='page-link' style='cursor:pointer; padding:5px 10px; border:1px solid #ccc;' data-page='".($page+1)."'>Next &raquo;</span>";
}
echo "</div>";