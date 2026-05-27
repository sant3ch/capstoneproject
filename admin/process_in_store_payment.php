<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require '../config.php';
require_once '../includes/booking-functions.php';

/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);
    $cash_amount = isset($_POST['cash_amount']) ? floatval($_POST['cash_amount']) : 0;
    
    if ($booking_id <= 0 || $cash_amount <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
        exit();
    }
    
    $booking_query = "
        SELECT 
            b.id,
            b.user_id,
            b.final_amount,
            b.service_type,
            b.detergent,
            b.status,
            b.booking_date,
            u.first_name,
            u.last_name,
            u.user_points
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ";
    
    $stmt = $conn->prepare($booking_query);
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
        exit();
    }
    
    // Determine holiday status
    require_once '../includes/holiday-utils.php';
    $is_holiday = isPhilippineHoliday($booking['booking_date']) ? 1 : 0;
    $holiday_name = getPhilippineHoliday($booking['booking_date']);

    $user_id = $booking['user_id'];
    $customer_name = $booking['first_name'] . ' ' . $booking['last_name'];
    $service_type = $booking['service_type'] ?? 'In-Store Service';
    
    // Calculate amount - use final_amount if available, otherwise calculate
    $amount = floatval($booking['final_amount']);
    if ($amount <= 0) {
        $amount = calculateBookingAmountFromDB([
            'service_type' => $booking['service_type'],
            'detergent' => $booking['detergent'],
            'id' => $booking['id']
        ]);
    }
    
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unable to calculate booking amount']);
        exit();
    }
    
    $current_points = intval($booking['user_points']);
    $points_earned = 1;
    $new_points = $current_points + $points_earned;
    
    $conn->begin_transaction();
    
    try {
        // Update booking status to Confirmed - Paid
        $update_booking_query = "UPDATE bookings SET status = 'Confirmed - Paid' WHERE id = ?";
        $update_booking_stmt = $conn->prepare($update_booking_query);
        if (!$update_booking_stmt) {
            throw new Exception("Prepare booking update failed: " . $conn->error);
        }
        $update_booking_stmt->bind_param('i', $booking_id);
        if (!$update_booking_stmt->execute()) {
            throw new Exception("Update booking status failed: " . $update_booking_stmt->error);
        }
        $update_booking_stmt->close();
        
        // Update gcash_requests status to completed
        $gcash_update_query = "UPDATE gcash_requests SET status = 'completed', payment_date = NOW() WHERE booking_id = ?";
        $gcash_update_stmt = $conn->prepare($gcash_update_query);
        if (!$gcash_update_stmt) {
            throw new Exception("Prepare gcash update failed: " . $conn->error);
        }
        $gcash_update_stmt->bind_param('i', $booking_id);
        if (!$gcash_update_stmt->execute()) {
            throw new Exception("Update gcash_requests failed: " . $gcash_update_stmt->error);
        }
        $gcash_update_stmt->close();
        
        $update_points_query = "UPDATE users SET user_points = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_points_query);
        if (!$update_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $update_stmt->bind_param('ii', $new_points, $user_id);
        if (!$update_stmt->execute()) {
            throw new Exception("Update points failed: " . $update_stmt->error);
        }
        $update_stmt->close();
        
        $trans_query = "INSERT INTO transactions (customer_name, user_id, booking_id, service_type, total_amount, payment_method, points_earned, transaction_date, is_holiday, holiday_name) VALUES (?, ?, ?, ?, ?, 'IN_STORE', ?, NOW(), ?, ?)";
        $trans_stmt = $conn->prepare($trans_query);
        if (!$trans_stmt) {
            throw new Exception("Prepare transaction failed: " . $conn->error);
        }
        $trans_stmt->bind_param('siisdiis', $customer_name, $user_id, $booking_id, $service_type, $amount, $points_earned, $is_holiday, $holiday_name);
        if (!$trans_stmt->execute()) {
            throw new Exception("Insert transaction failed: " . $trans_stmt->error);
        }
        $trans_id = $conn->insert_id;
        $trans_stmt->close();
        
        $notif_title = "In-Store Payment Confirmed";
        $notif_message = "Your in-store payment of ₱" . number_format($amount, 2) . " for booking #" . $booking_id . " has been confirmed. You have earned 1 point!";
        
        $notif_query = "INSERT INTO notifications (user_id, title, message, booking_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
        $notif_stmt = $conn->prepare($notif_query);
        if (!$notif_stmt) {
            throw new Exception("Prepare notification failed: " . $conn->error);
        }
        $notif_stmt->bind_param('issi', $user_id, $notif_title, $notif_message, $booking_id);
        if (!$notif_stmt->execute()) {
            throw new Exception("Insert notification failed: " . $notif_stmt->error);
        }
        $notif_stmt->close();
        
        $admin_notif_title = "In-Store Payment Confirmed - Booking #" . $booking_id;
        $admin_notif_message = "In-store payment of ₱" . number_format($amount, 2) . " from " . $customer_name . " has been confirmed. Customer earned 1 point.";
        
        $admin_notif_query = "INSERT INTO admin_notifications (title, message, type, related_id, status, created_at) VALUES (?, ?, 'in_store_payment_received', ?, 'read', NOW())";
        $admin_notif_stmt = $conn->prepare($admin_notif_query);
        if (!$admin_notif_stmt) {
            throw new Exception("Prepare admin notification failed: " . $conn->error);
        }
        $admin_notif_stmt->bind_param('ssi', $admin_notif_title, $admin_notif_message, $booking_id);
        if (!$admin_notif_stmt->execute()) {
            throw new Exception("Insert admin notification failed: " . $admin_notif_stmt->error);
        }
        $admin_notif_stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'transactionId' => $trans_id,
                'customer' => $customer_name,
                'amount' => floatval($amount),
                'pointsEarned' => $points_earned,
                'newBalance' => $new_points
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("In-store payment error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
