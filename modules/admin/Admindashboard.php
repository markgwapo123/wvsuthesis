<?php
require_once __DIR__ . '/../../auth.php';
allow_roles(['admin']);
require_once __DIR__ . '/../../db_connect.php';

/* ===========================================================
   LOAD THEME
   =========================================================== */
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
  ? $settingsQuery->fetch_assoc()
  : ['theme' => 'light'];

/* ===========================================================
   DETECT CORRECT QUANTITY FIELD IN ORDERS
   =========================================================== */
$qty_col = 'qty';
$colCheck = $conn->query("SHOW COLUMNS FROM orders");
while ($c = $colCheck->fetch_assoc()) {
    if (in_array($c['Field'], ['quantity','qty','requested_qty','order_qty'])) {
        $qty_col = $c['Field'];
        break;
    }
}

/* ===========================================================
   DETECT CORRECT ACTIVITY FIELD IN activity_logs
   =========================================================== */
$activity_col = 'description';
$possible_cols = ['activity','action','description','log_description','details','message','activity_description'];

$colCheck2 = $conn->query("SHOW COLUMNS FROM activity_logs");
while ($c = $colCheck2->fetch_assoc()) {
    if (in_array($c['Field'], $possible_cols)) {
        $activity_col = $c['Field'];
        break;
    }
}

/* ===========================================================
   DASHBOARD QUERIES
   =========================================================== */

// TOTAL STOCK
$total_stock = $conn->query("SELECT SUM(quantity) AS total FROM inventory")
                   ->fetch_assoc()['total'] ?? 0;

// LOW STOCK ITEMS
$low_stock = $conn->query("
    SELECT COUNT(*) AS cnt 
    FROM inventory 
    WHERE quantity <= min_stock
")->fetch_assoc()['cnt'] ?? 0;

// PENDING REQUESTS (ORDERS)
$pending_requests = $conn->query("
    SELECT COUNT(*) AS cnt 
    FROM orders 
    WHERE status = 'pending'
")->fetch_assoc()['cnt'] ?? 0;

// DISPOSAL COUNT TRACKERS
$awaiting_disposal = $conn->query("SELECT COUNT(*) AS cnt FROM disposal_requests WHERE status = 'Approved by DAA'")->fetch_assoc()['cnt'] ?? 0;
$discarded_items   = $conn->query("SELECT COUNT(*) AS cnt FROM disposal_requests WHERE status = 'Disposed'")->fetch_assoc()['cnt'] ?? 0;

// RECENT SUPPLY REQUESTS
$recent_requests = $conn->query("
    SELECT order_id, department, item_name, $qty_col AS qty, status, created_at
    FROM orders
    ORDER BY created_at DESC
    LIMIT 5
");

// TOP USED ITEMS BY CATEGORY
$top_items = $conn->query("
    SELECT 
        i.category,
        SUM(u.issued_qty) AS total_issued
    FROM issuances u
    LEFT JOIN inventory i ON u.item_name = i.name
    GROUP BY i.category
    ORDER BY total_issued DESC
    LIMIT 5
");

// RECENT ACTIVITIES
$activities = $conn->query("
    SELECT $activity_col AS activity, created_at
    FROM activity_logs
    ORDER BY created_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SOIMS Admin Dashboard</title>
<link rel="stylesheet" href="admindasboard.css">

<style>
/* ========= Layout & Background ========= */
body {
  background: #f4f6fb;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.main-content {
  padding: 24px;
  animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Header */
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.dashboard-title {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dashboard-title h2 {
  margin: 0;
  font-size: 26px;
  font-weight: 800;
  color: #f5f7fa;
}

.dashboard-title span {
  font-size: 13px;
  color: #64748b;
}

.dashboard-meta {
  font-size: 13px;
  color: #64748b;
}

/* ========= Cards ========= */
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 18px;
  margin-bottom: 22px;
}

.card {
  background: #ffffff;
  border-radius: 14px;
  padding: 16px 18px;
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
  display: flex;
  align-items: center;
  gap: 14px;
  transform: translateY(0);
  transition: transform 0.25s ease, box-shadow 0.25s ease;
  position: relative;
  overflow: hidden;
  text-decoration: none;
}

.card::after {
  content: "";
  position: absolute;
  right: -40px;
  top: -40px;
  width: 100px;
  height: 100px;
  border-radius: 999px;
  opacity: 0.06;
}

.card:hover {
  transform: translateY(-6px);
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
}

.card-icon {
  width: 46px;
  height: 46px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}

.card-content p {
  margin: 0;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #6b7280;
}

.card-content h3 {
  margin: 4px 0 0 0;
  font-size: 26px;
  font-weight: 800;
  color: #111827;
}

.card-content small {
  display: block;
  font-size: 11px;
  font-weight: 600;
  margin-top: 2px;
}

/* Color accents */
.green .card-icon { background:#ecfdf3; color:#16a34a; }
.blue .card-icon  { background:#e0f2fe; color:#0284c7; }
.orange .card-icon{ background:#fff7ed; color:#ea580c; }
.grey .card-icon  { background:#fee2e2; color:#dc2626; }

.green::after  { background:#22c55e; }
.blue::after   { background:#0ea5e9; }
.orange::after { background:#fb923c; }
.grey::after   { background:#ef4444; }

/* ========= Grid: table + chart ========= */
.grid {
  display: grid;
  grid-template-columns: minmax(0, 1.7fr) minmax(0, 1.3fr);
  gap: 20px;
  margin-bottom: 22px;
}

.table-card,
.chart-card {
  background:#ffffff;
  border-radius: 14px;
  padding: 18px;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
}

.table-card h4,
.chart-card h4 {
  margin: 0 0 10px 0;
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Table styling */
.table-card table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.table-card th,
.table-card td {
  padding: 8px 8px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
}

.table-card th {
  background:#f9fafb;
  font-weight: 600;
  color:#374151;
}

.table-card tr:hover td {
  background:#f3f4f6;
}

/* Status pill */
.status-pill {
  display:inline-block;
  padding:2px 8px;
  border-radius:999px;
  font-size:11px;
  font-weight:600;
}

.status-pending  { background:#fef3c7; color:#92400e; }
.status-approved { background:#dcfce7; color:#166534; }
.status-rejected { background:#fee2e2; color:#991b1b; }

/* Chart canvas */
.chart-card canvas {
  width: 100%;
  height: 260px;
}

/* ========= Recent Activities ========= */
.recent-activities {
  background:#ffffff;
  border-radius:14px;
  padding:18px;
  box-shadow:0 10px 24px rgba(15,23,42,0.06);
}

.recent-activities h4 {
  margin: 0 0 10px 0;
  font-size:18px;
  font-weight:700;
  color:#111827;
  display:flex;
  align-items:center;
  gap:8px;
}

.recent-activities ul {
  list-style:none;
  margin:0;
  padding:0;
}

.recent-activities li {
  padding:8px 0;
  border-bottom:1px solid #e5e7eb;
  font-size:13px;
  color:#374151;
  display:flex;
  flex-direction:column;
  gap:2px;
  opacity:0;
  animation: activityFadeIn 0.4s ease-out forwards;
}

.recent-activities li:last-child {
  border-bottom:none;
}

@keyframes activityFadeIn {
  from { opacity:0; transform: translateY(4px); }
  to   { opacity:1; transform: translateY(0); }
}

.activity-time {
  font-weight:600;
  color:#111827;
}

.activity-text {
  color:#4b5563;
}

/* ========= Dark mode tweaks ========= */
.dark-mode body { background:#020617; }

.dark-mode .card,
.dark-mode .table-card,
.dark-mode .chart-card,
.dark-mode .recent-activities {
  background:#020617 !important;
  box-shadow:0 10px 30px rgba(0,0,0,0.7) !important;
  border: 1px solid #1e293b !important;
}

.dark-mode .card-content h3,
.dark-mode .card-content p,
.dark-mode .table-card h4,
.dark-mode .chart-card h4,
.dark-mode .recent-activities h4,
.dark-mode .dashboard-title h2,
.dark-mode .dashboard-meta,
.dark-mode .table-card th,
.dark-mode .table-card td,
.dark-mode .recent-activities li {
  color:#e5e7eb !important;
}
.dark-mode .dashboard-title span { color: #64748b !important; }

.dark-mode .table-card th {
  background:#0f172a !important;
}

.dark-mode .table-card tr:hover td {
  background:#0f172a !important;
}
</style>

</head>

<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">

<div class="dashboard" style="display: flex !important;">
  <?php include __DIR__ . '/../../sidebar.php'; ?>

  <main class="main-content" style="flex: 1 !important;">

    <div class="dashboard-header">
      <div class="dashboard-title">
        <h2>Admin Dashboard</h2>
        <span>SOIMS · Supply Office Inventory Management System</span>
      </div>
      <div class="dashboard-meta">
        <?= date('M d, Y · h:i A') ?>
      </div>
    </div>

    <section class="cards">

      <a href="stock_levels.php" class="card green">
        <div class="card-icon">📦</div>
        <div class="card-content">
          <p>Total Items in Stock</p>
          <h3><?= number_format($total_stock) ?></h3>
        </div>
</a>

      <div class="card blue">
        <div class="card-icon">⚠️</div>
        <div class="card-content">
          <p>Low Stock Items</p>
          <h3><?= number_format($low_stock) ?></h3>
        </div>
      </div>

      <a href="order_management.php" class="card orange">
        <div class="card-icon">📄</div>
        <div class="card-content">
          <p>Pending Order</p>
          <h3><?= number_format($pending_requests) ?></h3>
        </div>
</a>

      <a href="admin_execute_disposals.php" class="card grey">
        <div class="card-icon">🗑️</div>
        <div class="card-content">
          <p>Item Discarded</p>
          <h3><?= number_format($discarded_items) ?></h3>
          <?php if($awaiting_disposal > 0): ?>
            <small style="color: #dc2626;">⚠️ <?= $awaiting_disposal ?> Approved Awaiting Action</small>
          <?php else: ?>
            <small style="color: #6b7280;">Pipeline Clean</small>
          <?php endif; ?>
        </div>
      </a>

    </section>

    <section class="grid">

      <div class="table-card">
        <h4>📥 Recent Supply Order</h4>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Department</th>
              <th>Item</th>
              <th>Qty</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recent_requests && $recent_requests->num_rows > 0): ?>
              <?php while($r = $recent_requests->fetch_assoc()): ?>
                <?php
                  $status = strtolower($r['status']);
                  $statusClass = 'status-pill';
                  if ($status === 'pending')  $statusClass .= ' status-pending';
                  elseif ($status === 'approved') $statusClass .= ' status-approved';
                  elseif ($status === 'rejected') $statusClass .= ' status-rejected';
                ?>
                <tr>
                  <td><?= htmlspecialchars($r['created_at']) ?></td>
                  <td><?= htmlspecialchars($r['department']) ?></td>
                  <td><?= htmlspecialchars($r['item_name']) ?></td>
                  <td><?= htmlspecialchars($r['qty']) ?></td>
                  <td><span class="<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5">No recent requests found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="chart-card">
        <h4>📊 Top Used Items by Category</h4>
        <canvas id="pieChart"></canvas>
      </div>

    </section>

    <section class="recent-activities">
      <h4>🕒 Recent Activities</h4>
      <ul>
        <?php if ($activities && $activities->num_rows > 0): ?>
            <?php 
              $delay = 0;
              while($a = $activities->fetch_assoc()): 
                $delay += 0.05;
            ?>
              <li style="animation-delay: <?= number_format($delay,2) ?>s;">
                <span class="activity-time">
                  <?= htmlspecialchars(date("M d, Y · h:i A", strtotime($a['created_at']))) ?>
                </span>
                <span class="activity-text">
                  <?= htmlspecialchars($a['activity']) ?>
                </span>
              </li>
            <?php endwhile; ?>
        <?php else: ?>
            <li>No activity logs found.</li>
        <?php endif; ?>
      </ul>
    </section>

  </main>
</div>

<script src="chart.umd.min.js"></script>
<script src="admindashboard.js" defer></script>

<script>
// Detect current layout theme context to configure matching chart text colors dynamically
const isDarkMode = document.body.classList.contains('dark-mode');
const chartTextColor = isDarkMode ? '#e5e7eb' : '#000000';

const pieLabels = [
  <?php
    if ($top_items && $top_items->num_rows > 0) {
      $top_items->data_seek(0);
      while ($ti = $top_items->fetch_assoc()) {
        echo "'" . addslashes($ti['category'] ?? 'Uncategorized') . "',";
      }
    }
  ?>
];

const pieValues = [
  <?php
    if ($top_items && $top_items->num_rows > 0) {
      $top_items->data_seek(0);
      while ($ti = $top_items->fetch_assoc()) {
        echo (int)$ti['total_issued'] . ",";
      }
    }
  ?>
];

new Chart(document.getElementById('pieChart'), {
  type: 'doughnut',
  data: {
    labels: pieLabels,
    datasets: [{
      data: pieValues,
      backgroundColor: ['#0ea5e9','#22c55e','#eab308','#ef4444','#8b5cf6'],
      hoverOffset: 8
    }]
  },
  options: {
    responsive: true,
    plugins: {
      title: {
        display: true,
        text: "Top Used Categories (Issuances)",
        color: chartTextColor,
        font: { size: 16, weight: "bold" }
      },
      legend: {
        position: "bottom",
        labels: { color: chartTextColor }
      }
    }
  }
});
// Check for items with low stock counts (Example threshold: less than 10 units left)
$lowStockQuery = $conn->query("SELECT item_name, current_stock FROM items WHERE current_stock <= 10");
while ($item = $lowStockQuery->fetch_assoc()) {
    $title = "🚨 Inventory Alert: Low Stock";
    $message = "The item '" . $item['item_name'] . "' is running low. Remaining units: " . $item['current_stock'];
    
    // Check if we have already alerted them within the last 24 hours to prevent duplicate spam logs
    $check = $conn->query("SELECT notification_id FROM system_notifications WHERE title='$title' AND message='$message' AND created_at >= NOW() - INTERVAL 1 DAY");
    if ($check && $check->num_rows == 0) {
        // Log alert for admins
        $conn->query("INSERT INTO system_notifications (target_role, module_type, title, message) VALUES ('admin', 'inventory', '$title', '$message')");
        // Log alert for end-users globally
        $conn->query("INSERT INTO system_notifications (target_role, module_type, title, message) VALUES ('end_user', 'inventory', '$title', '$message')");
    }
}
</script>
</body>
</html>