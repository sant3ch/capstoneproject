<?php
// includes/admin-notifications.php
require_once __DIR__ . '/booking-functions.php';

function sendAdminNotification($title, $message, $type = 'system', $related_id = null) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO admin_notifications (title, message, type, related_id, status, created_at) VALUES (?, ?, ?, ?, 'unread', NOW())");
    $stmt->bind_param("sssi", $title, $message, $type, $related_id);
    
    return $stmt->execute();
}

// Function to mark notification as read
function markAdminNotificationAsRead($notification_id) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE admin_notifications SET status = 'read', read_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    
    return $stmt->execute();
}

// Function to mark all admin notifications as read
function markAllAdminNotificationsAsRead() {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE admin_notifications SET status = 'read', read_at = NOW() WHERE status = 'unread'");
    
    return $stmt->execute();
}

// Function to get admin notifications
function getAdminNotifications($limit = 50, $unread_only = false) {
    global $conn;
    
    if ($unread_only) {
        $query = "SELECT * FROM admin_notifications WHERE status = 'unread' ORDER BY created_at DESC LIMIT ?";
    } else {
        $query = "SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}

// Function to get unread notification count
function getAdminUnreadCount() {
    global $conn;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE status = 'unread'");
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

/**
 * Get booking details including services and prices
 */
function getBookingDetailsForNotification($booking_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            b.id,
            b.service_type,
            b.detergent,
            b.machine_count,
            b.booking_date,
            b.time_slot,
            u.first_name,
            u.last_name,
            u.email
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ");
    
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        
        // Calculate total amount
        $total_amount = calculateBookingAmountFromDB($booking);
        
        return [
            'booking' => $booking,
            'total_amount' => $total_amount,
            'customer_name' => $booking['first_name'] . ' ' . $booking['last_name']
        ];
    }
    
    return null;
}

// Function to send GCASH request notification with amount
function sendGCASHRequestNotification($request_id, $customer_name, $amount, $booking_id = null) {
    $title = "New GCASH Payment Request";
    $formatted_amount = '₱' . number_format($amount, 2);
    
    $message = json_encode([
        'request_id' => $request_id,
        'customer_name' => $customer_name,
        'amount' => $amount,
        'formatted_amount' => $formatted_amount,
        'booking_id' => $booking_id,
        'type' => 'gcash_request',
        'message' => "New GCASH payment request from {$customer_name} for {$formatted_amount}"
    ]);
    
    return sendAdminNotification($title, $message, 'gcash_request', $request_id);
}

// Function to send GCASH request approval notification
function sendGCASHApprovalNotification($request_id, $customer_name, $amount, $reference_number, $booking_id = null) {
    $title = "GCASH Request Approved";
    $formatted_amount = '₱' . number_format($amount, 2);
    
    $message = json_encode([
        'request_id' => $request_id,
        'customer_name' => $customer_name,
        'amount' => $amount,
        'formatted_amount' => $formatted_amount,
        'reference_number' => $reference_number,
        'booking_id' => $booking_id,
        'type' => 'gcash_approved',
        'message' => "GCASH request #{$request_id} from {$customer_name} for {$formatted_amount} has been approved. Reference: {$reference_number}"
    ]);
    
    return sendAdminNotification($title, $message, 'gcash_approved', $request_id);
}

// Function to send GCASH rejection notification
function sendGCASHRejectionNotification($request_id, $customer_name, $amount, $reason = null, $booking_id = null) {
    $title = "GCASH Request Rejected";
    $formatted_amount = '₱' . number_format($amount, 2);
    
    $message = json_encode([
        'request_id' => $request_id,
        'customer_name' => $customer_name,
        'amount' => $amount,
        'formatted_amount' => $formatted_amount,
        'reason' => $reason,
        'booking_id' => $booking_id,
        'type' => 'gcash_rejected',
        'message' => "GCASH request #{$request_id} from {$customer_name} for {$formatted_amount} has been rejected" . ($reason ? " Reason: {$reason}" : "")
    ]);
    
    return sendAdminNotification($title, $message, 'gcash_rejected', $request_id);
}

// Function to send GCASH request completion notification (after payment is made)
function sendGCASHCompletionNotification($request_id, $customer_name, $amount, $reference_number, $booking_id = null) {
    $title = "GCASH Payment Completed";
    $formatted_amount = '₱' . number_format($amount, 2);
    
    $message = json_encode([
        'request_id' => $request_id,
        'customer_name' => $customer_name,
        'amount' => $amount,
        'formatted_amount' => $formatted_amount,
        'reference_number' => $reference_number,
        'booking_id' => $booking_id,
        'type' => 'gcash_completed',
        'message' => "GCASH payment for request #{$request_id} from {$customer_name} of {$formatted_amount} has been completed. Reference: {$reference_number}"
    ]);
    
    return sendAdminNotification($title, $message, 'gcash_completed', $request_id);
}

// ============================================
// INVENTORY NOTIFICATION FUNCTIONS
// ============================================

/**
 * Check for low stock items and send notifications
 * Call this function periodically (e.g., from a cron job or when inventory changes)
 */
function checkAndSendInventoryNotifications() {
    global $conn;
    
    // Get low stock items (stock < 5) with price
    $query = "SELECT id, item_name, stock_quantity, price FROM inventory WHERE stock_quantity < 5 AND stock_quantity > 0";
    $result = mysqli_query($conn, $query);
    
    $low_stock_items = [];
    $low_stock_count = 0;
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $low_stock_items[] = $row;
            $low_stock_count++;
        }
    }
    
    // Get out of stock items with price
    $out_of_stock_query = "SELECT id, item_name, price FROM inventory WHERE stock_quantity = 0";
    $out_of_stock_result = mysqli_query($conn, $out_of_stock_query);
    
    $out_of_stock_items = [];
    $out_of_stock_count = 0;
    
    if ($out_of_stock_result && mysqli_num_rows($out_of_stock_result) > 0) {
        while ($row = mysqli_fetch_assoc($out_of_stock_result)) {
            $out_of_stock_items[] = $row;
            $out_of_stock_count++;
        }
    }
    
    $notifications_sent = 0;
    
    // Send notifications for low stock items
    if ($low_stock_count > 0) {
        $message = "";
        
        if ($low_stock_count === 1) {
            $item = $low_stock_items[0];
            $price = isset($item['price']) ? $item['price'] : 16.00;
            $title = "Low Stock Alert: {$item['item_name']}";
            $message = json_encode([
                'item_id' => $item['id'],
                'item_name' => $item['item_name'],
                'stock_quantity' => $item['stock_quantity'],
                'price' => $price,
                'formatted_price' => '₱' . number_format($price, 2),
                'type' => 'inventory_low',
                'message' => "{$item['item_name']} has only {$item['stock_quantity']} units left (Price: ₱" . number_format($price, 2) . ")"
            ]);
        } else {
            $item_names = array_column($low_stock_items, 'item_name');
            $title = "Low Stock Alert: {$low_stock_count} Items";
            $message_text = "{$low_stock_count} items are running low on stock: " . implode(', ', $item_names);
            $message = json_encode([
                'item_count' => $low_stock_count,
                'items' => $low_stock_items,
                'type' => 'inventory_low',
                'message' => $message_text
            ]);
        }
        
        // Check if a similar notification was sent recently (last 24 hours)
        $recent_notification = checkRecentInventoryNotification('inventory_low', $low_stock_items);
        
        if (!$recent_notification) {
            sendAdminNotification($title, $message, 'inventory_low');
            $notifications_sent++;
        }
    }
    
    // Send notifications for out of stock items
    if ($out_of_stock_count > 0) {
        $message = "";
        
        if ($out_of_stock_count === 1) {
            $item = $out_of_stock_items[0];
            $price = isset($item['price']) ? $item['price'] : 16.00;
            $title = "Out of Stock: {$item['item_name']}";
            $message = json_encode([
                'item_id' => $item['id'],
                'item_name' => $item['item_name'],
                'price' => $price,
                'formatted_price' => '₱' . number_format($price, 2),
                'type' => 'inventory_out',
                'message' => "{$item['item_name']} is out of stock (Price: ₱" . number_format($price, 2) . ")"
            ]);
        } else {
            $item_names = array_column($out_of_stock_items, 'item_name');
            $title = "Out of Stock: {$out_of_stock_count} Items";
            $message_text = "{$out_of_stock_count} items are out of stock: " . implode(', ', $item_names);
            $message = json_encode([
                'item_count' => $out_of_stock_count,
                'items' => $out_of_stock_items,
                'type' => 'inventory_out',
                'message' => $message_text
            ]);
        }
        
        // Check if a similar notification was sent recently (last 24 hours)
        $recent_notification = checkRecentInventoryNotification('inventory_out', $out_of_stock_items);
        
        if (!$recent_notification) {
            sendAdminNotification($title, $message, 'inventory_out');
            $notifications_sent++;
        }
    }
    
    return $notifications_sent;
}

/**
 * Check if a similar inventory notification was sent recently
 * Prevents duplicate notifications for the same issue
 */
function checkRecentInventoryNotification($type, $items) {
    global $conn;
    
    $item_ids = array_column($items, 'id');
    $item_ids_string = implode(',', $item_ids);
    
    $query = "SELECT COUNT(*) as count FROM admin_notifications 
              WHERE type = ? 
              AND message LIKE ? 
              AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    
    // Create a search pattern for the item IDs
    $search_pattern = '%' . $item_ids_string . '%';
    $stmt->bind_param("ss", $type, $search_pattern);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

/**
 * Get current inventory status for dashboard display
 */
function getInventoryStatusSummary() {
    global $conn;
    
    $summary = [
        'low_stock_count' => 0,
        'out_of_stock_count' => 0,
        'low_stock_items' => [],
        'out_of_stock_items' => []
    ];
    
    // Get low stock items with price
    $low_stock_query = "SELECT id, item_name, stock_quantity, price FROM inventory WHERE stock_quantity < 5 AND stock_quantity > 0";
    $low_stock_result = mysqli_query($conn, $low_stock_query);
    
    if ($low_stock_result && mysqli_num_rows($low_stock_result) > 0) {
        while ($row = mysqli_fetch_assoc($low_stock_result)) {
            $summary['low_stock_items'][] = $row;
            $summary['low_stock_count']++;
        }
    }
    
    // Get out of stock items with price
    $out_of_stock_query = "SELECT id, item_name, price FROM inventory WHERE stock_quantity = 0";
    $out_of_stock_result = mysqli_query($conn, $out_of_stock_query);
    
    if ($out_of_stock_result && mysqli_num_rows($out_of_stock_result) > 0) {
        while ($row = mysqli_fetch_assoc($out_of_stock_result)) {
            $summary['out_of_stock_items'][] = $row;
            $summary['out_of_stock_count']++;
        }
    }
    
    return $summary;
}

/**
 * Send inventory replenishment notification when stock is updated
 */
function sendInventoryReplenishmentNotification($item_id, $item_name, $old_quantity, $new_quantity, $price = null) {
    // Only send notification if stock was replenished from low/out of stock
    if ($old_quantity < 5 && $new_quantity >= 5) {
        $price_display = $price ? '₱' . number_format($price, 2) : '₱16.00';
        $title = "Stock Replenished: {$item_name}";
        $message = json_encode([
            'item_id' => $item_id,
            'item_name' => $item_name,
            'old_quantity' => $old_quantity,
            'new_quantity' => $new_quantity,
            'price' => $price,
            'formatted_price' => $price_display,
            'type' => 'inventory_replenished',
            'message' => "{$item_name} stock has been replenished to {$new_quantity} units (Price: {$price_display})"
        ]);
        
        return sendAdminNotification($title, $message, 'inventory_replenished', $item_id);
    }
    
    return false;
}

/**
 * Send notification when a new inventory item is added
 */
function sendNewInventoryItemNotification($item_id, $item_name, $quantity, $item_type, $price = null) {
    $title = "New Inventory Item Added";
    $price_display = $price ? '₱' . number_format($price, 2) : '₱16.00';
    $message = json_encode([
        'item_id' => $item_id,
        'item_name' => $item_name,
        'quantity' => $quantity,
        'item_type' => $item_type,
        'price' => $price,
        'formatted_price' => $price_display,
        'type' => 'inventory_new',
        'message' => "New {$item_type}: {$item_name} (Initial stock: {$quantity}, Price: {$price_display})"
    ]);
    
    return sendAdminNotification($title, $message, 'inventory_new', $item_id);
}

// ============================================
// BOOKING NOTIFICATION FUNCTIONS
// ============================================

/**
 * Send notification for new booking
 */
function sendNewBookingNotification($booking_id, $customer_name, $service_type, $booking_date, $total_amount = null) {
    $title = "New Booking";
    $formatted_amount = $total_amount ? '₱' . number_format($total_amount, 2) : 'Pending calculation';
    
    $message = json_encode([
        'booking_id' => $booking_id,
        'customer_name' => $customer_name,
        'service_type' => $service_type,
        'booking_date' => $booking_date,
        'total_amount' => $total_amount,
        'formatted_amount' => $formatted_amount,
        'type' => 'booking_new',
        'message' => "New booking from {$customer_name} for {$service_type} on {$booking_date}. Amount: {$formatted_amount}"
    ]);
    
    return sendAdminNotification($title, $message, 'booking_new', $booking_id);
}

/**
 * Send notification for booking status change
 */
function sendBookingStatusNotification($booking_id, $customer_name, $old_status, $new_status, $total_amount = null) {
    $title = "Booking Status Updated";
    $formatted_amount = $total_amount ? '₱' . number_format($total_amount, 2) : '';
    
    $message = json_encode([
        'booking_id' => $booking_id,
        'customer_name' => $customer_name,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'total_amount' => $total_amount,
        'formatted_amount' => $formatted_amount,
        'type' => 'booking_status',
        'message' => "Booking #{$booking_id} from {$customer_name} changed from {$old_status} to {$new_status}" . 
                     ($formatted_amount ? " (Amount: {$formatted_amount})" : "")
    ]);
    
    return sendAdminNotification($title, $message, 'booking_status', $booking_id);
}
?>