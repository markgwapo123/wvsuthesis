<?php
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../auth.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'end_user') {
    die("Access Denied.");
}

$myDept     = trim($_SESSION['department'] ?? '');
$myUsername = trim($_SESSION['username'] ?? '');
$myRealName = trim($_SESSION['requestor_name'] ?? $_SESSION['fullname'] ?? $_SESSION['name'] ?? ''); 

if (isset($_POST['action']) && $_POST['action'] === 'fetch_received')  {
    $page   = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $limit  = 10;
    $offset = ($page - 1) * $limit;
    
    $where = [];
    if (!empty($myDept)) {
        $where[] = "LOWER(TRIM(u.department)) = LOWER('" . $conn->real_escape_string($myDept) . "')";
    }
    
    $userMatches = [];
    if (!empty($myRealName)) {
        $escapedReal = strtolower($conn->real_escape_string($myRealName));
        $userMatches[] = "LOWER(TRIM(u.requestor_name)) = '$escapedReal'";
        $userMatches[] = "LOWER(TRIM(u.receiver)) = '$escapedReal'";
        if (strpos($escapedReal, 'faith') !== false) {
            $userMatches[] = "LOWER(TRIM(u.receiver)) LIKE '%clea faith%'";
            $userMatches[] = "LOWER(TRIM(u.receiver)) LIKE '%faith florentino%'";
        }
    }
    if (!empty($myUsername)) {
        $escapedUser = strtolower($conn->real_escape_string($myUsername));
        $userMatches[] = "LOWER(TRIM(u.requestor_name)) = '$escapedUser'";
        $userMatches[] = "LOWER(TRIM(u.receiver)) = '$escapedUser'";
    }
    if (!empty($userMatches)) {
        $where[] = "(" . implode(" OR ", $userMatches) . ")";
    }

    if (!empty($_POST['live'])) {
        $s = $conn->real_escape_string($_POST['live']);
        $where[] = "u.item_name LIKE '%$s%'";
    }

    $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

    $countRes = $conn->query("SELECT COUNT(*) AS total FROM issuances u $whereSQL");
    $totalRows = $countRes ? (int)$countRes->fetch_assoc()['total'] : 0;
    $totalPages = max(1, ceil($totalRows / $limit));

    $sql = "SELECT u.issuance_id, u.item_name, u.issued_qty, u.issued_at, u.remarks, u.department,
                   (SELECT IFNULL(SUM(qty), 0) FROM disposal_requests WHERE issuance_id = u.issuance_id AND status IN ('Pending DAA', 'Approved by DAA')) AS qty_pending,
                   (SELECT IFNULL(SUM(qty), 0) FROM disposal_requests WHERE issuance_id = u.issuance_id AND status = 'Disposed') AS qty_disposed
            FROM issuances u 
            $whereSQL 
            ORDER BY u.issued_at DESC 
            LIMIT $limit OFFSET $offset";
    $res = $conn->query($sql);

    echo "
    <table class='table'>
      <thead>
        <tr>
          <th>Issuance ID</th>
          <th>Item Name</th>
          <th>Quantity Received</th>
          <th>Date Received</th>
          <th>Remarks</th>
          <th style='text-align: center;'>Actions Pipeline Status</th>
        </tr>
      </thead>
      <tbody>
    ";

    if (!$res || $res->num_rows === 0) {
        echo "<tr><td colspan='6' style='text-align:center; padding: 30px; color: #64748b;'>No received items found matching criteria.</td></tr>";
    } else {
        while ($r = $res->fetch_assoc()) {
            $formattedDate = date("M d, Y h:i A", strtotime($r['issued_at']));
            $exact_id = !empty($r['issuance_id']) ? $r['issuance_id'] : ($r['id'] ?? 0);

            $total_issued = (int)$r['issued_qty'];
            $qty_pending  = (int)$r['qty_pending'];
            $qty_disposed = (int)$r['qty_disposed'];
            
            $available_balance = $total_issued - ($qty_pending + $qty_disposed);

            $disposalParams = http_build_query([
                'issuance_id' => $exact_id,
                'item'        => $r['item_name'],
                'qty'         => $r['issued_qty'],
                'dept'        => $r['department']
            ]);

            $actionButton = "";
            
            if ($available_balance <= 0) {
                if ($qty_disposed >= $total_issued) {
                    $actionButton = "<button type='button' class='dispose-btn state-disabled' disabled><i class='fas fa-lock'></i> Disposed Complete</button>";
                } else {
                    $actionButton = "<button type='button' class='dispose-btn state-pending' disabled><i class='fas fa-hourglass-half'></i> Pending Disposal</button>";
                }
            } else {
                $label = "Request Disposal";
                if (($qty_pending + $qty_disposed) > 0) {
                    $label = "Dispose More ($available_balance Left)";
                }
                $actionButton = "<button type='button' class='dispose-btn' 
                                         onclick=\"window.location.href='request_disposal.php?{$disposalParams}'\">
                                    {$label}
                                 </button>";
            }

            echo "
            <tr>
              <td><span class='badge'>#{$exact_id}</span></td>
              <td><strong>" . htmlspecialchars($r['item_name']) . "</strong></td>
              <td><span style='font-weight:bold; color:#003366;'>{$total_issued}</span></td>
              <td><small class='text-muted'>{$formattedDate}</small></td>
              <td><small>" . htmlspecialchars($r['remarks'] ?? '—') . "</small></td>
              <td style='text-align: center;'>
                {$actionButton}
              </td>
            </tr>";
        }
    }
    echo "</tbody></table>";

    if ($totalPages > 1) {
        echo "<div class='pagination'>";
        if ($page > 1) echo "<span class='page-link' data-page='" . ($page - 1) . "'>&laquo; Previous</span>";
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i === $page) ? "active" : "";
            echo "<span class='page-link {$active}' data-page='{$i}'>{$i}</span>";
        }
        if ($page < $totalPages) echo "<span class='page-link' data-page='" . ($page + 1) . "'>Next &raquo;</span>";
        echo "</div>";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Received Items | SOIMS</title>
    <link rel="stylesheet" href="enduser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            padding: 0;
            background: #f4f7fc; 
            color: #333; 
        }

        /* Responsive Dashboard Split Matrix Container */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-workspace {
            flex: 1;
            padding: 30px;
            box-sizing: border-box;
        }

        .wrapper { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: transparent; 
        }

        /* Synchronized Top Navbar Header Area Component */
        .header-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: #fff;
            padding: 20px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .header-area h2 {
            margin: 0;
            font-size: 22px;
            color: #003366;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Workspace Meta Info Summary strip */
        .profile-summary { 
            background: #e8f4fd; 
            padding: 14px 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            color: #1d68a7; 
            font-size: 14px; 
            border-left: 4px solid #3187cc;
            display: flex;
            gap: 20px;
        }

        /* Operational Data Grid Control Card */
        .content-card {
            background: #fff;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .search-box { 
            padding: 10px 16px; 
            width: 100%;
            max-width: 320px; 
            border: 1px solid #cbd5e1; 
            border-radius: 6px; 
            margin-bottom: 20px; 
            font-size: 14px; 
            outline: none;
            transition: border-color 0.2s;
        }
        
        .search-box:focus {
            border-color: #003366;
        }

        /* Modular Core Data Presenter Elements */
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        .table th { background-color: #f8fafc; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        tr:hover td { background-color: #f8fafc; }

        /* Dynamic Pipeline Control Buttons */
        .dispose-btn { background-color: #dc3545; color: white; border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; transition: background 0.2s; min-width: 165px; text-align: center; }
        .dispose-btn:hover { background-color: #bd2130; }
        .dispose-btn.state-pending { background-color: #f59e0b !important; color: #ffffff !important; cursor: not-allowed !important; opacity: 0.85; }
        .dispose-btn.state-disabled { background-color: #64748b !important; color: #ffffff !important; cursor: not-allowed !important; opacity: 0.7; }
        
        /* Modernized Pagination Blocks */
        .pagination { margin-top: 25px; display: flex; gap: 6px; justify-content: center; }
        .page-link { padding: 8px 14px; border: 1px solid #cbd5e1; background: #fff; color: #003366; cursor: pointer; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .page-link.active { background: #003366; color: white; border-color: #003366; }
        .page-link:hover:not(.active) { background-color: #f1f5f9; }
        
        .badge { background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-family: monospace; color: #475569; font-weight: 600; }
        .text-muted { color: #94a3b8; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            color: #003366;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #003366;
            color: white;
            border-color: #003366;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <div class="dashboard-container">
        <?php include __DIR__ . '/endusersidebar.php'; ?>

        <main class="main-workspace">
            <div class="wrapper">
                
                <div class="header-area">
                    <h2><i class="fas fa-file-download" style="color: #004aad;"></i> Receive & Manage Items Panel</h2>
                    <a href="endUserDashboard.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Return to Workspace
                    </a>
                </div>

                <div class="profile-summary">
                    <div>🏢 Department: <strong><?= htmlspecialchars(!empty($myDept) ? $myDept : 'NOT SET'); ?></strong></div>
                    <div>👤 Active User: <strong><?= htmlspecialchars(!empty($myRealName) ? $myRealName : (!empty($myUsername) ? $myUsername : 'NOT SET')); ?></strong></div>
                </div>

                <div class="content-card">
                    <input type="text" id="liveSearch" class="search-box" placeholder="🔍 Search item name...">
                    <div id="tableContainer"></div>
                </div>

            </div>
        </main>
    </div>

    <script>
    $(document).ready(function(){
        function loadTable(page = 1){
            var live = $('#liveSearch').val();
            $.ajax({
                url: 'receive_item.php',
                type: 'POST',
                data: { action: 'fetch_received', page: page, live: live },
                success: function(response){
                    $('#tableContainer').html(response);
                }
            });
        }
        
        loadTable();
        
        $('#liveSearch').on('keyup', function(){ 
            loadTable(1); 
        });
        
        $(document).on('click', '.page-link', function(){
            var page = $(this).data('page');
            if(page) loadTable(page);
        });
    });
    </script>
</body>
</html>