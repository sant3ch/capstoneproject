<?php
require 'config.php';

echo "=== MACHINES TABLE ===\n";
$result = $conn->query("SELECT id, machine_name, machine_type, status, usage_count FROM machines ORDER BY machine_type, machine_name");
if ($result) {
    echo str_pad("ID", 4) . str_pad("Machine Name", 15) . str_pad("Type", 10) . str_pad("Status", 20) . str_pad("Usage", 6) . "\n";
    echo str_repeat("-", 65) . "\n";
    while ($row = $result->fetch_assoc()) {
        echo str_pad($row['id'], 4) . str_pad($row['machine_name'], 15) . str_pad($row['machine_type'], 10) . str_pad($row['status'], 20) . str_pad($row['usage_count'], 6) . "\n";
    }
}

echo "\n=== USAGE ANALYSIS ===\n";
$washers = $conn->query("SELECT COUNT(*) as total FROM machines WHERE machine_type = 'washer'");
$dryers = $conn->query("SELECT COUNT(*) as total FROM machines WHERE machine_type = 'dryer'");

$washer_row = $washers->fetch_assoc();
$dryer_row = $dryers->fetch_assoc();

echo "Total Washers: " . $washer_row['total'] . "\n";
echo "Total Dryers: " . $dryer_row['total'] . "\n";

echo "\n=== STATUS BREAKDOWN ===\n";
$status_result = $conn->query("SELECT machine_type, status, COUNT(*) as count FROM machines GROUP BY machine_type, status ORDER BY machine_type, status");
if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        echo $row['machine_type'] . " - " . $row['status'] . ": " . $row['count'] . "\n";
    }
}

echo "\n=== MACHINES REACHING MAINTENANCE (usage >= 10) ===\n";
$maintenance_result = $conn->query("SELECT id, machine_name, machine_type, status, usage_count FROM machines WHERE usage_count >= 10 ORDER BY machine_type, machine_name");
if ($maintenance_result && $maintenance_result->num_rows > 0) {
    while ($row = $maintenance_result->fetch_assoc()) {
        echo $row['machine_name'] . " (" . $row['machine_type'] . ") - Status: " . $row['status'] . ", Usage: " . $row['usage_count'] . "/10\n";
    }
} else {
    echo "No machines at or above 10 uses\n";
}

$conn->close();
?>
