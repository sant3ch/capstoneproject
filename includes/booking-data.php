<?php
// includes/booking-data.php

function getTotalMachineCounts($conn) {
    // Get TOTAL machine counts (all machines regardless of status)
    $counts = [
        'washers' => 0,
        'dryers' => 0,
        'total' => 0
    ];
    
    // Simple direct query to get counts by type
    $query = "SELECT 
                machine_type, 
                COUNT(*) as count 
              FROM machines 
              GROUP BY machine_type";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['machine_type'] == 'washer') {
                $counts['washers'] = (int)$row['count'];
            } elseif ($row['machine_type'] == 'dryer') {
                $counts['dryers'] = (int)$row['count'];
            }
        }
    } else {
        // If grouping fails, try a different approach
        error_log("Group query failed, trying alternative method");
        
        $washer_query = "SELECT COUNT(*) as count FROM machines WHERE machine_type = 'washer'";
        $washer_result = mysqli_query($conn, $washer_query);
        if ($washer_result) {
            $washer_row = mysqli_fetch_assoc($washer_result);
            $counts['washers'] = (int)$washer_row['count'];
        }
        
        $dryer_query = "SELECT COUNT(*) as count FROM machines WHERE machine_type = 'dryer'";
        $dryer_result = mysqli_query($conn, $dryer_query);
        if ($dryer_result) {
            $dryer_row = mysqli_fetch_assoc($dryer_result);
            $counts['dryers'] = (int)$dryer_row['count'];
        }
    }
    
    $counts['total'] = $counts['washers'] + $counts['dryers'];
    
    return $counts;
}

function getAvailableMachines($conn) {
    // Get count of bookable machines (only Available and In Use statuses)
    // Exclude: Unavailable, Needs Maintenance, Under Maintenance, Maintenance, and machines with usage_count >= 10
    $washer_query = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM machines WHERE machine_type = 'washer' AND status IN ('Available', 'In Use') AND usage_count < 10");
    if (!$washer_query) {
        error_log("Failed to prepare washer query: " . mysqli_error($conn));
        $washer_count = 0;
    } else {
        mysqli_stmt_execute($washer_query);
        mysqli_stmt_bind_result($washer_query, $washer_count);
        mysqli_stmt_fetch($washer_query);
        mysqli_stmt_close($washer_query);
        $washer_count = (int)$washer_count;
    }
    
    // Get count of available dryers (excluding machines needing maintenance)
    $dryer_query = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM machines WHERE machine_type = 'dryer' AND status IN ('Available', 'In Use') AND usage_count < 10");
    if (!$dryer_query) {
        error_log("Failed to prepare dryer query: " . mysqli_error($conn));
        $dryer_count = 0;
    } else {
        mysqli_stmt_execute($dryer_query);
        mysqli_stmt_bind_result($dryer_query, $dryer_count);
        mysqli_stmt_fetch($dryer_query);
        mysqli_stmt_close($dryer_query);
        $dryer_count = (int)$dryer_count;
    }
    
    error_log("getAvailableMachines - Washers: $washer_count, Dryers: $dryer_count");
    
    return [
        'washers' => $washer_count,
        'dryers' => $dryer_count,
        'total' => $washer_count + $dryer_count
    ];
}

function getServices($conn) {
    $services = [];
    $query = mysqli_query($conn, "SELECT * FROM services");
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $services[] = $row;
        }
    }
    return $services;
}

function getDetergents($conn) {
    $detergents = [];
    $query = mysqli_query($conn, "SELECT * FROM inventory WHERE stock_quantity > 0");
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $detergents[] = $row;
        }
    }
    return $detergents;
}

function getInventoryByType($conn) {
    $inventory = [];
    $query = mysqli_query($conn, "SELECT * FROM inventory WHERE stock_quantity > 0 ORDER BY item_type, item_name");
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $inventory[] = $row;
        }
    }
    return $inventory;
}

function getWashingMachines($conn) {
    $machines = [];
    // Only get available machines (excluding machines needing maintenance), ordered by type
    $query = mysqli_query($conn, "SELECT * FROM machines WHERE status = 'Available' AND usage_count < 10 ORDER BY machine_type, machine_name LIMIT 0, 25");
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $machines[] = $row;
        }
    }
    return $machines;
}

function getAllMachines($conn) {
    $machines = [];
    // Get all machines regardless of status for rescheduling purposes
    $query = mysqli_query($conn, "SELECT * FROM machines ORDER BY machine_type, machine_name");
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $machines[] = $row;
        }
    }
    return $machines;
}

function getNotifications($conn, $userId) {
    $notifications = [];
    $stmt = mysqli_prepare($conn, "SELECT title, message, created_at FROM notifications 
                            WHERE user_id = ? AND is_read = 0 
                            ORDER BY created_at DESC LIMIT 10");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
        
        mysqli_stmt_close($stmt);
    }
    return $notifications;
}

/* ── Walk-in machine usage (reduces availability for online bookings) ──── */

function walkinTableExists($conn) {
    static $exists = null;
    if ($exists === null) {
        $r = @mysqli_query($conn, "SHOW TABLES LIKE 'walkin_reservations'");
        $exists = $r && mysqli_num_rows($r) > 0;
    }
    return $exists;
}

// Walk-in washer/dryer usage for one date, keyed by time slot.
function getWalkinUsageForDate($conn, $date) {
    $usage = [];
    if (!walkinTableExists($conn)) return $usage;
    $stmt = mysqli_prepare($conn, "
        SELECT time_slot,
               COALESCE(SUM(washers_used),0) AS w,
               COALESCE(SUM(dryers_used),0)  AS d
        FROM walkin_reservations
        WHERE booking_date = ? AND status <> 'Cancelled'
        GROUP BY time_slot");
    if (!$stmt) return $usage;
    mysqli_stmt_bind_param($stmt, "s", $date);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $usage[$row['time_slot']] = ['washers' => (int)$row['w'], 'dryers' => (int)$row['d']];
    }
    mysqli_stmt_close($stmt);
    return $usage;
}

// Walk-in usage across all dates, keyed by date then time slot (for the calendar).
function getWalkinUsageAllDates($conn) {
    $usage = [];
    if (!walkinTableExists($conn)) return $usage;
    $res = @mysqli_query($conn, "
        SELECT booking_date, time_slot,
               COALESCE(SUM(washers_used),0) AS w,
               COALESCE(SUM(dryers_used),0)  AS d
        FROM walkin_reservations
        WHERE status <> 'Cancelled'
        GROUP BY booking_date, time_slot");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $usage[$row['booking_date']][$row['time_slot']] =
                ['washers' => (int)$row['w'], 'dryers' => (int)$row['d']];
        }
    }
    return $usage;
}

function getBookedMachinesForDate($conn, $date, $time_slot) {
    $booked_machines = [
        'washers' => 0,
        'dryers' => 0
    ];
    
    // Get booked machines by type for this date and time slot
    $query = mysqli_prepare($conn, "
        SELECT m.machine_type, COUNT(*) as booked_count
        FROM bookings b
        JOIN machines m ON FIND_IN_SET(m.machine_name, b.machine_names)
        WHERE b.booking_date = ? AND b.time_slot = ? 
        AND b.status NOT IN ('Cancelled', 'Rejected')
        GROUP BY m.machine_type
    ");
    
    if ($query) {
        mysqli_stmt_bind_param($query, "ss", $date, $time_slot);
        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['machine_type'] == 'washer') {
                $booked_machines['washers'] += $row['booked_count'];
            } else {
                $booked_machines['dryers'] += $row['booked_count'];
            }
        }
        mysqli_stmt_close($query);
    }

    // Include walk-in usage for this slot
    $wu = getWalkinUsageForDate($conn, $date);
    if (isset($wu[$time_slot])) {
        $booked_machines['washers'] += $wu[$time_slot]['washers'];
        $booked_machines['dryers']  += $wu[$time_slot]['dryers'];
    }

    return $booked_machines;
}

function getAvailabilityForSlot($conn, $date, $time_slot) {
    // Get AVAILABLE machine counts (only machines with status='Available')
    $available_machines = getAvailableMachines($conn);
    
    $availability = [
        'available_washers' => $available_machines['washers'],
        'available_dryers' => $available_machines['dryers'],
        'total_washers' => $available_machines['washers'],
        'total_dryers' => $available_machines['dryers']
    ];
    
    // Get booked machines by type for this slot
    $query = mysqli_prepare($conn, "
        SELECT m.machine_type, COUNT(*) as booked_count
        FROM bookings b
        JOIN machines m ON FIND_IN_SET(m.machine_name, b.machine_names)
        WHERE b.booking_date = ? AND b.time_slot = ? 
        AND b.status NOT IN ('Cancelled', 'Rejected')
        GROUP BY m.machine_type
    ");
    
    if ($query) {
        mysqli_stmt_bind_param($query, "ss", $date, $time_slot);
        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['machine_type'] == 'washer') {
                $availability['available_washers'] -= $row['booked_count'];
            } else {
                $availability['available_dryers'] -= $row['booked_count'];
            }
        }
        mysqli_stmt_close($query);
    }

    // Subtract walk-in usage for this slot
    $wu = getWalkinUsageForDate($conn, $date);
    if (isset($wu[$time_slot])) {
        $availability['available_washers'] -= $wu[$time_slot]['washers'];
        $availability['available_dryers']  -= $wu[$time_slot]['dryers'];
    }

    // Ensure we don't have negative numbers
    $availability['available_washers'] = max(0, $availability['available_washers']);
    $availability['available_dryers'] = max(0, $availability['available_dryers']);

    return $availability;
}

function getBookedDatesForCalendar($conn) {
    $total_machines = getTotalMachineCounts($conn);
    $available_machines = [];
    
    $query = mysqli_prepare($conn, "
        SELECT b.booking_date, b.time_slot, m.machine_type, COUNT(*) as booked_count
        FROM bookings b
        JOIN machines m ON FIND_IN_SET(m.machine_name, b.machine_names)
        WHERE b.status NOT IN ('Cancelled', 'Rejected')
        GROUP BY b.booking_date, b.time_slot, m.machine_type
        ORDER BY b.booking_date, b.time_slot
    ");
    
    if ($query) {
        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);

        while ($row = mysqli_fetch_assoc($result)) {
            $date = $row['booking_date'];
            $time_slot = $row['time_slot'];
            
            if (!isset($available_machines[$date])) {
                $available_machines[$date] = [];
            }
            
            if (!isset($available_machines[$date][$time_slot])) {
                $available_machines[$date][$time_slot] = [
                    'washers' => $total_machines['washers'],
                    'dryers' => $total_machines['dryers']
                ];
            }
            
            // Subtract booked machines
            if ($row['machine_type'] == 'washer') {
                $available_machines[$date][$time_slot]['washers'] -= $row['booked_count'];
            } else {
                $available_machines[$date][$time_slot]['dryers'] -= $row['booked_count'];
            }
        }
        mysqli_stmt_close($query);
    }

    // Merge walk-in usage into the calendar availability
    $walkin_all = getWalkinUsageAllDates($conn);
    foreach ($walkin_all as $wdate => $slots) {
        foreach ($slots as $wslot => $u) {
            if (!isset($available_machines[$wdate][$wslot])) {
                $available_machines[$wdate][$wslot] = [
                    'washers' => $total_machines['washers'],
                    'dryers'  => $total_machines['dryers'],
                ];
            }
            $available_machines[$wdate][$wslot]['washers'] -= $u['washers'];
            $available_machines[$wdate][$wslot]['dryers']  -= $u['dryers'];
        }
    }

    return $available_machines;
}

// Enhanced function: Get machine counts by type (operational status)
function getMachineCountsByType($conn) {
    $counts = [
        'washers' => 0,
        'dryers' => 0,
        'total' => 0
    ];
    
    $query = mysqli_query($conn, "
        SELECT machine_type, COUNT(*) as count 
        FROM machines 
        WHERE status IN ('Available', 'In Use') AND usage_count < 10
        GROUP BY machine_type
    ");
    
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            if ($row['machine_type'] == 'washer') {
                $counts['washers'] = $row['count'];
            } else {
                $counts['dryers'] = $row['count'];
            }
            $counts['total'] += $row['count'];
        }
    }
    
    return $counts;
}

function getDetailedDateAvailability($conn, $date) {
    // Get AVAILABLE machine counts (only machines with status='Available') for booking availability
    $total_machines = getAvailableMachines($conn);

    error_log("Available machines - Washers: " . $total_machines['washers'] . ", Dryers: " . $total_machines['dryers']);

    // Define time slots
    $time_slots = [
        "7:00 AM - 8:30 AM",
        "8:30 AM - 10:00 AM",
        "10:00 AM - 11:30 AM",
        "1:00 PM - 2:30 PM",
        "2:30 PM - 4:00 PM",
        "4:00 PM - 5:30 PM",
        "5:30 PM - 7:00 PM"
    ];

    // --- Fetch all bookable machine names ---
    $all_washers = [];
    $all_dryers  = [];
    $mach_result = mysqli_query($conn, "
        SELECT machine_name, machine_type
        FROM machines
        WHERE status IN ('Available', 'In Use') AND usage_count < 10
        ORDER BY machine_type, machine_name
    ");
    if ($mach_result) {
        while ($m = mysqli_fetch_assoc($mach_result)) {
            if ($m['machine_type'] === 'washer') {
                $all_washers[] = $m['machine_name'];
            } else {
                $all_dryers[] = $m['machine_name'];
            }
        }
    }

    // Initialize booked counts + booked machine name sets per slot
    $booked_by_type   = [];
    $booked_names_slot = []; // slot => [machine_name, ...]
    foreach ($time_slots as $slot) {
        $booked_by_type[$slot]    = ['washers' => 0, 'dryers' => 0];
        $booked_names_slot[$slot] = [];
    }

    // Get bookings for the date
    $stmt = mysqli_prepare($conn, "
        SELECT time_slot, machine_names
        FROM bookings
        WHERE booking_date = ?
        AND status NOT IN ('Cancelled', 'Rejected')
    ");

    if (!$stmt) {
        error_log("Error preparing booking query: " . mysqli_error($conn));
        return generateDefaultAvailability($time_slots, $total_machines, $all_washers, $all_dryers);
    }

    mysqli_stmt_bind_param($stmt, "s", $date);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Error executing booking query: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return generateDefaultAvailability($time_slots, $total_machines, $all_washers, $all_dryers);
    }

    $result       = mysqli_stmt_get_result($stmt);
    $booking_count = 0;

    while ($booking = mysqli_fetch_assoc($result)) {
        $booking_count++;
        $slot = $booking['time_slot'];

        if (!isset($booked_by_type[$slot])) {
            error_log("Unknown time slot in booking: " . $slot);
            continue;
        }

        $machines = explode(',', $booking['machine_names']);

        foreach ($machines as $machine_name) {
            $machine_name = trim($machine_name);
            if ($machine_name === '') continue;

            // Track booked name for this slot
            $booked_names_slot[$slot][] = $machine_name;

            if (stripos($machine_name, 'washer') !== false) {
                $booked_by_type[$slot]['washers']++;
            } elseif (stripos($machine_name, 'dryer') !== false) {
                $booked_by_type[$slot]['dryers']++;
            } else {
                error_log("Unknown machine type for: $machine_name");
            }
        }
    }

    mysqli_stmt_close($stmt);
    error_log("Processed $booking_count bookings for date $date");

    // Fold in walk-in usage (reduces availability the same as online bookings)
    $walkin_usage = getWalkinUsageForDate($conn, $date);
    foreach ($walkin_usage as $slot => $u) {
        if (!isset($booked_by_type[$slot])) continue;
        $booked_by_type[$slot]['washers'] += $u['washers'];
        $booked_by_type[$slot]['dryers']  += $u['dryers'];
    }

    // Compute availability
    $availability = [];

    foreach ($time_slots as $slot) {
        $booked_w = $booked_by_type[$slot]['washers'];
        $booked_d = $booked_by_type[$slot]['dryers'];

        $available_w = max(0, $total_machines['washers'] - $booked_w);
        $available_d = max(0, $total_machines['dryers'] - $booked_d);

        $has_any_booking = ($booked_w > 0 || $booked_d > 0);

        // Determine which specific machines are still free in this slot
        $booked_in_slot = $booked_names_slot[$slot];
        $free_washers   = array_values(array_filter($all_washers, fn($m) => !in_array($m, $booked_in_slot)));
        $free_dryers    = array_values(array_filter($all_dryers,  fn($m) => !in_array($m, $booked_in_slot)));

        $availability[$slot] = [
            'washers' => [
                'available' => $available_w,
                'booked'    => $booked_w,
                'total'     => $total_machines['washers'],
                'full'      => ($available_w <= 0),
                'machines'  => $free_washers,
            ],
            'dryers' => [
                'available' => $available_d,
                'booked'    => $booked_d,
                'total'     => $total_machines['dryers'],
                'full'      => ($available_d <= 0),
                'machines'  => $free_dryers,
            ],
            'total' => [
                'available'       => $available_w + $available_d,
                'booked'          => $booked_w + $booked_d,
                'total'           => $total_machines['washers'] + $total_machines['dryers'],
                'full'            => ($available_w <= 0 && $available_d <= 0),
                'has_any_booking' => $has_any_booking,
            ],
        ];
    }

    return $availability;
}

// Helper function to generate default availability (defined only ONCE)
function generateDefaultAvailability($time_slots, $total_machines, $all_washers = [], $all_dryers = []) {
    $availability = [];
    foreach ($time_slots as $slot) {
        $availability[$slot] = [
            'washers' => [
                'available' => $total_machines['washers'],
                'booked'    => 0,
                'total'     => $total_machines['washers'],
                'full'      => false,
                'machines'  => $all_washers,
            ],
            'dryers' => [
                'available' => $total_machines['dryers'],
                'booked'    => 0,
                'total'     => $total_machines['dryers'],
                'full'      => false,
                'machines'  => $all_dryers,
            ],
            'total' => [
                'available'       => $total_machines['washers'] + $total_machines['dryers'],
                'booked'          => 0,
                'total'           => $total_machines['washers'] + $total_machines['dryers'],
                'full'            => false,
                'has_any_booking' => false,
            ],
        ];
    }
    return $availability;
}

function checkSlotAvailability($conn, $date, $time_slot) {
    $total_machines = getTotalMachineCounts($conn);
    $booked = getBookedMachinesForDate($conn, $date, $time_slot);
    
    return [
        'washers_available' => max(0, $total_machines['washers'] - $booked['washers']),
        'dryers_available' => max(0, $total_machines['dryers'] - $booked['dryers']),
        'total_washers' => $total_machines['washers'],
        'total_dryers' => $total_machines['dryers'],
        'has_washer_availability' => ($total_machines['washers'] - $booked['washers']) > 0,
        'has_dryer_availability' => ($total_machines['dryers'] - $booked['dryers']) > 0,
        'fully_booked' => ($total_machines['washers'] - $booked['washers'] <= 0 && 
                          $total_machines['dryers'] - $booked['dryers'] <= 0)
    ];
}

/**
 * Get inventory items with their current stock levels
 * Returns items with stock_quantity > 0
 */
function getInventoryWithStock($conn) {
    $inventory = [];
    $query = mysqli_query($conn, "
        SELECT id, item_name, item_type, stock_quantity 
        FROM inventory 
        WHERE stock_quantity > 0 
        ORDER BY item_type, item_name
    ");
    
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $inventory[] = $row;
        }
    }
    return $inventory;
}

/**
 * Check if inventory items are available
 * Returns array of items with low stock or out of stock
 */
function checkInventoryAvailability($conn, $items) {
    $unavailable_items = [];
    
    if (empty($items)) {
        return $unavailable_items;
    }
    
    // Create placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($items), '?'));
    $types = str_repeat('s', count($items));
    
    $query = "SELECT item_name, stock_quantity FROM inventory WHERE item_name IN ($placeholders)";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$items);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['stock_quantity'] <= 0) {
                $unavailable_items[] = $row['item_name'];
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    return $unavailable_items;
}
?>