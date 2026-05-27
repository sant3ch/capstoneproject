<?php
session_start();
header('Content-Type: application/json');

// Resolve config path relative to this file location
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please login first.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info for logging/display
$user_query = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit();
}

$user_name = $user['first_name'] . ' ' . $user['last_name'];

// Step 1: Get all completed bookings for this user without a matching payment
$sql = "
    SELECT 
        b.id AS booking_id, 
        b.user_id, 
        t.total_amount,
        t.service_type,
        t.payment_method,
        t.transaction_date,
        t.laundry_weight,
        t.points_earned
    FROM bookings b
    JOIN transactions t ON t.booking_id = b.id
    WHERE b.status = 'Completed'
    AND b.user_id = ?  -- Only sync for logged-in user
    AND NOT EXISTS (
        SELECT 1 FROM payments p WHERE p.booking_id = b.id
    )
    ORDER BY t.transaction_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$synced_count = 0;
$errors = [];

if ($result->num_rows > 0) {
    echo "<h2>Payment Sync for " . htmlspecialchars($user_name) . "</h2>";
    echo "<p>User ID: " . $user_id . "</p>";
    echo "<p>Email: " . htmlspecialchars($user['email']) . "</p>";
    echo "<hr>";
    
    // Start transaction for batch processing
    $conn->begin_transaction();
    
    try {
        while ($row = $result->fetch_assoc()) {
            $bookingId = $row['booking_id'];
            $userId = $row['user_id'];
            $amount = $row['total_amount'];
            $service_type = $row['service_type'];
            $payment_method = $row['payment_method'];
            $transaction_date = $row['transaction_date'];
            $laundry_weight = $row['laundry_weight'];
            $points_earned = $row['points_earned'] ?: 1;
            
            // Determine payment service type from service_type
            $payment_service_type = 'full-service'; // default
            if (stripos($service_type, 'Self-Service') !== false) {
                $payment_service_type = 'self-service';
            } elseif (stripos($service_type, 'Full-Service') !== false) {
                $payment_service_type = 'full-service';
            }
            
            // Get transaction ID for this booking
            $transaction_query = $conn->prepare("SELECT id FROM transactions WHERE booking_id = ? ORDER BY transaction_date DESC LIMIT 1");
            $transaction_query->bind_param("i", $bookingId);
            $transaction_query->execute();
            $transaction_result = $transaction_query->get_result();
            $transaction_data = $transaction_result->fetch_assoc();
            $transaction_id = $transaction_data ? $transaction_data['id'] : null;
            
            // Check if payment already exists (double-check)
            $check_payment = $conn->prepare("SELECT id FROM payments WHERE booking_id = ?");
            $check_payment->bind_param("i", $bookingId);
            $check_payment->execute();
            $check_result = $check_payment->get_result();
            
            if ($check_result->num_rows === 0) {
                // Insert payment record
                $insert_stmt = $conn->prepare("
                    INSERT INTO payments (
                        user_id, 
                        amount, 
                        service_type, 
                        booking_id, 
                        payment_method,
                        transaction_id,
                        status,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)
                ");
                
                $insert_stmt->bind_param(
                    "idssiss",
                    $userId,
                    $amount,
                    $payment_service_type,
                    $bookingId,
                    $payment_method,
                    $transaction_id,
                    $transaction_date
                );
                
                if ($insert_stmt->execute()) {
                    $synced_count++;
                    echo "<div style='color: green; margin-bottom: 10px;'>";
                    echo "✓ Synced payment for Booking #" . $bookingId . " - Amount: ₱" . number_format($amount, 2);
                    echo "<br><small>Service: " . htmlspecialchars($service_type) . " | Method: " . $payment_method . "</small>";
                    echo "</div>";
                    
                    // Update booking payment status if not already set
                    $update_booking = $conn->prepare("
                        UPDATE bookings 
                        SET payment_status = 'Paid', 
                            payment_date = ?
                        WHERE id = ? AND (payment_status IS NULL OR payment_status = 'Pending')
                    ");
                    $update_booking->bind_param("si", $transaction_date, $bookingId);
                    $update_booking->execute();
                    
                } else {
                    $errors[] = "Failed to sync payment for Booking #" . $bookingId . ": " . $conn->error;
                    echo "<div style='color: red; margin-bottom: 10px;'>";
                    echo "✗ Failed to sync payment for Booking #" . $bookingId;
                    echo "</div>";
                }
                
                $insert_stmt->close();
            } else {
                echo "<div style='color: orange; margin-bottom: 10px;'>";
                echo "⚠ Payment already exists for Booking #" . $bookingId;
                echo "</div>";
            }
            
            $transaction_query->close();
            $check_payment->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        echo "<hr>";
        
        if ($synced_count > 0) {
            echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
            echo "<h3>✅ Sync Completed Successfully!</h3>";
            echo "<p>Total payments synced: <strong>" . $synced_count . "</strong></p>";
            
            // Show summary
            $summary_query = $conn->prepare("
                SELECT COUNT(*) as total_payments, SUM(amount) as total_amount 
                FROM payments 
                WHERE user_id = ?
            ");
            $summary_query->bind_param("i", $user_id);
            $summary_query->execute();
            $summary_result = $summary_query->get_result();
            $summary = $summary_result->fetch_assoc();
            
            echo "<p>Your total payments in system: <strong>" . $summary['total_payments'] . "</strong></p>";
            echo "<p>Total amount paid: <strong>₱" . number_format($summary['total_amount'], 2) . "</strong></p>";
            echo "</div>";
            
        } else {
            echo "<div style='background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px;'>";
            echo "<h3>⚠ No New Payments to Sync</h3>";
            echo "<p>All your completed bookings already have payment records.</p>";
            echo "</div>";
        }
        
        // Log the sync activity
        $activity_log = "User #$user_id synced $synced_count payments";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity, created_at) VALUES (?, ?, NOW())");
        $log_stmt->bind_param("is", $user_id, $activity_log);
        $log_stmt->execute();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "<h3>❌ Sync Failed!</h3>";
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>No payments were synced due to an error.</p>";
        echo "</div>";
    }
    
} else {
    echo "<div style='background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px;'>";
    echo "<h3>No Completed Bookings Found</h3>";
    echo "<p>You don't have any completed bookings without payment records.</p>";
    echo "<p>User: " . htmlspecialchars($user_name) . " (ID: " . $user_id . ")</p>";
    echo "</div>";
}

// Display any errors
if (!empty($errors)) {
    echo "<hr><h3 style='color: red;'>Errors:</h3>";
    foreach ($errors as $error) {
        echo "<p style='color: red;'>" . htmlspecialchars($error) . "</p>";
    }
}

// Show link to user dashboard
echo "<hr>";
echo "<div style='margin-top: 20px;'>";
echo "<a href='user-profile.php' style='padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>← Return to Profile</a>";
echo "</div>";

// Close connection
$conn->close();
?>