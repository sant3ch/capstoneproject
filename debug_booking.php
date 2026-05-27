<?php
require 'config.php';

// Get a recent In Process booking
$query = "SELECT 
            id,
            booking_date,
            time_slot,
            order_stage,
            machine_names,
            estimated_start_time,
            estimated_completion_time,
            process_started_at,
            status,
            pickup_status
          FROM bookings 
          WHERE order_stage = 'In Process'
          ORDER BY id DESC 
          LIMIT 5";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Error: " . mysqli_error($conn));
}

echo "<h2>In Process Bookings - Database Debug:</h2>";
if (mysqli_num_rows($result) === 0) {
    echo "<p style='color:red;'><strong>No bookings in 'In Process' stage found!</strong></p>";
} else {
    echo "<table border='1' cellpadding='15' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>
            <th>ID</th>
            <th>Booking Date</th>
            <th>Time Slot</th>
            <th>Stage</th>
            <th>estimated_start_time</th>
            <th>estimated_completion_time</th>
            <th>process_started_at</th>
            <th>Status</th>
          </tr>";

    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['booking_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['time_slot']) . "</td>";
        echo "<td>" . htmlspecialchars($row['order_stage']) . "</td>";
        echo "<td><strong>" . ($row['estimated_start_time'] === null ? '<span style="color:red;">NULL</span>' : htmlspecialchars($row['estimated_start_time'])) . "</strong></td>";
        echo "<td><strong>" . ($row['estimated_completion_time'] === null ? '<span style="color:red;">NULL</span>' : htmlspecialchars($row['estimated_completion_time'])) . "</strong></td>";
        echo "<td>" . ($row['process_started_at'] === null ? 'NULL' : htmlspecialchars($row['process_started_at'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
        
        // Debug: Check what times would be calculated
        if (!empty($row['estimated_start_time']) && !empty($row['estimated_completion_time'])) {
            $startTime = strtotime($row['estimated_start_time']);
            $endTime = strtotime($row['estimated_completion_time']);
            $duration = $endTime - $startTime;
            $half = $duration / 2;
            
            echo "<tr style='background-color: #ffffcc;'>";
            echo "<td colspan='8'>";
            echo "<strong>Time Calculation:</strong><br>";
            echo "Start: " . date('Y-m-d H:i:s', $startTime) . "<br>";
            echo "End: " . date('Y-m-d H:i:s', $endTime) . "<br>";
            echo "Total Duration: " . ($duration / 60) . " minutes<br>";
            echo "Wash Duration: " . ($half / 60) . " minutes<br>";
            echo "Wash End Time: " . date('Y-m-d H:i:s', $startTime + $half) . "<br>";
            echo "</td>";
            echo "</tr>";
        }
    }

    echo "</table>";
}

echo "<hr>";
echo "<h2>Check All Bookings Summary:</h2>";

$stages = ['Pending / Booked', 'Queued (Waiting for Machine)', 'Queuing (Assigning Machines)', 'In Process', 'Ready for Pickup', 'Completed / Picked Up', 'Missed Pickup'];

foreach ($stages as $st) {
    $countQuery = "SELECT COUNT(*) as count FROM bookings WHERE order_stage = ? AND status <> 'Cancelled'";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param('s', $st);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    
    echo "Stage: <strong>$st</strong> - Count: " . $countResult['count'] . "<br>";
}

mysqli_close($conn);
?>
