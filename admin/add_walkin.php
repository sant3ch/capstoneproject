<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/booking-data.php';

$redirectDate = date('Y-m-d');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_walkin'])) {
    $customer = trim($_POST['customer_name'] ?? '');
    $contact  = trim($_POST['contact'] ?? '');
    $date     = trim($_POST['booking_date'] ?? '');
    $slot     = trim($_POST['time_slot'] ?? '');
    $washers  = max(0, (int)($_POST['washers_used'] ?? 0));
    $dryers   = max(0, (int)($_POST['dryers_used'] ?? 0));
    $weight   = ($_POST['estimated_weight'] ?? '') !== '' ? (float)$_POST['estimated_weight'] : null;
    $notes    = trim($_POST['notes'] ?? '');
    $admin_id = (int)($_SESSION['user_id'] ?? 0);
    $redirectDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');

    if ($date === '' || $slot === '') {
        $_SESSION['error'] = "Date and time slot are required.";
    } elseif ($washers + $dryers < 1) {
        $_SESSION['error'] = "Enter at least one washer or dryer.";
    } else {
        // Availability already accounts for online bookings + existing walk-ins
        $avail = getAvailabilityForSlot($conn, $date, $slot);
        if ($washers > $avail['available_washers'] || $dryers > $avail['available_dryers']) {
            $_SESSION['error'] = "Not enough machines free for that slot (free: "
                . (int)$avail['available_washers'] . " washers, "
                . (int)$avail['available_dryers'] . " dryers).";
        } else {
            $name = $customer !== '' ? $customer : null;
            $cont = $contact !== '' ? $contact : null;
            $note = $notes !== '' ? $notes : null;
            $stmt = mysqli_prepare($conn, "INSERT INTO walkin_reservations
                (customer_name, contact, booking_date, time_slot, washers_used, dryers_used, estimated_weight, notes, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
            mysqli_stmt_bind_param($stmt, "ssssiidsi", $name, $cont, $date, $slot, $washers, $dryers, $weight, $note, $admin_id);
            $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] =
                mysqli_stmt_errno($stmt) ? ("Error: " . mysqli_stmt_error($stmt)) : "Walk-in added and slot reserved.";
            mysqli_stmt_close($stmt);
        }
    }
}

header("Location: manage_walkins.php?date=" . urlencode($redirectDate));
exit();
?>
