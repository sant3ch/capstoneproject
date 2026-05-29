<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_core_value'])) {
    $title = trim($_POST['title'] ?? '');
    $icon  = trim($_POST['icon_class'] ?? 'fas fa-star');
    $desc  = trim($_POST['description'] ?? '');
    $sort  = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    if ($icon === '') $icon = 'fas fa-star';

    if ($title !== '' && $desc !== '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO core_values (icon_class, title, description, is_active, sort_order) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssii", $icon, $title, $desc, $active, $sort);
        $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Core value added successfully!";
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Title and description are required.";
    }
}
header("Location: manage_about.php");
exit();
?>
