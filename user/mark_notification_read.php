<?php
// mark_notification_read.php - ENHANCED VERSION
session_start();
require '../config.php';

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');

// Add error logging for debugging
error_log("mark_notification_read.php accessed");

if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get and validate JSON input
$input = file_get_contents('php://input');
error_log("Raw input: " . $input);

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data.']);
    exit();
}

// Check if we have notification_id or marking all
if (isset($data['notification_id'])) {
    $notification_id = (int)$data['notification_id'];
    
    // Validate notification ID
    if ($notification_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid notification ID.']);
        exit();
    }
    
    error_log("Marking single notification as read: ID=$notification_id, User=$user_id");
    
    // Mark a specific notification as read
    $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database prepare failed.']);
        exit();
    }
    
    $stmt->bind_param('ii', $notification_id, $user_id);

    if ($stmt->execute()) {
        error_log("Notification $notification_id marked as read for user $user_id");
        echo json_encode(['status' => 'success', 'message' => 'Notification marked as read.']);
    } else {
        error_log("Execute failed: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to mark notification as read.']);
    }

    $stmt->close();
} else {
    // Mark all unread notifications as read for this user
    error_log("Marking all notifications as read for user: $user_id");
    
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database prepare failed.']);
        exit();
    }
    
    $stmt->bind_param('i', $user_id);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        error_log("All notifications marked as read for user $user_id. Affected: $affected");
        echo json_encode([
            'status' => 'success', 
            'message' => 'All notifications marked as read.',
            'affected_rows' => $affected
        ]);
    } else {
        error_log("Execute failed: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to mark notifications as read.']);
    }

    $stmt->close();
}

$conn->close();
exit();
?>