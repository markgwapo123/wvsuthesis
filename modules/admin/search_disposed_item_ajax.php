<?php 
require_once __DIR__ . '/../../db_connect.php';

/* Pagination */
$page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// BASE CONDITION: Only fetch items that have been successfully finalized/disposed
$where = ["status = 'Disposed'"];

/* =======================
   LIVE SEARCH
======================= */
if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(item_name LIKE '%$s%' 
                OR department LIKE '%$s%' 
                OR requestor_name LIKE '%$s%' 
                OR daa_approved_by LIKE '%$s%' 
                OR admin_disposed_by LIKE '%$s%' 
                OR reason LIKE '%$s%')";
}

/* =======================
   FILTERS
======================= */
$filters = [
    "department"        => "department",
    "requestor_name"    => "requestor_name"
];

foreach ($filters as $key => $col) {
    if (!empty($_POST[$key])) {
        $v = $conn->real_escape_string($_POST[$key]);
        $where[] = "$col LIKE '%$v%'";
    }
}

// Build the complete WHERE clause safely
$whereSQL = "WHERE " . implode(" AND ", $where);


/* =======================
   COUNT TOTAL
======================= */
$countRes = $conn->query("
    SELECT COUNT(*) AS total FROM disposal_requests
    $whereSQL
");
$totalRows = ($countRes) ? $countRes->fetch_assoc()['total'] : 0;
$totalPages = max(1, ceil($totalRows / $limit));


/* =======================
   MAIN QUERY
======================= */
$sql = "
SELECT disposal_id, requestor_name, department, item_name, qty, unit, reason, status, daa_approved_by, admin_disposed_by, created_at, updated_at 
FROM disposal_requests
$whereSQL
ORDER BY updated_at DESC
LIMIT $limit OFFSET $offset
";

$res = $conn->query($sql);


/* =======================
   OUTPUT TABLE
======================= */

echo "
<table class='table'>
<thead>
<tr>
  <th>ID</th>
  <th>Item Details</th>
  <th>Department</th>
  <th>Requestor</th>
  <th>Reason Given</th>
  <th> Authorized By</th>
  <th>Disposed By</th>
  <th>Disposal Date</th>
</tr>
</thead>
<tbody>
";

if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {

        $cls = "";
        
        // Format the final disposal completion timestamp cleanly
        $disposalDate = !empty($r['updated_at']) ? date("M d, Y h:i A", strtotime($r['updated_at'])) : 'N/A';
        
        // Split strings dynamically to handle potential multiple comma-separated items safely
        $items_arr = preg_split('/\s*,\s*/', trim($r['item_name'] ?? ''));
        $qtys_arr  = preg_split('/\s*,\s*/', trim($r['qty'] ?? ''));
        $units_arr = preg_split('/\s*,\s*/', trim($r['unit'] ?? ''));
        
        $item_details_html = "";
        $max_loops = count($items_arr);
        
        for ($i = 0; $i < $max_loops; $i++) {
            $curr_item = htmlspecialchars($items_arr[$i] ?? '');
            $curr_qty  = htmlspecialchars($qtys_arr[$i] ?? $qtys_arr[0] ?? '1');
            $curr_unit = htmlspecialchars($units_arr[$i] ?? $units_arr[0] ?? 'pcs');
            
            if (!empty($curr_item)) {
                // Layout arranged in a single row line matching your snapshot requirements
                $item_details_html .= "
                <div style='display: flex; align-items: center; gap: 8px; margin-bottom: 4px; font-size: 14px;'>
                    <span style='color: #2563eb; font-weight: 700;'>{$curr_qty}</span> 
                    <span style='color: #64748b; font-size: 12px;'>{$curr_unit}</span> 
                    <strong style='color: #0f172a;'>{$curr_item}</strong>
                </div>";
            }
        }
        
        echo "
        <tr class='$cls'>
            <td>{$r['disposal_id']}</td>
            <td>
                <div style='display: flex; flex-direction: column; padding: 4px 0;'>{$item_details_html}</div>
            </td>
            <td>".htmlspecialchars($r['department'])."</td>
            <td>".htmlspecialchars($r['requestor_name'])."</td>
            <td>
                <div style='max-width: 240px; word-wrap: break-word;'>
                    ".htmlspecialchars($r['reason'])."
                </div>
            </td>
            <td><strong>".htmlspecialchars($r['daa_approved_by'] ?? 'N/A')."</strong></td>
            <td><strong>".htmlspecialchars($r['admin_disposed_by'] ?? 'N/A')."</strong></td>
            <td>$disposalDate</td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='8'>No completed disposal records found matching this layout selection.</td></tr>";
}

echo "</tbody></table>";

/* Pagination Output */
echo "<div class='pagination'>";
if ($page > 1) echo "<span class='page-link' data-page='".($page-1)."'>Previous</span>";

for ($i = 1; $i <= $totalPages; $i++) {
    $active = ($i == $page) ? "active" : "";
    echo "<span class='page-link $active' data-page='$i'>$i</span>";
}

if ($page < $totalPages) echo "<span class='page-link' data-page='".($page+1)."'>Next</span>";
echo "</div>";
?>