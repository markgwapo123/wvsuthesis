<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../auth.php';   // IMPORTANT: Needed for role permissions

$page  = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];

// ============================================
// ROLE-BASED RESTRICTIONS (End User Enforcement)
// ============================================
if (isset($_SESSION['role']) && $_SESSION['role'] === 'end_user') {
    $myDepartment = $conn->real_escape_string($_SESSION['department'] ?? '');
    $where[] = "department = '$myDepartment'";
}

/*----------------------------------------------
  LIVE SEARCH
----------------------------------------------*/
if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(name LIKE '%$s%' 
                  OR category LIKE '%$s%' OR status_label LIKE '%$s%'
                  OR COALESCE(department,'') LIKE '%$s%')";
}

/*----------------------------------------------
  FILTERS
----------------------------------------------*/
if (!empty($_POST['department']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'end_user')) {
    $d = $conn->real_escape_string($_POST['department']);
    $where[] = "COALESCE(department,'No Department') LIKE '%$d%'";
}

if (!empty($_POST['category'])) {
    $c = $conn->real_escape_string($_POST['category']);
    $where[] = "category LIKE '%$c%'";
}

if (!empty($_POST['status_label'])) {
    $c = $conn->real_escape_string($_POST['status_label']); 
    $where[] = "status_label LIKE '%$c%'";
}

if (!empty($_POST['condition_label'])) {
     $cond = $conn->real_escape_string($_POST['condition_label']);
     $where[] = "condition_label LIKE '%$cond%'";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

/*----------------------------------------------
  COUNT GROUPS
----------------------------------------------*/
$countSql = "
    SELECT COUNT(*) AS total_groups
    FROM (
        SELECT 1
        FROM inventory
        $whereSQL
        GROUP BY name, COALESCE(department,'No Department')
    ) AS g
";
$countRes = $conn->query($countSql);
$totalRows = $countRes ? (int)$countRes->fetch_assoc()['total_groups'] : 0;
$totalPages = max(1, ceil($totalRows / $limit));

/*----------------------------------------------
  FETCH GROUPED INVENTORY (Orders Table Removed)
----------------------------------------------*/
$query = "
    SELECT 
        MIN(i.inventory_id) AS inventory_id, 
        i.name,
        COALESCE(i.department,'No Department') AS department,
        SUM(i.quantity) AS total_qty,
        SUM(i.initial_stock) AS total_initial_stock, /* Permanent non-decreasing stock metric */
        MIN(i.min_stock) AS min_stock,
        GROUP_CONCAT(DISTINCT i.unit) AS units,
        GROUP_CONCAT(DISTINCT i.category) AS categories,
        GROUP_CONCAT(DISTINCT i.condition_label) AS conds,
        GROUP_CONCAT(DISTINCT i.status_label) AS statuses,
        MIN(i.expiration_date) AS min_expiration,
        MAX(i.expiration_date) AS max_expiration
    FROM inventory i
    $whereSQL
    GROUP BY i.name, COALESCE(i.department,'No Department')
    ORDER BY i.name ASC
    LIMIT $limit OFFSET $offset
";

$res = $conn->query($query);

/*----------------------------------------------
  HELPER — NORMALIZE LIST LABEL
----------------------------------------------*/
function normalizeListLabel(?string $csv)
{
  if (!$csv) return '-';

  $parts = array_unique(array_filter(
    array_map('trim', explode(',', $csv)),
    fn($v) => $v !== '' && $v !== 'NULL'
  ));

  if (count($parts) === 0) return '-';
  if (count($parts) === 1) return $parts[0];

  return "Mixed";
}

/*----------------------------------------------
  TABLE OUTPUT
----------------------------------------------*/
echo "
<style>
.badge {
  padding: 4px 8px;
  border-radius: 4px;
  color: white;
  font-size: 12px;
  font-weight: bold;
}
.badge-available { background: #28a745; }
.badge-low { background: #ffc107; color:black; }
.badge-out { background: #dc3545; }
.badge-disposed { background: #6c757d; }
.edit-btn {
  padding: 5px 8px; background:#007bff; border:none; color:white;
  cursor:pointer; border-radius:3px; font-size:12px;
}
.delete-btn {
  padding: 5px 8px; background:#dc3545; border:none; color:white;
  cursor:pointer; border-radius:3px; font-size:12px;
}
</style>
";

echo "
<table class='table'>
  <thead>
    <tr>
      <th>Item Name</th>
      <th>Department</th>
      <th>Initial Stock Qty</th>
      <th>Remaining Qty</th>
      <th>Unit</th>
      <th>Category</th>
      <th>Condition</th>
      <th>Status</th>
      <th>Min Stock</th>
      <th>Expiration</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
";

if (!$res || $res->num_rows === 0) {
  echo "<tr><td colspan='11' style='text-align:center;'>No items found.</td></tr>";
} else {
  while ($row = $res->fetch_assoc()) {

    $name = $row['name'];
    $dept = $row['department'];
    $inventory_id = (int)$row['inventory_id'];

    $nameParam = urlencode($name);
    $deptParam = urlencode($dept);

    /* CLEAN VALUES */
    $initialStockQty = (int)$row['total_initial_stock']; 
    $remainingQty    = (int)$row['total_qty']; 
    $minStock        = isset($row['min_stock']) ? (int)$row['min_stock'] : null;

    $unit      = normalizeListLabel($row['units']);
    $category  = normalizeListLabel($row['categories']);
    $condition = normalizeListLabel($row['conds']);
    $status    = normalizeListLabel($row['statuses']);

    $minExp = $row['min_expiration'];
    $maxExp = $row['max_expiration'];
    $expDisplay = $minExp
      ? ($minExp !== $maxExp ? "$minExp to $maxExp" : $minExp)
      : "-";

    // Update status display rules using Remaining Qty tracking values directly
    if ($remainingQty == 0) {
        $status = 'Out of Stock';
    } elseif (in_array(strtolower($category), ['expired', 'damage', 'lost'])) {
        $status = 'Disposed';
    } elseif ($remainingQty <= $minStock) {
        $status = 'Low Stock';
    } elseif ($status == 'Borrowed') {
        $status = 'Borrowed';
    } elseif ($remainingQty > $minStock) {
        $status = 'Available';
    }

    echo "<tr>
          <td>" . htmlspecialchars($name) . "</td>
          <td>" . htmlspecialchars($dept) . "</td>
          <td>{$initialStockQty}</td>
          <td>{$remainingQty}</td>
          <td>" . htmlspecialchars($unit) . "</td>
          <td>" . htmlspecialchars($category) . "</td>
          <td>" . htmlspecialchars($condition) . "</td>
          <td>" . htmlspecialchars($status) . "</td>
          <td>" . htmlspecialchars($minStock ?? '-') . "</td>
          <td>{$expDisplay}</td>
          <td>
        ";

    /*----------------------------------------------
        ROLE-BASED ACTION BUTTONS
    ----------------------------------------------*/
    if (is_role('admin')) {
      echo "
        <button class='edit-btn'
            onclick=\"window.location.href='edit_record.php?type=inventory&group=1&name={$nameParam}&department={$deptParam}'\">
          Edit
        </button>
      ";

      echo "
        <button class='delete-btn'
          onclick=\"deleteGroup('" . htmlspecialchars($name, ENT_QUOTES) . "','" . htmlspecialchars($dept, ENT_QUOTES) . "')\">
          Delete
        </button>
      ";

    } elseif (is_role('subadmin')) {
      echo "<span class='badge badge-low'>View Only</span>";
    } elseif (is_role('end_user')) {
        echo "<span class='badge badge-available'>View Only</span>";
    } else {
      echo "<span class='badge badge-low'>Restricted</span>";
    }

    echo "</td></tr>";
  }
}

echo "</tbody></table>";

/*----------------------------------------------
  PAGINATION
----------------------------------------------*/
echo "<div class='pagination'>";

if ($page > 1) {
  echo "<span class='page-link' data-page='" . ($page - 1) . "'>Previous</span>";
}

for ($i = 1; $i <= $totalPages; $i++) {
  $active = ($i == $page) ? "active" : "";
  echo "<span class='page-link {$active}' data-page='{$i}'>{$i}</span>";
}

if ($page < $totalPages) {
  echo "<span class='page-link' data-page='" . ($page + 1) . "'>Next</span>";
}

echo "</div>";
?>