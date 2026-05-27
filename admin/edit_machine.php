<?php
session_start();
require '../config.php';

// Check if the user is an admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Validate and sanitize ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_machines.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch the machine details
$result = mysqli_query($conn, "SELECT * FROM machines WHERE id = $id") or die("Query Failed: " . mysqli_error($conn));
$machine = mysqli_fetch_assoc($result);

if (!$machine) {
    $_SESSION['error'] = "Machine not found.";
    header("Location: manage_machines.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $machine_name = trim($_POST['machine_name']);
    $machine_model = trim($_POST['machine_model']);
    $status = trim($_POST['status']);

    if (!empty($machine_name) && !empty($status)) {
        $stmt = mysqli_prepare($conn, "UPDATE machines SET machine_name=?, machine_model=?, status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssi", $machine_name, $machine_model, $status, $id);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Machine updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating machine: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Please fill in all required fields.";
    }
    header("Location: manage_machines.php");
    exit();
}
?>