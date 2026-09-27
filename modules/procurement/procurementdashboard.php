<?php
require_once __DIR__ . '/../../auth.php';
allow_roles(['procurement']);  // only procurement role
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
  <title>Procurement Dashboard</title>
  <link rel="stylesheet" href="admindasboard.css">
</head>
<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">
<div class="dashboard">
  <?php include __DIR__ . '/../../sidebar.php'; ?>
  <main class="main-content">
    <header class="header">
      <h1>Procurement Dashboard</h1>
    </header>

    <section class="content-box">
      <h2>Procurement Requests</h2>
      <table>
        <tr>
          <th>Date</th>
          <th>Code</th>
          <th>Item</th>
          <th>Qty</th>
          <th>Status</th>
          <th>Action</th>
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
            <td>
              <form method="post" action="procurement_update_status.php" style="display:inline-flex; gap:4px;">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <select name="status">
                  <option value="for_procurement" <?= $r['status']=='for_procurement'?'selected':'' ?>>For Procurement</option>
                  <option value="on_process"      <?= $r['status']=='on_process'?'selected':'' ?>>On Process</option>
                  <option value="purchased"       <?= $r['status']=='purchased'?'selected':'' ?>>Purchased</option>
                  <option value="delivered"       <?= $r['status']=='delivered'?'selected':'' ?>>Delivered</option>
                  <option value="cancelled"       <?= $r['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
                <button type="submit">Update</button>
              </form>
            </td>
          </tr>
        <?php
          endwhile;
        else:
          echo "<tr><td colspan='6'>No procurement requests yet.</td></tr>";
        endif;
        ?>
      </table>
    </section>
  </main>
</div>
<script src="admindashboard.js"></script>
</body>
</html>
