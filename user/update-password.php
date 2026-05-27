<?php
session_start();
require '../config.php'; // Database connection

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Handle password update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = mysqli_real_escape_string($conn, $_POST['current_password']);
    $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    // Fetch the user's current password
    $user_query = mysqli_query($conn, "SELECT password FROM users WHERE id = '$user_id'");
    $user = mysqli_fetch_assoc($user_query);
    $hashed_password = $user['password'];

    // Verify current password
    if (!password_verify($current_password, $hashed_password)) {
        $message = "<div class='alert alert-danger'>Current password is incorrect.</div>";
    } elseif ($new_password !== $confirm_password) {
        $message = "<div class='alert alert-danger'>New passwords do not match.</div>";
    } elseif (strlen($new_password) < 6) {
        $message = "<div class='alert alert-danger'>Password must be at least 6 characters.</div>";
    } else {
        // Hash new password and update database
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password = '$new_hashed_password' WHERE id = '$user_id'");
        $message = "<div class='alert alert-success'>Password updated successfully.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <style>
        .navbar-brand img {
            height: 50px;
        }
        .container-box { padding: 20px; border-radius: 8px; background: #f8f9fa; box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <img src="../assets/images/logo.png" alt="Jorish Express Laundry">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="../index.php#why">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="../service-and-pricing.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="../contact-and-map-view.php">Find Location</a></li>

                <li class="nav-item"><a class="nav-link" href="../index.php#news">Blog</a></li>
            </ul>
            <a class="btn btn-primary text-white" href="user-profile.php"><i class="fas fa-user"></i></a>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <h2 class="text-center">Change Password</h2>

    <div class="col-md-6 mx-auto">
        <div class="container-box">
            <?php echo $message; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><strong>Current Password</strong></label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label"><strong>New Password</strong></label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label"><strong>Confirm New Password</strong></label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <div class="text-center mt-3">
        <button type="submit" class="btn btn-primary">Update Password</button>
        <a href="user-profile.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>
            </form>
        </div>
    </div>
</div>

<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
</body>
</html>



