<?php
// get_booked_dates.php

// Connect to your database
require_once('jorishlaundry_db');

// Query to fetch booked dates
$query = "SELECT booking_date FROM bookings WHERE status = 'Completed'";
$result = mysqli_query($conn, $query);

$bookedDates = [];
while ($row = mysqli_fetch_assoc($result)) {
    $bookedDates[] = $row['booking_date'];
}

// Return the booked dates as JSON
echo json_encode($bookedDates);
?>