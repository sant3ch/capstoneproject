<?php
session_start();
require '../config.php';
require '../includes/maintenance-functions.php';

// Assuming this endpoint is accessed by logged-in users (not just admin)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $laundry_weight = isset($_POST['laundry_weight']) ? floatval($_POST['laundry_weight']) : 0;
    $selected_services = isset($_POST['selected_services']) ? $_POST['selected_services'] : [];
    $detergent = isset($_POST['detergent']) ? $_POST['detergent'] : null;

    if (!is_array($selected_services)) {
        $selected_services = explode(',', $selected_services);
    }

    $total_points = 1; // Fixed 1 point per transaction

    if ($booking_id <= 0 || empty($payment_method) || $total_amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid payment data']);
        exit();
    }

    // Fetch booking and user info with additional validation
    $query = "
        SELECT b.*, u.first_name, u.last_name, u.user_points AS points, u.id as user_id
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
        AND b.status != 'Completed'
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if (!$booking) {
        echo json_encode(['status' => 'error', 'message' => 'Booking not found or already completed']);
        exit();
    }

    $customer_name = $booking['first_name'] . ' ' . $booking['last_name'];
    $user_id = $booking['user_id'];
    
    // ========== HANDLE MULTIPLE SERVICES ==========
    // Get selected services from form
    $selected_services = isset($_POST['selected_services']) ? $_POST['selected_services'] : [];
    if (!is_array($selected_services)) {
        $selected_services = explode(',', $selected_services);
    }

    // If no services from form, use booking service
    if (empty($selected_services) || (count($selected_services) == 1 && empty($selected_services[0]))) {
        $selected_services = [$booking['service_type']];
    }

    // Clean up the array
    $selected_services = array_filter(array_map('trim', $selected_services));
    
    // Remove duplicates
    $selected_services = array_unique($selected_services);

    // Determine payment service type(s)
    $payment_service_types = [];
    foreach ($selected_services as $service) {
        if (stripos($service, 'Self-Service') !== false) {
            $payment_service_types[] = 'self-service';
        } else {
            $payment_service_types[] = 'full-service';
        }
    }
    // Remove duplicates and create string
    $payment_service_type = implode(',', array_unique($payment_service_types));

    // For transactions - store actual service names
    $transaction_service_type = implode(', ', $selected_services);

    // For updating booking - store all services
    $booking_service_types = implode(', ', $selected_services);

    $conn->begin_transaction();

    try {
        // Determine holiday status
        require_once '../includes/holiday-utils.php';
        $is_holiday = isPhilippineHoliday($booking['booking_date']) ? 1 : 0;
        $holiday_name = getPhilippineHoliday($booking['booking_date']);

        // Insert into transactions table - store ALL services
        $insert = $conn->prepare("
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
                is_holiday,
                holiday_name
            ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)
        ");

        $insert->bind_param(
            "ssdsiidiis",
            $customer_name,
            $transaction_service_type, // Store all service names
            $total_amount,
            $payment_method,
            $user_id,
            $booking_id,
            $laundry_weight,
            $total_points,
            $is_holiday,
            $holiday_name
        );

        if (!$insert->execute()) {
            throw new Exception('Failed to record transaction: ' . $conn->error);
        }

        $transaction_id = $conn->insert_id;

        // Check for existing payment
        $check_payment = $conn->prepare("SELECT id FROM payments WHERE booking_id = ?");
        $check_payment->bind_param("i", $booking_id);
        $check_payment->execute();
        $check_result = $check_payment->get_result();

        if ($check_result->num_rows === 0) {
            // Insert into payments table WITH service_type(s)
            $insert_payment = $conn->prepare("
                INSERT INTO payments (
                    user_id, 
                    amount, 
                    service_type, 
                    booking_id, 
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $insert_payment->bind_param("idsi", $user_id, $total_amount, $payment_service_type, $booking_id);
            if (!$insert_payment->execute()) {
                throw new Exception('Failed to insert into payments table: ' . $conn->error);
            }
        } else {
            // Update existing payment with correct service_type(s)
            $update_payment = $conn->prepare("
                UPDATE payments 
                SET 
                    amount = ?,
                    service_type = ?,
                    created_at = NOW()
                WHERE booking_id = ?
            ");
            $update_payment->bind_param("dsi", $total_amount, $payment_service_type, $booking_id);
            if (!$update_payment->execute()) {
                throw new Exception('Failed to update payments table: ' . $conn->error);
            }
        }

        // Update user points
        $new_points = $booking['points'] + $total_points;
        $update_points = $conn->prepare("UPDATE users SET user_points = ? WHERE id = ?");
        $update_points->bind_param("ii", $new_points, $user_id);
        if (!$update_points->execute()) {
            throw new Exception('Failed to update user points');
        }

        // Update booking status - store ALL services
        $update_booking = $conn->prepare("
            UPDATE bookings 
            SET 
                status = 'Completed',
                service_type = ?,
                detergent = ?
            WHERE id = ?
        ");
        $update_booking->bind_param("ssi", $booking_service_types, $detergent, $booking_id);
        if (!$update_booking->execute()) {
            throw new Exception('Failed to update booking status');
        }

        // Update machine status back to Available and increment usage count
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
                    } else {
                        error_log("Machine marked as Available and usage incremented after payment: " . $machine_name . " for booking #" . $booking_id);
                        
                        // Get updated machine details to check if maintenance is needed
                        $check_machine = $conn->prepare("SELECT id, machine_type, usage_count FROM machines WHERE machine_name = ?");
                        $check_machine->bind_param("s", $machine_name);
                        $check_machine->execute();
                        $machine_result = $check_machine->get_result();
                        $machine_data = $machine_result->fetch_assoc();
                        $check_machine->close();
                        
                        if ($machine_data) {
                            // Define threshold based on machine type
                            $usage_threshold = 10; // Both washer and dryer use 10
                            
                            // Check if usage just reached threshold (current = 10, previous = 9)
                            if ($machine_data['usage_count'] >= $usage_threshold && $machine_data['usage_count'] - 1 < $usage_threshold) {
                                // Usage just reached 10, auto-trigger maintenance
                                $maintenance_update = $conn->prepare("UPDATE machines SET status = 'Needs Maintenance' WHERE id = ?");
                                $maintenance_update->bind_param("i", $machine_data['id']);
                                $maintenance_update->execute();
                                $maintenance_update->close();
                                
                                // Log the auto-triggered maintenance event
                                logMaintenanceEvent(
                                    $machine_data['id'],
                                    $machine_name,
                                    'usage-based',
                                    $machine_data['usage_count'],
                                    'Machine automatically triggered for maintenance after reaching 10 uses during booking #' . $booking_id,
                                    'scheduled',
                                    $conn
                                );
                                
                                error_log("Machine " . $machine_name . " automatically set to 'Needs Maintenance' after reaching 10 uses");
                            }
                        }
                    }
                    $machine_update->close();
                }
            }
        }

        // Notification here after success
        $conn->commit();
        
        // Send notification after successful commit
        require_once 'send_queue_notification.php';
        sendQueueNotification($booking_id, $conn);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Payment processed successfully',
            'points_earned' => $total_points,
            'new_balance' => $new_points,
            'transaction_id' => $transaction_id,
            'selected_services' => $selected_services, // For debugging
            'payment_service_type' => $payment_service_type, // For debugging
            'transaction_service_type' => $transaction_service_type // For debugging
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'status' => 'error',
            'message' => 'Payment processing failed: ' . $e->getMessage(),
            'error_code' => $conn->errno
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);}