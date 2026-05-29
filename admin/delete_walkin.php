<?php
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';

$redirectDate = date('Y-m-d');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $new_status = ($_POST['new_status'] ?? 'Cancelled');
    if (!in_array($new_status, ['Cancelled', 'Completed'], true)) {
        $new_status = 'Cancelled';
    }

    // Find the row's date for redirect
    $q = mysqli_prepare($conn, "SELECT booking_date FROM walkin_reservations WHERE id = ?");
    mysqli_stmt_bind_param($q, "i", $id);
    mysqli_stmt_execute($q);
    if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($q))) {
        $redirectDate = $row['booking_date'];
    }
    mysqli_stmt_close($q);

    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE walkin_reservations SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $new_status, $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = $new_status === 'Cancelled'
                ? "Walk-in cancelled — slot freed."
                : "Walk-in marked completed.";
        } else {
            $_SESSION['error'] = "Could not update walk-in.";
        }
        mysqli_stmt_close($stmt);
    }
}

header("Location: manage_walkins.php?date=" . urlencode($redirectDate));
exit();
?>
