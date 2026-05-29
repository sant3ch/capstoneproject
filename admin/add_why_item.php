<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_why_item'])) {
    $label = trim($_POST['label'] ?? '');
    $icon  = trim($_POST['icon_class'] ?? 'fas fa-star');
    $accent = ($_POST['accent'] ?? 'default') === 'orange' ? 'orange' : 'default';
    $sort  = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    if ($icon === '') $icon = 'fas fa-star';

    if ($label !== '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO why_choose_us_items (icon_class, label, accent, is_active, sort_order) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssii", $icon, $label, $accent, $active, $sort);
        $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Item added successfully!";
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Label is required.";
    }
}
header("Location: manage_why_choose_us.php");
exit();
?>
