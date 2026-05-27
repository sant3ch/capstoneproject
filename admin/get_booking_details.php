<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require '../config.php';
require_once '../includes/booking-functions.php';

/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    
    // Fetch booking details with user info
    $query = "
        SELECT 
            b.id,
            b.user_id,
            b.service_type,
            b.detergent,
            b.final_amount,
            b.status,
            u.first_name,
            u.last_name,
            u.user_points
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();
    
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
        exit();
    }
    
    // Get all services from database for lookup
    $services_from_db = [];
    $service_query = "SELECT id, service_name, price FROM services";
    $service_result = $conn->query($service_query);
    if ($service_result) {
        while ($row = $service_result->fetch_assoc()) {
            $services_from_db[$row['id']] = $row;
            $services_from_db[strtolower(trim($row['service_name']))] = $row;
            $services_from_db[$row['service_name']] = $row;
        }
    }
    
    // Calculate total using system function
    $finalAmount = null;
    if ($booking['final_amount'] > 0) {
        $finalAmount = $booking['final_amount'];
    } else {
        $finalAmount = calculateBookingAmountFromDB([
            'service_type' => $booking['service_type'],
            'detergent' => $booking['detergent'],
            'id' => $booking['id']
        ], $services_from_db);
    }
    
    // Parse services
    $services = parseServiceNames($booking['service_type'] ?? '');
    $service_items = [];
    
    foreach ($services as $service_name) {
        $service_name_lower = strtolower(trim($service_name));
        $service_price = 0;
        
        // Try to find price from services_from_db
        if (isset($services_from_db[$service_name])) {
            $service_price = $services_from_db[$service_name]['price'];
        } elseif (isset($services_from_db[$service_name_lower])) {
            $service_price = $services_from_db[$service_name_lower]['price'];
        } else {
            // Fallback pricing based on keywords
            if (strpos($service_name_lower, 'wash') !== false && strpos($service_name_lower, 'dry') !== false) {
                $service_price = 145.00;
            } elseif (strpos($service_name_lower, 'wash') !== false) {
                $service_price = 65.00;
            } elseif (strpos($service_name_lower, 'dry') !== false) {
                $service_price = 80.00;
            } elseif (strpos($service_name_lower, 'fold') !== false) {
                $service_price = 30.00;
            } else {
                $service_price = 65.00;
            }
        }
        
        $service_items[] = [
            'name' => $service_name,
            'price' => floatval($service_price),
            'quantity' => 1
        ];
    }
    
    // Parse detergents
    $detergent_items = [];
    if (!empty($booking['detergent']) && $booking['detergent'] !== 'N/A') {
        $detergents = explode(',', $booking['detergent']);
        foreach ($detergents as $detergent_entry) {
            $detergent_entry = trim($detergent_entry);
            
            if (empty($detergent_entry) || $detergent_entry === 'Bring my own detergent' || $detergent_entry === 'Bring my own') {
                continue;
            }
            
            // Parse "Qty x ItemName" format
            $qty = 1;
            $item_name = $detergent_entry;
            
            if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $detergent_entry, $matches)) {
                $qty = intval($matches[1]);
                $item_name = trim($matches[2]);
            }
            
            // Get price from inventory
            $item_price = 16.00; // Default detergent price
            $inventory_query = "SELECT price FROM inventory WHERE item_name = ?";
            $inv_stmt = $conn->prepare($inventory_query);
            if ($inv_stmt) {
                $inv_stmt->bind_param("s", $item_name);
                $inv_stmt->execute();
                $inv_result = $inv_stmt->get_result();
                if ($inv_row = $inv_result->fetch_assoc()) {
                    $item_price = floatval($inv_row['price']);
                } else {
                    // Detect fabric conditioner
                    $item_lower = strtolower($item_name);
                    if (strpos($item_lower, 'fabric') !== false || 
                        strpos($item_lower, 'conditioner') !== false ||
                        strpos($item_lower, 'softener') !== false ||
                        strpos($item_lower, 'downy') !== false) {
                        $item_price = 11.00;
                    }
                }
                $inv_stmt->close();
            }
            
            $detergent_items[] = [
                'name' => $item_name,
                'price' => floatval($item_price),
                'quantity' => $qty
            ];
        }
    }
    
    // Return response
    echo json_encode([
        'status' => 'success',
        'data' => [
            'bookingId' => intval($booking['id']),
            'userName' => $booking['first_name'] . ' ' . $booking['last_name'],
            'services' => $service_items,
            'detergents' => $detergent_items,
            'totalAmount' => floatval($finalAmount),
            'accumulatedPoints' => 1 // Fixed 1 point per transaction
        ]
    ]);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
