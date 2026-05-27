
<?php
//created by Bryce
//this is a script to sync payments for completed bookings
//just back up your database before running this script
require '../config.php';

// Step 1: Get all completed bookings without a matching payment
$sql = "
    SELECT b.id AS booking_id, b.user_id, t.total_amount 
    FROM bookings b
    JOIN transactions t ON t.booking_id = b.id
    WHERE b.status = 'Completed'
    AND NOT EXISTS (
        SELECT 1 FROM payments p WHERE p.booking_id = b.id
    )
";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookingId = $row['booking_id'];
        $userId = $row['user_id'];
        $amount = $row['total_amount'];

        $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, booking_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("idi", $userId, $amount, $bookingId);

        $stmt->execute();
    }

    echo "Payment sync completed successfully.";
} else {
    echo "No new completed bookings to sync.";
}
