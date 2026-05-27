<?php
require __DIR__ . '/../config.php';
echo "--- BOOKINGS ---\n";
$res = mysqli_query($conn, 'SELECT id, status FROM bookings');
while($row = mysqli_fetch_assoc($res)) {
    echo "ID: " . $row['id'] . " | Status: " . $row['status'] . "\n";
}

echo "\n--- TRANSACTIONS ---\n";
$res = mysqli_query($conn, 'SELECT id, booking_id, payment_method FROM transactions');
while($row = mysqli_fetch_assoc($res)) {
    echo "ID: " . $row['id'] . " | Booking ID: " . $row['booking_id'] . " | Method: " . $row['payment_method'] . "\n";
}

echo "\n--- GCASH REQUESTS ---\n";
$res = mysqli_query($conn, 'SELECT id, booking_id, status, payment_method FROM gcash_requests');
while($row = mysqli_fetch_assoc($res)) {
    echo "ID: " . $row['id'] . " | Booking ID: " . $row['booking_id'] . " | Status: " . $row['status'] . " | Method: " . $row['payment_method'] . "\n";
}
?>
