<?php

require_once 'booking-data.php';

function handleDateAvailabilityRequest($conn, $date) {
    // Validate date format strictly
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        http_response_code(400);
        error_log("Invalid date format received: " . $date);
        return ['error' => 'Invalid date format'];
    }

    error_log("Fetching availability for date: " . $date);
    
    try {
        // Use the enhanced function that returns detailed availability with washer/dryer breakdown
        $availability = getDetailedDateAvailability($conn, $date);
        
        if (!$availability || !is_array($availability)) {
            error_log("getDetailedDateAvailability returned invalid data: " . var_export($availability, true));
            // Return default availability if function fails
            return generateDefaultAvailability([
                "7:00 AM - 8:30 AM", "8:30 AM - 10:00 AM", "10:00 AM - 11:30 AM",
                "1:00 PM - 2:30 PM", "2:30 PM - 4:00 PM", "4:00 PM - 5:30 PM", 
                "5:30 PM - 7:00 PM"
            ], getAvailableMachines($conn));
        }
        
        // Log what we got back
        error_log("Availability data for $date: " . json_encode($availability));
        
        return $availability;
    } catch (Exception $e) {
        error_log("Exception in handleDateAvailabilityRequest: " . $e->getMessage());
        return ['error' => 'Failed to fetch availability: ' . $e->getMessage()];
    }
}

function handleDateSlotsRequest($conn, $date) {
    // Validate date format strictly
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        http_response_code(400);
        return ['error' => 'Invalid date format'];
    }

    // Return time slots booked on selected date
    $stmt = mysqli_prepare($conn, "SELECT b.time_slot 
                            FROM bookings b
                            WHERE b.booking_date = ? 
                            AND b.status NOT IN ('Cancelled', 'Rejected')");
    mysqli_stmt_bind_param($stmt, "s", $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $bookedSlots = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $bookedSlots[] = $row['time_slot'];
    }
    mysqli_stmt_close($stmt);

    return $bookedSlots;
}

function handleMonthRequest($conn, $month) {
    // Validate month format
    $monthObj = DateTime::createFromFormat('Y-m', $month);
    if (!$monthObj || $monthObj->format('Y-m') !== $month) {
        http_response_code(400);
        return ['error' => 'Invalid month format'];
    }

    $stmt = mysqli_prepare($conn, "SELECT DISTINCT booking_date FROM bookings 
                            WHERE DATE_FORMAT(booking_date, '%Y-%m') = ? 
                            AND status NOT IN ('Cancelled', 'Rejected')");
    mysqli_stmt_bind_param($stmt, "s", $month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $bookedDays = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $bookedDays[] = $row['booking_date'];
    }
    mysqli_stmt_close($stmt);

    return $bookedDays;
}

// Add this new handler function for getting availability for a specific date and slot
function handleSlotAvailabilityRequest($conn, $date, $time_slot) {
    // Validate inputs
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        http_response_code(400);
        return ['error' => 'Invalid date format'];
    }
    
    if (empty($time_slot)) {
        http_response_code(400);
        return ['error' => 'Time slot is required'];
    }
    
    // Get availability for this specific slot
    $availability = getAvailabilityForSlot($conn, $date, $time_slot);
    
    return $availability;
}

/**
 * Handle inventory request - returns current stock levels
 */
function handleInventoryRequest($conn) {
    // Get all inventory items
    $query = "SELECT id, item_name, item_type, stock_quantity FROM inventory ORDER BY item_type, item_name";
    $result = mysqli_query($conn, $query);
    
    $all_items = [];
    $inventory = []; // Only items with stock > 0
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $all_items[] = $row;
            if ($row['stock_quantity'] > 0) {
                $inventory[] = $row;
            }
        }
    }
    
    return [
        'success' => true,
        'inventory' => $inventory,
        'all_items' => $all_items
    ];
}
?>