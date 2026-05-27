<?php
session_start();
require 'config.php'; // Include database connection

if (!isset($conn)) {
    die("Database connection error.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form data
    $email = mysqli_real_escape_string($conn, trim($_POST["email"]));
    $password = trim($_POST["password"]);

    // Prepare SQL statement to fetch user data
    $query = "SELECT id, first_name, role, password FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            // Verify password
            if (password_verify($password, $row["password"])) {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // Set session variables
                $_SESSION["user_id"] = $row["id"];
                $_SESSION["user_name"] = $row["first_name"];
                $_SESSION["user_role"] = $row["role"];

                mysqli_stmt_close($stmt);
                mysqli_close($conn);

                // Debugging output (Remove after testing)
                error_log("Login successful. Role: " . $_SESSION["user_role"]);

                // Redirect based on role
                if (strtolower($row["role"]) === "admin") {
                    header("Location: admin/admin_home.php");
                    exit();
                } else {
                    header("Location: index.php");
                    exit();
                }
            } else {
                $_SESSION["error"] = "Invalid email or password.";
            }
        } else {
            $_SESSION["error"] = "No account found with that email.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION["error"] = "Login error. Please try again.";
    }

    mysqli_close($conn);
    header("Location: login.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
?>