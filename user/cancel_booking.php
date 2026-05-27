<?php
session_start();
require '../config.php';

// Make sure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to cancel a booking',
        'redirect' => '../login.php'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Set content type to JSON
header('Content-Type: application/json');

// Validate booking ID
if ($booking_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID provided'
    ]);
    exit();
}

try {
    // Use prepared statement to prevent SQL injection
    $check_query = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? AND status = 'Pending'");
    $check_query->bind_param("ii", $booking_id, $user_id);
    $check_query->execute();
    $result = $check_query->get_result();
    
    if ($result->num_rows === 0) {
        $check_query->close();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid booking ID, booking doesn\'t belong to you, or booking is already processed'
        ]);
        exit();
    }
    
    // Fetch the booking details
    $booking = $result->fetch_assoc();
    $service_type = $booking['service_type'] ?? 'Service';
    $booking_date = date("F j, Y", strtotime($booking['booking_date'] ?? ''));
    $time_slot = $booking['time_slot'] ?? '';
    $machine_names = $booking['machine_names'] ?? '';
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update booking status to 'Cancelled'
    $update_query = $conn->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ?");
    $update_query->bind_param("i", $booking_id);
    
    if (!$update_query->execute()) {
        throw new Exception("Failed to update booking status: " . $update_query->error);
    }
    
    // Update machine status back to 'Available'
    if (!empty($machine_names)) {
        $machine_list = explode(', ', $machine_names);
        
        foreach ($machine_list as $machine_name) {
            $machine_name = trim($machine_name);
            
            if (empty($machine_name)) continue;
            
            // Update machine status in machines table
            $machine_update = $conn->prepare("UPDATE machines SET status = 'Available' WHERE machine_name = ?");
            $machine_update->bind_param("s", $machine_name);
            
            if (!$machine_update->execute()) {
                error_log("Failed to update machine status for: " . $machine_name . " - Error: " . $machine_update->error);
                // Continue with other machines even if one fails
            }
            $machine_update->close();
            
            // Also remove from machine_schedule table if exists
            $schedule_delete = $conn->prepare("DELETE FROM machine_schedule WHERE machine_name = ? AND DATE(restore_time) = ?");
            $schedule_date = $booking['booking_date']; // Use booking date
            $schedule_delete->bind_param("ss", $machine_name, $schedule_date);
            
            if (!$schedule_delete->execute()) {
                error_log("Failed to delete from machine_schedule for: " . $machine_name . " - Error: " . $schedule_delete->error);
                // Continue even if schedule delete fails
            }
            $schedule_delete->close();
        }
    }
    
    // Restore inventory when booking is cancelled
    if (!empty($booking['detergent'])) {
        $detergent_list = explode(', ', $booking['detergent']);
        
        foreach ($detergent_list as $detergent_entry) {
            $detergent_entry = trim($detergent_entry);
            
            if (empty($detergent_entry) || $detergent_entry === 'Bring my own detergent' || $detergent_entry === 'Bring my own') {
                continue;
            }
            
            // Parse "Qty x ItemName" format (new format) or plain ItemName (old format)
            $qty = 1;
            $item_name = $detergent_entry;
            
            // Check if it matches the format "NUMBER x NAME"
            if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $detergent_entry, $matches)) {
                $qty = intval($matches[1]);
                $item_name = trim($matches[2]);
            }
            
            // Restore the inventory quantity
            $inventory_restore = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity + ? WHERE item_name = ?");
            if ($inventory_restore) {
                $inventory_restore->bind_param("is", $qty, $item_name);
                $inventory_restore->execute();
                
                if ($inventory_restore->affected_rows > 0) {
                    error_log("Inventory restored on cancel: $qty × $item_name for booking #$booking_id");
                } else {
                    error_log("Failed to restore inventory: $item_name (not found)");
                }
                $inventory_restore->close();
            }
        }
    }
    
    // Create a notification message
    $message = "Your booking for $service_type on $booking_date at $time_slot has been cancelled.";
    $title = "Booking #$booking_id Cancelled";
    
    $notification_query = $conn->prepare("INSERT INTO notifications (user_id, title, message, booking_id) VALUES (?, ?, ?, ?)");
    $notification_query->bind_param("issi", $user_id, $title, $message, $booking_id);
    
    if (!$notification_query->execute()) {
        error_log("Failed to create notification for cancelled booking: " . $notification_query->error);
        // Continue even if notification fails
    }
    $notification_query->close();
    
    // Commit transaction
    $conn->commit();
    
    // Close prepared statements
    $check_query->close();
    $update_query->close();
    
    // Add success message to session for when page reloads
    $_SESSION['success'] = "Booking #$booking_id cancelled successfully. Machines and inventory items have been released.";
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking cancelled successfully',
        'booking_id' => $booking_id,
        'redirect' => 'user-profile.php'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    
    error_log("Booking cancellation error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to cancel booking: ' . $e->getMessage()
    ]);
    exit();
    
} finally {
    // Close connection if open
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>