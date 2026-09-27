<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['role'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php");
    exit();
}

// Handle mark-as-read when clicked
if (isset($_GET['mark']) && is_numeric($_GET['mark'])) {
    $id = intval($_GET['mark']);
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $id");
    // Redirect to its target
    $link = $conn->query("SELECT link FROM notifications WHERE id = $id")->fetch_assoc()['link'];
    header("Location: $link");
    exit();
}

// --- Fetch all notifications ---
$result = $conn->query("SELECT * FROM notifications ORDER BY created_at ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
        }

        .content-box {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            max-width: 1500px;
            margin: 20px auto;
            box-shadow: 0 0 8px rgba(0, 0, 0, .1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
            color: #fff;
        }

        h1 {
            margin: 0 0 20px;
            color: #f8f9faff;
        }

        .notification {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification:hover {
            background: #eef5ff;
        }

        .unread {
            background: #e3f2fd;
        }

        .notif-msg {
            flex: 1;
        }

        .notif-time {
            font-size: 12px;
            color: #666;
        }

        .tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            color: #fff;
            margin-right: 8px;
        }

        .low_stock {
            background: #f6c23e;
        }

        .out_of_stock {
            background: #e74a3b;
        }

        .expiring {
            background: #36b9cc;
        }

        .expired {
            background: #b52a36;
        }

        .order {
            background: #1cc88a;
        }
    </style>
</head>

<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">
    <div class="dashboard">
        <?php include __DIR__ . '/../../sidebar.php'; ?>

        <main class="main-content">
            <div class="inventory-container">
                <header class="header">
                    <h1>Notifications</h1>
                    <div>
                        <input type="text" id="search-input" placeholder="Search notifications..." />
                    </div>
                </header>

                <div class="content-box" id="notification-table">
                    <!-- Notifications will be loaded here -->
                    <div id="loading-message">Loading...</div>
                </div>
            </div>
        </main>
    </div>
    <script>
        const searchInput = document.getElementById('search-input');
        const notificationTable = document.getElementById('notification-table');

        // Function to load notifications with search parameters
        const loadNotifications = (searchQuery = '', page = 1) => {
            const formData = new FormData();
            formData.append('live', searchQuery);
            formData.append('page', page); // Send the page number

            // Fetch search results via AJAX
            fetch('search_notification_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                notificationTable.innerHTML = data;
            })
            .catch(error => {
                console.error('Error:', error);
            });
        };

        // Event listener for live search
        searchInput.addEventListener('input', (e) => {
            loadNotifications(e.target.value);
        });

        // Event listener for pagination buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('page-link')) {
                const page = e.target.getAttribute('data-page');
                loadNotifications(searchInput.value, page); // Load notifications for selected page
            }
        });

        // Event listener for deleting a notification
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('delete-notif')) {
                const notifId = e.target.getAttribute('data-id');
                
                // Confirm the delete action
                if (confirm('Are you sure you want to delete this notification?')) {
                    // Send AJAX request to delete the notification
                    const formData = new FormData();
                    formData.append('delete_id', notifId); // Add the delete ID
                    
                    fetch('search_notification_ajax.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Remove the row from the table
                            const row = e.target.closest('tr');
                            row.remove();
                            alert('Notification deleted successfully');
                        } else {
                            alert('Error deleting notification');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred');
                    });
                }
            }
        });

        // Initial load of notifications
        loadNotifications();
    </script>
    
    <script src="chart.umd.min.js"></script>
    <script src="admindashboard.js" defer></script>
</body>

</html>

