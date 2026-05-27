<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access denied');
}

echo "<h2>Database Schema Check</h2>";
echo "<pre>";

// Check the SHOW CREATE TABLE output
$result = $conn->query("SHOW CREATE TABLE bookings");
if ($result) {
    $row = $result->fetch_row();
    echo "=== BOOKINGS TABLE STRUCTURE ===\n";
    echo $row[1]; // The CREATE TABLE statement
} else {
    echo "Error: " . $conn->error;
}

echo "\n\n=== CHECK order_stage COLUMN DETAILS ===\n";
$colResult = $conn->query("DESCRIBE bookings order_stage");
if ($colResult) {
    while ($col = $colResult->fetch_assoc()) {
        echo "Column: " . $col['Field'] . "\n";
        echo "Type: " . $col['Type'] . "\n";
        echo "Null: " . $col['Null'] . "\n";
        echo "Default: '" . $col['Default'] . "'\n";
        echo "Extra: " . $col['Extra'] . "\n";
    }
}

echo "</pre>";
?>
