<?php
// includes/reschedule-init.php

require_once __DIR__ . '/booking-data.php';

/**
 * Initialize reschedule page with all required data
 * Returns an array with all data needed for the reschedule page
 * 
 * @param mysqli $conn Database connection
 * @param string $booking_id Booking ID from GET parameter
 * @return array Array containing all page data
 */
function initReschedulePage($conn, $booking_id) {
    global $washers, $dryers;
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
    
    // Validate booking ID
    if (!$booking_id) {
        echo "Booking ID missing.";
        exit();
    }
    
    // Fetch existing booking data with security check
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        echo "Booking not found or you don't have permission to reschedule it.";
        exit();
    }
    
    // Check if booking can still be rescheduled
    if (($booking['order_stage'] ?? '') !== 'Pending / Booked') {
        $_SESSION['error'] = "The booking has already started and can no longer be rescheduled.";
        header("Location: user-profile.php");
        exit();
    }
    
    // Fetch available services
    $services_result = $conn->query("SELECT * FROM services");
    $services = [];
    while ($row = $services_result->fetch_assoc()) {
        $services[] = $row;
    }

    // Shared booking page data used by the book-now layout
    $total_machines = getTotalMachineCounts($conn);
    $available_machines_status = getAvailableMachines($conn);

    $total_machines['washers'] = (int)($total_machines['washers'] ?? 0);
    $total_machines['dryers'] = (int)($total_machines['dryers'] ?? 0);

    $time_slots = [
        "7:00 AM - 8:30 AM", "8:30 AM - 10:00 AM", "10:00 AM - 11:30 AM",
        "1:00 PM - 2:30 PM", "2:30 PM - 4:00 PM", "4:00 PM - 5:30 PM",
        "5:30 PM - 7:00 PM"
    ];

    // Fetch inventory items for the laundry selector
    $inventory_items = [];
    $inventory_result = $conn->query("SELECT * FROM inventory WHERE stock_quantity > 0 ORDER BY item_type, item_name");
    if ($inventory_result) {
        while ($row = $inventory_result->fetch_assoc()) {
            $inventory_items[] = $row;
        }
    }

    $detergents = [];
    $fabric_conditioners = [];
    foreach ($inventory_items as $item) {
        if (($item['item_type'] ?? '') === 'detergent') {
            $detergents[] = $item;
        } elseif (($item['item_type'] ?? '') === 'fabric_conditioner') {
            $fabric_conditioners[] = $item;
        }
    }
    
    // Fetch available washing machines for the selector
    $washingMachine = getAllMachines($conn);
    $washers = [];
    $dryers = [];
    
    foreach ($washingMachine as $machine) {
        if ($machine['machine_type'] == 'washer') {
            $washers[] = $machine;
        } elseif ($machine['machine_type'] == 'dryer') {
            $dryers[] = $machine;
        }
    }
    
    // Get current month and year for calendar - use booking's month
    $bookingDate = new DateTime($booking['booking_date']);
    $currentMonth = (int)$bookingDate->format('n');
    $currentYear = (int)$bookingDate->format('Y');
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
    $firstDay = date('N', strtotime("$currentYear-$currentMonth-01"));
    
    // Fetch notifications for the user
    $notifications = [];
    $notification_query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
    $notification_stmt = $conn->prepare($notification_query);
    $notification_stmt->bind_param("i", $_SESSION['user_id']);
    $notification_stmt->execute();
    $notification_result = $notification_stmt->get_result();
    while ($row = $notification_result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    // Parse existing booking data for form pre-selection
    $selected_services = [];
    if (!empty($booking['service_type'])) {
        $selected_services = explode(', ', $booking['service_type']);
    }
    
    $selected_request_services = [];
    if (!empty($booking['request_service'])) {
        $selected_request_services = explode(', ', $booking['request_service']);
    }

    $selected_machines = [];
    if (!empty($booking['machine_names'])) {
        $selected_machines = array_map('trim', explode(',', $booking['machine_names']));
    }

    $selected_laundry_items = [];
    if (!empty($booking['detergent']) && $booking['detergent'] !== 'N/A') {
        $raw_items = explode(',', $booking['detergent']);
        foreach ($raw_items as $raw_item) {
            $raw_item = trim($raw_item);
            if ($raw_item === '' || $raw_item === 'Bring my own detergent' || $raw_item === 'Bring my own') {
                continue;
            }

            $qty = 1;
            $name = $raw_item;
            if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $raw_item, $matches)) {
                $qty = (int)$matches[1];
                $name = trim($matches[2]);
            }

            $selected_laundry_items[] = [
                'name' => $name,
                'qty' => $qty,
                'timestamp' => time() + count($selected_laundry_items)
            ];
        }
    }
    
    // Return all data as an array
    return [
        'booking' => $booking,
        'services' => $services,
        'currentMonth' => $currentMonth,
        'currentYear' => $currentYear,
        'daysInMonth' => $daysInMonth,
        'firstDay' => $firstDay,
        'time_slots' => $time_slots,
        'total_machines' => $total_machines,
        'available_machines_status' => $available_machines_status,
        'notifications' => $notifications,
        'selected_services' => $selected_services,
        'selected_request_services' => $selected_request_services,
        'inventory_items' => $inventory_items,
        'detergents' => $detergents,
        'fabric_conditioners' => $fabric_conditioners,
        'selected_laundry_items' => $selected_laundry_items,
        'selected_machines' => $selected_machines,
        'washers' => $washers,
        'dryers' => $dryers,
        'user_id' => $_SESSION['user_id']
    ];
}

/**
 * Alternative version that handles the entire initialization
 * This version doesn't return data but sets variables directly
 * 
 * @param mysqli $conn Database connection
 * @param string $booking_id Booking ID from GET parameter
 */
function initReschedulePageDirect($conn, $booking_id) {
    global $booking, $services, $currentMonth, $currentYear, $daysInMonth, $firstDay;
    global $time_slots, $total_machines, $available_machines_status;
    global $notifications, $selected_services, $selected_request_services, $selected_machines;
    global $inventory_items, $detergents, $fabric_conditioners, $selected_laundry_items;
    global $washers, $dryers;
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
    
    // Validate booking ID
    if (!$booking_id) {
        echo "Booking ID missing.";
        exit();
    }
    
    // Fetch existing booking data with security check
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        echo "Booking not found or you don't have permission to reschedule it.";
        exit();
    }

    // Check if booking can still be rescheduled
    if (($booking['order_stage'] ?? '') !== 'Pending / Booked') {
        $_SESSION['error'] = "The booking has already started and can no longer be rescheduled.";
        header("Location: user-profile.php");
        exit();
    }
    
    // Fetch available services
    $services_result = $conn->query("SELECT * FROM services");
    $services = [];
    while ($row = $services_result->fetch_assoc()) {
        $services[] = $row;
    }

    // Shared booking page data used by the book-now layout
    $total_machines = getTotalMachineCounts($conn);
    $available_machines_status = getAvailableMachines($conn);

    $total_machines['washers'] = (int)($total_machines['washers'] ?? 0);
    $total_machines['dryers'] = (int)($total_machines['dryers'] ?? 0);

    $time_slots = [
        "7:00 AM - 8:30 AM", "8:30 AM - 10:00 AM", "10:00 AM - 11:30 AM",
        "1:00 PM - 2:30 PM", "2:30 PM - 4:00 PM", "4:00 PM - 5:30 PM",
        "5:30 PM - 7:00 PM"
    ];

    // Fetch inventory items for the laundry selector
    $inventory_items = [];
    $inventory_result = $conn->query("SELECT * FROM inventory WHERE stock_quantity > 0 ORDER BY item_type, item_name");
    if ($inventory_result) {
        while ($row = $inventory_result->fetch_assoc()) {
            $inventory_items[] = $row;
        }
    }

    $detergents = [];
    $fabric_conditioners = [];
    foreach ($inventory_items as $item) {
        if (($item['item_type'] ?? '') === 'detergent') {
            $detergents[] = $item;
        } elseif (($item['item_type'] ?? '') === 'fabric_conditioner') {
            $fabric_conditioners[] = $item;
        }
    }
    
    // Fetch available washing machines for the selector
    $washingMachine = getAllMachines($conn);
    $washers = [];
    $dryers = [];
    
    foreach ($washingMachine as $machine) {
        if ($machine['machine_type'] == 'washer') {
            $washers[] = $machine;
        } elseif ($machine['machine_type'] == 'dryer') {
            $dryers[] = $machine;
        }
    }
    
    // Get current month and year for calendar - use booking's month
    $bookingDate = new DateTime($booking['booking_date']);
    $currentMonth = (int)$bookingDate->format('n');
    $currentYear = (int)$bookingDate->format('Y');
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
    $firstDay = date('N', strtotime("$currentYear-$currentMonth-01"));
    
    // Fetch notifications for the user
    $notifications = [];
    $notification_query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
    $notification_stmt = $conn->prepare($notification_query);
    $notification_stmt->bind_param("i", $_SESSION['user_id']);
    $notification_stmt->execute();
    $notification_result = $notification_stmt->get_result();
    while ($row = $notification_result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    // Parse existing booking data for form pre-selection
    $selected_services = [];
    if (!empty($booking['service_type'])) {
        $selected_services = array_map('trim', explode(',', $booking['service_type']));
    }
    
    $selected_request_services = [];
    if (!empty($booking['request_service'])) {
        $selected_request_services = array_map('trim', explode(',', $booking['request_service']));
    }

    $selected_machines = [];
    if (!empty($booking['machine_names'])) {
        $selected_machines = array_map('trim', explode(',', $booking['machine_names']));
    }

    $selected_laundry_items = [];
    if (!empty($booking['detergent']) && $booking['detergent'] !== 'N/A') {
        $raw_items = explode(',', $booking['detergent']);
        foreach ($raw_items as $raw_item) {
            $raw_item = trim($raw_item);
            if ($raw_item === '' || $raw_item === 'Bring my own detergent' || $raw_item === 'Bring my own') {
                continue;
            }

            $qty = 1;
            $name = $raw_item;
            if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $raw_item, $matches)) {
                $qty = (int)$matches[1];
                $name = trim($matches[2]);
            }

            $selected_laundry_items[] = [
                'name' => $name,
                'qty' => $qty,
                'timestamp' => time() + count($selected_laundry_items)
            ];
        }
    }
}
?>