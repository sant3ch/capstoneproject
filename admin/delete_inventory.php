<?php
session_start();
require '../config.php';

require_once '../includes/admin-notifications.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_inventory'])) {
    $id = intval($_POST['id']);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM inventory WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Inventory item deleted successfully!";
        // Trigger low stock notifications check
        checkAndSendInventoryNotifications();
    } else {
        $_SESSION['error'] = "Error deleting inventory item.";
    }
    mysqli_stmt_close($stmt);
}

// Redirect back to `manage_inventory.php`
header("Location: manage_inventory.php");
exit();
?>