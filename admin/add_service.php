<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_service'])) {
    $service_name = trim($_POST['service_name'] ?? '');
    $price        = (float)($_POST['price'] ?? 0);
    $description  = trim($_POST['description'] ?? '');

    if ($service_name !== '' && $price >= 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO services (service_name, price, description) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sds", $service_name, $price, $description);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Service added successfully!";
        } else {
            $_SESSION['error'] = "Error adding service: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Please provide a valid service name and price.";
    }
}

header("Location: manage_services.php");
exit();
?>
