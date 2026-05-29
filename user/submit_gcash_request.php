<?php
// user/submit_gcash_request.php
// Start output buffering to catch any stray output
ob_start();

session_start();

// Set JSON header at the VERY TOP
header('Content-Type: application/json; charset=utf-8');

// Turn off error display to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log errors to file instead
ini_set('log_errors', 1);
ini_set('error_log', '../php_errors.log');

// Determine correct path for config.php
$config_path = dirname(__FILE__) . '/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    // Try parent directory
    $config_path = dirname(__FILE__, 2) . '/config.php';
    if (file_exists($config_path)) {
        require_once $config_path;
    } else {
        // Clean any output and return JSON error
        ob_end_clean();
        echo json_encode([
            'status' => 'error',
            'message' => 'Configuration file not found'
        ]);
        exit();
    }
}

// Include booking functions for shared pricing logic
require_once dirname(__FILE__, 2) . '/includes/booking-functions.php';

// Clean output buffer
ob_end_clean();

// Allow registered users (session) OR guests (booking token)
require_once dirname(__FILE__, 2) . '/includes/guest-access.php';
$user_id = $_SESSION['user_id'] ?? null;
$guest_token = $_POST['guest_token'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw POST data to debug
    $raw_post = file_get_contents('php://input');
    error_log("Raw POST data: " . $raw_post);

    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $payment_method = 'GCASH';

    if ($booking_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID']);
        exit();
    }

    try {
        // Authorize: session owner or matching guest token
        $booking = getBookingForViewer($conn, $booking_id, $guest_token);
        if (!$booking) {
            echo json_encode(['status' => 'error', 'message' => 'Booking not found or access denied']);
            exit();
        }
        // Use the booking's own user_id (NULL for guests) for inserts
        $user_id = $booking['user_id'];
        // Resolve display name/contact: booking snapshot, fall back to the user profile
        $booking['first_name'] = $booking['customer_first_name'] ?? '';
        $booking['last_name']  = $booking['customer_last_name'] ?? '';
        $booking['email']      = $booking['customer_email'] ?? '';
        $booking['phone']      = $booking['customer_mobile'] ?? '';
        if ($user_id) {
            $uq = $conn->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
            $uq->bind_param("i", $user_id);
            $uq->execute();
            $ur = $uq->get_result()->fetch_assoc();
            $uq->close();
            if ($ur) {
                $booking['first_name'] = $booking['first_name'] ?: $ur['first_name'];
                $booking['last_name']  = $booking['last_name']  ?: $ur['last_name'];
                $booking['email']      = $booking['email']      ?: $ur['email'];
                $booking['phone']      = $booking['phone']      ?: $ur['phone'];
            }
        }
        
        // Check if booking is already paid or has pending GCASH request
        $check_query = "
            SELECT id, amount FROM gcash_requests 
            WHERE booking_id = ? AND status IN ('pending', 'approved')
        ";
        
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception('Failed to prepare check query: ' . ($conn->error ?? 'Unknown database error'));
        }
        
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_request = $check_result->fetch_assoc();
            echo json_encode([
                'status' => 'error', 
                'message' => 'A GCASH request is already pending for this booking',
                'existing_amount' => $existing_request['amount'] ?? 0
            ]);
            exit();
        }
        
        $customer_name = $booking['first_name'] . ' ' . $booking['last_name'];
        
        // Calculate the amount using the shared database pricing function
        // Fetch services from database for price lookup
        $services_from_db = [];
        $service_query = "SELECT id, service_name, price FROM services";
        $service_result = $conn->query($service_query);
        if ($service_result) {
            while ($row = $service_result->fetch_assoc()) {
                $services_from_db[$row['id']] = $row;
                $services_from_db[strtolower($row['service_name'])] = $row;
            }
        }
        
        // Fetch inventory items with prices
        $inventory_items = [];
        $inventory_query = "SELECT id, item_name, price, item_type FROM inventory";
        $inventory_result = $conn->query($inventory_query);
        if ($inventory_result) {
            while ($row = $inventory_result->fetch_assoc()) {
                $inventory_items[$row['item_name']] = [
                    'price' => $row['price'],
                    'item_type' => $row['item_type'],
                    'id' => $row['id']
                ];
            }
        }
        
        // Calculate amount using the shared function
        $amount = calculateBookingAmountFromDB($booking, $services_from_db);
        
        if ($amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Unable to calculate amount. Please contact support.']);
            exit();
        }
        
        error_log("Calculated amount for booking #{$booking_id}: ₱{$amount}");
        
        // Start transaction
        $conn->begin_transaction();
        
        // Determine holiday status
        require_once dirname(__FILE__, 2) . '/includes/holiday-utils.php';
        $is_holiday = isPhilippineHoliday($booking['booking_date']) ? 1 : 0;
        $holiday_name = getPhilippineHoliday($booking['booking_date']);

        // Generate a reference number
        $reference_number = 'GCASH-' . date('Ymd') . '-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
        
        // 1. Insert into gcash_requests table with amount and reference number
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
            throw new Exception('Failed to prepare insert query: ' . ($conn->error ?? 'Unknown database error'));
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
            throw new Exception('Failed to create GCASH request: ' . ($conn->error ?? 'Unknown database error'));
        }
        
        $request_id = $conn->insert_id;
        $insert_request->close();
        
        // 2. Create notification for admin using the admin-notifications function
        if (function_exists('sendGCASHRequestNotification')) {
            sendGCASHRequestNotification($request_id, $customer_name, $amount, $booking_id);
        } else {
            // Fallback: Insert into admin_notifications directly
            $admin_title = "New GCASH Payment Request";
            $admin_message = "New GCASH payment request #{$request_id} from {$customer_name} for ₱" . number_format($amount, 2);
            
            $admin_notif_stmt = $conn->prepare("
                INSERT INTO admin_notifications (
                    title,
                    message,
                    type,
                    related_id,
                    status,
                    created_at
                ) VALUES (?, ?, 'gcash_request', ?, 'unread', NOW())
            ");
            
            if ($admin_notif_stmt) {
                $admin_notif_stmt->bind_param("ssi", $admin_title, $admin_message, $request_id);
                if (!$admin_notif_stmt->execute()) {
                    error_log("Failed to insert admin notification: " . $conn->error);
                }
                $admin_notif_stmt->close();
            }
        }
        
        // 3. Create notification for user with a clickable link (registered users only)
        if ($user_id) {
            $user_title = "GCASH Payment Request Submitted";
            $user_message = "Your GCASH payment request for ₱" . number_format($amount, 2) . " (Booking #$booking_id) has been submitted. Reference: $reference_number";
            $user_link = "user-profile.php?view_gcash_request=" . $request_id;

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
        }
        
        // 4. Update booking status (use 'Pending' since it's in the enum)
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
            'message' => 'GCASH payment request submitted successfully',
            'request_id' => $request_id,
            'booking_id' => $booking_id,
            'amount' => $amount,
            'formatted_amount' => '₱' . number_format($amount, 2),
            'reference_number' => $reference_number
        ];
        
        error_log("GCASH request successful: " . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        
        $error_msg = 'Failed to submit GCASH request: ' . $e->getMessage();
        error_log("GCASH request error: " . $error_msg);
        
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