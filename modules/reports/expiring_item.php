<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Load theme settings
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
  ? $settingsQuery->fetch_assoc()
  : ['theme' => 'light'];
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>Expiring & Expired Items</title>
  <link rel="stylesheet" href="admindasboard.css">

  <style>
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    th,
    td {
      border: 1px solid #ccc;
      padding: 6px;
      text-align: center;
      font-size: 14px;
    }

    .expired {
      background: #ffcccc !important;
    }

    /* red */
    .soon {
      background: #fff4cc !important;
    }

    /* yellow */

    .controls {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }

    .btn {
      padding: 6px 10px;
      border: 1px solid #ccc;
      background: #f7f7f7;
      cursor: pointer;
    }
  </style>
</head>

<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">

  <div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="main-content">
      <div class="inventory-container">

        <!-- Header -->
        <header class="header">
          <h1>Expiring & Expired Item Report</h1>

          <div class="search-bar">
            <input type="text" id="liveSearch" placeholder="Search items...">
            <button><span>&#128269;</span></button>
          </div>
        </header>

        <div class="content-box">

          <!-- Filter Form -->
          <form id="filterForm" class="controls">
            <label>Category:
              <input type="text" name="category">
            </label>
            <label>Status:
              <input type="text" name="status_label">
            </label>
            <label>Condition:
              <input type="text" name="condition_label">
            </label>
            <label>Expiring Within (days):
              <input type="number" name="days" placeholder="e.g. 30">
            </label>

            <button type="submit" class="btn" style="background:#6c757d;color:#fff;">Apply</button>
          </form>
          <!-- AJAX Table -->
          <div id="expTable">Loading...</div>

          <!-- Export Buttons -->
          <div style="margin-bottom:10px;">
            <button id="btnCSV" class="btn" style="background:#17a2b8;color:#fff;">Export CSV</button>
            <button id="btnExcel" class="btn" style="background:#28a745;color:#fff;">Export Excel</button>
            <button id="btnPDF" class="btn" style="background:#FF0000;color:#fff;">Export PDF</button>
          </div>

        </div>

      </div>
    </main>
  </div>

  <!-- JS -->
  <script src="chart.umd.min.js"></script>
  <script src="admindashboard.js" defer></script>
  <script src="assets/js/jspdf.umd.min.js"></script>
  <script src="assets/js/jspdf.plugin.autotable.min.js"></script>
  <script src="assets/js/tableModule.js"></script>

  <script>
    initTableModule({
      ajaxUrl: "/SUPPLY_OFFICE_first/search_expiring_item_ajax.php",
      filterForm: "#filterForm",
      liveSearch: "#liveSearch",
      tableContainer: "#expTable",

      exportCSVBtn: "#btnCSV",
      exportExcelBtn: "#btnExcel",
      exportPDFBtn: "#btnPDF",

      exportFileName: "expiring_items",
      exportTitle: "Expiring & Expired Items Report"
    });
  </script>

</body>

</html>