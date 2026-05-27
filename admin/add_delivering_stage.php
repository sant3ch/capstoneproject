<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access denied');
}

echo "<h2>Adding 'Delivering' Stage to Database Schema</h2>";
echo "<pre>";

// Modify the ENUM to include 'Delivering'
$alterQuery = "ALTER TABLE bookings MODIFY COLUMN order_stage ENUM(
    'Pending / Booked',
    'Queued (Waiting for Machine)',
    'Queuing (Assigning Machines)',
    'In Process',
    'Delivering',
    'Ready for Pickup',
    'Completed / Picked Up',
    'Missed Pickup'
) NOT NULL DEFAULT 'Pending / Booked'";

echo "Running ALTER TABLE to add 'Delivering' to enum...\n\n";

if ($conn->query($alterQuery)) {
    echo "✓ SUCCESS: 'Delivering' added to order_stage enum\n";
} else {
    echo "✗ ERROR: " . $conn->error . "\n";
    exit();
}

// Verify the schema change
echo "\n=== VERIFYING SCHEMA ===\n";
$colResult = $conn->query("DESCRIBE bookings order_stage");
if ($colResult) {
    while ($col = $colResult->fetch_assoc()) {
        echo "Column: " . $col['Field'] . "\n";
        echo "Type: " . $col['Type'] . "\n";
        echo "Default: " . $col['Default'] . "\n";
    }
}

echo "\n✓ Schema fix complete! 'Delivering' stage is now available in the database.\n";
echo "The queue_management.php page can now properly use 'Delivering' stage.\n";

echo "</pre>";
?>
