<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

/** Upload a blog image. Returns root-relative path, or '' if no/invalid file. */
function uploadBlogImage(&$error) {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== 0) return '';
    $file = $_FILES['image'];
    $type = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$type])) { $error = "Only JPG, PNG, or WebP images are allowed."; return false; }
    if ($file['size'] > 4 * 1024 * 1024) { $error = "Image must be smaller than 4MB."; return false; }
    $newName = "blog_" . uniqid() . "." . $allowed[$type];
    $destRel = "uploads/blog/" . $newName;
    if (move_uploaded_file($file['tmp_name'], "../" . $destRel)) return $destRel;
    $error = "Failed to upload image.";
    return false;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_blog'])) {
    $title    = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $excerpt  = trim($_POST['excerpt'] ?? '');
    $content  = trim($_POST['content'] ?? '');
    $readmin  = max(1, (int)($_POST['read_minutes'] ?? 5));
    $sort     = (int)($_POST['sort_order'] ?? 0);
    $pub      = isset($_POST['is_published']) ? (int)$_POST['is_published'] : 1;

    if ($title === '' || $content === '') {
        $_SESSION['error'] = "Title and content are required.";
        header("Location: manage_blog.php"); exit();
    }

    $err = '';
    $image = uploadBlogImage($err);
    if ($image === false) {
        $_SESSION['error'] = $err;
        header("Location: manage_blog.php"); exit();
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO blog_posts (title, category, excerpt, content, image_path, read_minutes, is_published, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sssssiii", $title, $category, $excerpt, $content, $image, $readmin, $pub, $sort);
    $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Blog post added successfully!";
    mysqli_stmt_close($stmt);
}
header("Location: manage_blog.php");
exit();
?>
