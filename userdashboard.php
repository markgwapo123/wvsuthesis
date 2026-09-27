<?php
require_once 'auth.php';
allow_roles(['subadmin']); // Only subadmin can access
require_once 'db_connect.php';

/* ---------------------------------------------
   LOAD SETTINGS (theme)
--------------------------------------------- */
$settingsQuery = $conn->query("SELECT * FROM user_settings WHERE user_id = 1 LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0)
    ? $settingsQuery->fetch_assoc()
    : ['theme' => 'light'];

/* ---------------------------------------------
   1. DASHBOARD ANALYTICS
--------------------------------------------- */

// Total quantity of all items
$row = $conn->query("SELECT SUM(quantity) AS t FROM inventory")->fetch_assoc();
$total_items = (int)($row['t'] ?? 0);

// Low stock: quantity < min_stock (and min_stock > 0)
$row = $conn->query("
    SELECT COUNT(*) AS t 
    FROM inventory 
    WHERE quantity < min_stock AND min_stock > 0
")->fetch_assoc();
$low_stock = (int)($row['t'] ?? 0);

// Out of stock: quantity <= 0
$row = $conn->query("
    SELECT COUNT(*) AS t 
    FROM inventory 
    WHERE quantity <= 0
")->fetch_assoc();
$out_stock = (int)($row['t'] ?? 0);

// Damaged
$discarded_items = $conn->query("
    SELECT COUNT(*) AS cnt 
    FROM disposals
")->fetch_assoc()['cnt'] ?? 0;

// Expired
$row = $conn->query("
    SELECT COUNT(*) AS t
    FROM inventory
    WHERE condition_label = 'Expired'
")->fetch_assoc();
$expired_items = (int)($row['t'] ?? 0);

// Lost
$row = $conn->query("
    SELECT COUNT(*) AS t
    FROM inventory
    WHERE condition_label = 'Lost'
")->fetch_assoc();
$lost_items = (int)($row['t'] ?? 0);

/* ---------------------------------------------
   2. MOST ISSUED BY CATEGORY (BAR CHART)
   (uses item_name -> inventory.name)
--------------------------------------------- */
$issuedData = $conn->query("
    SELECT 
        i.category,
        COUNT(*) AS total_issued
    FROM issuances u
    LEFT JOIN inventory i 
        ON i.name = u.item_name
    WHERE i.category IS NOT NULL AND i.category <> ''
    GROUP BY i.category
    ORDER BY total_issued DESC
    LIMIT 6
");

$issued_labels = [];
$issued_values = [];

if ($issuedData && $issuedData->num_rows > 0) {
    while ($row = $issuedData->fetch_assoc()) {
        $issued_labels[] = $row['category'];
        $issued_values[] = (int)$row['total_issued'];
    }
}

$issued_labels_js = json_encode($issued_labels);
$issued_values_js = json_encode($issued_values);

/* ---------------------------------------------
   3. STOCK STATUS PIE CHART
--------------------------------------------- */

// Stock OK = quantity >= min_stock (and min_stock > 0)
$row = $conn->query("
    SELECT COUNT(*) AS t
    FROM inventory
    WHERE quantity >= min_stock AND min_stock > 0
")->fetch_assoc();
$ok_count = (int)($row['t'] ?? 0);

$low_count = $low_stock;
$out_count = $out_stock;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Subadmin Dashboard</title>
  <link rel="stylesheet" href="admindasboard.css">
  <!-- Use your local Chart.js bundle -->
  <script src="chart.umd.min.js"></script>

  <style>
    /* ===== DASHBOARD CARDS ===== */
    .dashboard-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin: 20px 0;
    }

    .card-box {
      padding: 18px 16px;
      border-radius: 12px;
      color: white;
      text-align: center;
      box-shadow: 0 4px 10px rgba(0,0,0,0.18);
    }

    .card-box p {
      margin: 0;
      font-size: 15px;
      font-weight: 600;
    }

    .card-box h3 {
      font-size: 32px;
      margin: 8px 0 0 0;
      font-weight: 800;
    }

    .card-blue    { background:#007bff; }
    .card-yellow  { background:#ffc107; color:#222; }
    .card-red     { background:#dc3545; }
    .card-orange  { background:#fd7e14; }
    .card-purple  { background:#6f42c1; }
    .card-grey    { background:#6c757d; }

    /* ===== CHARTS ===== */
    .chart-wrap {
      display: flex;
      flex-wrap: wrap;
      gap: 30px;
      justify-content: center;
      margin-top: 30px;
    }

    .chart-container {
      width: 100%;
      max-width: 825px;
      background:#fff;
      border-radius: 14px;
      padding: 18px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .chart-container h3 {
      margin-bottom: 10px;
      font-size: 18px;
      font-weight: 700;
      text-align:center;
      color:#222;
    }
  </style>
</head>
<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">

<div class="dashboard">
  <?php include 'sidebar.php'; ?>

  <main class="main-content">
    <header class="header">
      <h1>Subadmin Dashboard</h1>
    </header>

    <!-- TOP ANALYTIC CARDS -->
    <section class="dashboard-cards">

      <div class="card-box card-blue">
        <p>📦 Total Items in Stock</p>
        <h3><?= $total_items ?></h3>
      </div>

      <div class="card-box card-yellow">
        <p>⚠️ Low Stock Items</p>
        <h3><?= $low_stock ?></h3>
      </div>

      <div class="card-box card-red">
        <p>❌ Out of Stock</p>
        <h3><?= $out_stock ?></h3>
      </div>

      <div class="card-box card-orange">
        <p>🔧 Disposed Items</p>
        <h3><?= number_format($discarded_items) ?></h3>
      </div>

      <div class="card-box card-purple">
        <p>🗑️ Expired Items</p>
        <h3><?= $expired_items ?></h3>
      </div>

      <div class="card-box card-grey">
        <p>🛑 Lost Items</p>
        <h3><?= $lost_items ?></h3>
      </div>

    </section>

    <!-- CHARTS SECTION -->
    <section class="chart-wrap">

      <div class="chart-container">
        <h3>📈 Most Issued Categories</h3>
        <canvas id="barChart"></canvas>
      </div>

      <div class="chart-container">
        <h3>📊 Stock Level Overview</h3>
        <canvas id="pieChart"></canvas>
      </div>

    </section>

  </main>
</div>
<script src="chart.umd.min.js"></script>
  <script src="admindashboard.js" defer></script>
<script>
// BAR CHART: most issued categories
// BAR CHART – Issued Items
new Chart(document.getElementById('barChart'), { 
  type: 'bar',
  data: {
    labels: <?= $issued_labels_js ?>,
    datasets: [{
      label: 'Issued Items',
      data: <?= $issued_values_js ?>,
      backgroundColor: '#007bff',
      borderRadius: 6
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        display: true,
        labels: { color: '#000' }  // <-- FIX LEGEND TEXT COLOR
      },
      title: {
        display: true,
        text: 'Most Issued Items by Category',
        color: '#000',            // <-- FIX TITLE COLOR
        font: { size: 16, weight: "bold" }
      }
    },
    scales: {
      x: {
        ticks: { color: '#000' }, // <-- FIX X LABELS
        grid: { color: '#ccc' }
      },
      y: {
        beginAtZero: true,
        ticks: { color: '#000' }, // <-- FIX Y LABELS
        grid: { color: '#ddd' }
      }
    }
  }
});


// PIE CHART – Stock Overview
new Chart(document.getElementById('pieChart'), {
  type: 'pie',
  data: {
    labels: ['Stock OK', 'Low Stock', 'Out of Stock'],
    datasets: [{
      data: [<?= $ok_count ?>, <?= $low_count ?>, <?= $out_count ?>],
      backgroundColor: ['#28a745','#ffc107','#dc3545']
    }]
  },
});
</script>
</body>
</html>
