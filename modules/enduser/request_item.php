<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Force authentication context (Accessible by end_users and admins)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentUserId = $_SESSION['user_id'] ?? 0;
$userRole      = $_SESSION['role'] ?? 'end_user';
$userName      = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Unknown User');
$userDept      = $_SESSION['department'] ?? 'General Department'; 

// Fetch user theme setting
$settingsQuery = $conn->query("SELECT theme FROM user_settings WHERE user_id = " . (int)$currentUserId . " LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0) ? $settingsQuery->fetch_assoc() : ['theme' => 'light'];

// Process form submission
$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName = trim($_POST['item_name'] ?? '');
    $quantity = (int)($_POST['qty'] ?? 0);
    $unit     = trim($_POST['unit'] ?? ''); // Captured from the new input field
    
    if (empty($itemName) || $quantity <= 0 || empty($unit)) {
        $message = "❌ Please provide a valid item name, quantity, and unit.";
        $messageClass = "msg-error";
    } else {
        // Prepare Statement with 5 placeholder tokens matching the columns listed
        $stmt = $conn->prepare("INSERT INTO orders (requestor_name, department, item_name, qty, unit, status, order_date, is_read) VALUES (?, ?, ?, ?, ?, 'Pending', NOW(), 0)");
        
        // FIX: Changed $item_name to $itemName to match line 24.
        // Also verified types: "s" (string), "s" (string), "s" (string), "i" (integer), "s" (string) -> "sssis"
        $stmt->bind_param("sssis", $userName, $userDept, $itemName, $quantity, $unit);
        
        if ($stmt->execute()) {
            $message = "🎉 Request submitted successfully and forwarded to DAA!";
            $messageClass = "msg-success";
        } else {
            $message = "❌ Database Error: Failed to save your request. " . $conn->error;
            $messageClass = "msg-error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Restock Request</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        :root {
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #475569;
            --border-color: #e2e8f0;
            --input-disabled: #f1f5f9;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
        }

        .dark-mode {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --input-disabled: #0f172a;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.2);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }

        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 20px;
            box-sizing: border-box;
        }

        .form-container {
            width: 100%;
            max-width: 650px;
            background: var(--bg-card);
            padding: 40px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .form-header {
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 20px;
        }

        .form-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .form-header p {
            margin: 6px 0 0 0;
            font-size: 14px;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            height: 48px;
            padding: 10px 16px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-main);
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }

        .form-control:disabled {
            background: var(--input-disabled);
            color: var(--text-muted);
            cursor: not-allowed;
            border-color: var(--border-color);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-row-triple {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 20px;
        }

        @media (max-width: 550px) {
            .form-row, .form-row-triple {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            height: 50px;
            padding: 0 24px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: background 0.15s ease;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
        }

        .back-btn {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            margin-top: 24px;
            display: inline-block;
            text-decoration: none;
            text-align: center;
        }

        .back-btn:hover {
            background: var(--input-disabled);
            color: var(--text-main);
        }

        .alert-message {
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 14.5px;
            font-weight: 500;
            margin-bottom: 24px;
        }
        .msg-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .msg-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">
<div class="dashboard" style="display: flex;">
    <?php if ($userRole !== 'end_user') { include __DIR__ . '/../../sidebar.php'; } ?>
    
    <main class="main-content" style="width: 100%;">
        <div class="form-container">
            <div class="form-header">
                <h2>Create Restock Request</h2>
                <p>Fill out the item specifications below to submit an order request to your DAA.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert-message <?= $messageClass ?>"><?= $message ?></div>
            <?php endif; ?>

            <form action="request_item.php" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Requestor Name</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($userName) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($userDept) ?>" disabled>
                    </div>
                </div>

                <div class="form-group">
                    <label for="item_name">Item / Goods Description</label>
                    <input type="text" name="item_name" id="item_name" class="form-control" placeholder="e.g., A4 Copy Paper, Ballpen Blue" required autocomplete="off">
                </div>

                <div class="form-row-triple">
                    <div class="form-group">
                        <label for="qty">Quantity</label>
                        <input type="number" name="qty" id="qty" class="form-control" min="1" placeholder="0" required>
                    </div>
                    <div class="form-group">
                        <label for="unit">Unit</label>
                        <input type="text" name="unit" id="unit" class="form-control" placeholder="pcs, boxes, reams" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Expected Date</label>
                        <input type="date" name="expected_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Order Date & Time</label>
                        <input type="text" id="live_date" class="form-control" disabled>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Submit Request Order</button>
            </form>

            <a href="endUserDashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </main>
</div>

<script>
    function updateLiveClock() {
        const now = new Date();
        const options = { 
            year: 'numeric', month: 'short', day: '2-digit', 
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: true 
        };
        document.getElementById('live_date').value = now.toLocaleString('en-US', options);
    }
    updateLiveClock();
    setInterval(updateLiveClock, 1000);
</script>
</body>
</html>