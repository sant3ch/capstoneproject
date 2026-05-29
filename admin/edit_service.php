<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_service'])) {
    $id           = (int)($_POST['id'] ?? 0);
    $service_name = trim($_POST['service_name'] ?? '');
    $price        = (float)($_POST['price'] ?? 0);
    $description  = trim($_POST['description'] ?? '');

    if ($id > 0 && $service_name !== '' && $price >= 0) {
        $stmt = mysqli_prepare($conn, "UPDATE services SET service_name = ?, price = ?, description = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sdsi", $service_name, $price, $description, $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Service updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating service: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Please provide a valid service name and price.";
    }
}

header("Location: manage_services.php");
exit();
?>
