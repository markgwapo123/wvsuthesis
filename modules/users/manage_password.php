<?php
require_once __DIR__ . '/../../db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Password</title>
  <link rel="stylesheet" href="admindasboard.css">
  <style>
    input {
      padding: 6px;
      margin: 4px 0;
      width: 100%
    }

    button {
      padding: 6px 10px;
      border: 1px solid #888;
      background: #f7f7f7;
      cursor: pointer
    }

    form {
      width: 300px;
      margin: auto
    }
  </style>
</head>

<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">

  <body>
    <div class="dashboard">
      <!-- Sidebar -->
      <?php include __DIR__ . '/../../sidebar.php'; ?>

      <!-- Main Content -->
      <main class="main-content">
        <div class="inventory-container">
          <!-- Header -->
          <header class="header">
            <h1>Manage Password </h1>
                <div class="search-bar">
                  <input type="text" placeholder="Search items">
                  <button><span>&#128269;</span></button>
                </div>
          </header>

          <!-- Content Box -->
          <div class="content-box">
            <!-- Filter Section -->
            <div class="filter-bar">
              <button class="add-btn" onclick="window.location.href='add_item.php'">➕ Add Item</button>
            </div>

            <!-- Table placeholder -->
            <div class="table-placeholder">
              <!-- Items will be displayed here -->
              <form method="POST">
                <label>User ID:</label>
                <input type="number" name="user_id" required>
                <label>New Password:</label>
                <input type="password" name="new_password" required>
                <button type="submit" name="update">🔄 Update Password</button>
              </form>

              <?php
              if (isset($_POST['update'])) {
                $uid = intval($_POST['user_id']);
                $pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password='$pass' WHERE id=$uid");
                echo "<p>Password updated successfully.</p>";
              }
              ?>
            </div>

            <!-- Pagination -->
            <div class="pagination">
              <span>&lt;&lt; Previous</span>
              <span class="page-number">1</span>
              <span class="page-number">2</span>
              <span class="page-number">3</span>
              <span>Next &gt;&gt;</span>
            </div>

            <!-- Export button -->
            <div style="margin-bottom: 15px;">
              <button id="downloadBtn">⬇ Download</button>
              <div id="downloadOptions" class="hidden">
                <button onclick="exportTableToCSV('table_data.csv')">Download as CSV</button>
                <button onclick="exportTableToPDF()">Download as PDF</button>
              </div>
            </div>
          </div>

        </div>
      </main>
    </div>

    <!-- Scripts -->
    <script src="chart.umd.min.js"></script>
    <script src="admindashboard.js" defer></script>
  </body>

</html>