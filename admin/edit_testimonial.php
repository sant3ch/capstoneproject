<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_testimonial'])) {
    $id = (int)($_POST['id'] ?? 0);
    $author = trim($_POST['author_name'] ?? '');
    $initials = trim($_POST['initials'] ?? '');
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $quote = trim($_POST['quote'] ?? '');
    $sort = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if ($id > 0 && $author !== '' && $quote !== '') {
        $stmt = mysqli_prepare($conn, "UPDATE testimonials SET author_name=?, initials=?, rating=?, quote=?, is_active=?, sort_order=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssisiii", $author, $initials, $rating, $quote, $active, $sort, $id);
        $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Testimonial updated successfully!";
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Author name and quote are required.";
    }
}
header("Location: manage_testimonials.php");
exit();
?>
