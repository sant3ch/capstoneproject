<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_why_item'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM why_choose_us_items WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Item deleted successfully!";
        mysqli_stmt_close($stmt);
    }
}
header("Location: manage_why_choose_us.php");
exit();
?>
