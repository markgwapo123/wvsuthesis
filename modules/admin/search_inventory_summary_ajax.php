<?php
require_once __DIR__ . '/../../db_connect.php';

/*-------------------------------------------------------
  LOAD USER SETTINGS (thresholds)
-------------------------------------------------------*/
$settings   = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1")->fetch_assoc() ?? [];
$lowStock   = isset($settings['low_stock_threshold']) ? (int)$settings['low_stock_threshold'] : 10;
$expDays    = isset($settings['expiring_days_threshold']) ? (int)$settings['expiring_days_threshold'] : 7;

/*-------------------------------------------------------
  PAGINATION
-------------------------------------------------------*/
$page   = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
$limit  = 20;
$offset = ($page - 1) * $limit;

/*-------------------------------------------------------
  BUILD WHERE CONDITIONS
-------------------------------------------------------*/
$where = [];

// Live search
if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(name LIKE '%$s%' 
                 OR category LIKE '%$s%' 
                 OR department LIKE '%$s%' 
                 OR location LIKE '%$s%'
                 OR condition_label LIKE '%$s%'
                 OR status_label LIKE '%$s%')";
}

// Filters
$filters = [
    'category'        => 'category',
    'status_label'    => 'status_label',
    'condition_label' => 'condition_label'
];

foreach ($filters as $postKey => $column) {
    if (!empty($_POST[$postKey])) {
        $v = $conn->real_escape_string($_POST[$postKey]);
        $where[] = "$column LIKE '%$v%'";
    }
}

// Date filters
if (!empty($_POST['from'])) {
    $from = $conn->real_escape_string($_POST['from']);
    $where[] = "DATE(updated_at) >= '$from'";
}
if (!empty($_POST['to'])) {
    $to = $conn->real_escape_string($_POST['to']);
    $where[] = "DATE(updated_at) <= '$to'";
}

// Minimum stock
if (!empty($_POST['min_qty'])) {
    $minq = (int)$_POST['min_qty'];
    $where[] = "quantity <= $minq";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

/*-------------------------------------------------------
  COUNT GROUPS FOR PAGINATION
-------------------------------------------------------*/
$countSql = "
    SELECT COUNT(*) AS total_groups
    FROM (
        SELECT 1
        FROM inventory
        $whereSQL
        GROUP BY name, unit, condition_label, status_label, department, category
    ) AS g
";
$countRes   = $conn->query($countSql);
$totalRows  = $countRes ? (int)$countRes->fetch_assoc()['total_groups'] : 0;
$totalPages = max(1, ceil($totalRows / $limit));

/*-------------------------------------------------------
  MAIN QUERY (Group by item)
-------------------------------------------------------*/
$sql = "
SELECT
    MIN(inventory_id) AS inventory_id,
    name,
    category,
    SUM(quantity) AS total_qty,
    unit,
    condition_label,
    status_label,
    department,
    MIN(location) AS location,
    MIN(expiration_date) AS min_exp,
    MAX(expiration_date) AS max_exp
FROM inventory
$whereSQL
GROUP BY 
    name, unit, condition_label, status_label, department, category
ORDER BY name ASC
LIMIT $limit OFFSET $offset
";
$res = $conn->query($sql);

/*-------------------------------------------------------
  OUTPUT TABLE
-------------------------------------------------------*/
echo "<table class='table'>
<thead>
<tr>
  <th>ID</th>
  <th>Name</th>
  <th>Category</th>
  <th>Qty</th>
  <th>Unit</th>
  <th>Condition</th>
  <th>Status</th>
  <th>Location</th>
  <th>Department</th>
  <th>Expiration</th>
</tr>
</thead>
<tbody>
";

$today = new DateTime();
$totalQtyAll = 0;

if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {

        $qty  = (int)$r['total_qty'];
        $cond = $r['condition_label'];
        $stat = $r['status_label'];

        $minExp = $r['min_exp'];
        $maxExp = $r['max_exp'];

        // Compute days left
        $daysLeft = null;
        if ($minExp) {
            $expDate = new DateTime($minExp);
            $diff = $today->diff($expDate);
            $daysLeft = ($expDate < $today) ? -$diff->days : $diff->days;
        }

        // Expiration display
        $expDisplay = "-";
        if ($minExp && $maxExp && $minExp !== $maxExp)
            $expDisplay = "$minExp to $maxExp";
        elseif ($minExp)
            $expDisplay = $minExp;

        /*-------------------------------------------------------
          ROW COLORING
        -------------------------------------------------------*/
        $cls = "";

        // Expired (by condition OR expiration date)
        if ($cond === "Expired" || ($daysLeft !== null && $daysLeft < 0)) {
            $cls = "expired";
        }
        // Out of stock
        elseif ($stat === "Out of Stock" || $qty <= 0) {
            $cls = "out";
        }
        // Low stock or expiring soon
        elseif ($qty <= $lowStock || ($daysLeft !== null && $daysLeft <= $expDays)) {
            $cls = "low";
        }

        $totalQtyAll += $qty;

        echo "
        <tr class='{$cls}'>
            <td>{$r['inventory_id']}</td>
            <td>".htmlspecialchars($r['name'])."</td>
            <td>".htmlspecialchars($r['category'])."</td>
            <td>{$qty}</td>
            <td>".htmlspecialchars($r['unit'])."</td>
            <td>".htmlspecialchars($cond)."</td>
            <td>".htmlspecialchars($stat)."</td>
            <td>".htmlspecialchars($r['location'])."</td>
            <td>".htmlspecialchars($r['department'])."</td>
            <td>{$expDisplay}</td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='10'>No items found.</td></tr>";
}

echo "
<tr>
  <td colspan='3'><b>Total</b></td>
  <td colspan='7'><b>{$totalQtyAll} items</b></td>
</tr>
</tbody>
</table>";

/*-------------------------------------------------------
  PAGINATION
-------------------------------------------------------*/
echo "<div class='pagination'>";
if ($page > 1)
    echo "<span class='page-link' data-page='".($page-1)."'>Previous</span>";

for ($i = 1; $i <= $totalPages; $i++) {
    $active = ($i == $page) ? "active" : "";
    echo "<span class='page-link {$active}' data-page='{$i}'>{$i}</span>";
}

if ($page < $totalPages)
    echo "<span class='page-link' data-page='".($page+1)."'>Next</span>";

echo "</div>";
