<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
<meta charset="UTF-8">
<title>User Management</title>
<link rel="stylesheet" href="admindasboard.css">

<style>
.table {
  width: 100%;
  border-collapse: collapse;
}
.table th, .table td {
  border: 1px solid #ddd;
  padding: 8px;
  text-align: center;
  font-size: 14px;
}
.table th {
  background: #007bff;
  color: #fff;
}

.add-btn {
  background: #007bff;
  color:#fff;
  padding:10px 15px;
  border-radius:5px;
  border:none;
  cursor:pointer;
}
.add-btn:hover { background:#0056b3; }

.apply-btn {
  background: #007bff;
  color:#fff;
  padding:10px 15px;
  border-radius:5px;
  border:none;
  cursor:pointer;
}
.apply-btn:hover { background:#0056b3; }

.dl-btn {
  padding: 10px 15px;
  color:#fff;
  border-radius:5px;
  border:none;
  cursor:pointer;
}

/* Alert Notification System Styling */
.alert-message {
  padding: 12px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
}
.msg-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.msg-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

/* Custom Action Modal Component CSS */
.custom-modal-overlay {
  display: none; 
  position: fixed; 
  top: 0; left: 0; right: 0; bottom: 0; 
  background: rgba(0,0,0,0.5); 
  justify-content: center; 
  align-items: center;
  z-index: 9999;
}
.custom-modal {
  background: #fff; 
  border-radius: 8px; 
  padding: 24px; 
  width: 90%; 
  max-width: 420px;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.modal-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-top: 20px;
}
.btn-confirm { background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; }
.btn-cancel { background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; }

/* Filter option dropdown styling alignment */
.form-select-custom {
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  background-color: white;
  min-width: 180px;
}
</style>
</head>

<body class="<?= ($settings['theme']=='dark') ? 'dark-mode' : '' ?>">
<div class="dashboard">

<?php include __DIR__ . '/../../sidebar.php'; ?>

<main class="main-content">
<div class="inventory-container">

<header class="header">
    <h1>User Management</h1>
    <div class="search-bar">
        <input type="text" id="liveSearch" placeholder="Search users...">
        <button><span>&#128269;</span></button>
    </div>
</header>

<div class="content-box">

    <?php if (isset($_SESSION['success_flash'])): ?>
        <div class="alert-message msg-success">
            🎉 <?= $_SESSION['success_flash']; unset($_SESSION['success_flash']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_flash'])): ?>
        <div class="alert-message msg-error">
            ⚠️ <?= $_SESSION['error_flash']; unset($_SESSION['error_flash']); ?>
        </div>
    <?php endif; ?>

    <a href="signup.php" class="add-btn" style="text-decoration: none; display: inline-block;">➕ Add User</a>

    <form id="filterForm" style="display:flex; gap:15px; margin:15px 0; flex-wrap:wrap; align-items:center;">
        <label style="font-size:14px; font-weight:600; color:#475569;">
            Role:
            <select name="role" class="form-select-custom">
                <option value="">All Roles</option>
                <option value="admin">Supply Admin</option>
                <option value="subadmin">Sub Admin</option>
                <option value="inst_admin">Admin</option>
                <option value="DAA">DAA</option>
                <option value="end_user">End User</option>
            </select>
        </label>
        <label style="font-size:14px; font-weight:600; color:#475569;">
            Created After:
            <input type="date" name="created_from" class="form-select-custom" style="font-family:inherit;">
        </label>
        <button class="apply-btn" style="background:#6c757d; margin-top:20px;">Apply</button>
    </form>
    
    <div id="usersTable">Loading users...</div>

    <div style="margin-bottom:15px;">
        <button id="btnCSV" class="dl-btn" style="background:#17a2b8;">Download CSV</button>
        <button id="btnExcel" class="dl-btn" style="background:#28a745;">Download Excel</button>
        <button id="btnPDF" class="dl-btn" style="background:#FF0000;"> Download PDF</button>
    </div>

</div>
</main>
</div>

<div id="usrModalOverlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:9998;">
  <div id="usrModal" style="background:#fff; border-radius:8px; padding:20px; width:90%; max-width:700px;">
      <button id="usrModalClose" style="float:right; background:#dc3545; color:#fff; border:none; padding:5px 10px;">Close</button>
      <h2>User Details</h2>
      <div id="usrModalContent"></div>
  </div>
</div>

<div id="customConfirmOverlay" class="custom-modal-overlay">
  <div class="custom-modal">
      <h3 style="margin-top:0; color:#334155;">Confirm Action</h3>
      <p id="customConfirmMessage" style="color:#64748b; font-size:14px;">Are you sure you want to proceed with deleting this user?</p>
      <div class="modal-actions">
          <button id="btnModalConfirm" class="btn-confirm">Delete Account</button>
          <button id="btnModalCancel" class="btn-cancel">Cancel</button>
      </div>
  </div>
</div>

<script src="chart.umd.min.js"></script>
<script src="admindashboard.js" defer></script>

<script src="assets/js/jspdf.umd.min.js"></script>
<script src="assets/js/jspdf.plugin.autotable.min.js"></script>
<script src="assets/js/tableModule.js"></script>

<script>
let activeDeleteUrl = "";

initTableModule({
    ajaxUrl: "/SUPPLY_OFFICE_first/search_users_ajax.php",
    filterForm: "#filterForm",
    liveSearch: "#liveSearch",
    tableContainer: "#usersTable",

    modalOverlay: "#usrModalOverlay",
    modalClose: "#usrModalClose",
    modalContent: "#usrModalContent",

    exportCSVBtn: "#btnCSV",
    exportExcelBtn: "#btnExcel",
    exportPDFBtn: "#btnPDF",

    exportFileName: "users_list",
    exportTitle: "Users List"
});

// INTERCEPT SYSTEM: Replaces default browser confirm boxes smoothly
function openCustomConfirm(message, actionUrl) {
    activeDeleteUrl = actionUrl;
    document.getElementById("customConfirmMessage").textContent = message;
    document.getElementById("customConfirmOverlay").style.display = "flex";
}

document.getElementById("btnModalCancel").addEventListener("click", function() {
    document.getElementById("customConfirmOverlay").style.display = "none";
    activeDeleteUrl = "";
});

document.getElementById("btnModalConfirm").addEventListener("click", function() {
    if (activeDeleteUrl !== "") {
        window.location.href = activeDeleteUrl;
    }
});

// WATCHER: Rewrites database column text matches dynamically inside AJAX output container
const targetContainer = document.getElementById('usersTable');
const roleMap = {
    'admin': 'Supply Admin',
    'subadmin': 'Sub Admin',
    'inst_admin': 'Admin',
    'end_user': 'End User'
};

const observer = new MutationObserver(() => {
    const tableCells = targetContainer.querySelectorAll('td');
    tableCells.forEach(cell => {
        const rawText = cell.textContent.trim().toLowerCase();
        if (roleMap[rawText]) {
            cell.textContent = roleMap[rawText];
        }
    });
});

observer.observe(targetContainer, { childList: true, subtree: true });
</script>

<script>
    window.alert = function(message) {
        console.warn("Blocked a legacy window.alert popup: ", message);
        return true;
    };

    window.confirm = function(message) {
        console.warn("Blocked a legacy window.confirm popup: ", message);
        return true; 
    };
</script>

</body>
</html>
