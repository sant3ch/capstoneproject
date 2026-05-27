<?php
// fetch_booked_slots.php
include '../config.php'; //

header('Content-Type: application/json');

// Fetch all bookings
$sql = "SELECT booking_date, time_slot FROM bookings WHERE status != 'Cancelled'";
$result = $conn->query($sql);

$booked = [];
while ($row = $result->fetch_assoc()) {
    $date = $row['booking_date'];
    $time = $row['time_slot'];

    if (!isset($booked[$date])) {
        $booked[$date] = [];
    }
    $booked[$date][] = $time;
}

echo json_encode($booked);
