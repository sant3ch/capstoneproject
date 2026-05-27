<?php
// user_operations.php

// Handle form submission for adding a user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $status = $_POST['user_status'];

    $stmt = mysqli_prepare($conn, "INSERT INTO users (first_name, last_name, phone, email, address, password, role, user_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sssssssi", $first_name, $last_name, $phone, $email, $address, $password, $role, $status);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION['success'] = "User added successfully!";
    header("Location: manage_users.php");
    exit();
}

// Logic for fetching users removed; pagination is handled in manage_users.php directly.
?>