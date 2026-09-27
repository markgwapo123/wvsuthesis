<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Access control
if (!isset($_SESSION['role'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

// One-time message
$message = $_SESSION['borrow_message'] ?? "";
unset($_SESSION['borrow_message']);

// Fetch items WITHOUT department = ready for borrowing
$items = $conn->query("
    SELECT inventory_id, name, category, quantity, condition_label, status_label
    FROM inventory
    WHERE (department IS NULL OR department = '')
      AND quantity > 0
    ORDER BY name ASC
");

/* =============================================
   HANDLE BORROW SUBMISSION
============================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $inventory_id = (int)$_POST['inventory_id'];
    $borrower_name = trim($_POST['borrower_name']);
    $borrower_department = trim($_POST['borrower_department']);
    $qty_borrow = (int)$_POST['quantity_borrowed'];
    $expected = $_POST['expected_return_date'] ?: NULL;
    $remarks = trim($_POST['remarks']);

    // Check stock
    $stockRow = $conn->query("SELECT quantity FROM inventory WHERE inventory_id=$inventory_id")->fetch_assoc();
    $stock = $stockRow['quantity'] ?? 0;

    if ($qty_borrow > $stock) {
        $_SESSION['borrow_message'] = "❌ Not enough stock (Available: $stock)";
        header("Location: borrow_item.php");
        exit();
    }

    // Insert into borrow_item
    $stmt = $conn->prepare("
        INSERT INTO borrow_item 
        (inventory_id, borrower_name, borrower_department, quantity_borrowed, expected_return_date, remarks)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ississ", 
        $inventory_id, 
        $borrower_name, 
        $borrower_department, 
        $qty_borrow,
        $expected,
        $remarks
    );

    $stmt->execute();
    $stmt->close();

    // Reduce stock
    $conn->query("UPDATE inventory SET quantity = quantity - $qty_borrow WHERE inventory_id=$inventory_id");

    $_SESSION['borrow_message'] = "✅ Item borrowed successfully!";
    header("Location: borrow_item.php");
    exit();
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Borrow Items</title>
<link rel="stylesheet" href="admindasboard.css">

<style>
body {
    font-family: Poppins, sans-serif;
    background: #f1f3f7;
    margin: 0;
}

/* FIX INVISIBLE TEXT IN DARK MODE */
.main-content,
.main-content * {
    color: #000 !important;
}

/* MAIN TABLE BOX */
.table-box {
    background: #fff;
    margin: 30px;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.1);
}

/* TABLE DESIGN */
table {
    width: 100%;
    border-collapse: collapse;
}
th {
    background: #1c7ac7;
    color: white;
    padding: 12px;
    font-size: 14px;
    text-transform: uppercase;
    text-align: center;
}
td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: center;
}

/* SEARCH BOX */
.search-box {
    width: 300px;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #888;
    margin-bottom: 15px;
}

/* BUTTONS */
.borrow-btn {
    background: #007bff;
    padding: 7px 14px;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.borrow-btn:hover { background: #005fcc; }

/* FORM BOX */
.form-box {
    display: none;
    background: #fff;
    padding: 25px;
    margin: 25px auto;
    max-width: 550px;
    border-radius: 12px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.1);
    animation: slideDown .3s ease;
}
label { font-weight: 600; margin-top: 10px; display:block; }
input, select, textarea {
    width: 100%;
    padding: 10px;
    margin-top: 4px;
    border-radius: 6px;
    border: 1px solid #aaa;
}
.submit-btn {
    margin-top: 15px;
    background: #28a745;
    padding: 10px 14px;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.submit-btn:hover { background: #1f8b3a; }

/* MESSAGE */
.message {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    border-radius: 8px;
    margin: 15px;
    font-weight: bold;
    text-align: center;
}

/* Animation */
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-15px); }
    to   { opacity: 1; transform: translateY(0); }
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

<h2 style="color:#1c7ac7;">Available Items for Borrowing</h2>

<table>
<thead>
<tr>
    <th>Item</th>
    <th>Category</th>
    <th>Quantity</th>
    <th>Condition</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>

<tbody>
<?php while ($row = $items->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td><?= htmlspecialchars($row['category']) ?></td>
    <td><?= $row['quantity'] ?></td>
    <td><?= $row['condition_label'] ?></td>
    <td><?= $row['status_label'] ?></td>

    <td>
        <button class="borrow-btn"
            onclick="openBorrowForm(
                <?= $row['inventory_id'] ?>,
                '<?= htmlspecialchars($row['name']) ?>',
                <?= $row['quantity'] ?>
            )"
        >Borrow</button>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</div>

<!-- BORROW FORM -->
<div class="form-box" id="borrowFormBox">
<h2>Borrow This Item</h2>

<form method="POST">

    <input type="hidden" name="inventory_id" id="inventory_id">
    
    <label>Item Name</label>
    <input type="text" id="item_name" readonly>

    <label>Available Stock</label>
    <input type="text" id="item_stock" readonly>

    <label>Quantity to Borrow</label>
    <input type="number" name="quantity_borrowed" min="1" required>

    <label>Borrower Name</label>
    <input type="text" name="borrower_name" required>

    <label>Department</label>
    <input type="text" name="borrower_department">

    <label>Expected Return Date</label>
    <input type="date" name="expected_return_date">

    <label>Remarks</label>
    <textarea name="remarks"></textarea>

    <button type="submit" class="submit-btn">Confirm Borrow</button>

</form>
</div>

</main>
</div>

<script>
function openBorrowForm(id, name, stock) {
    document.getElementById('inventory_id').value = id;
    document.getElementById('item_name').value = name;
    document.getElementById('item_stock').value = stock;

    const form = document.getElementById('borrowFormBox');
    form.style.display = 'block';
    form.scrollIntoView({ behavior: "smooth" });
}
</script>

<script src="chart.umd.min.js"></script>
<script src="admindashboard.js" defer></script>

</body>
</html>

