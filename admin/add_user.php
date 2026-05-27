<?php
session_start();
require '../config.php';

// Ensure only admins can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add User - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Add New User</h2>
        <form action="" method="POST">
            <input type="text" class="form-control mb-3" name="first_name" placeholder="First Name" required>
            <input type="text" class="form-control mb-3" name="last_name" placeholder="Last Name" required>
            <input type="text" class="form-control mb-3" name="phone" placeholder="Phone Number" required>
            <input type="email" class="form-control mb-3" name="email" placeholder="Email Address" required>
            <input type="text" class="form-control mb-3" name="address" placeholder="Address" required>
            <input type="password" class="form-control mb-3" name="password" placeholder="Password" required>
            <select class="form-control mb-3" name="role">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
            <select class="form-control mb-3" name="user_status">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <button type="submit" class="btn btn-success">Add User</button>
            <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>


