<?php
//Author Bryce
require '../config.php';

$currentTime = date("Y-m-d H:i:s");

$query = "SELECT machine_name FROM machine_schedule 
          WHERE restore_time <= ? AND is_restored = 0";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $currentTime);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $machineName = $row['machine_name'];

    // Set machine to Available
    $updateMachine = $conn->prepare("UPDATE machines SET status = 'Available' WHERE machine_name = ?");
    $updateMachine->bind_param("s", $machineName);
    $updateMachine->execute();
    $updateMachine->close();

    // Mark this restore entry as done
    $markRestored = $conn->prepare("UPDATE machine_schedule SET is_restored = 1 WHERE machine_name = ? AND restore_time <= ?");
    $markRestored->bind_param("ss", $machineName, $currentTime);
    $markRestored->execute();
    $markRestored->close();
}

$stmt->close();
$conn->close();
