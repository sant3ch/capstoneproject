<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_core_value'])) {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $icon  = trim($_POST['icon_class'] ?? 'fas fa-star');
    $desc  = trim($_POST['description'] ?? '');
    $sort  = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    if ($icon === '') $icon = 'fas fa-star';

    if ($id > 0 && $title !== '' && $desc !== '') {
        $stmt = mysqli_prepare($conn, "UPDATE core_values SET icon_class=?, title=?, description=?, is_active=?, sort_order=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssiii", $icon, $title, $desc, $active, $sort, $id);
        $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Core value updated successfully!";
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Title and description are required.";
    }
}
header("Location: manage_about.php");
exit();
?>
