<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Access control
if (!isset($_SESSION['role'])) {
  header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
  exit();
}

// Load theme
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
  ? $settingsQuery->fetch_assoc()
  : ['theme' => 'light'];
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>Borrowed Items Report</title>
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
    }

    tr:nth-child(even) {
      background: #f9f9f9;
    }

    tr:hover {
      background: #eef5ff;
    }

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

        <header class="header">
          <h1>Borrowed Items Report</h1>

          <div class="search-bar">
            <input type="text" id="liveSearch" placeholder="Search borrowed items...">
            <button><span>&#128269;</span></button>
          </div>
        </header>

        <div class="content-box">

          <!-- FILTERS -->
          <form id="filterForm" class="controls">
            <label>From: <input type="date" name="from"></label>
            <label>To: <input type="date" name="to"></label>

            <button type="submit" class="btn" style="background:#6c757d;color:#fff;">Apply</button>
          </form>
          <!-- AJAX Table -->
          <div id="borrowTable">Loading...</div>

          <!-- EXPORT BUTTONS -->
          <div style="margin-bottom:10px;">
            <button id="btnCSV" class="btn" style="background:#17a2b8;color:#fff;">CSV</button>
            <button id="btnExcel" class="btn" style="background:#28a745;color:#fff;">Excel</button>
            <button id="btnPDF" class="btn" style="background:#6f42c1;color:#fff;">PDF</button>
          </div>

        </div>
      </div>
    </main>
  </div>

  <!-- Scripts -->
  <script src="chart.umd.min.js"></script>
  <script src="admindashboard.js" defer></script>
  <script src="assets/js/jspdf.umd.min.js"></script>
  <script src="assets/js/jspdf.plugin.autotable.min.js"></script>
  <script src="assets/js/tableModule.js"></script>

  <script>
    initTableModule({
      ajaxUrl: "/SUPPLY_OFFICE_first/search_borrow_report_ajax.php",
      filterForm: "#filterForm",
      liveSearch: "#liveSearch",
      tableContainer: "#borrowTable",

      modalOverlay: null,
      modalClose: null,
      modalContent: null,

      exportCSVBtn: "#btnCSV",
      exportExcelBtn: "#btnExcel",
      exportPDFBtn: "#btnPDF",

      exportFileName: "borrowed_items_report",
      exportTitle: "Borrowed Items Report"
    });
  </script>

</body>

</html>
