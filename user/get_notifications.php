<?php
include('../config.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

$query = "SELECT id, title, message, is_read, booking_id, created_at, link FROM notifications 
          WHERE user_id = ? 
          ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'is_read' => $row['is_read'],
        'booking_id' => $row['booking_id'],
        'created_at' => $row['created_at'],
        'link' => $row['link']
    ];
}

echo json_encode(['status' => 'success', 'notifications' => $notifications]);

$stmt->close();
$conn->close();
?>
