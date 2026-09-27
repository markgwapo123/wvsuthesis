<?php
require_once __DIR__ . '/../../db_connect.php';

$page  = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];

// LIVE SEARCH
if (!empty($_POST['live'])) {
    $s = $conn->real_escape_string($_POST['live']);
    $where[] = "(first_name LIKE '%$s%' 
              OR last_name LIKE '%$s%'
              OR middle_name LIKE '%$s%'
              OR email LIKE '%$s%'
              OR role LIKE '%$s%')";
}

// FILTERS
if (!empty($_POST['role'])) {
    $r = $conn->real_escape_string($_POST['role']);
    $where[] = "role LIKE '%$r%'";
}
if (!empty($_POST['created_from'])) {
    $d = $conn->real_escape_string($_POST['created_from']);
    $where[] = "DATE(created_at) >= '$d'";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// COUNT
$countRes = $conn->query("SELECT COUNT(*) AS total FROM users $whereSQL");
$totalRows = $countRes->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalRows / $limit));

// FETCH USERS
$q = "
    SELECT id,
           CONCAT(first_name,' ',middle_name,' ',last_name) AS full_name,
           role,
           email,
           created_at
    FROM users
    $whereSQL
    ORDER BY id DESC
    LIMIT $limit OFFSET $offset
";

$res = $conn->query($q);

echo "<table class='table'>
<thead>
<tr>
    <th>User ID</th>
    <th>Name</th>
    <th>Role</th>
    <th>Email</th>
    <th>Created At</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
";

if ($res->num_rows == 0) {
    echo "<tr><td colspan='6'>No users found.</td></tr>";
} else {
    while ($row = $res->fetch_assoc()) {

        $details = "
            <b>Name:</b> {$row['full_name']}<br>
            <b>Email:</b> {$row['email']}<br>
            <b>Role:</b> {$row['role']}<br>
            <b>Created At:</b> {$row['created_at']}
        ";
        $detailsAttr = htmlspecialchars($details, ENT_QUOTES);

        echo "
        <tr>
            <td>{$row['id']}</td>
            <td>{$row['full_name']}</td>
            <td>{$row['role']}</td>
            <td>{$row['email']}</td>
            <td>{$row['created_at']}</td>
            <td>
                <button class='view-details' data-details=\"$detailsAttr\">View</button>
                <button onclick=\"window.location='edit_record.php?type=user&id={$row['id']}'\">Edit</button>
                <button onclick=\"if(confirm('Delete this user?')) window.location='deleteusers.php?type=user&id={$row['id']}';\">Delete</button>
            </td>
        </tr>";
    }
}

echo "</tbody></table>";

// PAGINATION
echo "<div class='pagination'>";
if ($page > 1) echo "<span class='page-link' data-page='".($page-1)."'>Previous</span>";

for ($i=1;$i<=$totalPages;$i++) {
    $active = ($i==$page) ? "active" : "";
    echo "<span class='page-link $active' data-page='$i'>$i</span>";
}

if ($page < $totalPages) echo "<span class='page-link' data-page='".($page+1)."'>Next</span>";

echo "</div>";
