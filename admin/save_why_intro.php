<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/content-helpers.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_why_intro'])) {
    $val = trim($_POST['home_why_intro'] ?? '');
    $_SESSION[upsertSetting($conn, 'home_why_intro', $val, 'home') ? 'success' : 'error'] = "Intro text saved!";
}
header("Location: manage_why_choose_us.php");
exit();
?>
