<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/content-helpers.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_about'])) {
    upsertSetting($conn, 'about_title', trim($_POST['about_title'] ?? ''), 'about');
    upsertSetting($conn, 'about_tagline', trim($_POST['about_tagline'] ?? ''), 'about');
    upsertSetting($conn, 'about_description', trim($_POST['about_description'] ?? ''), 'about');

    // Optional new image
    if (isset($_FILES['about_image']) && $_FILES['about_image']['error'] === 0) {
        $file = $_FILES['about_image'];
        $type = mime_content_type($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $maxSize = 4 * 1024 * 1024;

        if (!isset($allowed[$type])) {
            $_SESSION['error'] = "Only JPG, PNG, or WebP images are allowed.";
            header("Location: manage_about.php"); exit();
        }
        if ($file['size'] > $maxSize) {
            $_SESSION['error'] = "Image must be smaller than 4MB.";
            header("Location: manage_about.php"); exit();
        }

        $ext = $allowed[$type];
        $newName = "about_" . uniqid() . "." . $ext;
        $destRel = "uploads/about/" . $newName;       // stored in DB (root-relative)
        $destAbs = "../" . $destRel;                    // path from admin/

        if (move_uploaded_file($file['tmp_name'], $destAbs)) {
            // Remove old uploaded image (only if it lived in uploads/about)
            $old = getSetting($conn, 'about_image', '');
            if ($old && strpos($old, 'uploads/about/') === 0 && file_exists("../" . $old)) {
                @unlink("../" . $old);
            }
            upsertSetting($conn, 'about_image', $destRel, 'about');
        } else {
            $_SESSION['error'] = "Failed to upload image.";
            header("Location: manage_about.php"); exit();
        }
    }

    $_SESSION['success'] = "About content saved successfully!";
}
header("Location: manage_about.php");
exit();
?>
