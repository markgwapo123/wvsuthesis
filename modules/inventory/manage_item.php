<?php
require_once __DIR__ . '/../../auth.php';
allow_roles(['admin', 'sub_admin']);
require_once __DIR__ . '/../../db_connect.php';

$id = intval($_GET['item_id'] ?? 0);
$item = null;
if ($id > 0) {
  // if editing existing item, only admin allowed
  if (!is_role('admin')) {
    http_response_code(403);
    exit('⛔ Only admin can edit existing items.');
  }
  $stmt = $conn->prepare("SELECT * FROM items WHERE item_id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $item = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>Manage Item</title>
  <link rel="stylesheet" href="admindasboard.css">
</head>

<body class="<?= $settings['theme']=='dark' ? 'dark-mode' : '' ?>">
  <h1>Manage Item</h1>
  <form id="editForm" method="post" action="items_api.php" enctype="multipart/form-data">
    <input type="hidden" name="action" value="<?php echo $item ? 'update' : 'create'; ?>">
    <?php if ($item) {
      echo '<input type="hidden" name="item_id" value="' . $item['item_id'] . '">';
    } ?>
    <input name="code" placeholder="Code" value="<?php echo htmlspecialchars($item['code'] ?? ''); ?>">
    <input name="barcode" placeholder="Barcode" value="<?php echo htmlspecialchars($item['barcode'] ?? ''); ?>">
    <input name="name" placeholder="Name" required value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
    <textarea name="description" placeholder="Description"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
    <input name="category" placeholder="Category" value="<?php echo htmlspecialchars($item['category'] ?? ''); ?>">
    <input name="quantity" type="number" min="0" value="<?php echo htmlspecialchars($item['quantity'] ?? 0); ?>">
    <input name="unit" placeholder="Unit" value="<?php echo htmlspecialchars($item['unit'] ?? ''); ?>">
    <select name="condition_label">
      <?php foreach (['New', 'Used', 'Damaged'] as $v) {
        $sel = (($item['condition_label'] ?? 'New') == $v) ? 'selected' : '';
        echo "<option $sel>$v</option>";
      } ?>
    </select>
    <select name="status_label">
      <?php foreach (['Available', 'Borrowed', 'Out of Stock'] as $v) {
        $sel = (($item['status_label'] ?? 'Available') == $v) ? 'selected' : '';
        echo "<option $sel>$v</option>";
      } ?>
    </select>
    <input name="location" placeholder="Location" value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>">
    <input name="supplier_id" type="number" placeholder="Supplier ID" value="<?php echo htmlspecialchars($item['supplier_id'] ?? ''); ?>">
    <input type="file" name="image" accept="image/*">
    <button type="submit"><?php echo $item ? 'Update' : 'Create'; ?></button>
  </form>
</body>

</html>