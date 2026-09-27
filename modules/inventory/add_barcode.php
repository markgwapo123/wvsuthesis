<?php 
session_start();
require_once __DIR__ . '/db_connect.php';

// Handle barcode generation form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name        = $conn->real_escape_string($_POST['name']);
  $description = $conn->real_escape_string($_POST['description']);
  $category    = $conn->real_escape_string($_POST['category']);
  $quantity    = (int) $_POST['quantity'];
  $expiration  = !empty($_POST['expiration_date']) ? "'" . $conn->real_escape_string($_POST['expiration_date']) . "'" : "NULL";
  $unit        = $conn->real_escape_string($_POST['unit']);
  $condition   = $conn->real_escape_string($_POST['condition_label']);
  $status      = $conn->real_escape_string($_POST['status_label']);
  $location    = $conn->real_escape_string($_POST['location']);
  $department  = $conn->real_escape_string($_POST['department']); 
  $supplier_id = !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : "NULL";
  $min_stock   = isset($_POST['min_stock']) ? (int) $_POST['min_stock'] : 0; 

  $image_path = '';
  if (!empty($_FILES['image']['name'])) {
    $dir = 'uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $dir . basename($_FILES['image']['name']);
    move_uploaded_file($_FILES['image']['tmp_name'], $file);
    $image_path = $conn->real_escape_string($file);
  }

  // Insert item into supply office intake ledger
  $sql = "
    INSERT INTO items
    (name, description, category, quantity, expiration_date, unit,
     condition_label, status_label, min_stock, location, department, supplier_id,
     image_path, created_at, updated_at)
    VALUES
    ('$name', '$description', '$category', $quantity,
     $expiration, '$unit', '$condition', '$status','$min_stock',
     '$location', '$department', $supplier_id, '$image_path', NOW(), NOW())
  ";

  if (!$conn->query($sql)) die('Insert failed: ' . $conn->error);

  $item_id = $conn->insert_id;
  $base = 'ITEM' . str_pad($item_id, 6, '0', STR_PAD_LEFT);
  $conn->query("UPDATE items SET barcode='$base', code='$base' WHERE item_id=$item_id");

  for ($i = 1; $i <= $quantity; $i++) {
    $unique = $base . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
    $conn->query("INSERT INTO barcodes (item_id, barcode_code) VALUES ($item_id, '$unique')");
  }

  header("Location: generate_barcode.php?success=" . urlencode("Item added successfully! $quantity barcodes generated."));
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Supply Intake & Barcode Creation</title>
<link href="assets/bootstrap-5.3.2-dist/css/bootstrap.css" rel="stylesheet">
<style>
  body {
    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    background-color: #f4f6f9;
    margin: 0;
    padding: 0;
    color: #333;
  }

  .main-content {
    max-width: 900px;
    margin: 50px auto;
    background: #fff;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    animation: fadeIn 0.4s ease;
  }

  .form-header-box {
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 15px;
    margin-bottom: 30px;
  }

  h1 {
    color: #004085;
    font-size: 26px;
    font-weight: 600;
    margin: 0;
  }

  .section-title {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    margin-top: 20px;
    margin-bottom: 15px;
    font-weight: bold;
    border-left: 3px solid #007bff;
    padding-left: 10px;
  }

  label {
    font-size: 13px;
    font-weight: 500;
    color: #495057;
    margin-bottom: 6px;
  }

  input,
  select,
  textarea {
    border-radius: 6px !important;
    border: 1px solid #ced4da;
    font-size: 14px;
    padding: 10px 12px;
    background-color: #fdfdfd;
    transition: all 0.2s ease;
  }

  input:focus,
  select:focus,
  textarea:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
    background-color: #fff;
  }

  textarea {
    resize: vertical;
    min-height: 80px;
  }

  .submit-btn {
    padding: 12px 24px;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    transition: background-color 0.2s ease;
    width: 100%;
    margin-top: 20px;
  }

  .submit-btn:hover {
    background-color: #0056b3;
  }

  .back-btn-container {
    text-align: center;
    margin-top: 25px;
    border-top: 1px solid #e9ecef;
    padding-top: 20px;
  }

  .back-link {
    color: #6c757d;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.2s;
  }

  .back-link:hover {
    color: #343a40;
    text-decoration: underline;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>
</head>
<body>

<div class="main-content">
  <div class="form-header-box">
    <h1>📦 Supply Property Intake Registration</h1>
  </div>

  <form method="POST" enctype="multipart/form-data">
    
    <div class="section-title">Asset Identification</div>
    <div class="row g-3">
      <div class="col-md-6 d-flex flex-column">
        <label>Item Nomenclature / Name</label>
        <input type="text" name="name" placeholder="e.g. Acer Laptop, Office Scissors" required>
      </div>
      <div class="col-md-6 d-flex flex-column">
        <label>Asset Category Group</label>
        <select name="category" required>
          <option value="" disabled selected>Select category...</option>
          <option value="Stationary">Stationery Supplies</option>
          <option value="Cleaning">Cleaning & Maintenance</option>
          <option value="Tech">IT & Technology Equipment</option>
          <option value="Misc">Miscellaneous Supplies</option>
        </select>
      </div>
    </div>

    <div class="section-title">Stocking & Logistics Metric</div>
    <div class="row g-3">
      <div class="col-md-4 d-flex flex-column">
        <label>Physical Quantity</label>
        <input type="number" name="quantity" placeholder="0" min="1" required>
      </div>
      <div class="col-md-4 d-flex flex-column">
        <label>Measurement Unit</label>
        <input type="text" name="unit" placeholder="e.g. pcs, boxes, reams" required>
      </div>
      <div class="col-md-4 d-flex flex-column">
        <label>Minimum Stock Alert Level</label>
        <input type="number" name="min_stock" placeholder="e.g. 5" min="0">
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-4 d-flex flex-column">
        <label>Allocated Department Allocation</label>
        <input type="text" name="department" placeholder="e.g. ICT Department, Admin">
      </div>
      <div class="col-md-4 d-flex flex-column">
        <label>Storage Location Floor/Shelf</label>
        <input type="text" name="location" placeholder="e.g. Cabinet B, Shelf 3">
      </div>
      <div class="col-md-4 d-flex flex-column">
        <label>Expiration Tracking Date (If applicable)</label>
        <input type="date" name="expiration_date">
      </div>
    </div>

    <div class="section-title">Condition & Vendor Specifications</div>
    <div class="row g-3">
      <div class="col-md-4 d-flex flex-column">
        <label>Initial Asset Condition</label>
        <select name="condition_label">
          <option value="New" selected>New / Mint Condition</option>
          <option value="Expired">Expired</option>
          <option value="Damaged">Damaged</option>
          <option value="Lost">Lost</option>
        </select>
      </div>
      <div class="col-md-4 d-flex flex-column">
        <label>Warehouse Availability Status</label>
        <select name="status_label">
          <option value="Available" selected>Available for Issue</option>
          <option value="Borrowed">Borrowed</option>
          <option value="Low Stock">Low Stock</option>
          <option value="Out of Stock">Out of Stock</option>
          <option value="Disposed">Disposed From Records</option>
        </select>
      </div>
      <div class="col-md-4 d-flex flex-column">
        <label>Supplier Index ID Number</label>
        <input type="number" name="supplier_id" placeholder="Index database ID">
      </div>
    </div>

    <div class="section-title">Documentation & Remarks</div>
    <div class="row g-3">
      <div class="col-12 d-flex flex-column">
        <label>Reference Asset Image Thumbnail</label>
        <input type="file" name="image" accept="image/*">
      </div>
      <div class="col-12 d-flex flex-column mt-2">
        <label>Detailed Specifications / Remarks</label>
        <textarea name="description" placeholder="Provide serial notes, model breakdowns, or extra parameters..."></textarea>
      </div>
    </div>

    <button type="submit" class="submit-btn">Register Supply & Execute Barcodes</button>
  </form>

  <div class="back-btn-container">
    <a href="generate_barcode.php" class="back-link">← Cancel and Return to Generated Barcode List</a>
  </div>
</div>

</body>
</html>