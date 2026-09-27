<?php
require_once __DIR__ . "/db_connect.php";

if (($_SESSION['role'] ?? '') !== 'end_user') {
    exit("Unauthorized");
}

$user_id = $_SESSION['user_id'];

$history = $conn->query("
    SELECT r.qty_requested, r.status, r.created_at,
           i.name AS item_name
    FROM requests r
    JOIN inventory i ON r.inventory_id = i.inventory_id
    WHERE r.requester_user_id = $user_id
    ORDER BY r.created_at DESC
");
?>

<h2>My Requests</h2>

<table border="1" cellpadding="8">
<tr>
    <th>Item</th>
    <th>Qty</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php while ($r = $history->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($r['item_name']) ?></td>
    <td><?= $r['qty_requested'] ?></td>
    <td><?= $r['status'] ?></td>
    <td><?= $r['created_at'] ?></td>
</tr>
<?php endwhile; ?>
</table>
