<?php 
session_start();
require_once __DIR__ . '/db_connect.php';

// Access control: only admin can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
  exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // Collect form data
  $name = $_POST['name'];
  $contact_person = $_POST['contact_person'];
  $contact_email = $_POST['contact_email'];
  $contact_phone = $_POST['contact_phone'];
  $address = $_POST['address'];
  $product_supplied = $_POST['product_supplied'];
  $status = $_POST['status'];

  // Insert the supplier into the database
  $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, contact_email, contact_phone, address, product_supplied, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
  $stmt->bind_param("sssssss", $name, $contact_person, $contact_email, $contact_phone, $address, $product_supplied, $status);
  
  if ($stmt->execute()) {
    // Success message
    header("Location: manage_suppliers.php?success=Supplier added successfully");
  } else {
    // Error handling
    header("Location: add_supplier.php?error=Failed to add supplier");
  }
  $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Supplier</title>
  <link rel="stylesheet" href="admindasboard.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #143ab7ff, #143ab7ff);
      margin: 0;
      padding: 0;
      color: #333;
    }

    .main-content {
      max-width: 800px;
      margin: 50px auto;
      background: #fff;
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
      animation: fadeIn 0.6s ease;
    }

    h1 {
      text-align: center;
      color: #3b3b98;
      margin-bottom: 25px;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    input,
    select {
      height: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #ccd6f6;
      font-size: 14px;
      background-color: #fafaff;
      transition: all 0.3s ease;
    }

    input:focus,
    select:focus {
      outline: none;
      border-color: #2c69daff;
      box-shadow: 0 0 4px rgba(47, 57, 252, 0.3);
    }

    .form-group {
      position: relative;
      margin-top: 20px;
      bottom: 20px;
    }

    .form-group input {
      width: 99%;
      padding: 10px 12px;
      font-size: 14px;
    }

    .form-group label {
      position: absolute;
      top: 10px;
      left: 120px;
      color: #777;
      transition: 0.2s;
      pointer-events: none;
    }

    .form-group input:focus+label,
    .form-group input:valid+label {
      top: -10px;
      left: 10px;
      font-size: 12px;
      color: #000;
      background: #fff;
      padding: 0 4px;
    }

    .submit-btn {
      grid-column: 1 / 3;
      padding: 12px;
      background-color: #395ffaff;
      color: #fff;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      font-size: 15px;
      transition: background-color 0.3s, transform 0.2s;
    }

    .submit-btn:hover {
      background-color: #180fcaff;
      transform: translateY(-2px);
    }

    .back-btn {
      display: block;
      margin: 25px auto 0;
      background-color: #ff7b9c;
      color: #fff;
      border: none;
      padding: 10px 25px;
      border-radius: 10px;
      font-size: 14px;
      cursor: pointer;
      transition: background 0.3s, transform 0.2s;
    }

    .back-btn:hover {
      background-color: #ff4f7a;
      transform: translateY(-2px);
    }

    /* Success/Error alert */
    .alert {
      background-color: #4CAF50;
      color: white;
      padding: 15px;
      margin-top: 20px;
      text-align: center;
      border-radius: 5px;
      display: none;
    }

    .alert.error {
      background-color: #f44336;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    status{
        position: relative;
        align-items: left;
    }
  </style>
  </head>

<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">
  <div class="main-content">
    <h1>Add Supplier</h1>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert" style="color: green;"><?= $_GET['success'] ?></div>
    <?php elseif (isset($_GET['error'])): ?>
      <div class="alert" style="color: red;"><?= $_GET['error'] ?></div>
    <?php endif; ?>

    <form action="add_supplier.php" method="POST">
      <div class="grid">
        <div class="form-group">
          <input type="text" name="name" id="name" required>
          <label for="name">Supplier Name</label>
        </div>
        <div class="form-group">
          <input type="text" name="contact_person" id="contact_person" required>
          <label for="contact_person">Contact Person</label>
        </div>
        <div class="form-group">
          <input type="email" name="contact_email" id="contact_email" required>
          <label for="contact_email">Contact Email</label>
        </div>
        <div class="form-group">
          <input type="tel" name="contact_phone" id="contact_phone" required>
          <label for="contact_phone">Contact Phone</label>
        </div>
        <div class="form-group">
          <input type="text" name="address" id="address" required>
          <label for="address">Address</label>
        </div>
        <div class="form-group">
          <input type="text" name="product_supplied" id="product_supplied" required>
          <label for="product_supplied">Product Supplied</label>
        </div>
        <div class="form-group">
          <select name="status" id="status" required>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <label for="status">Status</label>
        </div>
        <button type="submit" class="submit-btn">Submit</button>
      </div>
    </form>
  </div>

</body>

</html>

