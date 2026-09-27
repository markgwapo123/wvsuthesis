
<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function json_out($ok, $data = null, $msg = '') {
  echo json_encode(['ok'=>$ok,'data'=>$data,'msg'=>$msg]); exit;
}

if ($method === 'GET') {
  $where = [];
  if (!empty($_GET['q'])) {
    $q = '%' . $conn->real_escape_string($_GET['q']) . '%';
    $where[] = "(i.name LIKE '$q' OR i.code LIKE '$q' OR i.category LIKE '$q' OR i.barcode LIKE '$q')";
  }
  if (!empty($_GET['barcode'])) {
    $b = $conn->real_escape_string($_GET['barcode']);
    $where[] = "i.barcode='$b'";
  }
  $where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

  $sql = "SELECT i.*, s.name AS supplier_name FROM items i LEFT JOIN suppliers s ON i.supplier_id=s.supplier_id $where_sql ORDER BY i.updated_at DESC";
  $res = $conn->query($sql);
  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  json_out(true, $rows);
}

if ($method === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'create' || $action === 'update') {
    $id = $_POST['item_id'] ?? null;
    $code = $_POST['code'] ?? null;
    $barcode = $_POST['barcode'] ?? null;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? null;
    $category = $_POST['category'] ?? null;
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unit = $_POST['unit'] ?? null;
    $condition_label = $_POST['condition_label'] ?? 'New';
    $status_label = $_POST['status_label'] ?? 'Available';
    $location = $_POST['location'] ?? null;
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
      $fname = time().'_'.basename($_FILES['image']['name']);
      $dest = __DIR__ . '/uploads/' . $fname;
      if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
        $image_path = 'uploads/' . $fname;
      }
    }

    if ($action === 'create') {
      $stmt = $conn->prepare("INSERT INTO items(code,barcode,name,description,category,quantity,unit,condition_label,status_label,location,supplier_id,image_path) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
      $stmt->bind_param("ssssisssssis", $code,$barcode,$name,$description,$category,$quantity,$unit,$condition_label,$status_label,$location,$supplier_id,$image_path);
      $ok = $stmt->execute();
      json_out($ok, null, $ok ? 'Created' : $conn->error);
    } else {
      $sql = "UPDATE items SET code=?, barcode=?, name=?, description=?, category=?, quantity=?, unit=?, condition_label=?, status_label=?, location=?, supplier_id=?";
      if ($image_path) $sql .= ", image_path=?";
      $sql .= " WHERE item_id=?";
      if ($image_path) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssissssssii", $code,$barcode,$name,$description,$category,$quantity,$unit,$condition_label,$status_label,$location,$supplier_id,$image_path,$id);
      } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssissssssi", $code,$barcode,$name,$description,$category,$quantity,$unit,$condition_label,$status_label,$location,$supplier_id,$id);
      }
      $ok = $stmt->execute();
      json_out($ok, null, $ok ? 'Updated' : $conn->error);
    }
  } elseif ($action === 'delete') {
    $id = (int)($_POST['item_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM items WHERE item_id=?");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    json_out($ok, null, $ok ? 'Deleted' : $conn->error);
  }
}

json_out(false, null, 'Unsupported');
