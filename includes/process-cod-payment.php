<?php
/**
 * includes/process-cod-payment.php
 * Handles Cash on Delivery payment submission
 * Creates pending COD payment record and sends notifications
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin-notifications.php';
require_once __DIR__ . '/booking-functions.php';

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, "UTF-8");
}

header('Content-Type: application/json');

// Verify POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get POST data
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : null;
    $user_id = $_SESSION['user_id'];
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $reward_id = isset($_POST['reward_id']) ? intval($_POST['reward_id']) : null;
    $reward_name = isset($_POST['reward_name']) ? sanitize_input($_POST['reward_name']) : null;
    $discount_amount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
    $original_amount = isset($_POST['original_amount']) ? floatval($_POST['original_amount']) : $amount;

    // Validate inputs
    if (!$booking_id || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    // Verify booking exists and belongs to user
    $booking_check = $conn->prepare("SELECT id, user_id FROM bookings WHERE id = ? AND user_id = ?");
    $booking_check->bind_param("ii", $booking_id, $user_id);
    $booking_check->execute();
    $booking_result = $booking_check->get_result();

    if ($booking_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }
    $booking_check->close();

    // Get user details for notifications
    $user_query = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();
    $user = $user_result->fetch_assoc();
    $user_query->close();

    $customer_name = $user['first_name'] . ' ' . $user['last_name'];

    // Create COD payment request in gcash_requests table
    // Using gcash_requests table with payment_method='Cash on Delivery'
    $stmt = $conn->prepare("
        INSERT INTO gcash_requests 
        (booking_id, user_id, customer_name, amount, reward_id, reward_name, discount_amount, original_amount, payment_method, status, requested_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Cash on Delivery', 'pending', NOW())
    ");
    $stmt->bind_param("iisdisdd", $booking_id, $user_id, $customer_name, $amount, $reward_id, $reward_name, $discount_amount, $original_amount);

    if (!$stmt->execute()) {
        throw new Exception("Failed to create COD payment request: " . $stmt->error);
    }

    $payment_id = $stmt->insert_id;
    $stmt->close();

    // Update the final_amount in the bookings table
    $update_booking_stmt = $conn->prepare("UPDATE bookings SET final_amount = ? WHERE id = ?");
    $update_booking_stmt->bind_param("di", $amount, $booking_id);
    $update_booking_stmt->execute();
    $update_booking_stmt->close();

    // Send admin notification about new COD request
    $admin_title = "New Cash on Delivery Request";
    $admin_message = "New cash on delivery request #$payment_id from $customer_name for ₱" . number_format($amount, 2);
    if ($reward_name && $discount_amount > 0) {
        $admin_message .= " (with $reward_name: -₱" . number_format($discount_amount, 2) . ")";
    }
    sendAdminNotification($admin_title, $admin_message, 'cash_request', $payment_id);

    // Mark reward as Used if provided
    if ($reward_id) {
        $update_reward_stmt = $conn->prepare("UPDATE claimed_rewards SET status = 'Used' WHERE id = ? AND user_id = ?");
        if ($update_reward_stmt) {
            $update_reward_stmt->bind_param("ii", $reward_id, $user_id);
            $update_reward_stmt->execute();
            $update_reward_stmt->close();
        }
    }

    // Send user notification about COD submission
    $user_notification = $conn->prepare("
        INSERT INTO notifications 
        (user_id, booking_id, title, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $user_title = "Cash on Delivery Order Submitted";
    $user_message = "Your Cash on Delivery order for booking #$booking_id has been submitted. Amount: ₱" . number_format($amount, 2) . ". Awaiting admin confirmation.";
    
    $user_notification->bind_param("iiss", $user_id, $booking_id, $user_title, $user_message);
    
    if (!$user_notification->execute()) {
        error_log("Warning: Failed to create user notification for COD payment");
    }
    $user_notification->close();

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'COD payment submitted successfully',
        'booking_id' => $booking_id,
        'amount' => $amount,
        'payment_id' => $payment_id
    ]);

} catch (Exception $e) {
    error_log("COD Payment Handler Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your payment. Please try again.'
    ]);
}

exit;
?>
