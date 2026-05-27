<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access denied');
}

// Check timezone
date_default_timezone_set('Asia/Manila');

echo "<h2>Diagnostic: Delivering Bookings</h2>";
echo "<pre>";

// 1. Check all bookings with their exact stage values
echo "=== ALL BOOKINGS ===\n";
$allResult = $conn->query("SELECT id, queue_code, order_stage, status FROM bookings ORDER BY id DESC LIMIT 10");
if ($allResult) {
    while ($row = $allResult->fetch_assoc()) {
        echo "ID: {$row['id']}, Queue: {$row['queue_code']}, Stage: '{$row['order_stage']}', Status: '{$row['status']}'\n";
    }
}

echo "\n=== QUERY FOR DELIVERING ===\n";
$delQuery = "SELECT id, queue_code, order_stage, status FROM bookings WHERE order_stage = 'Delivering' AND status <> 'Cancelled'";
echo "Query: " . $delQuery . "\n";
$delResult = $conn->query($delQuery);
echo "Rows found: " . ($delResult ? $delResult->num_rows : 'ERROR') . "\n";
if ($delResult && $delResult->num_rows > 0) {
    while ($row = $delResult->fetch_assoc()) {
        echo "  - ID: {$row['id']}, Queue: {$row['queue_code']}\n";
    }
}

echo "\n=== CHECK ACTIVE FILTER ===\n";
$activeWhere = "b.order_stage IN ('Pending / Booked', 'Queued (Waiting for Machine)', 'Queuing (Assigning Machines)', 'In Process', 'Delivering', 'Ready for Pickup')";
$activeQuery = "SELECT COUNT(*) as count FROM bookings b WHERE $activeWhere AND b.status <> 'Cancelled'";
echo "Active filter query result: ";
$res = $conn->query($activeQuery);
if ($res) {
    $r = $res->fetch_assoc();
    echo $r['count'] . " bookings\n";
}

echo "\n=== CHECK EXACT STAGE VALUES IN DATABASE ===\n";
$stageQuery = "SELECT DISTINCT order_stage FROM bookings WHERE status <> 'Cancelled' ORDER BY order_stage";
$stageResult = $conn->query($stageQuery);
if ($stageResult) {
    echo "Unique stages in DB:\n";
    while ($row = $stageResult->fetch_assoc()) {
        echo "  - '" . $row['order_stage'] . "'\n";
    }
}

echo "</pre>";
?>
