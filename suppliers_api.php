
<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
function out($ok,$data=null,$msg=''){echo json_encode(['ok'=>$ok,'data'=>$data,'msg'=>$msg]);exit;}
if ($method==='GET'){
  $res = $conn->query("SELECT * FROM suppliers ORDER BY updated_at DESC");
  $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
  out(true,$rows);
}
if ($method==='POST'){
  $action = $_POST['action'] ?? '';
  if ($action==='create'){
    $stmt=$conn->prepare("INSERT INTO suppliers(name,contact_person,contact_email,contact_phone,address,product_supplied,status) VALUES(?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss", $_POST['name'],$_POST['contact_person'],$_POST['contact_email'],$_POST['contact_phone'],$_POST['address'],$_POST['product_supplied'],$_POST['status']);
    $ok=$stmt->execute(); out($ok,null,$ok?'Created':$conn->error);
  } elseif ($action==='update'){
    $stmt=$conn->prepare("UPDATE suppliers SET name=?,contact_person=?,contact_email=?,contact_phone=?,address=?,product_supplied=?,status=? WHERE supplier_id=?");
    $stmt->bind_param("sssssssi", $_POST['name'],$_POST['contact_person'],$_POST['contact_email'],$_POST['contact_phone'],$_POST['address'],$_POST['product_supplied'],$_POST['status'], $_POST['supplier_id']);
    $ok=$stmt->execute(); out($ok,null,$ok?'Updated':$conn->error);
  } elseif ($action==='delete'){
    $stmt=$conn->prepare("DELETE FROM suppliers WHERE supplier_id=?");
    $stmt->bind_param("i", $_POST['supplier_id']); $ok=$stmt->execute(); out($ok,null,$ok?'Deleted':$conn->error);
  }
}
out(false,null,'Unsupported');
