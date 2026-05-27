<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

/**
 * Handle User Update Request
 * This script processes the form submission from the "Edit User" modal in manage_users.php
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $id = isset($_POST['id']) ? mysqli_real_escape_string($conn, $_POST['id']) : null;
    $first_name = isset($_POST['first_name']) ? mysqli_real_escape_string($conn, $_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? mysqli_real_escape_string($conn, $_POST['last_name']) : '';
    $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, $_POST['phone']) : '';
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : '';
    $address = isset($_POST['address']) ? mysqli_real_escape_string($conn, $_POST['address']) : '';
    $role = isset($_POST['role']) ? mysqli_real_escape_string($conn, $_POST['role']) : 'user';
    $user_status = isset($_POST['user_status']) ? mysqli_real_escape_string($conn, $_POST['user_status']) : '1';

    if (!$id) {
        $_SESSION['error'] = "Invalid user ID.";
        header("Location: manage_users.php");
        exit();
    }

    // Update the user record
    $query = "UPDATE users SET 
                first_name = '$first_name', 
                last_name = '$last_name', 
                phone = '$phone', 
                email = '$email', 
                address = '$address', 
                role = '$role', 
                user_status = '$user_status' 
              WHERE id = '$id'";

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "User updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating user: " . mysqli_error($conn);
    }
    
    // Redirect back to the management page
    header("Location: manage_users.php");
    exit();
} else {
    // If accessed directly without POST, redirect back
    header("Location: manage_users.php");
    exit();
}
?>
