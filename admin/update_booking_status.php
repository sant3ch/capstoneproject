<?php
/**
 * admin/update_booking_status.php
 * Updates booking status and sends notifications
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-notifications.php';

header('Content-Type: application/json');

// Verify admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Verify POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
    
    if (!$booking_id || !$new_status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    // Get current booking details
    $booking_query = $conn->prepare("
        SELECT b.id, b.status, b.user_id, u.first_name, u.last_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ");
    $booking_query->bind_param("i", $booking_id);
    $booking_query->execute();
    $booking_result = $booking_query->get_result();
    
    if ($booking_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }
    
    $booking = $booking_result->fetch_assoc();
    $booking_query->close();
    
    // Update booking status
    $update_query = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $update_query->bind_param("si", $new_status, $booking_id);
    
    if (!$update_query->execute()) {
        throw new Exception("Failed to update booking status");
    }
    $update_query->close();
    
    // Get cancellation reason if provided
    $cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
    
    // Send user notification based on new status
    $user_id = $booking['user_id'];
    $title = "";
    $message = "";
    $send_notif = false;

    if ($new_status === 'Confirmed - Scheduled for Pickup') {
        $title = "Booking Confirmed and Scheduled for Pickup";
        $message = "Your booking #$booking_id has been confirmed and is scheduled for pickup. Thank you!";
        $send_notif = true;
    } elseif ($new_status === 'Cancelled') {
        $title = "Booking Cancelled";
        $message = "Your booking #$booking_id has been cancelled by the administrator.";
        if (!empty($cancel_reason)) {
            $message .= " Reason: " . $cancel_reason;
        }
        $send_notif = true;
    } elseif ($new_status === 'Confirmed - Paid') {
        $title = "Payment Confirmed";
        $message = "Your payment for booking #$booking_id has been confirmed. Thank you!";
        $send_notif = true;
    } elseif ($new_status === 'Completed') {
        $title = "Booking Completed";
        $message = "Your booking #$booking_id has been marked as completed. We hope to see you again!";
        $send_notif = true;
    }
    
    if ($send_notif) {
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications 
            (user_id, booking_id, title, message, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $notif_stmt->bind_param("iiss", $user_id, $booking_id, $title, $message);
        
        if (!$notif_stmt->execute()) {
            error_log("Warning: Failed to create notification for booking #$booking_id");
        }
        $notif_stmt->close();
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Booking status updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Update Booking Status Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while updating the booking status'
    ]);
}

exit;
?>
