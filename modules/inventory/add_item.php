<?php  
require_once __DIR__ . '/../../db_connect.php';
$msg = '';
$color = '';
$itemInfo = '';
$autoHide = false;

if ($_SERVER['REQUEST_METHOD']==='POST'){

  $barcode = trim($_POST['barcode']);

  // FIND BARCODE IN barcodes TABLE
  $r = $conn->query("SELECT * FROM barcodes WHERE barcode_code='$barcode'");

  if($r->num_rows===0){
      $msg = "❌ Error: Barcode not found.";
      $color = 'error';

  } else {

      $b = $r->fetch_assoc();

      if($b['is_scanned']){
          $msg = "⚠ This barcode is already scanned!";
          $color = 'error';

      } else {

          // GET ITEM DETAILS
          $item = $conn->query("SELECT * FROM items WHERE item_id={$b['item_id']}")->fetch_assoc();

          if ($item) {

              // SAFE VARIABLE EXTRACTION
              $code        = $conn->real_escape_string($item['code']);
              $name        = $conn->real_escape_string($item['name']);
              $desc        = $conn->real_escape_string($item['description']);
              $cat         = $conn->real_escape_string($item['category']);
              $exp         = $item['expiration_date'] ? "'" . $conn->real_escape_string($item['expiration_date']) . "'" : "NULL";
              $unit        = $conn->real_escape_string($item['unit']);
              $condition   = $conn->real_escape_string($item['condition_label']);
              $minStock    = intval($item['min_stock']);
              $department  = $item['department'];  // may be null
              $supplier_id = intval($item['supplier_id']);
              $image       = $conn->real_escape_string($item['image_path']);

              // STATUS IS NOW AUTOMATIC BASED ON QTY AND MIN STOCK
              // We will calculate it after determining quantity.

              /*
                MERGE LOGIC:
                SAME NAME + SAME DEPARTMENT → merge
                SAME NAME + NULL DEPT → merge only with null dept
                NULL never merges with non-null departments
              */
              if ($department === null || $department === "") {
                  $deptSQL = "department IS NULL";
              } else {
                  $deptSQL = "department = '" . $conn->real_escape_string($department) . "'";
              }

              // CHECK IF ITEM ALREADY EXISTS
              $findInventory = $conn->query("
                  SELECT * FROM inventory
                  WHERE name = '$name'
                    AND $deptSQL
                  LIMIT 1
              ");

              // -------------------------------
              // CASE 1: MERGE INTO EXISTING ROW
              // -------------------------------
              if ($findInventory->num_rows > 0) {

                  $existing = $findInventory->fetch_assoc();

                  $newQty = $existing['quantity'] + 1;

                  // AUTO STATUS CALCULATION
                  if ($newQty == 0) {
                      $autoStatus = "Out of Stock";
                  } elseif ($newQty == $existing['min_stock']) {
                      $autoStatus = "Low Stock";
                  } elseif ($newQty > $existing['min_stock']) {
                      $autoStatus = "Available";
                  } else {
                      $autoStatus = $existing['status_label']; // fallback
                  }

                  // UPDATE INVENTORY ROW
                  $conn->query("
                      UPDATE inventory
                      SET quantity = $newQty,
                          status_label = '$autoStatus',
                          updated_at = NOW()
                      WHERE inventory_id = {$existing['inventory_id']}
                  ");

                  $msg = "✔ Updated existing item: <b>$name</b> (Qty +1)";
                  $color = 'success';

              } 
              // -------------------------------
              // CASE 2: INSERT NEW ITEM ROW
              // -------------------------------
              else {

                  // New item always starts with qty = 1
                  if (1 == 0) {
                      $autoStatus = "Out of Stock";
                  } elseif (1 == $minStock) {
                      $autoStatus = "Low Stock";
                  } elseif (1 > $minStock) {
                      $autoStatus = "Available";
                  } else {
                      $autoStatus = "Available";
                  }

                  $departmentVal = ($department === null || $department === "")
                      ? "NULL"
                      : "'" . $conn->real_escape_string($department) . "'";

                  // INSERT NEW RECORD
                  $conn->query("
                      INSERT INTO inventory
                      (item_id, code, barcode, name, description, category, quantity, min_stock, expiration_date,
                       unit, condition_label, status_label, department, supplier_id, image_path, created_at, updated_at)
                      VALUES
                      ({$item['item_id']}, '$code', '$barcode', '$name', '$desc', '$cat', 1, $minStock, $exp,
                       '$unit', '$condition', '$autoStatus', $departmentVal, $supplier_id, '$image', NOW(), NOW())
                  ");

                  $msg = "✔ Stored successfully: <b>$name</b>";
                  $color = 'success';
              }

              // UPDATE OTHER TABLES
              $conn->query("UPDATE barcodes SET is_scanned=1 WHERE barcode_code='$barcode'");
              $conn->query("UPDATE items SET quantity = quantity - 1 WHERE item_id={$b['item_id']}");
              $conn->query("DELETE FROM items WHERE item_id={$b['item_id']} AND quantity <= 0");

              // POPUP DETAILS
              $itemInfo = "
                <div class='info-box'>
                  <h3>Item Stored</h3>
                  <p><strong>Name:</strong> $name</p>
                  <p><strong>Category:</strong> $cat</p>
                  <p><strong>Department:</strong> " . ($department ?: 'None') . "</p>
                  <p><strong>Unit:</strong> $unit</p>
                  <p><strong>Status:</strong> $autoStatus</p>
                  <p><strong>Barcode:</strong> $barcode</p>
                </div>
              ";

              $autoHide = true;

          } else {
              $msg = "❌ Error: Item not found for this barcode.";
              $color = 'error';
          }
      }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Scan / Add Item | SOIMS</title>
<style>
  :root {
    --soims-blue: #007bff;
    --soims-dark: #004b9a;
    --soims-gray: #f4f6f8;
    --soims-green: #28a745;
    --soims-red: #dc3545;
  }

  body { font-family: 'Segoe UI', Arial; background: var(--soims-gray); margin: 0; padding: 0; }

  .container {
    max-width: 650px; background: #fff; margin: 80px auto; padding: 40px;
    border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }

  h2 { text-align: center; color: var(--soims-dark); margin-bottom: 25px; }

  form { display: flex; flex-direction: column; align-items: center; gap: 15px; }

  input[type="text"] {
    width: 80%; padding: 12px 15px; border: 2px solid #ccc; border-radius: 8px; font-size: 16px;
  }

  .btn { padding: 10px 16px; border-radius: 8px; font-size: 15px; cursor: pointer; border: none; margin: 5px; }

  .scan-btn { background: var(--soims-blue); color:#fff; }
  .cam-btn  { background: #343a40; color:#fff; }

  .message { text-align: center; margin-top: 20px; font-size: 16px; padding: 10px; border-radius: 8px; }

  .success { background: #d4edda; color: var(--soims-green); border: 1px solid var(--soims-green); }
  .error   { background: #f8d7da; color: var(--soims-red); border: 1px solid var(--soims-red); }

  .info-box {
    margin-top: 25px; padding: 20px; border-radius: 10px;
    border-left: 5px solid var(--soims-blue); background:#eef5ff;
  }

  .back-btn {
    display: block; margin-top: 25px; text-align: center; padding: 10px 15px;
    background: #6c757d; color: white; text-decoration: none; border-radius: 8px;
  }

  #camera-area { display:none; margin-top:20px; text-align:center; }
  video { width: 100%; max-height: 320px; border-radius: 10px; background:#000; }

</style>

<script src="assets/js/zxing.min.js"></script>
</head>
<body>

<div class="container">
    <h2>📦 Scan / Add Item to Inventory</h2>

    <form method="POST" id="scanForm">
      <input type="text" name="barcode" id="barcodeInput"
             placeholder="Scan barcode here..." autofocus autocomplete="off" required>

      <div>
        <button class="btn scan-btn" type="submit">Add Item</button>
        <button type="button" class="btn cam-btn" id="camToggleBtn" onclick="toggleCamera()">📷 Use Camera Scanner</button>
      </div>
    </form>

    <?php if ($msg): ?>
      <div class="message <?= $color ?>" id="msgBox"><?= $msg ?></div>
    <?php endif; ?>

    <?= $itemInfo ?>

    <div id="camera-area">
      <p>Align the barcode inside the frame.</p>
      <video id="preview"></video>
    </div>

    <a href="manage_inventory.php" class="back-btn">← Back to Inventory</a>
</div>

<!-- BEEP SOUNDS -->
<audio id="successSound"><source src="success-beep.mp3"></audio>
<audio id="errorSound"><source src="error-beep.mp3"></audio>

<?php if ($color === 'success'): ?>
<script> document.getElementById("successSound").play(); </script>
<?php elseif ($color === 'error'): ?>
<script> document.getElementById("errorSound").play(); </script>
<?php endif; ?>

<script>
// USB barcode scanner auto-submit
barcodeInput.addEventListener("keydown", function(e) {
  if (e.key === "Enter") {
    e.preventDefault();
    if (this.value.trim().length > 0) scanForm.submit();
  }
});

// Auto-hide info box
<?php if ($autoHide): ?>
setTimeout(() => {
    document.querySelector('.info-box')?.remove();
    document.getElementById('msgBox')?.remove();
}, 7000);
<?php endif; ?>


// CAMERA SCANNER
let camOn = false;
let codeReader = null;
let selectedDeviceId = null;

function toggleCamera() {
  camOn = !camOn;
  const area = document.getElementById("camera-area");
  const btn  = document.getElementById("camToggleBtn");

  if (camOn) {
    area.style.display = "block";
    btn.textContent = "❌ Stop Camera";
    startCameraScanner();
  } else {
    area.style.display = "none";
    btn.textContent = "📷 Use Camera Scanner";
    stopCamera();
  }
}

function stopCamera() {
  if (codeReader) {
    try { codeReader.reset(); } catch(e){}
  }
}

function startCameraScanner() {
  
  codeReader = new ZXing.BrowserMultiFormatReader();
  const video = document.getElementById("preview");

  codeReader.listVideoInputDevices().then(devices => {

    if (devices.length === 0) {
      alert("No camera detected.");
      return;
    }

    selectedDeviceId = devices[devices.length - 1].deviceId;

    codeReader.decodeFromVideoDevice(
      selectedDeviceId, 
      "preview",
      (result, err) => {
        if (result) {
          barcodeInput.value = result.getText();
          stopCamera();
          scanForm.submit();
        }
      }
    );

  });
}
</script>

</body>
</html>
