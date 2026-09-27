<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'end_user') {
    header("Location: login.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$department = trim($_SESSION['department'] ?? '');
$myUsername = trim($_SESSION['username'] ?? '');
$myRealName = trim($_SESSION['requestor_name'] ?? ''); 

// -------------------- MARK AS READ --------------------
if (isset($_POST['mark_read'])) {
    $conn->query("UPDATE requests SET is_read = 1 WHERE requester_user_id = $user_id");
    // Note: To fully clear Arrivals from the unread badge in the future, 
    // you may want to add an 'is_read' column to the 'issuances' table as well.
    header("Location: endusernotif.php");
    exit;
}

// -------------------- COMBINED FEED --------------------
$feed = [];

/* ---- 1. APPROVED / REJECTED REQUESTS ---- */
$reqStmt = $conn->prepare("
    SELECT 
        'request' AS type,
        item_name AS title,
        status,
        admin_comment AS message,
        processed_at AS created_at,
        is_read
    FROM requests
    WHERE requester_user_id = ?
      AND status IN ('Approved','Rejected')
");
$reqStmt->bind_param("i", $user_id);
$reqStmt->execute();
$reqRes = $reqStmt->get_result();

while ($r = $reqRes->fetch_assoc()) {
    $feed[] = $r;
}

/* ---- 2. NEW ARRIVALS (RECEIVED ITEMS) ---- */
$escapedReal = $conn->real_escape_string($myRealName);
$escapedUser = $conn->real_escape_string($myUsername);
$escapedDept = $conn->real_escape_string($department);

$userMatches = [];
if (!empty($escapedReal)) {
    $userMatches[] = "TRIM(requestor_name) = '$escapedReal'";
    $userMatches[] = "TRIM(receiver) = '$escapedReal'";
}
if (!empty($escapedUser)) {
    $userMatches[] = "TRIM(requestor_name) = '$escapedUser'";
    $userMatches[] = "TRIM(receiver) = '$escapedUser'";
}
$userFilterSql = !empty($userMatches) ? "(" . implode(" OR ", $userMatches) . ")" : "1=0";

$arrSql = "
    SELECT 
        'arrival' AS type,
        item_name AS title,
        'Stock Arrived' AS status,
        CONCAT('You received ', issued_qty, ' quantity.') AS message,
        issued_at AS created_at
    FROM issuances 
    WHERE TRIM(department) = '$escapedDept' AND $userFilterSql
    ORDER BY issued_at DESC LIMIT 10
";
$arrRes = $conn->query($arrSql);

if ($arrRes && $arrRes->num_rows > 0) {
    while ($a = $arrRes->fetch_assoc()) {
        $feed[] = [
            'type'       => $a['type'],
            'title'      => $a['title'],
            'status'     => $a['status'],
            'message'    => $a['message'],
            'created_at' => $a['created_at'],
            'is_read'    => 0 // Defaults to unread so the user sees it
        ];
    }
}

/* ---- 3. LOW / OUT OF STOCK ---- */
$stockStmt = $conn->prepare("
    SELECT
        'stock' AS type,
        name AS title,
        quantity,
        CASE 
            WHEN quantity = 0 THEN 'Out of Stock'
            ELSE 'Low Stock'
        END AS status,
        NOW() AS created_at
    FROM inventory
    WHERE department = ?
      AND quantity <= 5
");
$stockStmt->bind_param("s", $department);
$stockStmt->execute();
$stockRes = $stockStmt->get_result();

while ($s = $stockRes->fetch_assoc()) {
    $feed[] = [
        'type'       => 'stock',
        'title'      => $s['title'],
        'status'     => $s['status'],
        'message'    => 'Remaining: ' . $s['quantity'],
        'created_at' => $s['created_at'],
        'is_read'    => 0
    ];
}

/* ---- SORT BY DATE ---- */
usort($feed, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

/* ---- BADGE COUNTER ---- */
$badgeCount = 0;
foreach ($feed as $f) {
    if (!$f['is_read']) $badgeCount++;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Notifications</title>
<style>
body { font-family: Arial; background:#f4f6f8; padding:20px; }

.header {
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.badge {
    background:red;
    color:white;
    padding:3px 8px;
    border-radius:20px;
    font-size:12px;
}

.section {
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
}

.alert {
    padding:12px;
    margin-bottom:10px;
    border-radius:6px;
    font-size:14px;
}

/* Custom Colors for different Notification Types */
.success { background:#d4edda; color:#155724; border-left: 4px solid #28a745; }
.danger  { background:#f8d7da; color:#721c24; border-left: 4px solid #dc3545; }
.warning { background:#fff3cd; color:#856404; border-left: 4px solid #ffc107; }
.primary { background:#cce5ff; color:#004085; border-left: 4px solid #007bff; } /* Used for new arrivals */

.unread {
    font-weight: bold;
    box-shadow: inset 5px 0 0 rgba(0,0,0,0.1);
}
</style>
</head>

<body>

<div class="header">
    <h2>Notifications</h2>
    <span class="badge"><?= $badgeCount ?></span>
</div>

<form method="post" style="margin:10px 0;">
    <button type="submit" name="mark_read" style="padding:8px 12px; cursor:pointer; background:#007bff; color:white; border:none; border-radius:4px;">Mark Requests as Read</button>
</form>

<div class="section">

<?php if (empty($feed)): ?>
    <p>No notifications.</p>
<?php endif; ?>

<?php foreach ($feed as $n): ?>
    <?php
    // Assign appropriate color based on status/type
    $class = 'warning'; 
    if ($n['status'] === 'Approved') $class = 'success';
    if ($n['status'] === 'Rejected' || $n['status'] === 'Out of Stock') $class = 'danger';
    if ($n['type'] === 'arrival') $class = 'primary';
    ?>

    <div class="alert <?= $class ?> <?= !$n['is_read'] ? 'unread' : '' ?>">
        <strong><?= htmlspecialchars($n['title']) ?></strong><br>
        <span style="font-size: 13px; text-transform: uppercase;"><?= htmlspecialchars($n['status']) ?></span><br>
        <?= htmlspecialchars($n['message'] ?? '') ?><br>
        <small style="color: #666;"><?= date("M d, Y - g:i A", strtotime($n['created_at'])) ?></small>
    </div>
<?php endforeach; ?>

</div>

</body>
</html>