<?php require_once __DIR__ . '/../../db_connect.php'; ?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Print suppliers</title>
<style>body{font-family:Arial} table{width:100%;border-collapse:collapse} th,td{border:1px solid #ccc;padding:6px} @media print{.noprint{display:none}}</style>
</head><body>
<h2>Suppliers Report</h2>
<button class="noprint" onclick="window.print()">Print / Save as PDF</button>
<table><thead><tr>
<?php
  $res=$conn->query("SELECT supplier_id,name,contact_person,contact_email,contact_phone,status,updated_at FROM suppliers");
  $first=true;
  if($row=$res->fetch_assoc()){ foreach(array_keys($row) as $h) echo "<th>".htmlspecialchars($h)."</th>"; echo "</tr></thead><tbody>"; echo "<tr>"; foreach($row as $v) echo "<td>".htmlspecialchars($v)."</td>"; echo "</tr>"; }
  while($r=$res->fetch_assoc()){ echo "<tr>"; foreach($r as $v) echo "<td>".htmlspecialchars($v)."</td>"; echo "</tr>"; }
?>
</tbody></table>
</body></html>