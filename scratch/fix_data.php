<?php
require 'config.php';
$sql = "UPDATE claimed_rewards SET status = 'Used' WHERE status = '' OR status IS NULL";
if ($conn->query($sql)) {
    echo "Data updated successfully. Rows affected: " . $conn->affected_rows;
} else {
    echo "Error updating data: " . $conn->error;
}
?>
