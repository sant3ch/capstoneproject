<?php
// ajax/get_gcash_request.php
session_start();
header('Content-Type: application/json');

// Resolve config path relative to this file location
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit();
}

$query = "SELECT id as request_id, booking_id, amount, reference_number, status, approved_at, payment_method
          FROM gcash_requests 
          WHERE id = ? AND user_id = ? AND status IN ('approved', 'completed')";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'request' => [
            'request_id' => $row['request_id'],
            'booking_id' => $row['booking_id'],
            'amount' => $row['amount'],
            'reference_number' => $row['reference_number'],
            'status' => $row['status'],
            'approved_at' => $row['approved_at'],
            'payment_method' => $row['payment_method'] ?? 'GCASH'
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Request not found or not approved']);
}

$stmt->close();
$conn->close();
?>
