<?php
session_start();
header('Content-Type: application/json');

// Resolve config path relative to this file location
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get approved GCASH requests that haven't been notified yet
$query = "SELECT 
            gr.id as request_id,
            gr.booking_id,
            gr.amount,
            gr.reference_number,
            gr.status,
            gr.approved_at,
            gr.is_notified,
            gr.qr_code_url,
            gr.payment_method
          FROM gcash_requests gr
          WHERE gr.user_id = ? 
            AND gr.status = 'approved' 
            AND (gr.is_notified = 0 OR gr.is_notified IS NULL)
          ORDER BY gr.approved_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$approved_requests = [];

while ($row = $result->fetch_assoc()) {
    $approved_requests[] = [
    'request_id' => $row['request_id'],
    'booking_id' => $row['booking_id'],
    'amount' => $row['amount'],
    'reference_number' => $row['reference_number'],
    'approved_at' => $row['approved_at'],
    'payment_method' => $row['payment_method'] ?? 'GCASH',
    'qr_code' => !empty($row['qr_code_url']) ? $row['qr_code_url'] : '.assets/images/gcash-qr-placeholder.png'
];
    
    // Mark as notified
    $update_stmt = $conn->prepare("UPDATE gcash_requests SET is_notified = 1 WHERE id = ?");
    $update_stmt->bind_param("i", $row['request_id']);
    $update_stmt->execute();
    $update_stmt->close();
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'approved_requests' => $approved_requests,
    'count' => count($approved_requests)
]);
?>
