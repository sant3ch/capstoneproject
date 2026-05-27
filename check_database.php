<?php
require 'config.php';

// Check recent bookings
$query = "SELECT id, user_id, booking_date, time_slot, order_stage, 
                 estimated_start_time, estimated_completion_time, 
                 process_started_at, status, pickup_status
          FROM bookings 
          WHERE order_stage IN ('Queuing (Assigning Machines)', 'In Process')
          ORDER BY id DESC 
          LIMIT 10";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Error: " . mysqli_error($conn));
}

echo "<h2>Recent Bookings in Queueing or In Process:</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr>
        <th>ID</th>
        <th>Booking Date</th>
        <th>Time Slot</th>
        <th>Stage</th>
        <th>Est Start</th>
        <th>Est End</th>
        <th>Process Started</th>
        <th>Status</th>
        <th>Pickup</th>
      </tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['booking_date'] . "</td>";
    echo "<td>" . $row['time_slot'] . "</td>";
    echo "<td>" . $row['order_stage'] . "</td>";
    echo "<td>" . ($row['estimated_start_time'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['estimated_completion_time'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['process_started_at'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['pickup_status'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Check if there are any issues with status/cancellation
echo "<h3>Check for Cancelled Bookings:</h3>";
$cancelledQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'Cancelled'";
$cancelledResult = mysqli_query($conn, $cancelledQuery);
$cancelledRow = mysqli_fetch_assoc($cancelledResult);
echo "Total cancelled bookings: " . $cancelledRow['count'] . "<br>";

mysqli_close($conn);
?>
