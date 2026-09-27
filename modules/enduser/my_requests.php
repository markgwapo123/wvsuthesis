<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'end_user') {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit;
}

$user_id = $_SESSION['user_id'];
$department = $_SESSION['department'] ?? 'End User';

// PREPARED SQL ALIGNED WITH ADMIN SYSTEM
$stmt = $conn->prepare("
    SELECT 
        request_id,
        item_name,
        unit,
        qty_requested,
        remarks,
        status,
        reason_of_rejection, 
        approved_by,
        requested_at,
        processed_at
    FROM requests
    WHERE requester_user_id = ?
    ORDER BY requested_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests | SOIMS</title>
    <link rel="stylesheet" href="enduser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fc;
            margin: 0;
            padding: 0;
        }

        /* Responsive Layout Matrix Base */
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

        /* Synchronized Header Component Card */
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

        /* Clean Content Card Panel */
        .table-card {
            background: white;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            margin-top: 10px;
            font-size: 14px;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        tr:hover td {
            background-color: #f8fafc;
        }

        /* ---- MODERN STATUS BADGES ---- */
        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: bold;
            display: inline-block;
            text-align: center;
            text-transform: capitalize;
        }

        .badge.pending {
            background: #fff5d6;
            color: #a97810;
        }

        .badge.approved {
            background: #e2f7e9;
            color: #1b8a3b;
        }

        .badge.rejected {
            background: #fde2e2;
            color: #b62323;
        }

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

        .text-muted {
            color: #94a3b8;
        }

        .remarks-text {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
            font-style: italic;
        }
    </style>
</head>

<body>

    <div class="dashboard-container">
        <?php include __DIR__ . '/endusersidebar.php'; ?>

        <main class="main-workspace">
            <div class="wrapper">

                <div class="header-area">
                    <h2><i class="fas fa-paper-plane" style="color: #004aad;"></i> Track My Supply Requests</h2>
                    <a href="endUserDashboard.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Return to Workspace
                    </a>
                </div>

                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Item Details</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Approved By</th> 
                                <th>Reason of Rejection</th> 
                                <th>Requested Date</th>
                                <th>Processed Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($requests && $requests->num_rows > 0): ?>
                                <?php while ($r = $requests->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($r['item_name']) ?></strong> 
                                            <span class="text-muted">(<?= htmlspecialchars($r['unit']) ?>)</span>
                                            
                                            <?php if (!empty($r['remarks'])): ?>
                                                <span class="remarks-text">
                                                    <i class="far fa-comment-alt text-muted me-1"></i> 
                                                    "<?= htmlspecialchars($r['remarks']) ?>"
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= (int)$r['qty_requested'] ?></strong></td>
                                        <td>
                                            <span class="badge <?= strtolower($r['status']) ?>">
                                                <?= htmlspecialchars($r['status']) ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <?php if (strtolower($r['status']) === 'approved' && !empty($r['approved_by'])): ?>
                                                <span class="text-dark"><i class="fas fa-user-check text-success me-1"></i> <?= htmlspecialchars($r['approved_by']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">â€”</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if (strtolower($r['status']) === 'rejected' && !empty($r['rejection_reason'])): ?>
                                                <span class="text-danger" style="font-weight: 500;"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($r['rejection_reason']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">â€”</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <small><?= date('M d, Y h:i A', strtotime($r['requested_at'])) ?></small>
                                        </td>
                                        <td>
                                            <small>
                                                <?= $r['processed_at'] ? date('M d, Y h:i A', strtotime($r['processed_at'])) : '<span class="text-muted">â€”</span>' ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #64748b; padding: 40px 20px;">
                                        <i class="far fa-folder-open" style="font-size: 32px; color: #cbd5e1; display: block; margin-bottom: 10px;"></i>
                                        No item tracking requests found for your account.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

</body>
</html>
