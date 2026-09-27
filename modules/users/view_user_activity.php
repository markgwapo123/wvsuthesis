<?php
require_once __DIR__ . '/../../db_connect.php';

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
  <title>User Activity Log</title>
  <link rel="stylesheet" href="admindasboard.css">

  <style>
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 14px; }
    th { background: #007bff; color: white; }
    input, select { padding: 8px; margin: 4px 0; border: 1px solid #ccc; border-radius: 4px; }
    button { padding: 8px 12px; border: 1px solid #888; background: #f7f7f7; cursor: pointer; border-radius: 4px; }
    button:hover { background:#e2e2e2; }
    th.sortable { cursor: pointer; }
    th.sortable::after { content: ' ⇅'; font-size: 11px; color:#ddd; }

    /* Modal Styling */
    #logModalOverlay {
      display:none;
      position:fixed;
      top:0; left:0; right:0; bottom:0;
      background:rgba(0,0,0,0.6);
      justify-content:center;
      align-items:center;
      z-index:9999;
    }
    #logModal {
      background:#fff;
      border-radius:8px;
      padding:25px;
      max-width:550px;
      width:90%;
      max-height:85vh;
      overflow-y:auto;
      box-shadow:0 10px 30px rgba(0,0,0,0.5);
    }
    #logModalClose {
      float:right;
      border:none;
      background:#dc3545;
      color:#fff;
      padding:5px 10px;
      border-radius:4px;
      cursor:pointer;
    }
    pre.ua-block {
      background:#f1f3f5;
      padding:10px;
      border-radius:4px;
      font-size:11px;
      white-space:pre-wrap;
      border: 1px solid #ddd;
    }
    .badge-name {
        color: #007bff;
        font-weight: bold;
    }
  </style>
</head>

<body class="<?= ($settings['theme'] == 'dark') ? 'dark-mode' : '' ?>">
<div class="dashboard">
  <?php include __DIR__ . '/../../sidebar.php'; ?>

  <main class="main-content">
    <div class="inventory-container">

      <header class="header">
        <h1>User Activity Log</h1>
        <div class="search-bar">
          <input type="text" id="liveSearch" placeholder="Search by name, action, or module...">
        </div>
      </header>

      <div class="content-box">
        <div style="margin-bottom:15px;">
          <button type="button" onclick="setQuickFilter('today')">Today</button>
          <button type="button" onclick="setQuickFilter('week')">Last 7 Days</button>
          <button type="button" onclick="setQuickFilter('month')">This Month</button>
        </div>

        <div class="filter-bar" style="background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #eee;">
          <form id="filterForm" style="display:flex; flex-wrap:wrap; gap:15px; align-items:center;">
            <div>
                <label>User ID:</label><br>
                <input type="text" name="uid" style="width:80px;">
            </div>
            <div>
                <label>Module:</label><br>
                <input type="text" name="module">
            </div>
            <div>
                <label>Action:</label><br>
                <input type="text" name="action">
            </div>
            <div>
                <label>From:</label><br>
                <input type="date" name="from">
            </div>
            <div>
                <label>To:</label><br>
                <input type="date" name="to">
            </div>
            <button type="submit" style="background:#007bff; color:white; border:none; margin-top:20px;">Apply Filters</button>
          </form>
        </div>

        <div id="activityTable">
          <p style="text-align:center; padding:20px;">Loading activity logs...</p>
        </div>

        <div style="margin-top: 25px; display:flex; gap:10px;">
          <button type="button" onclick="downloadCSV()" style="background:#17a2b8; color:white; border:none;">CSV Export</button>
          <button type="button" onclick="downloadPDF()" style="background:#dc3545; color:white; border:none;">PDF Report</button>
        </div>

      </div>
    </div>
  </main>
</div>

<div id="logModalOverlay">
  <div id="logModal">
    <button id="logModalClose">Close</button>
    <h2 style="margin-top:0;">Log Details</h2>
    <hr>
    <div id="logModalContent"></div>
  </div>
</div>

<script src="assets/js/jspdf.umd.min.js"></script>
<script src="assets/js/jspdf.plugin.autotable.min.js"></script>
<script src="admindashboard.js" defer></script>

<script>
/* ============================
   CORE AJAX LOADING
============================ */
function loadActivityLogs(page = 1) {
  let formData = new FormData(document.getElementById('filterForm'));
  formData.append('live', document.getElementById('liveSearch').value);
  formData.append('page', page);

  fetch('search_activity_ajax.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.text())
  .then(html => {
    document.getElementById("activityTable").innerHTML = html;
    initDetailsButtons();
    initPagination();
  })
  .catch(err => console.error("Error loading logs:", err));
}

// Initial Load
document.addEventListener("DOMContentLoaded", () => loadActivityLogs());

// Live Search Event
document.getElementById('liveSearch').addEventListener('keyup', () => loadActivityLogs(1));

// Filter Form Event
document.getElementById('filterForm').addEventListener('submit', e => {
  e.preventDefault();
  loadActivityLogs(1);
});

/* ============================
   MODAL & DETAILS
============================ */
function initDetailsButtons() {
  document.querySelectorAll(".view-details-btn").forEach(btn => {
    btn.addEventListener("click", function() {
      // Build Modal Content using Dataset from AJAX
      document.getElementById("logModalContent").innerHTML = `
        <p><b>Log ID:</b> #${this.dataset.logId}</p>
        <p><b>User:</b> ${this.dataset.userName} (ID: ${this.dataset.userId})</p>
        <p><b>Module:</b> <span style="color:#007bff;">${this.dataset.module}</span></p>
        <p><b>Action:</b> ${this.dataset.action}</p>
        <p><b>Status:</b> ${this.dataset.status}</p>
        <p><b>Timestamp:</b> ${this.dataset.createdAt}</p>
        <hr>
        <p><b>IP Address:</b> ${this.dataset.ip}</p>
        <p><b>User Agent / Device:</b></p>
        <pre class="ua-block">${this.dataset.device}</pre>
      `;
      document.getElementById("logModalOverlay").style.display = "flex";
    });
  });
}

document.getElementById("logModalClose").addEventListener("click", () => {
  document.getElementById("logModalOverlay").style.display = "none";
});

/* ============================
   PAGINATION
============================ */
function initPagination() {
  document.querySelectorAll(".pagination-btn").forEach(btn => {
    btn.addEventListener("click", function() {
      loadActivityLogs(this.dataset.page);
    });
  });
}

/* ============================
   QUICK FILTERS
============================ */
function setQuickFilter(type) {
  let today = new Date().toISOString().split("T")[0];
  let fromDate = today;

  if (type === 'week') {
    let d = new Date();
    d.setDate(d.getDate() - 7);
    fromDate = d.toISOString().split("T")[0];
  } else if (type === 'month') {
    let d = new Date();
    d.setMonth(d.getMonth() - 1);
    fromDate = d.toISOString().split("T")[0];
  }

  document.querySelector("input[name='from']").value = fromDate;
  document.querySelector("input[name='to']").value = today;
  loadActivityLogs(1);
}

/* ============================
   EXPORTS
============================ */
function downloadCSV() {
  let table = document.querySelector("#activityTable table");
  if (!table) return;

  let csv = [];
  let rows = table.querySelectorAll("tr");
  
  rows.forEach(row => {
    let cols = row.querySelectorAll("td, th");
    let rowData = [];
    cols.forEach((col, index) => {
        if(index < 8) { // Skip "Details" column
            let text = col.innerText.replace(/"/g, '""');
            rowData.push(`"${text}"`);
        }
    });
    csv.push(rowData.join(","));
  });

  let blob = new Blob([csv.join("\n")], { type: "text/csv" });
  let link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = "Activity_Log_Report.csv";
  link.click();
}

function downloadPDF() {
  const { jsPDF } = window.jspdf;
  let doc = new jsPDF('l', 'pt', 'a4');
  doc.text("User Activity Report", 40, 40);
  doc.autoTable({ 
    html: '#activityTable table',
    columns: [0, 1, 2, 3, 4, 5, 6, 7], // Exclude the "Details" button column
    startY: 60,
    theme: 'striped'
  });
  doc.save("Activity_Report.pdf");
}
</script>

</body>
</html>