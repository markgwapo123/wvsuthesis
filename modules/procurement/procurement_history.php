<?php
require_once __DIR__ . '/../../auth.php';
allow_roles(['procurement', 'admin']); // both can see history if you want
require_once __DIR__ . '/../../db_connect.php';

// theme
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
  ? $settingsQuery->fetch_assoc()
  : ['theme' => 'light'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Procurement History</title>
  <link rel="stylesheet" href="admindasboard.css">
</head>
<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">
<div class="dashboard">
  <?php include __DIR__ . '/../../sidebar.php'; ?>
  <main class="main-content">
    <header class="header">
      <h1>Procurement History</h1>
    </header>

    <section class="content-box">
      <table>
        <tr>
          <th>Date</th>
          <th>Code</th>
          <th>Item</th>
          <th>Qty</th>
          <th>Status</th>
        </tr>
        <?php
        $res = $conn->query("
            SELECT pr.*, inv.name AS inv_name
            FROM procurement_requests pr
            LEFT JOIN inventory inv ON inv.inventory_id = pr.inventory_id
            ORDER BY pr.created_at DESC
        ");
        if ($res && $res->num_rows > 0):
          while ($r = $res->fetch_assoc()):
        ?>
          <tr>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td><?= htmlspecialchars($r['request_code']) ?></td>
            <td><?= htmlspecialchars($r['inv_name'] ?? $r['description']) ?></td>
            <td><?= (int)$r['quantity'] ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
          </tr>
        <?php
          endwhile;
        else:
          echo "<tr><td colspan='5'>No history yet.</td></tr>";
        endif;
        ?>
      </table>
    </section>
  </main>
</div>
<script src="admindashboard.js"></script>
</body>
</html>
