<?php
require_once __DIR__ . '/../../auth.php';

// inventory edit only for admin
if (($_GET['type'] ?? '') === 'inventory') {
    allow_roles(['admin']);
}
require_once __DIR__ . '/../../db_connect.php';

if (!isset($_SESSION['role'])) {
    header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;

// ------------------------------------
// BASIC METADATA
// ------------------------------------
$type = $_GET['type'] ?? '';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$entities = [
    'inventory' => [
        'table'    => 'inventory',
        'pk'       => 'inventory_id',
        'title'    => 'Edit Inventory',
        'redirect' => 'manage_inventory.php'
    ],
    'supplier'  => [
        'table'    => 'suppliers',
        'pk'       => 'supplier_id',
        'title'    => 'Edit Supplier',
        'redirect' => 'manage_suppliers.php'
    ],
    'user'      => [
        'table'    => 'users',
        'pk'       => 'id',
        'title'    => 'Edit User',
        'redirect' => 'manage_users.php'
    ],
    'order'     => [
        'table'    => 'orders',
        'pk'       => 'order_id',
        'title'    => 'Edit Order',
        'redirect' => 'order_management.php'
    ],
];

// inventory fields that must be shown but NOT editable
$inventoryReadOnly = [
    'item_id','code','barcode','expiration_date',
    'created_at','updated_at','date_stored','status_label'
];

$isGroup     = false;
$groupName   = '';
$groupDept   = '';
$groupDeptDB = '';
$rows        = [];
$row         = [];
$message     = "";
$fatal       = "";

// ------------------------------------
// VALIDATE TYPE
// ------------------------------------
if (!isset($entities[$type])) {
    $fatal = "Invalid or unknown record type.";
} else {
    $meta  = $entities[$type];
    $table = $meta['table'];
    $pk    = $meta['pk'];

    /* ------------------------------------------
       CHECK GROUP MODE (inventory)
    ------------------------------------------- */
    if ($type === 'inventory' && isset($_GET['group']) && $_GET['group'] == '1') {

        if (!isset($_GET['name']) || !isset($_GET['department'])) {
            $fatal = "Missing group parameters (name / department).";
        } else {
            $groupName = $_GET['name'];
            $groupDept = $_GET['department'];
            // in DB empty department is stored as '' / NULL
            $groupDeptDB = ($groupDept === "No Department" ? "" : $groupDept);

            $isGroup = true;

            $stmt = $conn->prepare("
                SELECT * FROM inventory 
                WHERE name = ? 
                AND COALESCE(department,'') = ?
            ");
            $stmt->bind_param("ss", $groupName, $groupDeptDB);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($r = $result->fetch_assoc()) {
                $rows[] = $r;
            }
            $stmt->close();

            if (empty($rows)) {
                $fatal = "Record not found for this group.";
            } else {
                // use first row as representative for the form
                $row = $rows[0];

                // compute total quantity for display
                $totalQty = 0;
                foreach ($rows as $r) {
                    $totalQty += (int)$r['quantity'];
                }
                $row['quantity'] = $totalQty;
            }
        }

    }
    /* ------------------------------------------
       SINGLE RECORD MODE
    ------------------------------------------- */
    else {

        if ($id <= 0) {
            $fatal = "Invalid or unknown record type or ID.";
        } else {
            $res = $conn->query("SELECT * FROM `$table` WHERE `$pk` = $id");
            if (!$res || $res->num_rows === 0) {
                $fatal = "Record not found.";
            } else {
                $row = $res->fetch_assoc();
            }
        }
    }
}

/* ------------------------------------------
   HANDLE POST UPDATE
------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$fatal && !empty($row)) {

    $updates = [];

    foreach ($_POST as $field => $value) {

        // skip PK always
        if ($field === $pk) continue;

        // skip password here (users)
        if ($table === 'users' && $field === 'password') continue;

        // inventory: status_label will be controlled automatically (see block below)
        if ($table === 'inventory' && $field === 'status_label') continue;

        // unknown column, skip
        if (!array_key_exists($field, $row)) continue;

        // inventory read-only fields are not editable
        if ($table === 'inventory' && in_array($field, $inventoryReadOnly, true)) {
            continue;
        }

        $value = trim((string)$value);

        // empty string = NULL
        if ($value === '') {
            $updates[] = "`$field` = NULL";
        } else {
            $updates[] = "`$field` = '" . $conn->real_escape_string($value) . "'";
        }
    }

    // Auto-set status_label for inventory based on condition_label
    if ($table === 'inventory') {
        // use the new POST value if present, otherwise the existing DB value
        $cond = $_POST['condition_label'] ?? ($row['condition_label'] ?? '');

        if (in_array($cond, ['Damaged', 'Lost', 'Expired'], true)) {
            // these conditions always become Disposed
            $updates[] = "`status_label` = 'Disposed'";
        } else {
            // Optional: otherwise keep / update whatever status was posted
            if (isset($_POST['status_label']) && $_POST['status_label'] !== '') {
                $status = $conn->real_escape_string($_POST['status_label']);
                $updates[] = "`status_label` = '{$status}'";
            }
        }
    }

    if (!empty($updates)) {

        if ($isGroup) {
            // update ALL records belonging to this group
            $sql = "
                UPDATE inventory 
                SET " . implode(", ", $updates) . "
                WHERE name = ? 
                AND COALESCE(department,'') = ?
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $groupName, $groupDeptDB);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                $message = "Group updated successfully.";

                log_activity(
                    $user_id,
                    "Updated inventory GROUP '{$groupName}' / '{$groupDept}'",
                    "Inventory Group Update",
                    "Success",
                    $conn
                );
            } else {
                $message = "Update failed: " . $conn->error;
            }

        } else {
            // normal single-record update
            $sql = "UPDATE `$table` 
                    SET " . implode(", ", $updates) . " 
                    WHERE `$pk` = $id";

            if ($conn->query($sql)) {
                $message = "Record updated successfully.";

                log_activity(
                    $user_id,
                    "Updated $type ID $id",
                    "Record Update",
                    "Success",
                    $conn
                );
            } else {
                $message = "Update failed: " . $conn->error;
            }
        }

        // re-fetch latest version for display after update
        if ($isGroup) {
            $rows = [];
            $stmt = $conn->prepare("
                SELECT * FROM inventory 
                WHERE name = ? AND COALESCE(department,'') = ?
            ");
            $stmt->bind_param("ss", $groupName, $groupDeptDB);
            $stmt->execute();
            $res2 = $stmt->get_result();
            while ($r = $res2->fetch_assoc()) {
                $rows[] = $r;
            }
            $stmt->close();

            if (!empty($rows)) {
                $row = $rows[0];
                $totalQty = 0;
                foreach ($rows as $r) {
                    $totalQty += (int)$r['quantity'];
                }
                $row['quantity'] = $totalQty;
            }

        } else {
            $res2 = $conn->query("SELECT * FROM `$table` WHERE `$pk` = $id");
            if ($res2 && $res2->num_rows > 0) {
                $row = $res2->fetch_assoc();
            }
        }

    } else {
        $message = "No changes were made.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= isset($meta) ? htmlspecialchars($meta['title']) : 'Edit Record' ?></title>
<link rel="stylesheet" href="admindasboard.css">
<style>
body {
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #143ab7ff, #143ab7ff);
  color: #222;
  margin: 0;
  padding: 0;
}
.form-box {
  background: #fff;
  border-radius: 15px;
  padding: 25px 30px;
  margin: 50px auto;
  box-shadow: 0 8px 20px rgba(0,0,0,0.15);
  max-width: 700px;
}
.form-box h2 {
  margin: 0 0 10px 0;
  font-size: 24px;
  color: #143ab7ff;
}
.form-box p.hint {
  font-size: 13px;
  color: #555;
  background: #f8f9fa;
  border-left: 3px solid #143ab7ff;
  padding: 8px 10px;
  border-radius: 4px;
}
label {
  display:block;
  font-weight:600;
  color:#333;
  margin-top:15px;
}
input, textarea {
  width:100%;
  padding:10px;
  border:1px solid #ccc;
  border-radius:8px;
  font-size:14px;
  margin-top:5px;
  box-sizing:border-box;
}
textarea {
  min-height:80px;
  resize: vertical;
}
.save-btn {
  display:inline-block;
  margin-top:20px;
  background:#28a745;
  color:#fff;
  border:none;
  padding:10px 20px;
  font-size:14px;
  border-radius:8px;
  cursor:pointer;
}
.save-btn:hover {
  background:#218838;
}
a.back-btn {
  display:inline-block;
  background:#6c757d;
  color:#fff;
  padding:10px 20px;
  border-radius:8px;
  text-decoration:none;
  margin-top:15px;
  margin-left:10px;
}
a.back-btn:hover { background:#5a6268; }
.alert, .success {
  padding:10px;
  border-radius:6px;
  margin-bottom:15px;
}
.alert { background:#f8d7da; color:#721c24; }
.success { background:#d4edda; color:#155724; }
.readonly {
  background:#e9ecef;
}
</style>
</head>
<body>
<div class="dashboard">
  <?php include __DIR__ . '/../../sidebar.php'; ?>
  <main class="main-content">
    <div class="form-box">
      <h2>
        <?= isset($meta) ? htmlspecialchars($meta['title']) : 'Edit Record' ?>
        <?php if ($isGroup): ?>
          <small style="font-size:12px;color:#777;">
            &nbsp;(Group: <?= htmlspecialchars($groupName) ?> / <?= htmlspecialchars($groupDept) ?>)
          </small>
        <?php endif; ?>
      </h2>

      <?php if ($fatal): ?>
        <div class="alert">
          <?= htmlspecialchars($fatal) ?>
        </div>
      <?php endif; ?>

      <?php if ($isGroup && !$fatal): ?>
        <p class="hint">
          You are editing a <strong>group</strong> of inventory records.
          Changes will apply to <strong>all items</strong> with this Item Name and Department.
          Read-only fields (IDs, codes, dates) cannot be changed here.
        </p>
      <?php endif; ?>

      <?php if ($message && !$fatal): ?>
        <div class="<?= (stripos($message, 'success') !== false) ? 'success' : 'alert' ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <?php if (!$fatal && !empty($row)): ?>
      <form method="POST">
        <?php foreach ($row as $field => $value): ?>
          <?php
            if ($field === ($entities[$type]['pk'] ?? '')) continue;
            if ($table === 'users' && $field === 'password') continue;

            $isReadOnlyField = ($table === 'inventory' && in_array($field, $inventoryReadOnly, true));
          ?>
          <label><?= htmlspecialchars(ucwords(str_replace("_", " ", $field))) ?></label>

          <?php if (strlen((string)$value) > 120): ?>
            <textarea
              name="<?= htmlspecialchars($field) ?>"
              <?= $isReadOnlyField ? 'readonly class="readonly"' : '' ?>
            ><?= htmlspecialchars($value ?? '') ?></textarea>
          <?php else: ?>
            <input
              type="text"
              name="<?= htmlspecialchars($field) ?>"
              value="<?= htmlspecialchars($value ?? '') ?>"
              <?= $isReadOnlyField ? 'readonly class="readonly"' : '' ?>
            >
          <?php endif; ?>
        <?php endforeach; ?>

        <button type="submit" class="save-btn">💾 Save Changes</button>
        <a href="javascript:history.back()" class="back-btn">⬅ Back</a>
      </form>
      <?php elseif (!$fatal): ?>
        <div class="alert">No data loaded for this record.</div>
        <a href="javascript:history.back()" class="back-btn">⬅ Back</a>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="chart.umd.min.js"></script>
<script src="admindashboard.js" defer></script>
</body>
</html>

