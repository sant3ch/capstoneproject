<?php
session_start();
require '../config.php'; // DB connection

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if file was uploaded
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $file = $_FILES['profile_picture'];
    $fileTmpPath = $file['tmp_name'];
    $fileName = basename($file['name']);
    $fileSize = $file['size'];
    $fileType = mime_content_type($fileTmpPath);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $maxFileSize = 2 * 1024 * 1024; // 2MB

    if (!in_array($fileType, $allowedTypes)) {
        $_SESSION['upload_error'] = "Only JPG and PNG images are allowed.";
    } elseif ($fileSize > $maxFileSize) {
        $_SESSION['upload_error'] = "File size must be less than 2MB.";
    } else {
        // Generate unique file name
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = "user_" . $user_id . "_" . uniqid() . "." . $ext;
        $uploadDir = "../uploads/profile_pictures/";
        $destPath = $uploadDir . $newFileName;

        // Move the uploaded file
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Delete old profile picture if it's not default
            $getOldPic = mysqli_fetch_assoc(mysqli_query($conn, "SELECT profile_picture FROM users WHERE id = '$user_id'"));
            if ($getOldPic && $getOldPic['profile_picture'] && $getOldPic['profile_picture'] != 'default.png') {
                $oldPath = $uploadDir . $getOldPic['profile_picture'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // Update DB
            $stmt = mysqli_prepare($conn, "UPDATE users SET profile_picture = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $newFileName, $user_id);
            mysqli_stmt_execute($stmt);

            $_SESSION['upload_success'] = "Profile picture updated successfully!";
        } else {
            $_SESSION['upload_error'] = "Error uploading file. Please try again.";
        }
    }
} else {
    $_SESSION['upload_error'] = "No file selected or upload error.";
}

// Redirect back to profile edit page
header("Location: edit-profile.php");
exit();
?>
