<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/content-helpers.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_service_settings'])) {
    $fields = [
        'svc_self_desc',
        'svc_full_desc',
        'svc_capacity_note',
        'svc_wash_dry_fold_label',
        'svc_wash_dry_fold_price',
    ];
    $ok = true;
    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        if (!upsertSetting($conn, $key, $val, 'services')) $ok = false;
    }
    $_SESSION[$ok ? 'success' : 'error'] = $ok
        ? "Display settings saved successfully!"
        : "Some settings could not be saved.";
}

header("Location: manage_services.php");
exit();
?>
