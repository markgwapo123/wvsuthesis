<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Access control
if (!isset($_SESSION['role'])) {
  header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
  exit();
}

// Get params
$type = $_GET['type'] ?? '';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Map type => table + PK
$entities = [
  'inventory' => ['table' => 'inventory', 'pk' => 'inventory_id', 'title' => 'View Inventory Record'],
  'item'      => ['table' => 'items', 'pk' => 'item_id', 'title' => 'View Item'],
  'supplier'  => ['table' => 'suppliers', 'pk' => 'supplier_id', 'title' => 'View Supplier'],
  'user'      => ['table' => 'users', 'pk' => 'id', 'title' => 'View User'],
  'order'     => ['table' => 'orders', 'pk' => 'order_id', 'title' => 'View Order'],
];

if (!isset($entities[$type]) || $id <= 0) {
  $error = "Invalid record type or ID.";
  $row = null;
} else {
  $meta  = $entities[$type];
  $table = $meta['table'];
  $pk    = $meta['pk'];

  $res = $conn->query("SELECT * FROM `$table` WHERE `$pk` = $id");
  if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
  } else {
    $error = "Record not found.";
    $row = null;
  }
}

// Load theme
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
  ? $settingsQuery->fetch_assoc()
  : ['theme' => 'light'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= isset($meta['title']) ? htmlspecialchars($meta['title']) : 'View Record' ?></title>
  <link rel="stylesheet" href="admindasboard.css">
<style>
/* ============================
   BASE VIEW BOX (LIGHT MODE)
============================ */
.view-box {
  background: #fff;
  padding: 25px;
  margin: 20px auto;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,.1);
  max-width: 800px;

  /* force readable text */
  color: #222 !important;
}

.view-box h2 {
  margin-top: 0;
  color: #222 !important;
}

.details-table {
  width: 100%;
  border-collapse: collapse;
}

.details-table th,
.details-table td {
  border: 1px solid #ddd;
  padding: 8px;
  text-align: left;
  font-size: 14px;

  /* force readable text */
  color: #222 !important;
}

.details-table th {
  width: 25%;
  background: #f5f5f5 !important;
  color: #000 !important;
}

.back-btn {
  display: inline-block;
  margin-top: 15px;
  background: #6c757d;
  color: #fff;
  padding: 8px 15px;
  border-radius: 6px;
  text-decoration: none;
}

.back-btn:hover {
  background: #5a6268;
}

.alert {
  background: #f8d7da;
  color: #721c24;
  padding: 10px;
  border-radius: 6px;
  margin-bottom: 15px;
}

/* ============================
   DARK MODE FULL FIX
============================ */
.dark-mode .view-box {
  background: #1e1e1e !important;
  color: #fff !important;
}

.dark-mode h1,
.dark-mode h2,
.dark-mode h3,
.dark-mode p,
.dark-mode span {
  color: #fff !important;
}

.dark-mode .details-table th {
  background: #333 !important;
  color: #fff !important;
}

.dark-mode .details-table td {
  background: #1e1e1e !important;
  color: #fff !important;
}

/* force inherit fix for parents */
.dark-mode .main-content,
.dark-mode .inventory-container,
.dark-mode .dashboard {
  color: #fff !important;
}
</style>

</head>

<body class="<?= ($settings['theme'] ?? 'light') === 'dark' ? 'dark-mode' : '' ?>">
  <div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="main-content">
      <div class="view-box">
        <h2><?= isset($meta['title']) ? htmlspecialchars($meta['title']) : 'View Record' ?></h2>

        <?php if (!empty($error)): ?>
          <div class="alert"><?= htmlspecialchars($error) ?></div>
          <a href="javascript:history.back()" class="back-btn">⬅ Back</a>
        <?php elseif ($row): ?>
          <table class="details-table">
            <tbody>
              <?php foreach ($row as $field => $value): ?>
                <tr>
                  <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?></th>
                  <td>
                    <?php
                    if ($value === null) {
                      echo '<span style="color:#888;">NULL</span>';
                    } else {
                      echo nl2br(htmlspecialchars((string)$value));
                    }
                    ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <a href="javascript:history.back()" class="back-btn">⬅ Back</a>
        <?php else: ?>
          <div class="alert">Unknown error.</div>
          <a href="javascript:history.back()" class="back-btn">⬅ Back</a>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script src="chart.umd.min.js"></script>
  <script src="admindashboard.js" defer></script>
</body>

</html>
