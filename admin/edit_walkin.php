<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/booking-data.php';

$redirectDate = date('Y-m-d');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_walkin'])) {
    $id       = (int)($_POST['id'] ?? 0);
    $customer = trim($_POST['customer_name'] ?? '');
    $contact  = trim($_POST['contact'] ?? '');
    $date     = trim($_POST['booking_date'] ?? '');
    $slot     = trim($_POST['time_slot'] ?? '');
    $washers  = max(0, (int)($_POST['washers_used'] ?? 0));
    $dryers   = max(0, (int)($_POST['dryers_used'] ?? 0));
    $weight   = ($_POST['estimated_weight'] ?? '') !== '' ? (float)$_POST['estimated_weight'] : null;
    $notes    = trim($_POST['notes'] ?? '');
    $redirectDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');

    // Fetch existing row to know its current usage/slot
    $old = null;
    $q = mysqli_prepare($conn, "SELECT booking_date, time_slot, washers_used, dryers_used FROM walkin_reservations WHERE id = ?");
    mysqli_stmt_bind_param($q, "i", $id);
    mysqli_stmt_execute($q);
    $old = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
    mysqli_stmt_close($q);

    if (!$old || $id <= 0) {
        $_SESSION['error'] = "Walk-in not found.";
    } elseif ($date === '' || $slot === '') {
        $_SESSION['error'] = "Date and time slot are required.";
    } elseif ($washers + $dryers < 1) {
        $_SESSION['error'] = "Enter at least one washer or dryer.";
    } else {
        $avail = getAvailabilityForSlot($conn, $date, $slot);
        $allowW = $avail['available_washers'];
        $allowD = $avail['available_dryers'];
        // If still the same slot, add back this row's own current usage (it's already counted)
        if ($old['booking_date'] === $date && $old['time_slot'] === $slot) {
            $allowW += (int)$old['washers_used'];
            $allowD += (int)$old['dryers_used'];
        }

        if ($washers > $allowW || $dryers > $allowD) {
            $_SESSION['error'] = "Not enough machines free for that slot (free: $allowW washers, $allowD dryers).";
        } else {
            $name = $customer !== '' ? $customer : null;
            $cont = $contact !== '' ? $contact : null;
            $note = $notes !== '' ? $notes : null;
            $stmt = mysqli_prepare($conn, "UPDATE walkin_reservations
                SET customer_name=?, contact=?, booking_date=?, time_slot=?, washers_used=?, dryers_used=?, estimated_weight=?, notes=?
                WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssssiidsi", $name, $cont, $date, $slot, $washers, $dryers, $weight, $note, $id);
            $_SESSION[mysqli_stmt_execute($stmt) ? 'success' : 'error'] = "Walk-in updated.";
            mysqli_stmt_close($stmt);
        }
    }
}

header("Location: manage_walkins.php?date=" . urlencode($redirectDate));
exit();
?>
