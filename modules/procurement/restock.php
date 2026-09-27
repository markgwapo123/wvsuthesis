<?php

session_start();

require_once __DIR__ . "/db_connect.php";



/* ======================

   SECURITY CHECK

====================== */

if (!isset($_SESSION['user_id'], $_SESSION['fullname'], $_SESSION['department'])) {

    die("Unauthorized access.");

}



/* ======================

   INIT VARIABLES

====================== */

$inventory_id = (int)($_GET['inventory_id'] ?? 0);

$item = null;

$items_list = [];



/* ======================

   FETCH DATA

====================== */

if ($inventory_id > 0) {

    // Mode 1: Specific Item Selected (via URL)

    $stmt = $conn->prepare("SELECT name AS item_name, unit FROM inventory WHERE inventory_id = ?");

    $stmt->bind_param("i", $inventory_id);

    $stmt->execute();

    $item = $stmt->get_result()->fetch_assoc();



    if (!$item) {

        die("Item not found in inventory.");

    }

} else {

    // Mode 2: No Item Selected (Fetch All for Dropdown)

    $result = $conn->query("SELECT inventory_id, name AS item_name, unit FROM inventory ORDER BY name ASC");

    if ($result) {

        $items_list = $result->fetch_all(MYSQLI_ASSOC);

    }

}



// Auto-filled values

$requestor    = $_SESSION['fullname'];

$department   = $_SESSION['department'];

$order_date   = date('Y-m-d');

$error_message = "";



// Variables for display (if item pre-selected)

$display_item_name = $item ? $item['item_name'] : '';

$display_unit      = $item ? $item['unit'] : '';



/* ======================

   HANDLE SUBMIT

====================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    $qty = (int)$_POST['qty'];

    $expected_date = $_POST['expected_date'];

    $target_unit = $_POST['unit'] ?? ''; // Get unit from user selection

    

    // Determine Item Name

    $target_name = "";



    if ($inventory_id > 0) {

        // Use the pre-loaded item

        $target_name = $item['item_name'];

    } elseif (isset($_POST['selected_inventory_id'])) {

        // Fetch details for the item selected in dropdown

        $selected_id = (int)$_POST['selected_inventory_id'];

        $stmt = $conn->prepare("SELECT name FROM inventory WHERE inventory_id = ?");

        $stmt->bind_param("i", $selected_id);

        $stmt->execute();

        $res = $stmt->get_result()->fetch_assoc();

        

        if ($res) {

            $target_name = $res['name'];

        } else {

            $error_message = "Invalid item selection.";

        }

    } else {

        $error_message = "Please select an item.";

    }



    // Process Insert

    if (empty($error_message)) {

        if ($qty <= 0 || empty($expected_date)) {

            $error_message = "Quantity and expected date are required.";

        } elseif (empty($target_unit)) {

             $error_message = "Unit is required.";

        } else {

            // FIXED: Removed the extra trailing '?' parameter placeholder

            $stmt = $conn->prepare("

                INSERT INTO orders

                (requestor_name, department, item_name, qty, order_date, expected_date, unit)

                VALUES (?, ?, ?, ?, ?, ?, ?)

            ");



            $stmt->bind_param(

                "sssisss",

                $requestor,

                $department,

                $target_name,

                $qty,

                $order_date,

                $expected_date,

                $target_unit

            );



            if ($stmt->execute()) {

                header("Location: order_management.php?success=restock_created");

                exit();

            } else {

                $error_message = "Failed to create restock order.";

            }

        }

    }

}



// Define standard units

$common_units = ['pcs', 'ream','box', 'pack', 'set', 'roll', 'can', 'liter', 'kg', 'meter', 'bundle', 'pair', 'dozen'];

// Add current DB unit to list if it's not standard

if (!empty($display_unit) && !in_array($display_unit, $common_units)) {

    array_unshift($common_units, $display_unit);

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Restock Item</title>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/bootstrap-5.3.2-dist/css/bootstrap.css">



<style>

body {

    font-family: 'Inter', Arial, sans-serif;

    background-color: #003366;

    color: #1e293b;

    padding: 0;

    margin: 0;

}

.main-content {

    max-width: 750px;

    margin: 60px auto;

    background: #ffffff;

    border-radius: 16px;

    padding: 40px;

    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);

    border: 1px solid #e2e8f0;

}

h1 {

    font-size: 1.75rem;

    font-weight: 700;

    color: #0f172a;

    margin-bottom: 30px;

    letter-spacing: -0.02em;

}

.grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 24px;

}

@media (max-width: 576px) {

    .grid {

        grid-template-columns: 1fr;

        gap: 16px;

    }

    .submit-btn {

        grid-column: 1 / 2;

    }

}

label {

    font-weight: 500;

    font-size: 0.875rem;

    color: #475569;

    margin-bottom: 8px;

    display: block;

}

input, select {

    height: 50px;

    padding: 10px 16px;

    border-radius: 8px;

    border: 1px solid #cbd5e1;

    background-color: #ffffff;

    width: 100%;

    font-size: 0.95rem;

    color: #1e293b;

    transition: all 0.2s ease-in-out;

}

input:focus, select:focus {

    outline: none;

    border-color: #3b82f6;

    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);

}

input[readonly] {

    background-color: #f1f5f9;

    border-color: #e2e8f0;

    color: #64748b;

    cursor: not-allowed;

}

.submit-btn {

    grid-column: 1 / 3;

    height: 52px;

    margin-top: 10px;

    background: #2563eb;

    color: #fff;

    border: none;

    border-radius: 8px;

    font-size: 1rem;

    font-weight: 600;

    cursor: pointer;

    transition: background-color 0.15s ease-in-out;

}

.submit-btn:hover {

    background: #1d4ed8;

}

.back-btn {

    margin-top: 24px;

    background: transparent;

    color: #64748b;

    border: 1px solid #cbd5e1;

    padding: 10px 20px;

    border-radius: 8px;

    font-size: 0.9rem;

    font-weight: 500;

    cursor: pointer;

    transition: all 0.15s ease-in-out;

}

.back-btn:hover {

    background: #f1f5f9;

    color: #334155;

    border-color: #94a3b8;

}

.alert {

    border-radius: 8px;

    font-size: 0.95rem;

    padding: 14px 18px;

    margin-bottom: 24px;

}

</style>

</head>



<body>

<div class="main-content">

    <h1>Restock Item</h1>



    <?php if (!empty($error_message)): ?>

        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>

    <?php endif; ?>



    <form method="post">

        <div class="grid">

            <div>

                <label>Requestor</label>

                <input value="<?= htmlspecialchars($requestor) ?>" readonly>

            </div>

            <div>

                <label>Department</label>

                <input value="<?= htmlspecialchars($department) ?>" readonly>

            </div>

            

            <div>

                <label>Item Name</label>

                <?php if ($inventory_id > 0): ?>

                    <input value="<?= htmlspecialchars($display_item_name) ?>" readonly>

                <?php else: ?>

                    <select name="selected_inventory_id" required>

                        <option value="" disabled selected>Select Item to Restock</option>

                        <?php foreach ($items_list as $i): ?>

                            <option value="<?= $i['inventory_id'] ?>">

                                <?= htmlspecialchars($i['item_name']) ?> (<?= htmlspecialchars($i['unit']) ?>)

                            </option>

                        <?php endforeach; ?>

                    </select>

                <?php endif; ?>

            </div>

            <div>

                <label>Quantity</label>

                <input type="number" name="qty" min="1" placeholder="Enter Quantity" required>

            </div>



            <div>

                <label>Expected Date</label>

                <input type="date" name="expected_date" required>

            </div>

            <div>

                <label>Unit</label>

                <select name="unit" required>

                    <option value="" disabled <?= empty($display_unit) ? 'selected' : '' ?>>Select Unit</option>

                    <?php foreach ($common_units as $u): ?>

                        <option value="<?= htmlspecialchars($u) ?>" <?= ($display_unit === $u) ? 'selected' : '' ?>>

                            <?= htmlspecialchars(ucfirst($u)) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <input type="hidden" name="order_date" value="<?= $order_date ?>">



            <button type="submit" class="submit-btn">Submit Restock Order</button>

        </div>

    </form>



    <button class="back-btn" onclick="window.location.href='endUserDashboard.php'">← Back to Dashboard</button>

</div>

</body>

</html>