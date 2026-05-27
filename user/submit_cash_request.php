<?php
// user/submit_cash_request.php - Handle cash on delivery requests
// Similar to GCASH but for cash payment requests
ob_start();

session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../php_errors.log');

// Include configuration and functions
require_once dirname(__FILE__, 2) . '/config.php';
require_once dirname(__FILE__, 2) . '/includes/booking-functions.php';
require_once dirname(__FILE__, 2) . '/includes/admin-notifications.php';

ob_end_clean();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please login.']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $payment_method = 'Cash on Delivery';
    
    error_log("Cash request received - Booking ID: {$booking_id}, User ID: {$user_id}");
    
    if ($booking_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID']);
        exit();
    }
    
    try {
        // Fetch booking details
        $query = "
            SELECT b.*, u.first_name, u.last_name, u.email, u.phone 
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            WHERE b.id = ? AND b.user_id = ?
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare query: ' . $conn->error);
        }
        
        $stmt->bind_param("ii", $booking_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();
        
        if (!$booking) {
            error_log("Booking not found: booking_id={$booking_id}, user_id={$user_id}");
            echo json_encode(['status' => 'error', 'message' => 'Booking not found or does not belong to you']);
            exit();
        }
        
        // Check if cash request already pending
        $check_query = "
            SELECT id, amount FROM gcash_requests 
            WHERE booking_id = ? AND payment_method = 'Cash on Delivery' AND status IN ('pending', 'approved')
        ";
        
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception('Failed to prepare check query: ' . $conn->error);
        }
        
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_request = $check_result->fetch_assoc();
            echo json_encode([
                'status' => 'error', 
                'message' => 'A cash on delivery request is already pending for this booking',
                'existing_amount' => $existing_request['amount'] ?? 0
            ]);
            exit();
        }
        
        $customer_name = $booking['first_name'] . ' ' . $booking['last_name'];
        
        // Calculate amount using database pricing
        $services_from_db = [];
        $service_query = "SELECT id, service_name, price FROM services";
        $service_result = $conn->query($service_query);
        if ($service_result) {
            while ($row = $service_result->fetch_assoc()) {
                $services_from_db[$row['id']] = $row;
                $services_from_db[strtolower($row['service_name'])] = $row;
            }
        }
        
        $amount = calculateBookingAmountFromDB($booking, $services_from_db);
        
        if ($amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Unable to calculate amount. Please contact support.']);
            exit();
        }
        
        error_log("Calculated amount for cash booking #{$booking_id}: ₱{$amount}");
        
        // Start transaction
        $conn->begin_transaction();
        
        // Determine holiday status
        require_once dirname(__FILE__, 2) . '/includes/holiday-utils.php';
        $is_holiday = isPhilippineHoliday($booking['booking_date']) ? 1 : 0;
        $holiday_name = getPhilippineHoliday($booking['booking_date']);

        // Generate reference number
        $reference_number = 'COD-' . date('Ymd') . '-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
        
        // Insert into gcash_requests table (reusing table for all payment methods)
        $insert_request = $conn->prepare("
            INSERT INTO gcash_requests (
                booking_id,
                user_id,
                customer_name,
                payment_method,
                amount,
                reference_number,
                status,
                requested_at,
                is_holiday,
                holiday_name
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), ?, ?)
        ");
        
        if (!$insert_request) {
            throw new Exception('Failed to prepare insert query: ' . $conn->error);
        }
        
        $insert_request->bind_param(
            "iissdsis",
            $booking_id,
            $user_id,
            $customer_name,
            $payment_method,
            $amount,
            $reference_number,
            $is_holiday,
            $holiday_name
        );
        
        if (!$insert_request->execute()) {
            throw new Exception('Failed to create cash request: ' . $conn->error);
        }
        
        $request_id = $conn->insert_id;
        $insert_request->close();
        
        // Create notification for admin
        if (function_exists('sendCashRequestNotification')) {
            sendCashRequestNotification($request_id, $customer_name, $amount, $booking_id);
        } else {
            // Fallback: Insert into admin_notifications directly
            $admin_title = "New Cash on Delivery Request";
            $admin_message = "New cash on delivery request #{$request_id} from {$customer_name} for ₱" . number_format($amount, 2);
            
            $admin_notif_stmt = $conn->prepare("
                INSERT INTO admin_notifications (
                    title,
                    message,
                    type,
                    related_id,
                    status,
                    created_at
                ) VALUES (?, ?, 'cash_request', ?, 'unread', NOW())
            ");
            
            if ($admin_notif_stmt) {
                $admin_notif_stmt->bind_param("ssi", $admin_title, $admin_message, $request_id);
                if (!$admin_notif_stmt->execute()) {
                    error_log("Failed to insert admin notification: " . $conn->error);
                }
                $admin_notif_stmt->close();
            }
        }
        
        // Create notification for user
        $user_title = "Cash on Delivery Request Submitted";
        $user_message = "Your cash on delivery request for ₱" . number_format($amount, 2) . " (Booking #$booking_id) has been submitted. Reference: $reference_number";
        $user_link = "user-profile.php?view_payment_request=" . $request_id;

        $insert_user_notif = $conn->prepare("
            INSERT INTO notifications (
                user_id,
                title,
                message,
                link,
                booking_id,
                is_read,
                created_at
            ) VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");

        if ($insert_user_notif) {
            $insert_user_notif->bind_param(
                "issis",
                $user_id,
                $user_title,
                $user_message,
                $user_link,
                $booking_id
            );
            $insert_user_notif->execute();
            $insert_user_notif->close();
        }
        
        // Update booking status to Pending
        $update_booking = $conn->prepare("
            UPDATE bookings 
            SET status = 'Pending'
            WHERE id = ?
        ");
                
        if ($update_booking) {
            $update_booking->bind_param("i", $booking_id);
            if (!$update_booking->execute()) {
                error_log("Failed to update booking: " . $conn->error);
            }
            $update_booking->close();
        }

        // Commit transaction
        $conn->commit();
        
        $response = [
            'status' => 'success',
            'message' => 'Cash on delivery request submitted successfully',
            'request_id' => $request_id,
            'booking_id' => $booking_id,
            'amount' => $amount,
            'formatted_amount' => '₱' . number_format($amount, 2),
            'reference_number' => $reference_number
        ];
        
        error_log("Cash request successful: " . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        
        $error_msg = 'Failed to submit cash on delivery request: ' . $e->getMessage();
        error_log("Cash request error: " . $error_msg);
        
        echo json_encode([
            'status' => 'error',
            'message' => $error_msg
        ]);
    }
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method. Expected POST.'
    ]);
}
?>
