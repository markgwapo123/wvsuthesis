<?php
require_once __DIR__ . '/../../auth.php';
// ALLOWED: Admin, Subadmin, AND End User (Restricted view)
allow_roles(['admin', 'subadmin', 'end_user']);
require_once __DIR__ . '/../../db_connect.php';

// load theme if needed
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
  ? $settingsQuery->fetch_assoc()
  : ['theme' => 'light'];

// ===================================================
// 0. Auto-update expired items based on expiration_date
// ===================================================
$conn->query("
  UPDATE inventory
  SET condition_label = 'Expired', status_label = 'Expired'
  WHERE expiration_date IS NOT NULL 
  AND expiration_date < CURDATE()
  AND (condition_label != 'Expired' OR condition_label IS NULL)
");

// ===================================================
// Auto-update: mark items as Out of Stock when quantity = 0
// ===================================================
$conn->query("
  UPDATE inventory AS inv
  JOIN (
    SELECT name, department
    FROM inventory
    GROUP BY name, department
    HAVING SUM(quantity) <= 0
  ) AS zero_stock
  ON inv.name = zero_stock.name AND inv.department = zero_stock.department
  SET inv.status_label = 'Out of Stock'
  WHERE inv.status_label != 'Out of Stock';
");

// ===================================================
// 1. Fetch user settings
// ===================================================
$current_user_id = $_SESSION['user_id'] ?? 0;
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = " . (int)$current_user_id . " LIMIT 1");

if ($settingsQuery && $settingsQuery->num_rows > 0) {
  $settings = $settingsQuery->fetch_assoc();
} else {
  // Defaults
  $settings = [
    'theme' => 'light',
    'low_stock_threshold' => 10,
    'auto_order' => 'manual',
    'color_stock_ok' => '#28a745',
    'color_stock_low' => '#ffc107',
    'color_stock_out' => '#dc3545',
    'expiring_days_threshold' => 7,
    'color_expiring' => '#ff851b',
    'color_expired' => '#dc3545'
  ];
}

// Check if user is end_user for UI logic
$is_end_user = (isset($_SESSION['role']) && $_SESSION['role'] === 'end_user');
$user_department = $_SESSION['department'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Inventory</title>
  <link rel="stylesheet" href="admindasboard.css">
  <style>
    /* ============================
       TABLE
    ============================ */
    .table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    .table th,
    .table td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: center;
      font-size: 14px;
    }

    .table th {
      background: #007bff;
      color: white;
    }

    .table img {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 4px;
    }

    /* ============================
       BUTTONS
    ============================ */
    .add-btn {
      background: #007bff;
      color: #fff;
      text-align: left;
      padding: 10px 15px;
      border-radius: 5px;
      text-decoration: none;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
    }

    .add-btn:hover {
      background: #0056b3;
    }

    .track-btn {
      background: #17a2b8;
      color: #fff;
      padding: 10px 15px;
      border-radius: 5px;
      text-decoration: none;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
    }

    .track-btn:hover {
      background: #117a8b;
    }

    .dl-btn {
      background: #007bff;
      color: #fff;
      padding: 10px 15px;
      border-radius: 5px;
      text-decoration: none;
      border: none;
      cursor: pointer;
    }

    .dl-btn:hover {
      background: #0056b3;
    }

    /* ============================
       LAYOUT FOR TOP CONTROLS
    ============================ */
    .top-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      flex-wrap: wrap;
      gap: 10px;
    }

    .top-left {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 15px;
    }

    .top-right {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }

    /* ============================
       DOWNLOAD BUTTON SECTION
    ============================ */
    .download-controls {
      display: flex;
      justify-content: flex-end;
      margin-top: 15px;
      gap: 40px;
    }

    /* ============================
       PAGINATION
    ============================ */
    .pagination {
      margin-top: 10px;
      text-align: center;
    }

    .pagination .page-link {
      display: inline-block;
      padding: 4px 8px;
      margin: 0 2px;
      border: 1px solid #777;
      cursor: pointer;
      font-size: 13px;
    }

    .pagination .page-link.active {
      background: #007bff;
      color: #fff;
    }

    /* ============================
       MODAL
    ============================ */
    #invModalOverlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }

    #invModal {
      background: #fff;
      border-radius: 8px;
      padding: 20px;
      max-width: 800px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
      font-size: 14px;
    }

    #invModalClose {
      float: right;
      border: none;
      background: #dc3545;
      color: #fff;
      padding: 4px 8px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
    }
  </style>
</head>

<body class="<?= ($settings['theme'] ?? 'light') == 'dark' ? 'dark-mode' : '' ?>">
  <div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="main-content">
      <?php if (isset($_GET['deleted'])): ?>
        <div id="alert" style="background:#28a745;color:#fff;padding:10px;margin:10px;border-radius:5px;text-align:center;font-weight:bold;">
          ✅ Record deleted successfully.
        </div>
        <script>
          setTimeout(() => {
            const alert = document.getElementById('alert');
            if (alert) alert.style.display = 'none';
          }, 5000);
        </script>
      <?php endif; ?>

      <div class="inventory-container">
        <header class="header">
          <h1>Inventory</h1>
          <div class="search-bar">
            <input type="text" id="liveSearch" placeholder="Search by name / category / department">
            <button type="button"><span>&#128269;</span></button>
          </div>
        </header>

        <div class="content-box">
          <div class="top-left">
            <?php if (!$is_end_user): ?>
              <button class="add-btn" onclick="window.location.href='add_item.php'">➕ Add Item</button>
              <button class="track-btn" onclick="window.location.href='view_barcode.php'">🔍 Scan & Verify Asset</button>
            <?php else: ?>
               <h4 style="margin:0;">Department: <span style="color:#007bff;"><?= htmlspecialchars($user_department) ?></span></h4>
            <?php endif; ?>
          </div>

          <div class="download-controls">
            <form id="filterForm" style="display:flex; gap:10px; flex-wrap:wrap; margin-right:auto;">
              <label>
                Department:
                <?php if ($is_end_user): ?>
                   <input type="text" name="department" value="<?= htmlspecialchars($user_department) ?>" readonly style="background-color:#e9ecef; color:#6c757d;">
                <?php else: ?>
                   <input type="text" name="department" placeholder="Department">
                <?php endif; ?>
              </label>
              <label>
                Category:
                <input type="text" name="category" placeholder="Category">
              </label>
              <label>
                Status:
                <input type="text" name="status_label" placeholder="Status (e.g. Available)">
              </label>
              <label>
                Condition:
                <input type="text" name="condition_label" placeholder="Condition (e.g. New)">
              </label>
              <button type="submit" class="add-btn" style="background:#6c757d;">Apply</button>
            </form>
          </div>

          <div class="table-placeholder" id="inventoryTable">
            Loading inventory...
          </div>

          <div style="margin-bottom:10px;">
            <button id="btnCSV" class="dl-btn" style="background:#17a2b8;">Download CSV</button>
            <button id="btnExcel" class="dl-btn" style="background:#28a745;">Download Excel</button>
            <button id="btnPDF" class="dl-btn" style="background:#ff0000;">Download PDF</button>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div id="invModalOverlay">
    <div id="invModal">
      <button id="invModalClose">Close</button>
      <h2>Inventory Group Details</h2>
      <div id="invModalContent"></div>
    </div>
  </div>

  <script src="chart.umd.min.js"></script>
  <script src="admindashboard.js" defer></script>
  <script src="assets/js/jspdf.umd.min.js"></script>
  <script src="assets/js/jspdf.plugin.autotable.min.js"></script>
  <script src="assets/js/tableModule.js"></script>

  <script>
    // Attach to your universal tableModule.js
    initTableModule({
      ajaxUrl: "search_inventory_ajax.php", 
      filterForm: "#filterForm",
      liveSearch: "#liveSearch",
      tableContainer: "#inventoryTable",

      // Reuse module's modal hooks for "View" in inventory
      modalOverlay: "#invModalOverlay",
      modalClose: "#invModalClose",
      modalContent: "#invModalContent",

      exportCSVBtn: "#btnCSV",
      exportExcelBtn: "#btnExcel",
      exportPDFBtn: "#btnPDF",

      exportFileName: "inventory_summary",
      exportTitle: "Inventory Summary"
    });

    function deleteGroup(name, department) {
      if (!name || !department) return;

      const choice = confirm(
        "Delete ALL items for '" + name + "' in '" + department + "'?\n\n" +
        "OK  = Delete ALL items in this group\n" +
        "Cancel = Delete only ONE item from this group"
      );

      const mode = choice ? 'all' : 'one';

      if (!confirm("Are you sure you want to delete (" + mode.toUpperCase() + ") for this group?")) {
        return;
      }

      const formData = new URLSearchParams();
      formData.append('name', name);
      formData.append('department', department);
      formData.append('mode', mode);

      fetch('delete_inventory_group.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: formData.toString()
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            alert("Deleted (" + data.mode.toUpperCase() + "). Rows affected: " + (data.deleted || 0));
            window.location.reload();
          } else {
            alert("Delete failed: " + (data.error || 'Unknown error'));
          }
        })
        .catch(err => {
          alert("Error contacting server: " + err);
        });
    }
  </script>
</body>

</html>