<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM machines WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Machine deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting machine.";
    }
    mysqli_stmt_close($stmt);
}
header("Location: manage_machines.php");
exit();
?>