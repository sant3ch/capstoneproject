<?php
session_start();
header('Content-Type: application/json');

// Resolve config path relative to this file location
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please login.']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $laundry_weight = isset($_POST['laundry_weight']) ? floatval($_POST['laundry_weight']) : 0;
    $selected_services = isset($_POST['selected_services']) ? $_POST['selected_services'] : [];
    $cash_amount = isset($_POST['cash_amount']) ? floatval($_POST['cash_amount']) : 0;
    $total_points = isset($_POST['total_points']) ? intval($_POST['total_points']) : 1; // Default 1 point

    // Ensure selected_services is an array
    if (!is_array($selected_services)) {
        $selected_services = explode(',', $selected_services);
    }

    // Clean up the array
    $selected_services = array_filter(array_map('trim', $selected_services));
    $selected_services = array_unique($selected_services);

    // Validate required fields
    if ($booking_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID']);
        exit();
    }

    if (empty($payment_method)) {
        echo json_encode(['status' => 'error', 'message' => 'Payment method is required']);
        exit();
    }

    if ($total_amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid total amount']);
        exit();
    }

    if ($laundry_weight <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid laundry weight']);
        exit();
    }

    if (empty($selected_services)) {
        echo json_encode(['status' => 'error', 'message' => 'No services selected']);
        exit();
    }

    // For Cash payment, validate cash amount
    if ($payment_method === 'Cash' && $cash_amount < $total_amount) {
        echo json_encode(['status' => 'error', 'message' => 'Cash amount must be at least equal to total amount']);
        exit();
    }

    // Fetch booking details with user verification
    $query = "
        SELECT b.*, u.first_name, u.last_name, u.email, u.phone, u.user_points,
               u.id as user_id
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
        AND u.id = ?  -- Verify the booking belongs to the logged-in user
        AND b.status IN ('Pending Payment', 'Pending')  -- Only allow payment for pending bookings
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if (!$booking) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Booking not found, already completed, or does not belong to you'
        ]);
        exit();
    }

    // Get customer name
    $customer_name = $booking['first_name'] . ' ' . $booking['last_name'];
    $current_points = $booking['user_points'];
    $new_points = $current_points + $total_points;

    // Determine service types for payment records
    $payment_service_types = [];
    foreach ($selected_services as $service) {
        if (stripos($service, 'Self-Service') !== false) {
            $payment_service_types[] = 'self-service';
        } else {
            $payment_service_types[] = 'full-service';
        }
    }
    $payment_service_type = implode(',', array_unique($payment_service_types));

    // For transaction record - store actual service names
    $transaction_service_type = implode(', ', $selected_services);

    // For updating booking - store all services
    $booking_service_types = implode(', ', $selected_services);

    // Start transaction
    $conn->begin_transaction();

    try {
        // 1. Insert into transactions table
        $insert_transaction = $conn->prepare("
            INSERT INTO transactions (
                customer_name, 
                service_type, 
                total_amount, 
                payment_method, 
                transaction_date,
                user_id,
                booking_id,
                laundry_weight,
                points_earned,
                cash_amount,
                change_amount
            ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)
        ");

        $change_amount = ($payment_method === 'Cash') ? ($cash_amount - $total_amount) : 0;
        
        $insert_transaction->bind_param(
            "ssdsiiiddd",
            $customer_name,
            $transaction_service_type,
            $total_amount,
            $payment_method,
            $user_id,
            $booking_id,
            $laundry_weight,
            $total_points,
            $cash_amount,
            $change_amount
        );

        if (!$insert_transaction->execute()) {
            throw new Exception('Failed to record transaction: ' . $conn->error);
        }

        $transaction_id = $conn->insert_id;

        // 2. Insert or update payments table
        $check_payment = $conn->prepare("SELECT id FROM payments WHERE booking_id = ?");
        $check_payment->bind_param("i", $booking_id);
        $check_payment->execute();
        $check_result = $check_payment->get_result();

        if ($check_result->num_rows === 0) {
            // Insert new payment
            $insert_payment = $conn->prepare("
                INSERT INTO payments (
                    user_id, 
                    amount, 
                    service_type, 
                    booking_id, 
                    payment_method,
                    transaction_id,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $insert_payment->bind_param(
                "idsisi",
                $user_id,
                $total_amount,
                $payment_service_type,
                $booking_id,
                $payment_method,
                $transaction_id
            );
            
            if (!$insert_payment->execute()) {
                throw new Exception('Failed to insert payment record: ' . $conn->error);
            }
        } else {
            // Update existing payment
            $update_payment = $conn->prepare("
                UPDATE payments 
                SET 
                    amount = ?,
                    service_type = ?,
                    payment_method = ?,
                    transaction_id = ?,
                    status = 'completed',
                    updated_at = NOW()
                WHERE booking_id = ?
            ");
            $update_payment->bind_param(
                "dssii",
                $total_amount,
                $payment_service_type,
                $payment_method,
                $transaction_id,
                $booking_id
            );
            
            if (!$update_payment->execute()) {
                throw new Exception('Failed to update payment record: ' . $conn->error);
            }
        }

        // 3. Update user points
        $update_points = $conn->prepare("UPDATE users SET user_points = ? WHERE id = ?");
        $update_points->bind_param("ii", $new_points, $user_id);
        if (!$update_points->execute()) {
            throw new Exception('Failed to update user points');
        }

        // 4. Update booking status
        $update_booking = $conn->prepare("
            UPDATE bookings 
            SET 
                status = 'Completed',
                service_type = ?,
                payment_status = 'Paid',
                payment_date = NOW()
            WHERE id = ?
        ");
        $update_booking->bind_param("si", $booking_service_types, $booking_id);
        
        if (!$update_booking->execute()) {
            throw new Exception('Failed to update booking status');
        }

        // 4.5 Update machine status back to Available and increment usage count
        if (!empty($booking['machine_names'])) {
            $machine_list = explode(', ', $booking['machine_names']);
            
            foreach ($machine_list as $machine_name) {
                $machine_name = trim($machine_name);
                
                if (!empty($machine_name)) {
                    // Update machine status back to 'Available' AND increment usage count
                    $machine_update = $conn->prepare("UPDATE machines SET status = 'Available', usage_count = usage_count + 1 WHERE machine_name = ?");
                    $machine_update->bind_param("s", $machine_name);
                    
                    if (!$machine_update->execute()) {
                        error_log("Failed to update machine status for: " . $machine_name . " - Error: " . $machine_update->error);
                        // Continue with other machines even if one fails
                    } else {
                        error_log("Machine marked as Available and usage incremented after payment: " . $machine_name . " for booking #" . $booking_id);
                    }
                    $machine_update->close();
                }
            }
        }

        // 5. Create notification for user
        $notification_message = "Payment of ₱" . number_format($total_amount, 2) . " for booking #" . $booking_id . " has been processed successfully. You earned " . $total_points . " points.";
        
        $insert_notification = $conn->prepare("
            INSERT INTO notifications (user_id, message, type, related_id, created_at)
            VALUES (?, ?, 'payment', ?, NOW())
        ");
        $insert_notification->bind_param("isi", $user_id, $notification_message, $transaction_id);
        $insert_notification->execute();

        // 6. Send email notification (optional)
        if (!empty($booking['email'])) {
            $subject = "Payment Confirmation - Booking #" . $booking_id;
            $message = "
                <html>
                <body>
                    <h2>Payment Confirmation</h2>
                    <p>Dear " . $booking['first_name'] . ",</p>
                    <p>Your payment has been processed successfully.</p>
                    
                    <h3>Payment Details:</h3>
                    <ul>
                        <li><strong>Transaction ID:</strong> " . $transaction_id . "</li>
                        <li><strong>Booking ID:</strong> #" . $booking_id . "</li>
                        <li><strong>Amount:</strong> ₱" . number_format($total_amount, 2) . "</li>
                        <li><strong>Payment Method:</strong> " . $payment_method . "</li>
                        <li><strong>Points Earned:</strong> " . $total_points . "</li>
                        <li><strong>New Points Balance:</strong> " . $new_points . "</li>
                    </ul>
                    
                    <p>Thank you for your business!</p>
                </body>
                </html>
            ";
            
            // Send email using your preferred method
            // mail($booking['email'], $subject, $message, $headers);
        }

        // Commit transaction
        $conn->commit();

        // Log the payment activity
        $activity_log = "User #$user_id processed payment for booking #$booking_id. Amount: ₱$total_amount, Method: $payment_method";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $log_stmt->bind_param("is", $user_id, $activity_log);
        $log_stmt->execute();

        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Payment processed successfully',
            'transaction_id' => $transaction_id,
            'points_earned' => $total_points,
            'new_balance' => $new_points,
            'total_amount' => $total_amount,
            'customer_name' => $customer_name,
            'payment_method' => $payment_method
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Log error
        error_log("Payment processing error for user $user_id, booking $booking_id: " . $e->getMessage());
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Payment processing failed: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>