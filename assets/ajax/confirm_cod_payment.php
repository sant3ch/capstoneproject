<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin-notifications.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$request_id = $data['request_id'] ?? null;
$booking_id = $data['booking_id'] ?? null;

if (!$request_id || !$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Verify that the request belongs to this user and is approved (but not yet confirmed)
    $verify_query = "SELECT id, amount, booking_id FROM gcash_requests 
                     WHERE id = ? AND user_id = ? AND status = 'approved'";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("ii", $request_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows == 0) {
        throw new Exception('Payment request not found, already confirmed, or not approved');
    }
    
    $request_data = $verify_result->fetch_assoc();
    $amount = $request_data['amount'];
    
    // Get booking and user details for notification
    $booking_query = "SELECT b.service_type, u.first_name, u.last_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?";
    $stmt = $conn->prepare($booking_query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    $customer_name = $booking['first_name'] . ' ' . $booking['last_name'];

    // Set status to pending_admin_confirmation (user has confirmed they will pay COD)
    $update_request = "UPDATE gcash_requests SET status = 'pending_admin_confirmation', is_notified = 0 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_request);
    $stmt->bind_param("ii", $request_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows == 0) {
        throw new Exception('Failed to update payment request status');
    }

    // Reset booking status to pending (in case it was changed)
    $update_booking = "UPDATE bookings SET status = 'Pending' WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_booking);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();

    // Send admin notification that user has marked COD payment as ready
    sendAdminNotification(
        'User Confirmed Cash on Delivery Payment',
        "User {$customer_name} has confirmed Cash on Delivery payment for booking #{$booking_id} (₱" . number_format($amount, 2) . "). Please verify and approve/reject.",
        'gcash_request',
        $request_id
    );

    // Send user notification that their confirmation has been sent for admin review
    $user_notification = $conn->prepare("INSERT INTO notifications (user_id, title, message, link, booking_id, is_read, created_at) VALUES (?, 'Payment Confirmation Sent', ?, ?, ?, 0, NOW())");
    $user_msg = "Your Cash on Delivery payment confirmation for booking #{$booking_id} has been sent to admin for verification. You will be notified once it's approved.";
    $user_link = "user-profile.php";
    $user_notification->bind_param('issi', $user_id, $user_msg, $user_link, $booking_id);
    $user_notification->execute();
    $user_notification->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment confirmation has been sent to admin for verification.'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

