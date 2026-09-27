<?php
session_start();
require_once __DIR__ . '/db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* --------------------------------------------------
   PAGINATION + SEARCH
-------------------------------------------------- */
$limit  = 10; // rows per page
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$offset = ($page - 1) * $limit;

$whereParts = [];
$whereParts[] = "1"; // always true, so we can safely AND

if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $whereParts[] = "(
        name      LIKE '%$s%' OR
        barcode   LIKE '%$s%' OR
        category  LIKE '%$s%' OR
        location  LIKE '%$s%' OR
        department LIKE '%$s%'
    )";
}

$whereSQL = "WHERE " . implode(" AND ", $whereParts);

/* 1) COUNT ITEMS (with filters) */
$countSql = "SELECT COUNT(*) AS c FROM items $whereSQL";
$countRes = $conn->query($countSql);
if (!$countRes) {
    die("ERROR counting items: " . $conn->error);
}
$rowCount   = (int)$countRes->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($rowCount / $limit));

/* 2) FETCH ROWS FOR CURRENT PAGE (with filters) */
$dataSql = "
  SELECT 
    item_id,
    name,
    barcode,
    category,
    quantity,
    unit,
    condition_label,
    status_label,
    min_stock,
    location,
    department,
    image_path
  FROM items
  $whereSQL
  ORDER BY item_id DESC
  LIMIT $limit OFFSET $offset
";
$dataRes = $conn->query($dataSql);
if (!$dataRes) {
    die("SQL ERROR in generate_barcode.php: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Generated Barcodes</title>
  <link rel="stylesheet" href="admindasboard.css">

  <style>
    .container {
      background: #fff;
      padding: 25px;
      border-radius: 10px;
      max-width: 1200px;
      margin: auto;
      box-shadow: 0 0 8px rgba(0, 0, 0, .1);
    }

    /* Centers the heading text inside the container */
    h2 {
      margin: 0;
      color: #004085;
      font-size: 24px;
      text-align: center;
      flex-grow: 1; /* Allows the title to occupy the center space */
    }

    /* Layout structure to ensure the title stays centered while the button stays right */
    .content-box-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }

    /* Invisible spacer balancing out the right button to keep the header absolutely dead center */
    .header-spacer {
      width: 110px; 
    }

    .upper-right-btn {
      background: #007bff;
      color: white;
      padding: 10px 20px;
      border-radius: 5px;
      text-decoration: none;
      font-size: 14px;
      font-weight: bold;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      width: 190px; /* Explicit width matching the spacer for perfect center alignment */
      justify-content: center;
      transition: background 0.2s ease;
    }

    .upper-right-btn:hover {
      background: #0056b3;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    th,
    td {
      padding: 10px;
      text-align: center;
      border-bottom: 1px solid #ddd;
      font-size: 14px;
    }

    th {
      background: #007bff;
      color: white;
    }

    img {
      width: 50px;
      height: 50px;
      object-fit: cover;
    }

    .btn {
      background: #28a745;
      color: white;
      padding: 7px 10px;
      border-radius: 5px;
      text-decoration: none;
      font-size: 13px;
    }

    .btn:hover {
      background: #218838;
    }

    .pagination {
      margin-top: 10px;
      text-align: center;
    }

    .pagination a,
    .pagination span {
      margin: 0 4px;
      padding: 4px 8px;
      border-radius: 3px;
      text-decoration: none;
      font-size: 13px;
      color: #007bff;
      cursor: pointer;
    }

    .pagination .active {
      background: #007bff;
      color: #fff;
      cursor: default;
    }

    .pagination .disabled {
      color: #aaa;
      cursor: default;
    }

    .table-actions {
      margin: 10px 0;
      display: flex;
      gap: 10px;
      justify-content: flex-start;
      align-items: center;
    }

    .print-page-btn {
      background: #17a2b8;
    }

    .print-page-btn:hover {
      background: #138496;
    }
  </style>
</head>

<body>
  <div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="main-content">
      <div class="inventory-container">
        <header class="header">
          <h1>Generate Barcode</h1>

          <form method="get" class="search-bar">
            <input type="text"
                   name="q"
                   placeholder="Search items"
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><span>&#128269;</span></button>
          </form>
        </header>

        <div class="content-box">
          
          <div class="content-box-header">
            <div class="header-spacer"></div>
            
            <h2>📋 Generated Barcodes</h2>
            
            <button type="button" class="upper-right-btn" onclick="location.href='add_barcode.php'">
              🔍 Generate Barcode
            </button>
          </div>

          <p style="margin-bottom:10px;font-size:13px;color:#555; text-align: center;">
            Total items : <strong><?= $rowCount ?></strong>
            <?php if ($search !== ''): ?>
              (filtered by "<?= htmlspecialchars($search) ?>")
            <?php endif; ?>
          </p>

          <div class="barcode-table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Item Name</th>
                  <th>Image</th>
                  <th>Category</th>
                  <th>Qty</th>
                  <th>Unit</th>
                  <th>Condition</th>
                  <th>Status</th>
                  <th>Min Stock</th>
                  <th>Location</th>
                  <th>Department</th>
                  <th>Barcode</th>
                  <th>Action</th>
                </tr>
              </thead>

              <tbody>
                <?php if ($rowCount > 0 && $dataRes && $dataRes->num_rows > 0): ?>
                  <?php while ($row = $dataRes->fetch_assoc()): ?>
                    <tr>
                      <td><?= (int)$row['item_id'] ?></td>
                      <td><?= htmlspecialchars($row['name'] ?? '') ?></td>
                      <td>
                        <?php if (!empty($row['image_path'])): ?>
                          <img src="<?= htmlspecialchars($row['image_path']) ?>" alt="Item">
                        <?php else: ?>
                          <span style="color:#999;">No image</span>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($row['category'] ?? '') ?></td>
                      <td><?= (int)$row['quantity'] ?></td>
                      <td><?= htmlspecialchars($row['unit'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['condition_label'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['status_label'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['min_stock'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['location'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['department'] ?? 'N/A') ?></td>
                      <td><?= htmlspecialchars($row['barcode'] ?? '') ?></td>
                      <td>
                        <a class="btn" href="print_barcode.php?item_id=<?= (int)$row['item_id'] ?>" target="_blank">
                          🖨 Print
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="13">No items found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="pagination">
            <?php
            // Helper to keep search term in links
            $qParam = $search !== '' ? '&q=' . urlencode($search) : '';

            if ($page > 1): ?>
              <a href="?page=<?= $page - 1 . $qParam ?>">&lt;&lt; Previous</a>
            <?php else: ?>
              <span class="disabled">&lt;&lt; Previous</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
              <?php else: ?>
                <a href="?page=<?= $i . $qParam ?>"><?= $i ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
              <a href="?page=<?= $page + 1 . $qParam ?>">Next &gt;&gt;</a>
            <?php else: ?>
              <span class="disabled">Next &gt;&gt;</span>
            <?php endif; ?>
          </div>

          <div class="table-actions">
            <button type="button" class="btn print-page-btn" onclick="printCurrentPageBarcodes()">
              🖨 Print current page
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="chart.umd.min.js"></script>
  <script src="admindashboard.js" defer></script> 

  <script>
    // Print all barcodes currently shown in the table
    function printCurrentPageBarcodes() {
      const wrapper = document.querySelector('.barcode-table-wrapper');
      if (!wrapper) {
        window.print();
        return;
      }

      const printContents = wrapper.innerHTML;
      const w = window.open('', '_blank');

      w.document.write('<html><head><title>Print Barcodes</title>');
      w.document.write('<link rel="stylesheet" href="admindasboard.css">');
      w.document.write('</head><body>');
      w.document.write(printContents);
      w.document.write('</body></html>');

      w.document.close();
      w.focus();
      w.print();
      w.close();
    }
  </script>
</body>
</html>