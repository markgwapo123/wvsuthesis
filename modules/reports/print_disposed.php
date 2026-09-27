<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth.php';

// Access Control - Admins and Sub-admins only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'subadmin'])) {
    die("Access Denied: Unauthorized configuration perspective.");
}

$disposal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($disposal_id <= 0) {
    die("Error: No specific disposal transaction ID reference was provided.");
}

/* FETCH WITH NAMES FALLBACK MECHANISM:
  We perform LEFT JOINs against your users/accounts table to fetch full names.
  Replace 'users' and column names ('email', 'first_name', 'last_name') if your database tables vary.
*/
$query = "SELECT dr.*, 
                 CONCAT(u1.first_name, ' ', u1.last_name) AS requestor_real_name,
                 CONCAT(u2.first_name, ' ', u2.last_name) AS daa_real_name
          FROM disposal_requests dr
          LEFT JOIN users u1 ON dr.requestor_name = u1.email
          LEFT JOIN users u2 ON dr.daa_approved_by = u2.email
          WHERE dr.disposal_id = ? AND dr.status = 'Disposed' LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $disposal_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Error: Eligible finalized asset disposal matching transaction ID #$disposal_id could not be isolated.");
}

// Fallback logic: If the JOIN strings are empty, fall back to displaying the raw string/email recorded
$requestorDisplayName = (!empty(trim($data['requestor_real_name']))) ? $data['requestor_real_name'] : $data['requestor_name'];
$daaDisplayName = (!empty(trim($data['daa_real_name']))) ? $data['daa_real_name'] : ($data['daa_approved_by'] ?? 'Verified Staff');

$formattedDate = date("F d, Y | h:i A", strtotime($data['created_at']));
$printTimestamp = date("F d, Y h:i A");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Disposal_Voucher_#<?php echo $disposal_id; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #0f172a;
            background-color: #ffffff;
            margin: 0;
            padding: 20px;
        }

        /* Toolbar Header Area */
        .no-print-toolbar {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .toolbar-title { font-size: 14px; font-weight: 600; color: #475569; }
        .btn-action { padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; text-decoration: none; transition: all 0.1s; }
        .btn-close { background: #ffffff; color: #475569; border: 1px solid #cbd5e1; }
        .btn-close:hover { background: #f1f5f9; }
        .btn-print { background: #2563eb; color: #ffffff; border: 1px solid #2563eb; }
        .btn-print:hover { background: #1d4ed8; }

        /* Receipt Copy Containers */
        .receipt-copy {
            border: 1px dashed #94a3b8;
            padding: 30px;
            border-radius: 8px;
            position: relative;
            background: #ffffff;
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        .copy-tag {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 4px;
            color: #475569;
            letter-spacing: 0.5px;
        }

        /* Letterhead Layout Elements */
        .letterhead {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .letterhead h2 { margin: 0; font-size: 20px; font-weight: 700; text-transform: uppercase; }
        .letterhead p { margin: 3px 0 0 0; font-size: 13px; color: #475569; }

        .voucher-title { text-align: center; margin: 20px 0; }
        .voucher-title h3 { margin: 0; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Field Layout Matrices */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
            font-size: 13.5px;
        }
        .info-item { line-height: 1.6; }
        .info-item span { color: #475569; font-weight: 500; }

        /* Data Items Table */
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 35px;
        }
        .receipt-table th, .receipt-table td {
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            text-align: left;
            font-size: 13px;
        }
        .receipt-table th { background: #f8fafc; font-weight: 600; text-transform: uppercase; font-size: 11px; }

        /* Signatures Grid */
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
            margin-top: 40px;
            text-align: center;
        }
        .sig-block { margin-top: 15px; }
        .sig-line { border-bottom: 1px solid #0f172a; margin-bottom: 6px; height: 35px; display: flex; align-items: flex-end; justify-content: center; font-size: 13px; font-weight: 600; color: #1e293b; }
        .sig-label { font-size: 11px; font-weight: 600; color: #475569; text-transform: uppercase; border-top: 1px solid #cbd5e1; padding-top: 4px; }

        /* CSS Print System Overrides */
        @media print {
            .no-print-toolbar { display: none !important; }
            body { padding: 0; margin: 0; }
            .receipt-copy { border: 1px solid #cbd5e1; margin-bottom: 50px; }
            .receipt-table th { background-color: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="no-print-toolbar">
        <div class="toolbar-title">📋 Disposal Manifest Print Preview Mode</div>
        <div style="display: flex; gap: 8px;">
            <a href="admin_execute_disposals.php?tab=completed" class="btn-action btn-close">← Back to Logs</a>
            <button onclick="window.print();" class="btn-action btn-print">🖨️ Print Copies</button>
        </div>
    </div>

    <?php 
    // Array to map layout iterations sequentially to produce both Office and Enduser copies
    $copies = ["Supply Office Copy Archive", "End-User Acknowledgment Copy"];
    foreach ($copies as $title_copy): 
    ?>
    <div class="receipt-copy">
        <div class="copy-tag"><?php echo $title_copy; ?></div>
        
        <div class="letterhead">
            <h2>Supply Office Management System</h2>
            <p>Inventory Control & Final Asset Disposal Verification Ledger</p>
        </div>

        <div class="voucher-title">
            <h3>Disposal Confirmation Receipt</h3>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <span>Voucher reference:</span> <strong>#DR-<?php echo str_pad($data['disposal_id'], 5, '0', STR_PAD_LEFT); ?></strong><br>
                <span>Request Origin Date:</span> <?php echo $formattedDate; ?><br>
                <span>Printed Timestamp:</span> <?php echo $printTimestamp; ?>
            </div>
            <div class="info-item" style="text-align: right;">
                <span>Originating Dept:</span> <strong><?php echo htmlspecialchars($data['department']); ?></strong><br>
                <span>Original Requestor:</span> <?php echo htmlspecialchars($requestorDisplayName); ?><br>
                <span>Workflow Status:</span> <span style="color: #16a34a; font-weight: 700;">DISPOSED</span>
            </div>
        </div>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th style="width: 15%;">System ID</th>
                    <th style="width: 50%;">Description / Item Specifications</th>
                    <th style="width: 35%; text-align: center;">Disposed Quantity</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>#<?php echo $data['disposal_id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($data['item_name']); ?></strong><br>
                        <small style="color: #475569;">Reason given: <?php echo htmlspecialchars($data['reason']); ?></small>
                    </td>
                    <td style="text-align: center; font-size: 15px; font-weight: 700; color: #dc2626;">
                        <?php echo htmlspecialchars($data['qty']); ?> <?php echo htmlspecialchars($data['unit']); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="signature-section">
            <div class="sig-block">
                <div class="sig-line"><?php echo htmlspecialchars($requestorDisplayName); ?></div>
                <div class="sig-label">Issued By (End-User)</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"><?php echo htmlspecialchars($daaDisplayName); ?></div>
                <div class="sig-label">Verified By (DAA Head)</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'System Admin'); ?></div>
                <div class="sig-label">Executed By (Supply Admin)</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</body>
</html>