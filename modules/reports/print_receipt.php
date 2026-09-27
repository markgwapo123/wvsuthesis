<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';

// SECURITY: Must be logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

// Get the Issuance ID from the URL
$issuance_id = isset($_GET['issuance_id']) ? (int)$_GET['issuance_id'] : 0;

if ($issuance_id <= 0) {
    die("Invalid Issuance ID.");
}

/* STEP 1: Fetch the single issuance row based on issuance_id */
$sql = "SELECT i.*, 
               CONCAT(u1.first_name, ' ', u1.last_name) AS issuer_real_name,
               CONCAT(u2.first_name, ' ', u2.last_name) AS receiver_real_name
        FROM issuances i
        LEFT JOIN users u1 ON i.issued_by = u1.email
        LEFT JOIN users u2 ON i.receiver = u2.email
        WHERE i.issuance_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $issuance_id);
$stmt->execute();
$issuance_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$issuance_data) {
    die("Record not found for Issuance ID: " . htmlspecialchars($issuance_id));
}

$request_id = $issuance_data['request_id'];

// Get fallback Request details from requests table
$reqQuery = $conn->query("SELECT * FROM requests WHERE request_id = $request_id");
$reqData = $reqQuery->fetch_assoc();

/* STEP 2: Explode the single row's comma-separated strings into individual arrays */
$arrBarcodes = explode(', ', $issuance_data['barcode']);
$arrNames    = explode(', ', $issuance_data['item_name']);
$arrQtys     = explode(', ', $issuance_data['issued_qty']);
$totalItems  = count($arrNames);

// Global Variables Mapping (Constant across the entire slip)
$issuerDisplayName   = (!empty(trim($issuance_data['issuer_real_name']))) ? $issuance_data['issuer_real_name'] : $issuance_data['issued_by'];
$receiverDisplayName = (!empty(trim($issuance_data['receiver_real_name']))) ? $issuance_data['receiver_real_name'] : $issuance_data['receiver'];
$requestorName       = $issuance_data['requestor_name'];
$approvedBy          = $reqData['approved_by'] ?? 'MARLYN V. RIVERA, Ph.D.';
$department          = $issuance_data['department'];
$purpose             = $reqData['purpose'] ?? '';
$dateStr             = date("M d, Y", strtotime($issuance_data['issued_at']));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RIS_Voucher_#<?= $request_id ?></title>
    <style>
        /* Base RIS styling */
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background-color: #e2e8f0;
            margin: 0;
            padding: 20px;
        }

        .no-print-toolbar {
            background-color: #fff;
            border: 1px solid #cbd5e1;
            padding: 15px 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn-print { background: #16a34a; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 5px; cursor: pointer; }
        .btn-print:hover { background: #15803d; }
        .btn-close { background: #64748b; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 5px; cursor: pointer; margin-right: 10px;}

        /* Paper Sheet Canvas */
        .print-canvas {
            background: #fff;
            width: 800px;
            margin: 0 auto;
            padding: 20px 40px;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* AUTO PAGE BREAK LOGIC */
        .ris-copy {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        
        .copy-tag { font-size: 11px; font-weight: bold; margin-bottom: -15px; }
        
        .header-title { text-align: center; font-size: 13px; line-height: 1.3; margin-bottom: 15px; }
        .header-title h3 { margin: 15px 0 5px; font-size: 15px; text-decoration: underline; letter-spacing: 0.5px; font-weight: bold;}

        /* Tables matching RIS layout */
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: center; }
        .text-left { text-align: left !important; }
        
        .no-border-table th, .no-border-table td { border: none; padding: 2px 4px; font-size: 12px; font-weight: bold;}
        
        /* Specific borders to connect RIS header */
        .info-table { border: 1px solid #000; margin-bottom: -1px; }
        .info-table td { border: 1px solid #000; font-size: 12px;}
        
        .main-table th { background: #f8fafc; font-weight: bold; }
        .main-table tr td { padding: 8px 5px; } 

        .footer-table td { border: 1px solid #000; height: 22px; font-size: 11px;}
        .footer-table .sig-title { background: #f8fafc; font-weight: normal; }
        
        .cut-line { border: 1px dashed #cbd5e1; margin: 40px 0; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print-toolbar { display: none !important; }
            .print-canvas { box-shadow: none; width: 100%; padding: 0; }
            .main-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .cut-line { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print-toolbar">
        <div><strong>WVSU Format</strong> - Multiple Items Requisition & Issue Slip</div>
        <div>
            <button onclick="window.close();" class="btn-close">Cancel</button>
            <button onclick="window.print();" class="btn-print">🖨️ Print Form</button>
        </div>
    </div>

    <div class="print-canvas">
        <?php 
        $copies = ["Requestor's Copy", "Supply Office Copy"];
        foreach ($copies as $index => $copy_title): 
        ?>
        <div class="ris-copy">
            <div class="copy-tag"><?= $copy_title ?></div>
            
            <div class="header-title">
                WEST VISAYAS STATE UNIVERSITY<br>
                Himamaylan City Campus<br>
                Brgy. Caradio-an, Himamaylan City Negros Occidental<br>
                <h3>REQUISITION AND ISSUE SLIP</h3>
            </div>

            <table class="no-border-table">
                <tr>
                    <td class="text-left" style="width: 70%;">Entity Name: WEST VISAYAS STATE UNIVERSITY Himamaylan Campus</td>
                    <td class="text-left" style="width: 30%;">Fund Cluster: 101</td>
                </tr>
            </table>

            <table class="info-table">
                <tr>
                    <td class="text-left" style="width: 15%; border-right: none; border-bottom: none;">Division:</td>
                    <td style="width: 35%; border-left: none; border-right: none; border-bottom: none;"></td>
                    <td class="text-left" style="width: 25%; border-left: none; border-right: none; border-bottom: none;">Responsibility Center Code:</td>
                    <td style="width: 25%; border-left: none; border-bottom: none;"></td>
                </tr>
                <tr>
                    <td class="text-left" style="border-right: none; border-top: none;">Office:</td>
                    <td class="text-left" style="border-left: none; border-right: none; border-top: none;"><strong><?= htmlspecialchars($department) ?></strong></td>
                    <td class="text-left" style="border-left: none; border-right: none; border-top: none;">RIS No.:</td>
                    <td class="text-left" style="border-left: none; border-top: none;"><strong><?= str_pad($request_id, 4, '0', STR_PAD_LEFT); ?></strong></td>
                </tr>
            </table>

            <table class="main-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:12%;">Barcode</th>
                        <th rowspan="2" style="width:8%;">Unit</th>
                        <th rowspan="2" style="width:30%;">ARTICLE & DESCRIPTION</th>
                        <th colspan="3">Requisition</th>
                        <th colspan="2">Stock Available?</th>
                        <th colspan="2">Issue</th>
                    </tr>
                    <tr>
                        <th style="width:6%;">Qty.</th>
                        <th style="width:10%;">Unit Price</th>
                        <th style="width:10%;">Amount</th>
                        <th style="width:5%;">Yes</th>
                        <th style="width:5%;">No</th>
                        <th style="width:6%;">Qty.</th>
                        <th style="width:8%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Loop through our exploded comma arrays to make multiple printable visual rows
                    for ($i = 0; $i < $totalItems; $i++) {
                        $barcode  = isset($arrBarcodes[$i]) ? htmlspecialchars(trim($arrBarcodes[$i])) : '';
                        $itemName = isset($arrNames[$i]) ? htmlspecialchars(trim($arrNames[$i])) : '';
                        $issuedQty = isset($arrQtys[$i]) ? htmlspecialchars(trim($arrQtys[$i])) : '0';

                        if (empty($itemName)) continue;

                        echo "<tr>";
                        echo "<td><small>" . $barcode . "</small></td>";
                        echo "<td>pcs</td>"; 
                        echo "<td class='text-left'><strong>" . $itemName . "</strong></td>";
                        echo "<td>" . $issuedQty . "</td>"; 
                        echo "<td></td><td></td>"; 
                        echo "<td>✓</td><td></td>"; 
                        echo "<td><strong>" . $issuedQty . "</strong></td>"; 
                        echo "<td></td>"; 
                        echo "</tr>";
                    }

                    // Optional Structure Polish: Adds blank table rows if layout has less than 3 items
                    if ($totalItems < 3) {
                        for ($j = 0; $j < (3 - $totalItems); $j++) {
                            echo "<tr>
                                <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                            </tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>

            <table class="footer-table">
                <tr>
                    <td class="text-left sig-title" colspan="5">Purpose: <strong><?= htmlspecialchars($purpose) ?></strong></td>
                </tr>
                <tr class="sig-title">
                    <td style="width:12%;"></td>
                    <td style="width:22%;">Requested by:</td>
                    <td style="width:22%;">Approved by:</td>
                    <td style="width:22%;">Issued by:</td>
                    <td style="width:22%;">Received by:</td>
                </tr>
                <tr>
                    <td class="text-left sig-title">Signature:</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="text-left sig-title">Printed Name:</td>
                    <td><strong><?= htmlspecialchars($requestorName) ?></strong></td>
                    <td><strong><?= htmlspecialchars($approvedBy) ?></strong></td>
                    <td><strong><?= htmlspecialchars($issuerDisplayName) ?></strong></td>
                    <td><strong><?= htmlspecialchars($receiverDisplayName) ?></strong></td>
                </tr>
                <tr>
                    <td class="text-left sig-title">Designation:</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="text-left sig-title">Date:</td>
                    <td><?= $dateStr ?></td>
                    <td></td>
                    <td><?= $dateStr ?></td>
                    <td><?= $dateStr ?></td>
                </tr>
            </table>
            
            <?php if ($index === 0): ?>
                <hr class="cut-line">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        window.onload = function() {
            setTimeout(() => { window.print(); }, 500);
        }
    </script>
</body>
</html>