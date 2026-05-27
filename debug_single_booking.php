<?php
require 'config.php';

// Get a recent In Process booking by ID
$bookingId = $_GET['id'] ?? null;

if (!$bookingId) {
    echo "<h2>Usage:</h2>";
    echo "<p>Append ?id=<booking_id> to this URL to debug a specific booking</p>";
    echo "<p>Example: debug_booking.php?id=6</p>";
    
    echo "<h3>Recent In Process Bookings:</h3>";
    $query = "SELECT id, order_stage, booking_date, estimated_start_time, estimated_completion_time FROM bookings WHERE order_stage = 'In Process' LIMIT 5";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<ul>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<li><a href='?id=" . $row['id'] . "'>Booking #" . $row['id'] . "</a> - Stage: " . $row['order_stage'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No In Process bookings found</p>";
    }
    exit;
}

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
          WHERE id = ?
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    die("Booking #$bookingId not found!");
}

echo "<h2>Debugging Booking #" . htmlspecialchars($bookingId) . "</h2>";

echo "<h3>Database Values:</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><td><strong>Field</strong></td><td><strong>Value</strong></td></tr>";
echo "<tr><td>ID</td><td>" . htmlspecialchars($row['id']) . "</td></tr>";
echo "<tr><td>Booking Date</td><td>" . htmlspecialchars($row['booking_date']) . "</td></tr>";
echo "<tr><td>Time Slot</td><td>" . htmlspecialchars($row['time_slot']) . "</td></tr>";
echo "<tr><td>Order Stage</td><td>" . htmlspecialchars($row['order_stage']) . "</td></tr>";
echo "<tr><td>Machine Names</td><td>" . htmlspecialchars($row['machine_names'] ?? 'NULL') . "</td></tr>";
echo "<tr><td><strong>estimated_start_time</strong></td><td style='background-color: #ffffcc;'><strong>" . ($row['estimated_start_time'] === null ? '<span style="color:red;">NULL</span>' : htmlspecialchars($row['estimated_start_time'])) . "</strong></td></tr>";
echo "<tr><td><strong>estimated_completion_time</strong></td><td style='background-color: #ffffcc;'><strong>" . ($row['estimated_completion_time'] === null ? '<span style="color:red;">NULL</span>' : htmlspecialchars($row['estimated_completion_time'])) . "</strong></td></tr>";
echo "<tr><td>process_started_at</td><td>" . ($row['process_started_at'] === null ? 'NULL' : htmlspecialchars($row['process_started_at'])) . "</td></tr>";
echo "<tr><td>Status</td><td>" . htmlspecialchars($row['status']) . "</td></tr>";
echo "<tr><td>Pickup Status</td><td>" . htmlspecialchars($row['pickup_status'] ?? 'N/A') . "</td></tr>";
echo "</table>";

if (!empty($row['estimated_start_time']) && !empty($row['estimated_completion_time'])) {
    echo "<h3>Time Calculations:</h3>";
    
    $startTime = new DateTime($row['estimated_start_time']);
    $endTime = new DateTime($row['estimated_completion_time']);
    $nowTime = new DateTime();
    
    $totalDuration = $endTime->getTimestamp() - $startTime->getTimestamp();
    $halfDuration = $totalDuration / 2;
    
    $washEndTimeObj = clone $startTime;
    $washEndTimeObj->modify('+' . intval($halfDuration) . ' seconds');
    $washEndTime = $washEndTimeObj->format('Y-m-d H:i:s');
    
    $dryEndTime = $row['estimated_completion_time'];
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><td><strong>Calculation</strong></td><td><strong>Value</strong></td></tr>";
    echo "<tr><td>Start Time</td><td>" . $startTime->format('Y-m-d H:i:s') . "</td></tr>";
    echo "<tr><td>End Time</td><td>" . $endTime->format('Y-m-d H:i:s') . "</td></tr>";
    echo "<tr><td>Current Time</td><td>" . $nowTime->format('Y-m-d H:i:s') . "</td></tr>";
    echo "<tr><td>Total Duration</td><td>" . ($totalDuration / 60) . " minutes</td></tr>";
    echo "<tr><td>Half Duration</td><td>" . ($halfDuration / 60) . " minutes</td></tr>";
    echo "<tr><td><strong>Wash End Time</strong></td><td style='background-color: #ffffcc;'><strong>" . htmlspecialchars($washEndTime) . "</strong></td></tr>";
    echo "<tr><td><strong>Dry End Time</strong></td><td style='background-color: #ffffcc;'><strong>" . htmlspecialchars($dryEndTime) . "</strong></td></tr>";
    echo "</table>";
    
    // Determine current phase
    if ($nowTime->getTimestamp() < $washEndTimeObj->getTimestamp()) {
        $currentPhase = 'Washing';
    } else {
        $currentPhase = 'Drying';
    }
    
    echo "<h3>Current Phase: <span style='color: blue; font-weight: bold;'>" . htmlspecialchars($currentPhase) . "</span></h3>";
} else {
    echo "<h3 style='color: red;'><strong>ERROR: Times are not set in database!</strong></h3>";
    echo "<p>estimated_start_time and estimated_completion_time must be populated for wash/dry timers to work.</p>";
}

mysqli_close($conn);
?>
