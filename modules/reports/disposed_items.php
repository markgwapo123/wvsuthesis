<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
<html>
<head>
<meta charset="utf-8">
<title>Disposed</title>
<link rel="stylesheet" href="admindasboard.css">

<style>
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{border:1px solid #ccc;padding:6px;text-align:center;font-size:14px;}
tr:nth-child(even){background:#f5f5f5;}
tr:hover{background:#eef5ff;}
.controls{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;}
.btn{padding:6px 10px;border:1px solid #ccc;background:#f7f7f7;cursor:pointer;}

.bad{background:#ffeaea!important;color:#c00;font-weight:600;}
.lost{background:#f8d7da!important;color:#721c24;font-weight:600;}
.expired{background:#fff3cd!important;color:#856404;font-weight:600;}

</style>
</head>

<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">

<div class="dashboard">
<?php include __DIR__ . '/../../sidebar.php'; ?>

<main class="main-content">
<div class="inventory-container">

  <header class="header">
    <h1>Disposed</h1>

    <div class="search-bar">
      <input type="text" id="liveSearch" placeholder="Search items...">
      <button><span>&#128269;</span></button>
    </div>
  </header>
  <div class="content-box">

    <!-- FILTERS -->
    <form id="filterForm" class="controls">
        <label>Department:
            <input type="text" name="department">
        </label>

        <label>Requestor:
            <input type="text" name="requestor_name">
        </label>

        <label>Receiver:
            <input type="text" name="receiver">
        </label>

        <button type="submit" class="btn" style="background:#6c757d;color:#fff;">Apply</button>
    </form>
    
    <!-- AJAX TABLE -->
    <div id="damageTable">Loading...</div>

    <!-- EXPORT BUTTONS -->
    <div style="margin-bottom:10px;">
      <button id="btnCSV" class="btn" style="background:#17a2b8;color:#fff;">Export as CSV</button>
      <button id="btnExcel" class="btn" style="background:#28a745;color:#fff;">Export as Excel</button>
      <button id="btnPDF" class="btn" style="background:#FF0000;color:#fff;">Export as PDF</button>
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
    ajaxUrl: "/SUPPLY_OFFICE_first/search_disposed_item_ajax.php",
    filterForm: "#filterForm",
    liveSearch: "#liveSearch",
    tableContainer: "#damageTable",

    exportCSVBtn: "#btnCSV",
    exportCSVBtn: "#btnExcel",
    exportPDFBtn: "#btnPDF",

    exportFileName: "damage_lost_expired_items",
    exportTitle: "Damaged / Lost / Expired Items Report"
});
</script>

</body>
</html>

