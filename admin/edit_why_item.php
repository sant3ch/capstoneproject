<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_why_item'])) {
    $id = (int)($_POST['id'] ?? 0);
    $label = trim($_POST['label'] ?? '');
    $icon  = trim($_POST['icon_class'] ?? 'fas fa-star');
    $accent = ($_POST['accent'] ?? 'default') === 'orange' ? 'orange' : 'default';
    $sort  = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    if ($icon === '') $icon = 'fas fa-star';

    if ($id > 0 && $label !== '') {
        $stmt = mysqli_prepare($conn, "UPDATE why_choose_us_items SET icon_class=?, label=?, accent=?, is_active=?, sort_order=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssiii", $icon, $label, $accent, $active, $sort, $id);
        $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Item updated successfully!";
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Label is required.";
    }
}
header("Location: manage_why_choose_us.php");
exit();
?>
