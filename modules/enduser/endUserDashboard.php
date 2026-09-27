<?php
// Only DB + session, no auth.php
require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple auth: only allow logged-in end_user
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'end_user') {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

$user_id    = $_SESSION['user_id'];
$department = $_SESSION['department'] ?? null;

if (!$department) {
    exit('No department assigned to this End User. Contact the administrator.');
}

// --- Rest of your original database queries continue below ---
$stmtInv = $conn->prepare("SELECT * FROM inventory WHERE department = ?");
$stmtInv->bind_param("s", $department);
$stmtInv->execute();
$inventory_result = $stmtInv->get_result();

// Pending orders for this department
$stmtReq = $conn->prepare("SELECT * FROM orders WHERE department = ? AND status = 'Pending'");
$stmtReq->bind_param("s", $department);
$stmtReq->execute();
$pending_orders_result = $stmtReq->get_result();

// Pending request for this department
$stmtReq = $conn->prepare("SELECT * FROM requests WHERE requester_department = ? AND status = 'Pending'");
$stmtReq->bind_param("s", $department);
$stmtReq->execute();
$pending_requests_result = $stmtReq->get_result();

// Low stock (example: < 5)
$stmtLow = $conn->prepare("SELECT * FROM inventory WHERE department = ? AND quantity < 5 AND quantity > 0");
$stmtLow->bind_param("s", $department);
$stmtLow->execute();
$low_stock_result = $stmtLow->get_result();

// Out of stock
$stmtOut = $conn->prepare("SELECT * FROM inventory WHERE department = ? AND quantity = 0");
$stmtOut->bind_param("s", $department);
$stmtOut->execute();
$out_of_stock_result = $stmtOut->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>End User Dashboard</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="enduser.css">
    <style>
        /* CSS to style and natively disable click behaviors for the unclickable state */
        .btn.disabled-request {
            background-color: #cbd5e1 !important;
            color: #64748b !important;
            border-color: #cbd5e1 !important;
            cursor: not-allowed !important;
            pointer-events: none; /* Block click handlers entirely */
            opacity: 0.7;
        }
    </style>
</head>

<body>
    <div class="layout">
        
        <?php include __DIR__ . '/endusersidebar.php'; ?>

        <main class="content">

            <header>
                <h1>SOIMS | End User Workspace</h1>
                <nav>
                    <ul>
                        <li><strong><?= htmlspecialchars($_SESSION['fullname'] ?? 'End User'); ?></strong></li>
                        <li><a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </nav>
            </header>

            <div class="page-wrap">
                <h2 class="page-title"><?= htmlspecialchars($department); ?> Department</h2>
                <p class="subtitle">You are viewing stocks and requests for your assigned department only.</p>

                <div class="status-cards">
                    <div class="card">
                        <h2>Pending Orders</h2>
                        <p><?= $pending_orders_result->num_rows; ?> pending order(s)</p>
                    </div>
                    <div class="card">
                        <h2>Pending Requests</h2>
                        <p><?= $pending_requests_result->num_rows; ?> pending request(s)</p>
                    </div>
                    <div class="card">
                        <h2>Low Stock</h2>
                        <p><?= $low_stock_result->num_rows; ?> item(s) low on stock</p>
                    </div>
                    <div class="card">
                        <h2>Out of Stock</h2>
                        <p><?= $out_of_stock_result->num_rows; ?> item(s) out of stock</p>
                    </div>
                </div>

                <div class="inventory">
                    <h2>Inventory for <?= htmlspecialchars($department); ?> Department</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($inventory_result && $inventory_result->num_rows > 0): ?>
                                <?php while ($item = $inventory_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $itemName = $item['item_name']
                                                ?? $item['name']
                                                ?? $item['item']
                                                ?? $item['description']
                                                ?? '[Unnamed Item]';
                                            echo htmlspecialchars($itemName);
                                            ?>
                                        </td>

                                        <td><?= (int)$item['quantity'] ?></td>

                                        <td>
                                            <?php
                                            $qty = (int)$item['quantity'];
                                            if ($qty === 0) {
                                                echo '<span class="status out-of-stock">Out of Stock</span>';
                                            } elseif ($qty < 5) {
                                                echo '<span class="status low-stock">Low Stock</span>';
                                            } else {
                                                echo '<span class="status in-stock">In Stock</span>';
                                            }
                                            ?>
                                        </td>

                                        <td>
                                            <?php 
                                            $itemId = $item['inventory_id'] ?? null; 
                                            
                                            // Action Column logic: Show active or unclickable single request button
                                            if ($qty > 0 && $itemId): ?>
                                                <a href="request_stock.php?inventory_id=<?= $itemId ?>" class="btn">Request</a>
                                            <?php else: ?>
                                                <a href="#" class="btn disabled-request" tabindex="-1">Out of Stock</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #64748b; padding: 25px 10px;">
                                        No inventory line items cataloged under this department allocation.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <footer>
                &copy; <?= date('Y'); ?> SOIMS. All rights reserved.
            </footer>
        </main>
    </div>

    <div id="logoutConfirm" class="logout-overlay" style="display:none;">
        <div class="logout-box">
            <p>Are you sure you want to log out of SOIMS?</p>
            <div class="logout-actions">
                <button id="confirmLogout" class="btn-yes">Yes, Logout</button>
                <button id="cancelLogout" class="btn-no">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Open modal context view
        document.getElementById("logoutBtn").onclick = function(e) {
            e.preventDefault();
            document.getElementById("logoutConfirm").style.display = "flex";
        };

        // Terminate and exit modal layout view
        document.getElementById("cancelLogout").onclick = function() {
            document.getElementById("logoutConfirm").style.display = "none";
        };

        // Direct session destroy command pipeline
        document.getElementById("confirmLogout").onclick = async function() {
            try {
                const response = await fetch("/SUPPLY_OFFICE_first/modules/auth/logout.php", {
                    method: "POST",
                    headers: { "X-Requested-With": "XMLHttpRequest" },
                    credentials: "same-origin"
                });
                if (!response.ok) throw new Error("Logout request failed.");
            } catch (error) {
                console.error(error);
                window.location.replace("/SUPPLY_OFFICE_first/modules/auth/logout.php");
                return;
            }
            window.location.replace("/SUPPLY_OFFICE_first/modules/auth/index.php");
        };
    </script>
</body>
</html>
