<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['role'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

// Success / Error message
$message = $_SESSION['return_msg'] ?? "";
unset($_SESSION['return_msg']);

/* ===========================
   HANDLE RETURN SUBMISSION
=========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $borrow_id = (int)$_POST['borrow_id'];
    $qty_returned = (int)$_POST['quantity_returned'];
    $condition_label = trim($_POST['condition_label']);
    $remarks = trim($_POST['remarks']);
    $processed_by = $_SESSION['username'] ?? 'Admin';
    $return_date = date("Y-m-d H:i:s");

    // Fetch borrow + item name
    $borrow = $conn->query("
        SELECT 
            b.*, 
            i.name AS item_name,
            i.department AS inv_department
        FROM borrow_item b
        LEFT JOIN inventory i ON i.inventory_id = b.inventory_id
        WHERE b.borrow_id = $borrow_id
    ")->fetch_assoc();

    if (!$borrow) {
        $_SESSION['return_msg'] = "❌ Borrow record not found.";
        header("Location: return_item.php");
        exit();
    }

    $remaining = $borrow['quantity_borrowed'] - $borrow['quantity_returned'];
    if ($qty_returned > $remaining) {
        $_SESSION['return_msg'] = "❌ Returned quantity exceeds remaining borrowed items.";
        header("Location: return_item.php");
        exit();
    }

    $inventory_id = (int)$borrow['inventory_id'];
    $item_name = $borrow['item_name'];
    $borrower_name = $borrow['borrower_name'];
    $borrower_department = $borrow['borrower_department'];

    // Calculate new totals
    $new_total = $borrow['quantity_returned'] + $qty_returned;
    $new_status = ($new_total >= $borrow['quantity_borrowed']) ? "Returned" : "Partially Returned";

    // Update borrow_item
    $stmtU = $conn->prepare("
        UPDATE borrow_item SET 
            quantity_returned = ?,
            return_date = ?,
            processed_by = ?,
            condition_label = ?,
            remarks = ?,
            status = ? 
        WHERE borrow_id = ?
    ");
    $stmtU->bind_param("isssssi", 
        $new_total, $return_date, $processed_by, 
        $condition_label, $remarks, $new_status, 
        $borrow_id
    );
    $stmtU->execute();
    $stmtU->close();

    // Insert into return_item table (FINAL + CORRECT)
    $stmt = $conn->prepare("
        INSERT INTO return_item 
        (borrow_id, inventory_id, item_name, quantity_returned, condition_label, remarks, processed_by, department, borrower_name, return_date)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param("iisiisssss", 
        $borrow_id, 
        $inventory_id, 
        $item_name, 
        $qty_returned, 
        $condition_label, 
        $remarks, 
        $processed_by, 
        $borrower_department, 
        $borrower_name, 
        $return_date
    );
    $stmt->execute();
    $stmt->close();

    // Restore to inventory only if good
    if ($condition_label === "New") {
        $conn->query("UPDATE inventory SET quantity = quantity + $qty_returned WHERE inventory_id = $inventory_id");
    }

    // Insert into damage_lost_item if needed
    if ($condition_label !== "New") {
        $stmt2 = $conn->prepare("
            INSERT INTO damage_lost_item 
            (borrow_id, inventory_id, item_name, quantity, condition_label, remarks, processed_by, date_recorded)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt2->bind_param(
            "iiisssss", 
            $borrow_id, 
            $inventory_id, 
            $item_name, 
            $qty_returned, 
            $condition_label, 
            $remarks, 
            $processed_by, 
            $return_date
        );

        $stmt2->execute();
        $stmt2->close();
    }

    log_activity(
        $_SESSION['user_id'], 
        "Returned item: $item_name (Qty: $qty_returned, Condition: $condition_label)", 
        "Item Return", 
        "Success", 
        $conn
    );

    $_SESSION['return_msg'] = "✅ Item return recorded!";
    header("Location: return_item.php");
    exit();
}

/* =====================================
   FETCH ITEMS STILL NOT FULLY RETURNED
====================================== */
$pending = $conn->query("
    SELECT 
        b.borrow_id, 
        i.name AS item_name, 
        b.borrower_name, 
        b.borrower_department,
        b.quantity_borrowed, 
        b.quantity_returned, 
        b.date_borrowed
    FROM borrow_item b
    LEFT JOIN inventory i ON i.inventory_id = b.inventory_id
    WHERE b.quantity_returned < b.quantity_borrowed
    ORDER BY b.borrow_id DESC
");
?>
<!-- HTML code remains the same -->

<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Return Borrowed Items</title>
  <link rel="stylesheet" href="admindasboard.css">

  <style>
    body {
      font-family: Poppins, sans-serif;
      background: #f1f3f7;
    }

    /* FORCE BLACK TEXT */
    .main-content,
    .main-content * {
      color: #000 !important;
    }

    /* TABLE BOX */
    .table-box {
      background: #fff;
      margin: 30px;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.1);
    }

    h2 {
      color: #143ab7 !important;
    }

    /* TABLE */
    table {
      width: 100%;
      border-collapse: collapse;
    }

    th {
      background: #1c7ac7;
      color: white;
      padding: 10px;
      text-transform: uppercase;
    }

    td {
      padding: 10px;
      border-bottom: 1px solid #ddd;
    }

    /* SEARCH */
    .search-box {
      width: 300px;
      padding: 10px;
      border-radius: 6px;
      border: 1px solid #888;
      margin: 10px 0;
    }

    /* BUTTONS */
    .return-btn {
      background: #28a745;
      padding: 6px 12px;
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }

    .return-btn:hover {
      background: #1f8b3a;
    }

    .form-box {
      display: none;
      background: #fff;
      padding: 25px;
      margin: 25px auto;
      max-width: 550px;
      border-radius: 12px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.1);
    }

    .submit-btn {
      background: #007bff;
      padding: 10px 14px;
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }

    .submit-btn:hover {
      background: #0056b3;
    }

    .message {
      background: #d4edda;
      color: #155724;
      padding: 12px;
      border-radius: 8px;
      font-weight: bold;
      text-align: center;
    }
  </style>
</head>

<body>

  <div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="main-content">

      <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
      <?php endif; ?>

      <div class="table-box">
        <h2><b>Borrowed Items (Pending Return)</b></h2>

        <input type="text" id="searchInput" class="search-box" placeholder="Search borrowed items...">

        <table id="borrowTable">
          <thead>
            <tr>
              <th>Item</th>
              <th>Borrower</th>
              <th>Department</th>
              <th>Borrowed</th>
              <th>Remaining</th>
              <th>Date Borrowed</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <?php while ($row = $pending->fetch_assoc()):
              $remaining = $row['quantity_borrowed'] - $row['quantity_returned'];
            ?>
              <tr>
                <td><?= htmlspecialchars($row['item_name']) ?></td>
                <td><?= htmlspecialchars($row['borrower_name']) ?></td>
                <td><?= htmlspecialchars($row['borrower_department']) ?></td>
                <td><?= $row['quantity_borrowed'] ?></td>
                <td><?= $remaining ?></td>
                <td><?= $row['date_borrowed'] ?></td>
                <td>
                  <button class="return-btn"
                    onclick="openReturnForm(
                <?= $row['borrow_id'] ?>,
                '<?= htmlspecialchars($row['item_name']) ?>',
                <?= $remaining ?>
            )">Return</button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>


      <!-- RETURN FORM -->
      <div class="form-box" id="returnFormBox">
        <h2>Return Item</h2>

        <form method="POST">
          <input type="hidden" name="borrow_id" id="borrow_id">

          <label>Item</label>
          <input type="text" id="item_name" readonly>

          <label>Quantity Remaining</label>
          <input type="text" id="qty_remaining" readonly>

          <label>Quantity to Return</label>
          <input type="number" name="quantity_returned" min="1" required>

          <label>Condition</label>
          <select name="condition_label" required>
            <option value="New">New</option>
            <option value="Damage">Damage</option>
            <option value="Lost">Lost</option>
            <option value="Expired">Expired</option>
          </select>

          <label>Remarks</label>
          <textarea name="remarks" rows="3"></textarea>

          <button type="submit" class="submit-btn">Submit Return</button>
        </form>
      </div>

    </main>
  </div>

  <script>
    // Show return form with item selected
    function openReturnForm(id, name, remaining) {
      document.getElementById('borrow_id').value = id;
      document.getElementById('item_name').value = name;
      document.getElementById('qty_remaining').value = remaining;

      document.getElementById('returnFormBox').style.display = 'block';
      document.getElementById('returnFormBox').scrollIntoView({
        behavior: 'smooth'
      });
    }

    // Search filter
    document.getElementById('searchInput').addEventListener('keyup', function() {
      let filter = this.value.toLowerCase();
      document.querySelectorAll('#borrowTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
      });
    });
  </script>

  <script src="chart.umd.min.js"></script>
  <script src="admindashboard.js" defer></script>

</body>

</html>
