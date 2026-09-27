<?php
session_start();
require_once __DIR__ . '/auth.php';
allow_roles(['admin','subadmin']); // only admin and subadmin can access this page
require_once __DIR__ . '/db_connect.php';

// -----------------------------
// Load theme settings
// -----------------------------
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
    ? $settingsQuery->fetch_assoc()
    : ['theme' => 'light'];

// -----------------------------
// Map table => primary key column
// -----------------------------
$PK_MAP = [
    'inventory' => 'inventory_id',
    'items'     => 'item_id',
    'suppliers' => 'supplier_id',
    'users'     => 'id',
    'orders'    => 'order_id',
];

// -----------------------------
// RECOVER HANDLER
// -----------------------------
if (isset($_GET['recover'])) {
    $archId = (int)$_GET['recover'];

    $archRes = $conn->query("SELECT * FROM `archive_all` WHERE `archive_id` = {$archId} LIMIT 1");
    if ($archRes && $archRes->num_rows > 0) {
        $arch  = $archRes->fetch_assoc();
        $table = $arch['table_name'];
        $json  = $arch['data'];

        // Decode JSON snapshot
        $data = json_decode($json, true);
        if (!is_array($data)) {
            // try without slashes if it was double-escaped
            $data = json_decode(stripslashes($json), true);
        }

        if (is_array($data)) {
            // remove PK so we insert a new row
            if (isset($PK_MAP[$table])) {
                unset($data[$PK_MAP[$table]]);
            }

            // Build INSERT
            $cols = [];
            $vals = [];

            foreach ($data as $col => $val) {
                $colSafe = '`' . str_replace('`', '``', $col) . '`';
                $cols[] = $colSafe;

                if ($val === null) {
                    $vals[] = "NULL";
                } else {
                    if (is_bool($val)) {
                        $val = $val ? '1' : '0';
                    } elseif (is_array($val) || is_object($val)) {
                        $val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    $vals[] = "'" . $conn->real_escape_string((string)$val) . "'";
                }
            }

            $tableSafe = '`' . str_replace('`', '``', $table) . '`';
            $sql = "INSERT INTO {$tableSafe} (" . implode(',', $cols) . ")
                    VALUES (" . implode(',', $vals) . ")";

            if ($conn->query($sql)) {
                // remove from archive after successful recovery
                $conn->query("DELETE FROM `archive_all` WHERE `archive_id` = {$archId}");
                header("Location: archive_all.php?recovered=1");
                exit();
            } else {
                $err = urlencode("Recovery failed: " . $conn->error);
                header("Location: archive_all.php?error={$err}");
                exit();
            }
        } else {
            $err = urlencode("Invalid archived data format.");
            header("Location: archive_all.php?error={$err}");
            exit();
        }
    } else {
        $err = urlencode("Archived record not found.");
        header("Location: archive_all.php?error={$err}");
        exit();
    }
}

// -----------------------------
// PERMANENT DELETE HANDLER
// -----------------------------
if (isset($_GET['delete_perm'])) {
    $archId = (int)$_GET['delete_perm'];
    $conn->query("DELETE FROM `archive_all` WHERE `archive_id` = {$archId}");
    header("Location: archive_all.php?deleted=1");
    exit();
}

// -----------------------------
// FETCH ALL ARCHIVED ROWS
// -----------------------------
$rows = [];
$sql = "SELECT `archive_id`,`table_name`,`record_id`,`data`,`deleted_by`,
               `deleted_at`,`purge_at`
        FROM `archive_all`
        ORDER BY `deleted_at` DESC, `archive_id` DESC";

$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Archive All</title>
  <link rel="stylesheet" href="admindasboard.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
    .content-box {
      background:#fff;
      padding:20px;
      margin:20px;
      border-radius:8px;
      box-shadow:0 1px 4px rgba(0,0,0,.08);
    }
    h1 {
      margin-top:0;
      font-size:22px;
      color:#143ab7ff;
    }
    table.archive-table {
      width:100%;
      border-collapse:collapse;
      margin-top:15px;
      font-size:13px;
    }
    table.archive-table th,
    table.archive-table td {
      border:1px solid #ccc;
      padding:6px 8px;
      vertical-align:top;
    }
    table.archive-table th {
      background:#143ab7ff;
      color:#fff;
      text-align:left;
    }
    pre.json {
      max-height:160px;
      overflow:auto;
      margin:0;
      font-size:11px;
      white-space:pre-wrap;
      word-break:break-all;
    }
    .btn {
      display:inline-block;
      padding:4px 8px;
      margin:2px 0;
      font-size:12px;
      border-radius:4px;
      text-decoration:none;
      color:#fff;
    }
    .btn-recover { background:#28a745; }
    .btn-delete  { background:#dc3545; }
    .alert {
      padding:8px 12px;
      border-radius:4px;
      margin-bottom:10px;
      color:#fff;
      font-size:13px;
    }
  </style>
</head>
<body class="<?= $settings['theme']==='dark' ? 'dark-mode' : '' ?>">
<div class="dashboard">
  <?php include __DIR__ . '/../../sidebar.php'; ?>

  <main class="main-content">
    <div class="content-box">

      <?php if (isset($_GET['recovered'])): ?>
        <div class="alert" style="background:#28a745;">Record recovered successfully.</div>
      <?php elseif (isset($_GET['deleted'])): ?>
        <div class="alert" style="background:#dc3545;">Archived record permanently deleted.</div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert" style="background:#ffc107; color:#000;">
          <?= htmlspecialchars($_GET['error']) ?>
        </div>
      <?php endif; ?>

      <h1>Archived Records</h1>

      <?php if (empty($rows)): ?>
        <p>No archived records found.</p>
      <?php else: ?>
        <table class="archive-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Table</th>
              <th>Record ID</th>
              <th>Deleted By</th>
              <th>Deleted At</th>
              <th>Purge At</th>
              <th>Data Snapshot</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= (int)$row['archive_id'] ?></td>
              <td><?= htmlspecialchars($row['table_name']) ?></td>
              <td><?= (int)$row['record_id'] ?></td>
              <td><?= htmlspecialchars((string)$row['deleted_by']) ?></td>
              <td><?= htmlspecialchars($row['deleted_at']) ?></td>
              <td><?= htmlspecialchars($row['purge_at']) ?></td>
              <td>
                <pre class="json"><?= htmlspecialchars($row['data']) ?></pre>
              </td>
              <td>
                <a class="btn btn-recover"
                   href="archive_all.php?recover=<?= (int)$row['archive_id'] ?>"
                   onclick="return confirm('Recover this record?');">
                  ♻ Recover
                </a>
                <a class="btn btn-delete"
                   href="archive_all.php?delete_perm=<?= (int)$row['archive_id'] ?>"
                   onclick="return confirm('Delete this archived record permanently?');">
                  🗑 Delete
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>
</div>

<script src="chart.umd.min.js"></script>
<script src="admindashboard.js" defer></script>
</body>
</html>
