<?php
// Admin AJAX endpoint: live per-slot machine availability for a date
// (counts online bookings + walk-ins). Used by the walk-in board poll.
session_start();
require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/booking-data.php';

header('Content-Type: application/json; charset=utf-8');

$date = $_GET['date'] ?? '';
$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date']);
    exit;
}

echo json_encode(getDetailedDateAvailability($conn, $date));
exit;
?>
