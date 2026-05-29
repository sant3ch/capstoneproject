<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_blog'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        // Grab image to remove from disk
        $image = '';
        $q = mysqli_prepare($conn, "SELECT image_path FROM blog_posts WHERE id = ?");
        mysqli_stmt_bind_param($q, "i", $id);
        mysqli_stmt_execute($q);
        $r = mysqli_stmt_get_result($q);
        if ($row = mysqli_fetch_assoc($r)) $image = $row['image_path'] ?? '';
        mysqli_stmt_close($q);

        $stmt = mysqli_prepare($conn, "DELETE FROM blog_posts WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            if ($image && strpos($image, 'uploads/blog/') === 0 && file_exists("../" . $image)) {
                @unlink("../" . $image);
            }
            $_SESSION['success'] = "Blog post deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting post.";
        }
        mysqli_stmt_close($stmt);
    }
}
header("Location: manage_blog.php");
exit();
?>
