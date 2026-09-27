      <?php
      session_start();
      require_once __DIR__ . '/db_connect.php';

      // Check session
      if (!isset($_SESSION['role'])) {
        header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
        exit();
      }

      // Load theme settings
      $settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
      $settings = ($settingsQuery && $settingsQuery->num_rows > 0)
        ? $settingsQuery->fetch_assoc()
        : ['theme' => 'light'];

      ?>
      <!DOCTYPE html>
      <html lang="en">

      <head>
        <meta charset="utf-8">
        <title>Issuance Report</title>
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
            text-align: left;
            font-size: 14px;
          }

          th {
            background: #007bff;
            color: #fff;
          }

          .controls {
            margin: 10px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
          }

          .btn {
            padding: 6px 10px;
            border: 1px solid #ccc;
            background: #f7f7f7;
            cursor: pointer;
          }

          .add-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
          }

          .search-bar input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
          }

          .search-bar button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 10px;
            border-radius: 6px;
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
                <h1>Issuance Report</h1>

                <div class="search-bar">
                  <input type="text" id="liveSearch" placeholder="Search issuance...">
                  <button>🔍</button>
                </div>
              </header>

              <div class="content-box">

                <!-- FILTER BAR -->
                <form id="filterForm" class="controls">
                  <label>From: <input type="date" name="from"></label>
                  <label>To: <input type="date" name="to"></label>
                  <button type="submit" class="btn" style="background:#6c757d;color:white;">Apply</button>
                </form>
                <!-- AJAX TABLE -->
                <div id="issuanceTable">Loading...</div>

                <!-- EXPORT BUTTONS -->
                <div style="margin-bottom:10px;">
                  <button id="btnCSV" class="btn" style="background:#17a2b8;color:white;">Download CSV</button>
                  <button id="btnExcel" class="btn" style="background:#28a745;color:white;">Download Excel</button>
                  <button id="btnPDF" class="btn" style="background:#FF0000;color:white;">Download PDF</button>
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
            ajaxUrl: "/SUPPLY_OFFICE_first/search_issuance_report_ajax.php",
            filterForm: "#filterForm",
            liveSearch: "#liveSearch",
            tableContainer: "#issuanceTable",

            modalOverlay: null,
            modalClose: null,
            modalContent: null,

            exportCSVBtn: "#btnCSV",
            exportExcelBtn: "#btnExcel",
            exportPDFBtn: "#btnPDF",

            exportFileName: "issuance_report",
            exportTitle: "Issuance Report"
          });
        </script>

      </body>

      </html>
