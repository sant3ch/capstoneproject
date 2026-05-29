<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_blog'])) {
    $id       = (int)($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $excerpt  = trim($_POST['excerpt'] ?? '');
    $content  = trim($_POST['content'] ?? '');
    $readmin  = max(1, (int)($_POST['read_minutes'] ?? 5));
    $sort     = (int)($_POST['sort_order'] ?? 0);
    $pub      = isset($_POST['is_published']) ? (int)$_POST['is_published'] : 1;

    if ($id <= 0 || $title === '' || $content === '') {
        $_SESSION['error'] = "Title and content are required.";
        header("Location: manage_blog.php"); exit();
    }

    // Current image
    $oldImage = '';
    $q = mysqli_prepare($conn, "SELECT image_path FROM blog_posts WHERE id = ?");
    mysqli_stmt_bind_param($q, "i", $id);
    mysqli_stmt_execute($q);
    $r = mysqli_stmt_get_result($q);
    if ($row = mysqli_fetch_assoc($r)) $oldImage = $row['image_path'] ?? '';
    mysqli_stmt_close($q);

    $err = '';
    $newImage = uploadBlogImage($err);
    if ($newImage === false) {
        $_SESSION['error'] = $err;
        header("Location: manage_blog.php"); exit();
    }

    if ($newImage !== '') {
        $image = $newImage;
        if ($oldImage && strpos($oldImage, 'uploads/blog/') === 0 && file_exists("../" . $oldImage)) {
            @unlink("../" . $oldImage);
        }
    } else {
        $image = $oldImage; // keep existing
    }

    $stmt = mysqli_prepare($conn, "UPDATE blog_posts SET title=?, category=?, excerpt=?, content=?, image_path=?, read_minutes=?, is_published=?, sort_order=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "sssssiiii", $title, $category, $excerpt, $content, $image, $readmin, $pub, $sort, $id);
    $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Blog post updated successfully!";
    mysqli_stmt_close($stmt);
}
header("Location: manage_blog.php");
exit();
?>
