<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['role'])) {
  header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
  exit();
}

// Load theme
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
          ? $settingsQuery->fetch_assoc()
          : ['theme'=>'light'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Stock Levels Report</title>
<link rel="stylesheet" href="admindasboard.css">

<style>
table { width:100%; border-collapse:collapse; }
th, td { border:1px solid #ccc; padding:6px; text-align:center; }
th { background:#007bff; color:#fff; }

.low { background:#fff3cd !important; } /* yellow */

.controls { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
.btn { padding:6px 10px; border:1px solid #ccc; background:#f7f7f7; cursor:pointer; }

.search-bar input {
  padding:8px; border:1px solid #ccc; border-radius:6px; width:200px;
}

</style>
</head>

<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">

<div class="dashboard">
<?php include __DIR__ . '/../../sidebar.php'; ?>

<main class="main-content">
<div class="inventory-container">

<header class="header">
  <h1>Stock Levels Report</h1>

  <div class="search-bar">
    <input type="text" id="liveSearch" placeholder="Search items...">
    <button>🔍</button>
  </div>
</header>

<div class="content-box">

  <!-- Filters -->
  <form id="filterForm" class="controls">
    <label>Department:
      <input type="text" name="department" placeholder="Department">
    </label>
    <button type="submit" class="btn" style="background:#6c757d;color:#fff;">Apply</button>
  </form>

    <!-- AJAX table -->
  <div id="stockTable">Loading...</div>
  <!-- Export buttons -->
  <div style="margin-bottom:10px;">
    <button id="btnCSV" class="btn" style="background:#17a2b8;color:white;">Export as CSV</button>
    <button id="btnExcel" class="btn" style="background:#28a745;color:white;">Export as Excel</button>
    <button id="btnPDF" class="btn" style="background:#FF0000;color:white;">Export as PDF</button>
  </div>

</div>

</div>
</main>
</div>
<script src="chart.umd.min.js"></script>
<script src="admindashboard.js" defer></script>
<script src="assets/js/jspdf.umd.min.js"></script>
<script src="assets/js/jspdf.plugin.autotable.min.js"></script>
<script src="assets/js/tableModule.js"></script>

<script>
initTableModule({
    ajaxUrl: "/SUPPLY_OFFICE_first/search_stock_levels_ajax.php",
    filterForm: "#filterForm",
    liveSearch: "#liveSearch",
    tableContainer: "#stockTable",

    modalOverlay: null,
    modalClose: null,
    modalContent: null,

    exportCSVBtn: "#btnCSV",
    exportExcelBtn: "#btnExcel",
    exportPDFBtn: "#btnPDF",

    exportFileName: "stock_levels_report",
    exportTitle: "Stock Levels Report"
});
</script>

</body>
</html>

