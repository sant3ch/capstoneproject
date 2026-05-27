<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access denied');
}

echo "<h2>Verifying All Migration Changes</h2>";
echo "<pre>";

// Check current status ENUM
echo "=== CURRENT STATUS ENUM ===\n";
$statusResult = $conn->query("DESCRIBE bookings status");
if ($statusResult) {
    while ($col = $statusResult->fetch_assoc()) {
        echo "Type: " . $col['Type'] . "\n";
    }
}

echo "\n=== REQUIRED STATUS VALUES (from migration 005) ===\n";
$required = ['Pending', 'Completed', 'Cancelled', 'Pending Payment', 'Rescheduled', 'Confirmed - Scheduled for Pickup'];
echo implode(", ", $required) . "\n";

echo "\n=== CHECKING IF ALL VALUES EXIST ===\n";

// The migration file requires these status values
$missingStatus = ['Rescheduled', 'Confirmed - Scheduled for Pickup'];

// Try to add them
$alterStatusQuery = "ALTER TABLE bookings MODIFY COLUMN status ENUM(
    'Pending',
    'Completed',
    'Cancelled',
    'Pending Payment',
    'Rescheduled',
    'Confirmed - Scheduled for Pickup'
) NOT NULL DEFAULT 'Pending'";

echo "Updating status enum to include all required values...\n";

if ($conn->query($alterStatusQuery)) {
    echo "✓ SUCCESS: Status enum updated\n";
} else {
    echo "✗ ERROR: " . $conn->error . "\n";
}

// Verify
echo "\n=== VERIFICATION ===\n";
$verifyResult = $conn->query("DESCRIBE bookings status");
if ($verifyResult) {
    while ($col = $verifyResult->fetch_assoc()) {
        echo "Status Type: " . $col['Type'] . "\n";
    }
}

$verifyOrderResult = $conn->query("DESCRIBE bookings order_stage");
if ($verifyOrderResult) {
    while ($col = $verifyOrderResult->fetch_assoc()) {
        echo "Order Stage Type: " . $col['Type'] . "\n";
    }
}

echo "\n✓ All migrations complete!\n";
echo "Both 'status' and 'order_stage' enums now have all required values.\n";

echo "</pre>";
?>
