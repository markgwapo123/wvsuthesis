<?php 
require_once __DIR__ . '/../../db_connect.php';

$page   = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
$limit  = 20;
$offset = ($page - 1) * $limit;

$where = [];

if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(u.item_name LIKE '%$s%' 
                 OR u.barcode LIKE '%$s%'
                 OR u.department LIKE '%$s%' 
                 OR u.requestor_name LIKE '%$s%' 
                 OR u.receiver LIKE '%$s%'
                 OR u.approved_by LIKE '%$s%'
                 OR u.issued_by LIKE '%$s%'
                 OR i.category LIKE '%$s%')";
}

if (!empty($_POST['from'])) {
    $from = $conn->real_escape_string($_POST['from']);
    $where[] = "DATE(u.issued_at) >= '$from'";
}
if (!empty($_POST['to'])) {
    $to = $conn->real_escape_string($_POST['to']);
    $where[] = "DATE(u.issued_at) <= '$to'";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
SELECT 
  u.issuance_id,
  u.order_id,
  u.request_id,
  u.barcode,
  u.item_name,
  u.issued_qty,
  u.issued_by,
  u.approved_by,
  u.issued_at,
  u.remarks,
  u.department,
  u.requestor_name,
  u.receiver,
  i.category
FROM issuances u
LEFT JOIN inventory i ON TRIM(i.name) = SUBSTRING_INDEX(u.item_name, ',', 1) AND TRIM(i.department) = TRIM(u.department)
$whereSQL
ORDER BY u.issued_at DESC
LIMIT $limit OFFSET $offset
";

$res = $conn->query($sql);

echo "
<table class='table'>
<thead>
    <tr>
      <th>ID</th>
      <th>Barcode</th>
      <th>Item Detailed</th>
      <th>Category</th>
      <th>Department</th>
      <th>Requestor</th>
      <th>Approved By</th>
      <th>Issued By</th>
      <th>Receiver</th>
      <th>Date Issued</th>
      <th>Remarks</th>
    </tr>
</thead>
<tbody>
";

if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        
        // GINABUAK NATON ANG COMMA-SEPARATED VALUES PARA SA DISPLAY
        $barcodes_arr = explode(', ', $r['barcode']);
        $items_arr    = explode(', ', $r['item_name']);
        $qtys_arr     = explode(', ', $r['issued_qty']);
        
        // I-reconstruct ang detailed column row by row para sa gihapon nga table cell
        $item_detailed_html = "";
        $barcode_html = "";
        
        for ($i = 0; $i < count($items_arr); $i++) {
            $curr_item = htmlspecialchars($items_arr[$i] ?? '');
            $curr_qty  = htmlspecialchars($qtys_arr[$i] ?? '0');
            $curr_bc   = htmlspecialchars($barcodes_arr[$i] ?? '-');
            
            if(!empty($curr_item)) {
                $item_detailed_html .= "<div style='margin-bottom: 4px;'><span style='color: #2563eb;'><strong>{$curr_qty}</strong></span> <span style='color: #64748b; font-size: 12px;'>pcs</span> <strong>{$curr_item}</strong></div>";
                $barcode_html .= "<div style='margin-bottom: 4px;'><small class='text-muted'>{$curr_bc}</small></div>";
            }
        }

        echo "
        <tr>
          <td>{$r['issuance_id']}</td>
          <td>{$barcode_html}</td>
          <td>{$item_detailed_html}</td>
          <td>" . htmlspecialchars($r['category'] ?? 'General') . "</td>
          <td>" . htmlspecialchars($r['department']) . "</td>
          <td>" . htmlspecialchars($r['requestor_name']) . "</td>
          <td><span style='color: #2e7d32; font-weight: 500;'>" . htmlspecialchars($r['approved_by'] ?? '-') . "</span></td>
          <td>" . htmlspecialchars($r['issued_by'] ?? '-') . "</td>
          <td>" . htmlspecialchars($r['receiver'] ?? '-') . "</td>
          <td>" . date("M d, Y", strtotime($r['issued_at'])) . "</td>
          <td><small>" . htmlspecialchars($r['remarks'] ?? '-') . "</small></td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='11' style='text-align:center; padding: 20px;'>No issued items found matching your criteria.</td></tr>";
}
echo "</tbody></table>";
?>