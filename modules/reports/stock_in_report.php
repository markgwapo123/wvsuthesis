<?php require_once __DIR__ . '/../../db_connect.php'; ?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>Stock In Report</title>
  <link rel="stylesheet" href="admindasboard.css">
  <style>
    table {
      width: 100%;
      border-collapse: collapse
    }

    th,
    td {
      border: 1px solid #ccc;
      padding: 6px;
      text-align: left
    }

    .controls {
      margin: 10px 0;
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .btn {
      padding: 6px 10px;
      border: 1px solid #ccc;
      background: #f7f7f7;
      text-decoration: none
    }
  </style>
</head>

<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">

  <body>
    <div class="dashboard">
      <!-- Sidebar -->
      <?php include __DIR__ . '/../../sidebar.php'; ?>

      <!-- Main Content -->
      <main class="main-content">
        <div class="inventory-container">
          <!-- Header -->
          <header class="header">
            <h1>Stock In Report</h1>
            <div class="search-bar">
              <input type="text" placeholder="Search items">
              <button><span>&#128269;</span></button>
            </div>
          </header>

          <!-- Content Box -->
          <div class="content-box">
            <!-- Filter Section -->
            <div class="filter-bar">
              <button class="add-btn" onclick="window.location.href='add_item.php'">➕ Add Item</button>
            </div>

            <!-- Table placeholder -->
            <div class="table-placeholder">
              <!-- Items will be displayed here -->
              <form method="get" class="controls">
                <label>From: <input type="date" name="from" value="<?php echo htmlspecialchars($_GET['from'] ?? '') ?>"></label>
                <label>To: <input type="date" name="to" value="<?php echo htmlspecialchars($_GET['to'] ?? '') ?>"></label>
                <button class="btn">Filter</button>
              </form>
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>User</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $cond = "WHERE action='stock_in'";
                  if (!empty($_GET['from'])) $cond .= " AND DATE(created_at)>='" . $conn->real_escape_string($_GET['from']) . "'";
                  if (!empty($_GET['to'])) $cond .= " AND DATE(created_at)<='" . $conn->real_escape_string($_GET['to']) . "'";
                  $res = $conn->query("SELECT s.*, i.name FROM issuances s LEFT JOIN items i ON s.item_id=i.item_id $cond ORDER BY s.created_at DESC");
                  while ($r = $res->fetch_assoc()) {
                    echo "<tr><td>{$r['issuance_id']}</td><td>" . htmlspecialchars($r['name']) . "</td><td>{$r['qty']}</td><td>{$r['user_id']}</td><td>{$r['created_at']}</td></tr>";
                  }
                  ?>
                </tbody>
              </table>

            </div>

            <!-- Pagination -->
            <div class="pagination">
              <span>&lt;&lt; Previous</span>
              <span class="page-number">1</span>
              <span class="page-number">2</span>
              <span class="page-number">3</span>
              <span>Next &gt;&gt;</span>
            </div>

            <!-- Export button -->
            <div style="margin-bottom: 15px;">
              <button id="downloadBtn">⬇ Download</button>
              <div id="downloadOptions" class="hidden">
                <button onclick="exportTableToCSV('table_data.csv')">Download as CSV</button>
                <button onclick="exportTableToPDF()">Download as PDF</button>
              </div>
            </div>
          </div>
          <!-- Action Buttons -->
          <div class="actions">
            <span>✏ Edit</span>
            <span>🗑 Delete</span>
            <span>👁 View</span>
            <span>📤 Borrow item</span>
          </div>
        </div>
      </main>
    </div>

    <!-- Scripts -->
    <script src="chart.umd.min.js"></script>
    <script src="admindashboard.js" defer></script>
  </body>

</html>