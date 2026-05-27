<?php
require 'config.php';
$sql = "ALTER TABLE claimed_rewards MODIFY COLUMN status ENUM('Pending', 'Claimed', 'Rejected', 'Used') DEFAULT 'Pending'";
if ($conn->query($sql)) {
    echo "Table updated successfully";
} else {
    echo "Error updating table: " . $conn->error;
}
?>
