<?php
//Author Bryce
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machineName = $_POST['machine_name'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'reserve' && !empty($machineName)) {
        // 1. Mark machine as Unavailable
        $stmt = $conn->prepare("UPDATE machines SET status = 'Unavailable' WHERE machine_name = ?");
        $stmt->bind_param("s", $machineName);

        if ($stmt->execute()) {
            echo "Machine marked as Unavailable.";

            // 2. Schedule status to change back after 2 hours
            $scheduleTime = date("Y-m-d H:i:s", strtotime('+2 hours'));

            // Insert into temporary scheduler table
            $insertScheduler = $conn->prepare("INSERT INTO machine_schedule (machine_name, restore_time) VALUES (?, ?)");
            $insertScheduler->bind_param("ss", $machineName, $scheduleTime);
            $insertScheduler->execute();
            $insertScheduler->close();

        } else {
            echo "Error updating status: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Invalid machine or action.";
    }
} else {
    echo "Invalid request method.";
}

$conn->close();
