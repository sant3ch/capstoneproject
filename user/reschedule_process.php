<?php
include '../config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];

    // Fetch and sanitize POST data
    $booking_id = intval($_POST['booking_id']);
    $booking_date = trim($_POST['booking_date'] ?? '');
    $time_slot = trim($_POST['time_slot'] ?? '');
    $machine_count = intval($_POST['machine_count'] ?? 0);
    $selected_services = $_POST['service_type'] ?? [];
    $selected_machines = $_POST['machine_type'] ?? [];
    $request_services = $_POST['request_service'] ?? [];
    $selected_laundry_supplies_json = $_POST['selected_laundry_supplies_json'] ?? '[]';

    $selected_laundry_items = json_decode($selected_laundry_supplies_json, true);
    if (!is_array($selected_laundry_items)) {
        $selected_laundry_items = [];
    }

    // Handle JSON fallback for services and machines (used by reschedule_booking.php)
    if (empty($selected_services) && !empty($_POST['selected_services_json'])) {
        $selected_services = json_decode($_POST['selected_services_json'], true) ?? [];
    }
    
    if (empty($selected_machines) && !empty($_POST['selected_machines_json'])) {
        $selected_machines = json_decode($_POST['selected_machines_json'], true) ?? [];
    }

    // Handle machine count fallback
    if ($machine_count === 0) {
        if (!empty($_POST['machine_count'])) {
            $machine_count = intval($_POST['machine_count']);
        } else {
            // Use count of selected machines as fallback
            $machine_count = count($selected_machines);
            if ($machine_count === 0 && isset($old_booking)) {
                $machine_count = intval($old_booking['machine_count']);
            }
        }
    }

    // Validation
    // Handle machine count fallback (initial check)
    if ($machine_count === 0 && !empty($_POST['machine_count'])) {
        $machine_count = intval($_POST['machine_count']);
    }

    // Convert selected service types and request services to strings
    $service_type = implode(', ', $selected_services);
    $machine_names = implode(', ', $selected_machines);
    $request_service = implode(', ', $request_services);
    $detergent_parts = [];
    foreach ($selected_laundry_items as $item) {
        if (is_array($item) && isset($item['name'])) {
            $qty = intval($item['qty'] ?? 1);
            $name = trim($item['name']);
            if ($name !== '') {
                $detergent_parts[] = $qty . 'x ' . $name;
            }
        }
    }
    $detergent = !empty($detergent_parts) ? implode(', ', $detergent_parts) : 'N/A';

    // Check if the booking exists and belongs to the user
    $check_query = "SELECT * FROM bookings WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        echo "Error: Booking not found or access denied.";
        exit();
    }

    // Get the old booking details before updating
    $old_booking = $result->fetch_assoc();

    // Final fallbacks after fetching old booking
    if (empty($selected_services)) {
        $selected_services = !empty($old_booking['service_type']) ? array_map('trim', explode(',', $old_booking['service_type'])) : [];
    }
    if (empty($selected_machines)) {
        $selected_machines = !empty($old_booking['machine_names']) ? array_map('trim', explode(',', $old_booking['machine_names'])) : [];
    }
    if (empty($request_services)) {
        $request_services = !empty($old_booking['request_service']) ? array_map('trim', explode(',', $old_booking['request_service'])) : [];
    }
    if ($machine_count <= 0) {
        $machine_count = intval($old_booking['machine_count'] ?? 1);
    }

    // Re-implode strings after fallbacks to ensure correct values are saved
    $service_type = implode(', ', $selected_services);
    $machine_names = implode(', ', $selected_machines);
    $request_service = implode(', ', $request_services);

    // Validation
    if (empty($booking_date)) {
        echo "Error: Please select a booking date.";
        exit();
    }

    if (empty($time_slot)) {
        echo "Error: Please select a time slot.";
        exit();
    }

    if (empty($selected_services)) {
        echo "Error: Please select at least one service type.";
        exit();
    }

    if ($machine_count <= 0) {
        echo "Error: Please enter a valid machine count.";
        exit();
    }

    $old_machines = !empty($old_booking['machine_names']) ? explode(', ', $old_booking['machine_names']) : [];
    $old_machines = array_map('trim', $old_machines);
    $old_detergent = !empty($old_booking['detergent']) ? $old_booking['detergent'] : '';

    // Update the booking
    $update_query = "UPDATE bookings SET booking_date = ?, time_slot = ?, service_type = ?, detergent = ?, machine_count = ?, request_service = ?, machine_names = ? WHERE id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssssissii", $booking_date, $time_slot, $service_type, $detergent, $machine_count, $request_service, $machine_names, $booking_id, $user_id);

    if ($update_stmt->execute()) {
        // ========== HANDLE MACHINE STATUS CHANGES ==========
        $new_machines = !empty($machine_names) ? explode(', ', $machine_names) : [];
        $new_machines = array_map('trim', $new_machines);
        
        // Free old machines that are no longer needed
        $machines_to_free = array_diff($old_machines, $new_machines);
        foreach ($machines_to_free as $machine_name) {
            if (!empty($machine_name)) {
                $machine_update = $conn->prepare("UPDATE machines SET status = 'Available' WHERE machine_name = ?");
                if ($machine_update) {
                    $machine_update->bind_param("s", $machine_name);
                    $machine_update->execute();
                    error_log("Machine freed during reschedule: $machine_name for booking #$booking_id");
                    $machine_update->close();
                }
            }
        }
        
        // Mark new machines as unavailable (only if they weren't already used)
        $machines_to_occupy = array_diff($new_machines, $old_machines);
        foreach ($machines_to_occupy as $machine_name) {
            if (!empty($machine_name)) {
                $machine_update = $conn->prepare("UPDATE machines SET status = 'Unavailable' WHERE machine_name = ?");
                if ($machine_update) {
                    $machine_update->bind_param("s", $machine_name);
                    $machine_update->execute();
                    error_log("Machine marked Unavailable during reschedule: $machine_name for booking #$booking_id");
                    $machine_update->close();
                }
            }
        }
        // ========== END MACHINE STATUS CHANGES ==========
        
        // ========== HANDLE INVENTORY CHANGES ==========
        // Restore old inventory items
        if (!empty($old_detergent)) {
            $old_detergent_list = explode(', ', $old_detergent);
            foreach ($old_detergent_list as $old_item) {
                $old_item = trim($old_item);
                if (empty($old_item) || $old_item === 'Bring my own detergent' || $old_item === 'Bring my own') {
                    continue;
                }
                
                $qty = 1;
                $item_name = $old_item;
                if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $old_item, $matches)) {
                    $qty = intval($matches[1]);
                    $item_name = trim($matches[2]);
                }
                
                $inventory_restore = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity + ? WHERE item_name = ?");
                if ($inventory_restore) {
                    $inventory_restore->bind_param("is", $qty, $item_name);
                    $inventory_restore->execute();
                    error_log("Inventory restored during reschedule: $qty × $item_name for booking #$booking_id");
                    $inventory_restore->close();
                }
            }
        }
        
        // Deduct new inventory items
        if (!empty($detergent)) {
            $new_detergent_list = explode(', ', $detergent);
            foreach ($new_detergent_list as $new_item) {
                $new_item = trim($new_item);
                if (empty($new_item) || $new_item === 'Bring my own detergent' || $new_item === 'Bring my own') {
                    continue;
                }
                
                $qty = 1;
                $item_name = $new_item;
                if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $new_item, $matches)) {
                    $qty = intval($matches[1]);
                    $item_name = trim($matches[2]);
                }
                
                $inventory_deduct = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE item_name = ? AND stock_quantity > 0");
                if ($inventory_deduct) {
                    $inventory_deduct->bind_param("is", $qty, $item_name);
                    $inventory_deduct->execute();
                    
                    if ($inventory_deduct->affected_rows > 0) {
                        error_log("Inventory deducted during reschedule: $qty × $item_name for booking #$booking_id");
                    } else {
                        error_log("Failed to deduct inventory during reschedule: $item_name - out of stock");
                    }
                    $inventory_deduct->close();
                }
            }
        }
        // ========== END INVENTORY CHANGES ==========
        // Add notification
        $title = "Booking Rescheduled";
        $message = "Your booking #$booking_id was successfully rescheduled to $booking_date at $time_slot.";
        $notif_query = "INSERT INTO notifications (user_id, booking_id, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("iiss", $user_id, $booking_id, $title, $message);
        $notif_stmt->execute();

        header("Location: user-profile.php?reschedule=success");
        exit();
    } else {
        echo "Error: Failed to update booking.";
    }
} else {
    echo "Error: Invalid request method.";
}
?>
