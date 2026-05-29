<?php
/**
 * Guest booking access control.
 *
 * A booking can be viewed / paid by either:
 *   - the logged-in user who owns it ($_SESSION['user_id'] === bookings.user_id), or
 *   - anyone presenting the correct guest_token (for unregistered bookings).
 *
 * Returns the booking row (assoc) when access is granted, or null otherwise.
 * Use this in place of the old "WHERE id = ? AND user_id = ?" ownership checks.
 */
if (!function_exists('getBookingForViewer')) {
    function getBookingForViewer($conn, $booking_id, $token = null) {
        $booking_id = (int) $booking_id;
        if ($booking_id <= 0) return null;

        $stmt = mysqli_prepare($conn, "SELECT * FROM bookings WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        mysqli_stmt_execute($stmt);
        $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$booking) return null;

        // Logged-in owner
        if (isset($_SESSION['user_id']) && $booking['user_id'] !== null
            && (int) $booking['user_id'] === (int) $_SESSION['user_id']) {
            return $booking;
        }

        // Guest token (constant-time compare)
        if (!empty($booking['guest_token']) && $token !== null && $token !== ''
            && hash_equals((string) $booking['guest_token'], (string) $token)) {
            return $booking;
        }

        return null;
    }
}
?>
