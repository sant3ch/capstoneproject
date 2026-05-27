<?php
require '../config.php';
$res = mysqli_query($conn, "SELECT b.id, b.status as booking_status, gr.status as request_status, gr.payment_method 
                           FROM bookings b 
                           LEFT JOIN gcash_requests gr ON b.id = gr.booking_id 
                           WHERE b.status = 'Pending'");
echo "Pending Bookings Analysis:\n";
while($row = mysqli_fetch_assoc($res)) {
    echo "Booking ID: " . $row['id'] . " | Booking Status: " . $row['booking_status'] . " | Request Status: " . ($row['request_status'] ?? 'N/A') . " | Method: " . ($row['payment_method'] ?? 'N/A') . "\n";
}
?>
