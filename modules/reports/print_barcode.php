<?php 
require_once __DIR__ . '/db_connect.php';

$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if ($item_id <= 0) {
  die("<h3 style='text-align:center;color:red;'>Invalid item.</h3>");
}

// Get item info
$item_sql = "SELECT name, barcode, quantity FROM items WHERE item_id = $item_id";
$item = $conn->query($item_sql)->fetch_assoc();

if (!$item) {
  die("<h3 style='text-align:center;color:red;'>Item not found in database.</h3>");
}

// Fetch all unique barcodes
$codes_rs = $conn->query("SELECT barcode_code FROM barcodes WHERE item_id = $item_id ORDER BY barcode_code ASC");
$codes = [];
while ($r = $codes_rs->fetch_assoc()) {
  $codes[] = $r['barcode_code'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Print Barcodes — <?= htmlspecialchars($item['name']) ?></title>
  <style>
    @media print {
      .no-print { display: none !important; }
      body { margin: 0; background: #fff; }
    }

    body {
      font-family: Arial, sans-serif;
      background: #ffffff;
      margin: 20px;
      color: #333;
    }

    .header {
      text-align: center;
      margin-bottom: 10px;
    }

    .header h1 {
      margin: 0;
      font-size: 22px;
      color: #004085;
    }

    .meta {
      color: #555;
      font-size: 14px;
      margin-top: 4px;
    }

    .controls {
      text-align: center;
      margin: 15px 0;
    }

    .btn {
      display: inline-block;
      background: #007bff;
      color: #fff;
      border: none;
      border-radius: 5px;
      padding: 8px 15px;
      cursor: pointer;
      text-decoration: none;
      font-size: 14px;
    }

    .btn:hover { background: #0056b3; }

    .grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
    }

    .label {
      border: 1px dashed #ccc;
      border-radius: 8px;
      padding: 8px;
      text-align: center;
      page-break-inside: avoid;
    }

    .item-name {
      font-size: 13px;
      color: #222;
      font-weight: bold;
      margin-bottom: 6px;
    }

    svg.barcode {
      width: 180px;
      height: 60px;
      display: block;
      margin: 0 auto;
    }

    .code-text {
      font-size: 12px;
      color: #555;
      margin-top: 5px;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1><?= htmlspecialchars($item['name']) ?> — Barcodes</h1>
    <div class="meta">
      Base: <?= htmlspecialchars($item['barcode']) ?> |
      Quantity: <?= (int)$item['quantity'] ?> |
      Labels: <?= count($codes) ?>
    </div>
  </div>

  <div class="controls no-print">
    <a href="generate_barcode.php" class="btn">← Back to Generate</a>
    <button onclick="window.print()" class="btn">🖨 Print</button>
  </div>

  <?php if (empty($codes)): ?>
    <p style="text-align:center;color:red;">No barcodes found for this item.</p>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($codes as $i => $bc): ?>
        <div class="label">
          <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
          <svg class="barcode" id="bc<?= $i ?>"></svg>
          <div class="code-text"><?= htmlspecialchars($bc) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Local JsBarcode (offline) -->
  <script src="assets/js/JsBarcode.all.min.js"></script>

  <script>
    window.addEventListener("load", function() {
      if (typeof JsBarcode === "function") {
        <?php foreach ($codes as $i => $bc): ?>
          JsBarcode("#bc<?= $i ?>", "<?= $bc ?>", {
            format: "CODE128",
            lineColor: "#000",
            width: 2,
            height: 55,
            displayValue: false,
            margin: 5
          });
        <?php endforeach; ?>
      } else {
        console.error("JsBarcode not loaded or not found.");
        alert("Barcode script failed to load. Check js/JsBarcode.all.min.js path.");
      }
    });
  </script>
</body>
</html>
