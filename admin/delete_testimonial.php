<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_testimonial'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM testimonials WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Testimonial deleted successfully!";
        mysqli_stmt_close($stmt);
    }
}
header("Location: manage_testimonials.php");
exit();
?>
