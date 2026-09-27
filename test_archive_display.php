<?php
require_once __DIR__ . '/db_connect.php';
$res = $conn->query("SELECT * FROM archive_all");
?>
<!DOCTYPE html>
<html>
<body>
<h3>Archive_all Test Output</h3>
<table border="1" cellpadding="5">
<tr><th>ID</th><th>Table</th><th>Record</th></tr>
<?php
if ($res && $res->num_rows > 0) {
  while ($r = $res->fetch_assoc()) {
    echo "<tr><td>{$r['archive_id']}</td><td>{$r['table_name']}</td><td>{$r['record_id']}</td></tr>";
  }
} else {
  echo "<tr><td colspan='3'>NO ROWS</td></tr>";
}
?>
</table>
</body>
</html>
