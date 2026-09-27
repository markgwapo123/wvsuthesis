<?php
require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
allow_roles(['admin']);

// Theme Settings
$currentUserId = $_SESSION['user_id'] ?? 0;
$settingsQuery = $conn->query("SELECT theme FROM user_settings WHERE user_id = " . (int)$currentUserId . " LIMIT 1");
$settings = ($settingsQuery && $settingsQuery->num_rows > 0) ? $settingsQuery->fetch_assoc() : ['theme' => 'light'];

// 1. Updated Role List to include inst_admin as requested
$ALLOWED_ROLES = ['admin', 'subadmin', 'inst_admin', 'DAA', 'end_user'];

$message = "";

// HANDLE ROLE UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newRole = $_POST['role'] ?? '';

    if ($targetUserId > 0 && in_array($newRole, $ALLOWED_ROLES, true)) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $newRole, $targetUserId);
        if ($stmt->execute()) {
            $message = "success|Role updated successfully for User ID #$targetUserId.";
        } else {
            $message = "error|Database error occurred while updating role.";
        }
        $stmt->close();
    }
}

// 2. FIXED FILTER LOGIC
$search      = trim($_GET['search'] ?? '');
$roleFilter  = trim($_GET['role_filter'] ?? '');
$createdFrom = trim($_GET['created_from'] ?? '');
$page        = max(1, intval($_GET['page'] ?? 1));
$limit       = 10;
$offset      = ($page - 1) * $limit;

$where = [];
$params = [];
$types = "";

if ($search !== '') {
    $like = "%$search%";
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    array_push($params, $like, $like, $like);
    $types .= "sss";
}

if ($roleFilter !== '' && in_array($roleFilter, $ALLOWED_ROLES)) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
    $types .= "s";
}

if ($createdFrom !== '') {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $createdFrom;
    $types .= "s";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count Total
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users $whereSQL");
if ($where) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch Data
$listSql = "SELECT id, first_name, middle_name, last_name, role, email, created_at FROM users $whereSQL ORDER BY id DESC LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
$bindTypes = $types . "ii";
$bindParams = array_merge($params, [$limit, $offset]);
$listStmt->bind_param($bindTypes, ...$bindParams);
$listStmt->execute();
$usersRes = $listStmt->get_result();

// Helper Mapping function to display labels correctly
function getRoleLabel($roleKey) {
    switch ($roleKey) {
        case 'admin':       return 'Supply Admin';
        case 'subadmin':    return 'Sub Admin';
        case 'inst_admin':  return 'Admin';
        case 'end_user':    return 'End User';
        case 'DAA':         return 'DAA';
        default:            return htmlspecialchars(ucwords(str_replace('_', ' ', $roleKey)));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Permissions Management</title>
    <link rel="stylesheet" href="admindasboard.css">
    <style>
        :root {
            --primary: #2563eb;
            --bg-body: #11549b;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --border: #e2e8f0;
        }

        .main-content { background: var(--bg-body); padding: 30px; font-family: 'Inter', sans-serif; }
        
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 25px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filter-section {
            background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px;
            display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; border: 1px solid var(--border);
        }

        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }
        
        .form-control {
            padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px;
            font-size: 14px; min-width: 180px; transition: 0.2s;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        .btn-primary { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background: #1d4ed8; }

        .table-container { background: white; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; font-size: 13px; text-transform: uppercase; padding: 15px; font-weight: 700; border-bottom: 2px solid var(--border); }
        td { padding: 15px; border-bottom: 1px solid var(--border); vertical-align: middle; }

        .role-badge {
            padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;
            background: #f1f2f9; color: #475569; display: inline-block;
        }
        .role-daa { background: #dcfce7; color: #166534; }
        .role-admin { background: #dbeafe; color: #1e40af; }
        .role-inst { background: #f3e8ff; color: #6b21a8; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .pagination { margin-top: 20px; display: flex; justify-content: center; gap: 8px; }
        .page-link { padding: 8px 16px; border: 1px solid var(--border); border-radius: 6px; text-decoration: none; color: #475569; background: white; }
        .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
    </style>
</head>
<body class="<?= $settings['theme'] == 'dark' ? 'dark-mode' : '' ?>">

<div class="dashboard">
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 style="margin:0; font-size: 24px; color: var(--text-dark);">Assign Roles & Permissions</h1>
            <a href="assign_roles_permissions.php" style="color: var(--primary); font-size: 14px; text-decoration: none; font-weight: 500;">Refresh List</a>
        </div>

        <?php if ($message): 
            list($msgType, $msgText) = explode('|', $message); ?>
            <div class="alert alert-<?= $msgType ?>"><?= $msgText ?></div>
        <?php endif; ?>

        <form method="get" class="filter-section">
            <div class="filter-group">
                <label>Keyword Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name or Email..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-group">
                <label>System Role</label>
                <select name="role_filter" class="form-control">
                    <option value="">All User Roles</option>
                    <?php foreach ($ALLOWED_ROLES as $r): ?>
                        <option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>>
                            <?= getRoleLabel($r) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Joined After</label>
                <input type="date" name="created_from" class="form-control" value="<?= htmlspecialchars($createdFrom) ?>">
            </div>

            <button type="submit" class="btn-primary">Apply Filters</button>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>User Identity</th>
                        <th>Current Status</th>
                        <th>Role Assignment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($usersRes->num_rows === 0): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 50px; color: #64748b;">No matching user records found.</td></tr>
                    <?php else: while ($u = $usersRes->fetch_assoc()): 
                        $badgeClass = ($u['role'] === 'DAA') ? 'role-daa' : (($u['role'] === 'admin') ? 'role-admin' : (($u['role'] === 'inst_admin') ? 'role-inst' : ''));
                        $name = trim($u['first_name'] . ' ' . $u['last_name']);
                    ?>
                    <tr>
                        <td style="color: #64748b; font-weight: 600;">#<?= $u['id'] ?></td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($name ?: 'Unregistered') ?></div>
                            <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($u['email']) ?></div>
                        </td>
                        <td><span class="role-badge <?= $badgeClass ?>"><?= htmlspecialchars(getRoleLabel($u['role'])) ?></span></td>
                        <td>
                            <form method="post" style="display: flex; gap: 8px;">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <select name="role" class="form-control" style="min-width: 150px; padding: 6px 10px;">
                                    <?php foreach ($ALLOWED_ROLES as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $u['role'] === $opt ? 'selected' : '' ?>>
                                            <?= getRoleLabel($opt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_role" class="btn-primary" style="padding: 6px 12px;">Save</button>
                            </form>
                        </td>
                        <td>
                            <button class="btn-primary" style="background: #64748b;" onclick="location.href='edit_record.php?type=user&id=<?= $u['id'] ?>'">Edit Profile</button>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <?php
            $params_get = $_GET;
            for ($i = 1; $i <= $totalPages; $i++):
                $params_get['page'] = $i;
                $active = ($i == $page) ? 'active' : '';
                echo "<a class='page-link $active' href='?" . http_build_query($params_get) . "'>$i</a>";
            endfor;
            ?>
        </div>
    </main>
</div>
<script src="admindashboard.js" defer></script>
</body>
</html>