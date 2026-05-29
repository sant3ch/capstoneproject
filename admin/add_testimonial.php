<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_testimonial'])) {
    $author = trim($_POST['author_name'] ?? '');
    $initials = trim($_POST['initials'] ?? '');
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $quote = trim($_POST['quote'] ?? '');
    $sort = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if ($initials === '' && $author !== '') {
        $parts = preg_split('/\s+/', $author);
        $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }

    if ($author !== '' && $quote !== '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO testimonials (author_name, initials, rating, quote, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssisii", $author, $initials, $rating, $quote, $active, $sort);
        $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = mysqli_stmt_error($stmt) ? ("Error: " . mysqli_stmt_error($stmt)) : "Testimonial added successfully!";
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Author name and quote are required.";
    }
}
header("Location: manage_testimonials.php");
exit();
?>
