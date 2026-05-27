<?php
//Author Bryce
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $detergentName = $_POST['detergent_id'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'decrement' && !empty($detergentName)) {
        $query = "UPDATE inventory 
                  SET stock_quantity = stock_quantity - 1 
                  WHERE item_name = ? AND stock_quantity > 0";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $detergentName);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo "✅ Stock successfully updated.";
            } else {
                echo "⚠️ No update (item not found or stock is 0).";
            }
        } else {
            echo "❌ Database error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "❌ Invalid request (missing name or action).";
    }
} else {
    echo "❌ Invalid request method.";
}

$conn->close();
?>
