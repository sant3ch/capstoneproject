<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $user_id = $_GET['id'];
    mysqli_query($conn, "DELETE FROM users WHERE id = $user_id");
    $_SESSION['success'] = "User deleted successfully!";
}

$_SESSION['delete_success'] = "User deleted successfully!";
header("Location: manage_users.php");
exit();
?>