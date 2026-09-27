<?php
require_once __DIR__ . '/auth.php';
allow_roles(['admin', 'subadmin']);  // allow both

// Access control


// Load theme settings
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
            ? $settingsQuery->fetch_assoc()
            : ['theme' => 'light'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Supplier Management</title>
<link rel="stylesheet" href="admindasboard.css">

<style>
/* MATCH inventory style */
.table {
  width: 100%;
  border-collapse: collapse;
}
.table th, .table td {
  padding: 8px;
  border: 1px solid #ddd;
  text-align: center;
  font-size: 14px;
}
.table th {
  background: #007bff;
  color: #fff;
}
.add-btn {
  background: #007bff;
  color: #fff;
  padding: 10px 15px;
  border-radius: 5px;
  text-decoration: none;
  border: none;
  cursor: pointer;
}
.add-btn:hover { background: #0056b3; }
.apply-btn {
  background: #007bff;
  color: #fff;
  padding: 10px 15px;
  border-radius: 5px;
  text-decoration: none;
  border: none;
  cursor: pointer;
}
.apply-btn:hover { background: #0056b3; }


.dl-btn {
  padding: 10px 15px;
  color: #fff;
  border-radius: 5px;
  cursor: pointer;
  border: none;
}
</style>
</head>

<body class="<?= ($settings['theme']=='dark') ? 'dark-mode' : '' ?>">
<div class="dashboard">

<?php include __DIR__ . '/../../sidebar.php'; ?>

<main class="main-content">
<div class="inventory-container">

<header class="header">
  <h1>Supplier Management</h1>
  <div class="search-bar">
    <input type="text" id="liveSearch" placeholder="Search suppliers...">
    <button><span>&#128269;</span></button>
  </div>
</header>

<div class="content-box">

  <!-- Add Supplier -->
  <?php if (is_role('admin')): ?>
<button class="add-btn" onclick="window.location='add_supplier.php'">➕ Add Supplier</button>
<?php endif; ?>


  <!-- FILTERS -->
  <form id="filterForm" style="display:flex; gap:10px; margin:15px 0; flex-wrap:wrap;">
    <label>
      Status:
      <input type="text" name="status" placeholder="Active / Inactive">
    </label>
    <label>
      Product:
      <input type="text" name="product_supplied" placeholder="Product">
    </label>
    <button class="apply-btn" style="background:#6c757d;">Apply</button>
  </form>

  <!-- TABLE (AJAX LOADED) -->
  <div id="suppliersTable">Loading suppliers...</div>
 <!-- EXPORT BUTTONS -->
  <div style="margin-bottom:15px;">
      <button id="btnCSV" class="dl-btn" style="background:#17a2b8;">Export as CSV</button>
      <button id="btnExcel" class="dl-btn" style="background:#28a745;">Export as Excel</button>
      <button id="btnPDF" class="dl-btn" style="background:#FF0000;">Export as PDF</button>
  </div>

</div>
</main>
</div>

<!-- Modal for Details -->
<div id="supModalOverlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); justify-content:center; align-items:center;">
  <div id="supModal" style="background:#fff; border-radius:8px; padding:20px; width:90%; max-width:700px;">
      <button id="supModalClose" style="float:right; background:#dc3545; color:#fff; border:none; padding:5px 10px;">Close</button>
      <h2>Supplier Details</h2>
      <div id="supModalContent"></div>
  </div>
</div>

<script src="chart.umd.min.js"></script>
<script src="admindashboard.js" defer></script>

<script src="assets/js/jspdf.umd.min.js"></script>
<script src="assets/js/jspdf.plugin.autotable.min.js"></script>
<script src="assets/js/tableModule.js"></script>

<script>
initTableModule({
    ajaxUrl: "/SUPPLY_OFFICE_first/search_suppliers_ajax.php",
    filterForm: "#filterForm",
    liveSearch: "#liveSearch",
    tableContainer: "#suppliersTable",

    modalOverlay: "#supModalOverlay",
    modalClose: "#supModalClose",
    modalContent: "#supModalContent",

    exportCSVBtn: "#btnCSV",
    exportExcelBtn: "#btnExcel",
    exportPDFBtn: "#btnPDF",

    exportFileName: "suppliers_list",
    exportTitle: "Suppliers List"
});
</script>

</body>
</html>
