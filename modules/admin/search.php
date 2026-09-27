<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Access control
if (!isset($_SESSION['role'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

// Get search keyword
$keyword = trim($_GET['q'] ?? '');
$results = [];

if ($keyword !== '') {
    $safe = '%' . $conn->real_escape_string($keyword) . '%';

    // Map of table => columns to search
    $tables = [
        'activity_logs'     => ['action', 'details', 'user_id', 'created_at'],
        'archive_all'       => ['table_name', 'record_id', 'data', 'deleted_by'],
        'archive_inventory' => ['item_id', 'name', 'barcode', 'description', 'location'],
        'barcodes'          => ['barcode', 'item_id'],
        'borrow_item'       => ['borrower_name', 'borrower_department', 'remarks', 'status'],
        'inventory'         => ['name', 'code', 'barcode', 'category', 'location', 'department'],
        'issuances'         => ['issued_to', 'remarks', 'status'],
        'notifications'     => ['title', 'message', 'type'],
        'orders'            => ['order_number', 'status', 'remarks'],
        'roles'             => ['role_name', 'permissions'],
        'stock_status'      => ['status_name', 'remarks'],
        'suppliers'         => ['name', 'address', 'contact'],
        'users'             => ['username', 'email', 'role'],
        'user_settings'     => ['theme', 'auto_order', 'color_stock_ok', 'color_stock_low']
    ];


    foreach ($tables as $table => $cols) {
        $likeClauses = [];
        foreach ($cols as $col) {
            // ✅ Check column existence before querying
            $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($check && $check->num_rows > 0) {
                $likeClauses[] = "`$col` LIKE '$safe'";
            }
        }
        if (!empty($likeClauses)) {
            $where = implode(' OR ', $likeClauses);
            $sql = "SELECT * FROM `$table` WHERE $where LIMIT 10";
            $query = $conn->query($sql);
            if ($query && $query->num_rows > 0) {
                $results[$table] = $query->fetch_all(MYSQLI_ASSOC);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Search</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f6f8;
            color: #222;
            margin: 0;
            padding: 0;
        }

        .search-box {
            max-width: 600px;
            margin: 30px auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        input[type="text"] {
            width: 80%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
        }

        button {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            background: #007bff;
            color: #fff;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }

        .result-box {
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        h3 {
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
            color: #007bff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #007bff;
            color: white;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #888;
        }
    </style>
</head>

<body>
    <div class="dashboard">
        <?php include __DIR__ . '/../../sidebar.php'; ?>
        <main class="main-content">
            <div class="search-box">
                <form method="GET" action="search.php">
                    <input type="text" name="q" placeholder="Search anything..." value="<?= htmlspecialchars($keyword) ?>" required>
                    <button type="submit">🔍 Search</button>
                </form>
            </div>

            <?php if ($keyword !== ''): ?>
                <div class="result-box">
                    <h2>Search results for "<em><?= htmlspecialchars($keyword) ?></em>"</h2>

                    <?php if (empty($results)): ?>
                        <div class="no-results">No results found in any table.</div>
                    <?php else: ?>
                        <?php foreach ($results as $table => $rows): ?>
                            <h3><?= htmlspecialchars(ucwords(str_replace('_', ' ', $table))) ?></h3>
                            <table>
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($rows[0]) as $col): ?>
                                            <th><?= htmlspecialchars($col) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $val): ?>
                                                <td><?= htmlspecialchars((string)$val) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>

</html>
