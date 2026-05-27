<?php
session_start();
require '../config.php';

require_once '../includes/admin-notifications.php';

// Ensure only admins can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle form submission via modal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_inventory'])) {
    $item_name = trim($_POST['item_name']);
    $stock_quantity = (int)$_POST['stock_quantity'];
    $item_type = trim($_POST['item_type']); // New field

    // Validate item_type
    if ($item_type !== 'detergent' && $item_type !== 'fabric_conditioner') {
        $_SESSION['error'] = "Please select a valid item type.";
        header("Location: manage_inventory.php");
        exit();
    }

    if (!empty($item_name) && $stock_quantity >= 0 && !empty($item_type)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO inventory (item_name, stock_quantity, item_type, last_updated) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "sis", $item_name, $stock_quantity, $item_type);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "New inventory item added successfully!";
            // Trigger low stock notifications check
            checkAndSendInventoryNotifications();
        } else {
            $_SESSION['error'] = "Error adding item: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Please fill in all fields correctly.";
    }
}

// Redirect back to `manage_inventory.php`
header("Location: manage_inventory.php");
exit();
?>