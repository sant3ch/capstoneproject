<?php
session_start();
require '../config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure only admins can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $machine_name = trim($_POST['machine_name']);
    $machine_model = trim($_POST['machine_model']);
    $availability = trim($_POST['availability']);

    if (!empty($machine_name) && !empty($availability)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO machines (machine_name, machine_model, status) VALUES (?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $machine_name, $machine_model, $availability);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Machine added successfully!";
                header("Location: manage_machines.php");
                exit();
            } else {
                $_SESSION['error'] = "Error executing query: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error'] = "Error preparing statement: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Machine - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container-box {
            width: 100%;
            max-width: 500px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center align-items-center">
    <div class="container-box card">
        <div class="card-body">
            <h2 class="text-center mb-4">Add New Machine</h2>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <form action="add_machine.php" method="POST">
                <div class="mb-3">
                    <label for="machine_name" class="form-label">Machine Name</label>
                    <input type="text" class="form-control" id="machine_name" name="machine_name" required>
                </div>
                <div class="mb-3">
                    <label for="machine_model" class="form-label">Machine Model</label>
                    <input type="text" class="form-control" id="machine_model" name="machine_model">
                </div>
                <div class="mb-3">
                    <label for="availability" class="form-label">Availability</label>
                    <select class="form-control" id="availability" name="availability" required>
                        <option value="Available">Available</option>
                        <option value="Unavailable">Unavailable</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Add Machine</button>
                    <a href="manage_machines.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Machines</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
</body>
</html>


