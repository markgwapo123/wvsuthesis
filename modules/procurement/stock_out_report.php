
<?php require_once __DIR__ . '/../../db_connect.php'; ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8"><title>Stock Out Report</title>
<link rel="stylesheet" href="admindasboard.css">
<style>
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ccc;padding:6px;text-align:left}
.controls{margin:10px 0;display:flex;gap:10px;flex-wrap:wrap}
.btn{padding:6px 10px;border:1px solid #ccc;background:#f7f7f7;text-decoration:none}
</style>
</head>
<body>
<h1>Stock Out Report</h1>
<div class="controls">
  <a class="btn" href="export_issuances.php">Export CSV</a>
  <a class="btn" href="print_issuances.php" target="_blank">Print / PDF</a>
</div>
<form method="get" class="controls">
  <label>From: <input type="date" name="from" value="<?php echo htmlspecialchars($_GET['from'] ?? '')?>"></label>
  <label>To: <input type="date" name="to" value="<?php echo htmlspecialchars($_GET['to'] ?? '')?>"></label>
  <button class="btn">Filter</button>
</form>
<table>
<thead><tr><th>ID</th><th>Item</th><th>Qty</th><th>User</th><th>Date</th></tr></thead>
<tbody>
<?php
$cond="WHERE action='Issued'";
if(!empty($_GET['from'])) $cond.=" AND DATE(created_at)>='".$conn->real_escape_string($_GET['from'])."'";
if(!empty($_GET['to'])) $cond.=" AND DATE(created_at)<='".$conn->real_escape_string($_GET['to'])."'";
$res=$conn->query("SELECT s.*, i.name FROM issuances s LEFT JOIN items i ON s.item_id=i.item_id $cond ORDER BY s.created_at DESC");
if($res->num_rows==0){ echo "<tr><td colspan='5'>No data found</td></tr>"; }
while($r=$res->fetch_assoc()){
  echo "<tr><td>{$r['issuance_id']}</td><td>".htmlspecialchars($r['name'])."</td><td>{$r['qty']}</td><td>{$r['user_id']}</td><td>{$r['created_at']}</td></tr>";
}
?>
</tbody>
</table>
</body>
</html>
