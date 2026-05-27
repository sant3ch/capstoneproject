<?php
/**
 * Dashboard Statistics Functions
 * This file contains functions to fetch dashboard statistics
 */

/**
 * Fetches dashboard statistics from the database
 * 
 * @param mysqli $conn Database connection object
 * @return array Array containing all dashboard statistics
 */
function getDashboardStats($conn) {
    $stats = array();
    
    // Fetch total users
    $result_users = mysqli_query($conn, "SELECT COUNT(*) AS total_users FROM users");
    if ($result_users) {
        $stats['total_users'] = mysqli_fetch_assoc($result_users)['total_users'];
    } else {
        $stats['total_users'] = 0;
    }
    
    // Sales made today - from gcash_requests table (Official Source)
    $result_today_profit = mysqli_query($conn, "
        SELECT IFNULL(SUM(amount), 0) AS today_profit
        FROM gcash_requests
        WHERE DATE(approved_at) = CURDATE() AND status IN ('approved', 'completed')
    ");
    
    if ($result_today_profit) {
        $stats['today_profit'] = (float)mysqli_fetch_assoc($result_today_profit)['today_profit'];
    } else {
        $stats['today_profit'] = 0;
    }
    
    // Total sales (all time) - from gcash_requests table (Official Source)
    $result_total_profit = mysqli_query($conn, "
        SELECT IFNULL(SUM(amount), 0) AS total_profit
        FROM gcash_requests
        WHERE status IN ('approved', 'completed')
    ");
    
    if ($result_total_profit) {
        $stats['total_profit'] = (float)mysqli_fetch_assoc($result_total_profit)['total_profit'];
    } else {
        $stats['total_profit'] = 0;
    }
    
    // ========== TODAY'S BOOKINGS - UPDATED ==========
    // Check if we should count by booking_date OR created_at
    $use_created_at = false;
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'created_at'");
    if (mysqli_num_rows($check_column) > 0) {
        $use_created_at = true;
    }
    
    if ($use_created_at) {
        // Option A: Count bookings CREATED today (has created_at column)
        $result_today_bookings = mysqli_query($conn, "
            SELECT COUNT(*) AS today_bookings 
            FROM bookings 
            WHERE DATE(created_at) = CURDATE()
            AND status != 'Cancelled'
        ");
    } else {
        // Option B: Count bookings SCHEDULED for today (booking_date = today)
        $result_today_bookings = mysqli_query($conn, "
            SELECT COUNT(*) AS today_bookings 
            FROM bookings 
            WHERE DATE(booking_date) = CURDATE()
            AND status != 'Cancelled'
        ");
    }
    
    if ($result_today_bookings) {
        $stats['today_bookings'] = mysqli_fetch_assoc($result_today_bookings)['today_bookings'];
    } else {
        $stats['today_bookings'] = 0;
    }
    
    // Total bookings (all time) - exclude cancelled
    $result_total_bookings = mysqli_query($conn, "
        SELECT COUNT(*) AS total_bookings 
        FROM bookings
        WHERE status != 'Cancelled'
    ");
    if ($result_total_bookings) {
        $stats['total_bookings'] = mysqli_fetch_assoc($result_total_bookings)['total_bookings'];
    } else {
        $stats['total_bookings'] = 0;
    }
    
    // ========== MACHINE AVAILABILITY - UPDATED ==========
    // Count machines booked for TODAY only, exclude cancelled bookings
    $result_machines = mysqli_query($conn, "
        SELECT IFNULL(SUM(machine_count), 0) AS booked_machines 
        FROM bookings 
        WHERE DATE(booking_date) = CURDATE()
        AND status NOT IN ('Cancelled', 'Rejected')
    ");
    
    if ($result_machines) {
        $booked_machines = (int)mysqli_fetch_assoc($result_machines)['booked_machines'];
    } else {
        $booked_machines = 0;
    }
    
    // Get total machines from database
    $result_total_machines = mysqli_query($conn, "SELECT COUNT(*) AS total FROM machines WHERE status != 'Maintenance'");
    if ($result_total_machines) {
        $total_machines = (int)mysqli_fetch_assoc($result_total_machines)['total'];
        // If no machines found, use default 6
        $stats['total_machines'] = $total_machines > 0 ? $total_machines : 6;
    } else {
        $stats['total_machines'] = 6; // Default fallback
    }
    
    // Ensure booked machines doesn't exceed total
    $stats['booked_machines'] = min($booked_machines, $stats['total_machines']);
    $stats['available_machines'] = max(0, $stats['total_machines'] - $stats['booked_machines']);
    
    return $stats;
}

/**
 * Fetches recent bookings with proper service names
 * 
 * @param mysqli $conn Database connection object
 * @param int $limit Number of recent bookings to fetch (default: 6)
 * @return mysqli_result|false Query result or false on failure
 */
function getRecentBookings($conn, $limit = 6) {
    $recent_bookings = mysqli_query($conn, "
        SELECT b.id, u.first_name, u.last_name, b.booking_date, b.time_slot, 
               b.service_type, b.request_service, b.status 
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.status != 'Cancelled'
        ORDER BY b.booking_date DESC, b.time_slot DESC
        LIMIT $limit
    ");
    
    return $recent_bookings;
}
?>