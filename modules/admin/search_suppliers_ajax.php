<?php 
session_start();
require_once __DIR__ . '/../../db_connect.php';

$role = $_SESSION['role'] ?? 'guest';

$page  = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];

// Live search
if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(name LIKE '%$s%' 
              OR contact_person LIKE '%$s%'
              OR product_supplied LIKE '%$s%'
              OR status LIKE '%$s%')";
}

// Filters
if (!empty($_POST['status'])) {
    $st = $conn->real_escape_string($_POST['status']);
    $where[] = "status LIKE '%$st%'";
}
if (!empty($_POST['product_supplied'])) {
    $ps = $conn->real_escape_string($_POST['product_supplied']);
    $where[] = "product_supplied LIKE '%$ps%'";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count rows
$countRes = $conn->query("SELECT COUNT(*) AS total FROM suppliers $whereSQL");
$totalRows = $countRes->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalRows / $limit));

// Fetch suppliers
$query = "
    SELECT *
    FROM suppliers
    $whereSQL
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
";
$res = $conn->query($query);

echo "<table class='table'>
<thead>
<tr>
  <th>Supplier</th>
  <th>Contact Person</th>
  <th>Email</th>
  <th>Phone</th>
  <th>Product</th>
  <th>Status</th>
  <th>Created</th>
  <th>Actions</th>
</tr>
</thead>
<tbody>
";

if ($res->num_rows === 0) {
    echo "<tr><td colspan='8'>No suppliers found.</td></tr>";
} else {
    while ($row = $res->fetch_assoc()) {

        $details = "
            <b>Name:</b> {$row['name']}<br>
            <b>Contact Person:</b> {$row['contact_person']}<br>
            <b>Email:</b> {$row['contact_email']}<br>
            <b>Phone:</b> {$row['contact_phone']}<br>
            <b>Address:</b> {$row['address']}<br>
            <b>Product Supplied:</b> {$row['product_supplied']}<br>
            <b>Status:</b> {$row['status']}<br>
            <b>Created At:</b> {$row['created_at']}
        ";

        $detailsAttr = htmlspecialchars($details, ENT_QUOTES);

        echo "<tr>
          <td>{$row['name']}</td>
          <td>{$row['contact_person']}</td>
          <td>{$row['contact_email']}</td>
          <td>{$row['contact_phone']}</td>
          <td>{$row['product_supplied']}</td>
          <td>{$row['status']}</td>
          <td>{$row['created_at']}</td>
          <td>
            <button class='view-details' data-details=\"$detailsAttr\">View</button>";

        if ($role === 'admin') {
            echo "
            <button onclick=\"window.location='edit_record.php?type=supplier&id={$row['supplier_id']}'\">Edit</button>
            <button onclick=\"if(confirm('Delete supplier?')) window.location='delete_record.php?type=supplier&id={$row['supplier_id']}';\">Delete</button>
            ";
        } else {
            echo "<span style='color:#888;'>View Only</span>";
        }

        echo "</td></tr>";
    }
}

echo "</tbody></table>";

// Pagination
echo "<div class='pagination'>";
if ($page > 1)
    echo "<span class='page-link' data-page='".($page-1)."'>Previous</span>";

for ($i = 1; $i <= $totalPages; $i++) {
    $active = ($i == $page) ? "active" : "";
    echo "<span class='page-link $active' data-page='$i'>$i</span>";
}

if ($page < $totalPages)
    echo "<span class='page-link' data-page='".($page+1)."'>Next</span>";

echo "</div>";
