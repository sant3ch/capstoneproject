<?php
session_start();
require 'config.php'; // Database connection

if (!isset($conn)) {
    die("Database connection error.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect & sanitize form data
    $first_name = mysqli_real_escape_string($conn, trim($_POST["first_name"]));
    $last_name = mysqli_real_escape_string($conn, trim($_POST["last_name"]));
    $phone = mysqli_real_escape_string($conn, trim($_POST["phone"]));
    $address = mysqli_real_escape_string($conn, trim($_POST["address"]));
    $email = mysqli_real_escape_string($conn, trim($_POST["email"]));
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Default user role (change dynamically if needed)
    $role = 'user';

    // Validate passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: register.php");
        exit();
    }

    // Check if email already exists
    $check_email = "SELECT id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check_email);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        $_SESSION['error'] = "Email is already registered.";
        mysqli_stmt_close($stmt);
        header("Location: register.php");
        exit();
    }
    mysqli_stmt_close($stmt); // Close after checking email

    // Hash password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user into database
    $insert_user = "INSERT INTO users (first_name, last_name, phone, address, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_user);
    mysqli_stmt_bind_param($stmt, "sssssss", $first_name, $last_name, $phone, $address, $email, $hashed_password, $role);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Registration successful! You can now log in.";
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        header("Location: register.php?success=1");
        exit();
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        header("Location: register.php");
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}
?>