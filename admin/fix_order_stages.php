<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access denied');
}

date_default_timezone_set('Asia/Manila');

echo "<h2>Fixing Order Stages for All Bookings</h2>";
echo "<pre>";

// First, check the current state
echo "=== BEFORE FIX ===\n";
$beforeResult = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE order_stage IS NULL OR order_stage = ''");
if ($beforeResult) {
    $row = $beforeResult->fetch_assoc();
    echo "Bookings with empty/NULL order_stage: " . $row['total'] . "\n";
}

// Fix: Set order_stage based on status and other fields
// Logic: 
// - If status = 'Cancelled' -> stage = 'Completed / Picked Up' (just to have a value)
// - If pickup_status = 'Already Picked' OR pickup_status = 'Picked Up' -> stage = 'Completed / Picked Up'
// - If status = 'Completed' -> stage = 'Ready for Pickup'
// - If status = 'Pending' -> stage = 'Pending / Booked'
// - Default -> 'Pending / Booked'

echo "\nApplying fix...\n";

$fixQuery = "UPDATE bookings 
SET order_stage = CASE 
    WHEN pickup_status = 'Already Picked' OR pickup_status = 'Picked Up' THEN 'Completed / Picked Up'
    WHEN status = 'Completed' THEN 'Ready for Pickup'
    WHEN status = 'Pending' THEN 'Pending / Booked'
    ELSE 'Pending / Booked'
END
WHERE (order_stage IS NULL OR order_stage = '')";

if ($conn->query($fixQuery)) {
    echo "SUCCESS: " . $conn->affected_rows . " bookings updated\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}

// Verify
echo "\n=== AFTER FIX ===\n";
$afterResult = $conn->query("SELECT order_stage, COUNT(*) as total FROM bookings GROUP BY order_stage ORDER BY order_stage");
if ($afterResult) {
    while ($row = $afterResult->fetch_assoc()) {
        echo "Stage '" . ($row['order_stage'] ? $row['order_stage'] : '[EMPTY]') . "': " . $row['total'] . " bookings\n";
    }
}

echo "\n=== ALL BOOKINGS AFTER FIX ===\n";
$allResult = $conn->query("SELECT id, queue_code, order_stage, status, pickup_status FROM bookings ORDER BY id DESC LIMIT 15");
if ($allResult) {
    while ($row = $allResult->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Queue: " . $row['queue_code'] . ", Stage: '" . $row['order_stage'] . "', Status: '" . $row['status'] . "', Pickup: '" . $row['pickup_status'] . "'\n";
    }
}

echo "</pre>";
?>
