<?php
// user/booking_process.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get input
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

// If not JSON in request body, check $_POST (standard form submission)
if (!$data && !empty($_POST)) {
    $data = $_POST;
    
    // Map form field names to the expected array keys
    if (isset($data['selected_services_json'])) {
        $data['service_type'] = json_decode($data['selected_services_json'], true);
    }
    if (isset($data['selected_machines_json'])) {
        $data['machine'] = json_decode($data['selected_machines_json'], true);
    }
    if (isset($data['selected_laundry_supplies_json'])) {
        $data['detergent'] = json_decode($data['selected_laundry_supplies_json'], true);
    }
    
    // Ensure request_service is an array
    if (!isset($data['request_service']) && isset($_POST['request_service'])) {
        $data['request_service'] = $_POST['request_service'];
    }
}

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data or empty request.']);
    exit;
}

// Guests (unregistered customers) are allowed — user_id stays null for them.
$user_id = $_SESSION['user_id'] ?? null;
$is_guest = ($user_id === null);

// Validate
$booking_date = $data['booking_date'] ?? null;
$time_slot = $data['time_slot'] ?? null;

// Ensure these are arrays even if they come in as strings or single values
$services = isset($data['service_type']) ? (is_array($data['service_type']) ? $data['service_type'] : [$data['service_type']]) : [];
$detergents = isset($data['detergent']) ? (is_array($data['detergent']) ? $data['detergent'] : [$data['detergent']]) : [];
$machines = isset($data['machine']) ? (is_array($data['machine']) ? $data['machine'] : [$data['machine']]) : [];

// NEW FIELDS FROM STEP 3 OF WIZARD (optional but recommended)
$customer_first_name = $data['customer_first_name'] ?? null;
$customer_last_name = $data['customer_last_name'] ?? null;
$customer_mobile = $data['customer_mobile'] ?? null;
$customer_email = $data['customer_email'] ?? null;
$pickup_address = $data['pickup_address'] ?? null;
$delivery_address = $data['delivery_address'] ?? null;
$location_details = $data['location_details'] ?? null;
$estimated_weight = (isset($data['estimated_weight']) && $data['estimated_weight'] !== '') ? (float)$data['estimated_weight'] : null;

// Combine address with details if provided to ensure all info is saved
if (!empty($location_details)) {
    if (!empty($delivery_address)) {
        $delivery_address .= " (Note: " . $location_details . ")";
    } else {
        $delivery_address = $location_details;
    }
}

$pickup_coordinates = $data['pickup_coordinates'] ?? null;
$delivery_coordinates = $data['delivery_coordinates'] ?? null;

// Check if this is a Dryer-only booking (detergents are optional for this case)
$isDryerOnly = false;
if (!empty($services)) {
    $service_lower = strtolower(trim($services[0]));
    // Match variations of "Self-Service - Dryer" or just "Dryer"
    if (strpos($service_lower, 'dryer') !== false && strpos($service_lower, 'washer') === false) {
        $isDryerOnly = true;
    }
}

error_log("Booking validation - Services: " . implode(', ', $services) . " | Is Dryer Only: " . ($isDryerOnly ? 'yes' : 'no') . " | Has detergents: " . (empty($detergents) ? 'no' : 'yes'));

// Validate required fields
if (!$booking_date || !$time_slot || empty($services) || empty($machines)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
    exit;
}

// Guests must provide contact details (no account on file)
if ($is_guest) {
    if (empty($customer_first_name) || empty($customer_last_name) || empty($customer_mobile) || empty($customer_email)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide your name, mobile number and email to book as a guest.']);
        exit;
    }
    if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
        exit;
    }
}

// Token that lets a guest view / pay for this booking without an account
$guest_token = $is_guest ? bin2hex(random_bytes(16)) : null;

// Only require detergents if NOT dryer-only
if (!$isDryerOnly && empty($detergents)) {
    echo json_encode(['status' => 'error', 'message' => 'Please select at least one laundry supply.']);
    exit;
}

// ========== IMPORTANT: Include config file FIRST before using $conn ==========
require '../config.php';
require_once '../includes/booking-data.php';

// ========== Real-time availability check (online + walk-ins) ==========
// Re-verify the slot still has the requested machines free, in case a walk-in
// or another booking took them after this page was loaded.
$req_w = 0; $req_d = 0;
foreach ($machines as $mn) {
    $l = strtolower((string)$mn);
    if (strpos($l, 'washer') !== false)      $req_w++;
    elseif (strpos($l, 'dryer') !== false)   $req_d++;
}
$avail = getAvailabilityForSlot($conn, $booking_date, $time_slot);
if ($req_w > $avail['available_washers'] || $req_d > $avail['available_dryers']) {
    echo json_encode(['status' => 'error',
        'message' => "Those machines were just taken for that time slot. Only "
            . (int)$avail['available_washers'] . " washer(s) and "
            . (int)$avail['available_dryers'] . " dryer(s) remain — please pick another slot or fewer machines."]);
    exit;
}

// ========== Fetch services from database ==========
$services_from_db = [];

// Query services
$service_query = "SELECT id, service_name, price FROM services";
$service_result = $conn->query($service_query);

if ($service_result) {
    while ($row = $service_result->fetch_assoc()) {
        $services_from_db[$row['id']] = $row;
        $services_from_db[strtolower($row['service_name'])] = $row; // Also index by lowercase name for matching
    }
    error_log("Loaded " . count($services_from_db) . " services from database");
} else {
    error_log("Error fetching services: " . $conn->error);
}

// ========== Fetch inventory prices with item type ==========
$inventory_items = []; // Store full inventory details
$inventory_query = "SELECT id, item_name, price, item_type, stock_quantity FROM inventory";
$inventory_result = $conn->query($inventory_query);
if ($inventory_result) {
    while ($row = $inventory_result->fetch_assoc()) {
        $inventory_items[$row['item_name']] = [
            'price' => $row['price'],
            'item_type' => $row['item_type'],
            'stock_quantity' => $row['stock_quantity'],
            'id' => $row['id']
        ];
    }
    error_log("Loaded " . count($inventory_items) . " inventory items with prices and types");
} else {
    error_log("Error fetching inventory: " . $conn->error);
}

/**
 * Get inventory item price - now with type-based fallback
 */
function getInventoryItemPrice($item_name, $inventory_items) {
    if (isset($inventory_items[$item_name])) {
        return $inventory_items[$item_name]['price'];
    }
    
    // Default fallback based on item type detection from name
    $item_name_lower = strtolower($item_name);
    if (strpos($item_name_lower, 'detergent') !== false || 
        strpos($item_name_lower, 'soap') !== false ||
        strpos($item_name_lower, 'wash') !== false) {
        return 16.00;
    } elseif (strpos($item_name_lower, 'fabric') !== false || 
              strpos($item_name_lower, 'softener') !== false || 
              strpos($item_name_lower, 'conditioner') !== false ||
              strpos($item_name_lower, 'downy') !== false) {
        return 11.00;
    }
    
    return 16.00; // Default fallback
}

/**
 * Get inventory item type
 */
function getInventoryItemType($item_name, $inventory_items) {
    if (isset($inventory_items[$item_name])) {
        return $inventory_items[$item_name]['item_type'];
    }
    return null;
}

// Function to get service ID and name from database
function getServiceFromDB($service_id_or_name, $services_from_db) {
    // If it's a numeric ID, try to get by ID
    if (is_numeric($service_id_or_name) && isset($services_from_db[$service_id_or_name])) {
        $service = $services_from_db[$service_id_or_name];
        return [
            'id' => $service['id'],
            'name' => $service['service_name'],
            'price' => $service['price']
        ];
    }
    
    // Try to match by name (case-insensitive)
    $service_input_lower = strtolower(trim($service_id_or_name));
    
    foreach ($services_from_db as $key => $service) {
        if (is_array($service) && isset($service['service_name'])) {
            $db_service_name_lower = strtolower($service['service_name']);
            
            // Exact match
            if ($db_service_name_lower === $service_input_lower) {
                return [
                    'id' => $service['id'],
                    'name' => $service['service_name'],
                    'price' => $service['price']
                ];
            }
            
            // Partial match
            if (strpos($db_service_name_lower, $service_input_lower) !== false || 
                strpos($service_input_lower, $db_service_name_lower) !== false) {
                return [
                    'id' => $service['id'],
                    'name' => $service['service_name'],
                    'price' => $service['price']
                ];
            }
        }
    }
    
    // Try keyword-based matching as fallback
    $service_keywords = [
        'wash' => ['wash', 'full-service', 'washer'],
        'dry' => ['dry', 'dryer'],
        'fold' => ['fold']
    ];
    
    foreach ($service_keywords as $keyword_type => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($service_input_lower, $keyword) !== false) {
                foreach ($services_from_db as $service) {
                    if (is_array($service) && isset($service['service_name'])) {
                        $db_lower = strtolower($service['service_name']);
                        if (strpos($db_lower, $keyword) !== false) {
                            return [
                                'id' => $service['id'],
                                'name' => $service['service_name'],
                                'price' => $service['price']
                            ];
                        }
                    }
                }
            }
        }
    }
    
    return null;
}

// Function to validate and fix service names - returns ID:Name format
function validateServiceName($service, $services_from_db) {
    $db_service = getServiceFromDB($service, $services_from_db);
    
    if ($db_service) {
        return $db_service['id'] . ':' . $db_service['name'];
    }
    
    // Fallback mappings for common service names
    $service_map = [
        '1' => '1:Full-Service - Wash & Dry',
        '3' => '3:Self-Service - Washer',
        '4' => '4:Self-Service - Dryer',
        '5' => '5:Fold',
        'Full-Service - Wash & Dry' => '1:Full-Service - Wash & Dry',
        'full-Service Wash & Dry' => '1:Full-Service - Wash & Dry',
        'Full-Service Wash & Dry' => '1:Full-Service - Wash & Dry',
        'Self-Service - Washer' => '3:Self-Service - Washer',
        'Self-Service Wash Only' => '3:Self-Service - Washer',
        'Self-Service - Wash Only' => '3:Self-Service - Washer',
        'Self-Service - Dryer' => '4:Self-Service - Dryer',
        'Fold' => '5:Fold',
        'fold' => '5:Fold',
        'Fold Service' => '5:Fold',
    ];
    
    return $service_map[$service] ?? '0:' . $service;
}

/**
 * Generate next queue number for a specific booking date.
 */
function generateDailyQueueNumber($conn, $booking_date) {
    $next_queue_number = 1;

    $stmt = $conn->prepare(
        "SELECT COALESCE(MAX(queue_number), 0) + 1 AS next_queue_number
         FROM bookings
         WHERE booking_date = ?"
    );

    if ($stmt) {
        $stmt->bind_param("s", $booking_date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $next_queue_number = (int)$row['next_queue_number'];
        }
        $stmt->close();
    }

    return max(1, $next_queue_number);
}

// ========== Check inventory availability ==========
if (!empty($detergents)) {
    // Filter out "Bring my own" items
       // Handle both old format (array of strings) and new format (array of objects with {name, qty})
       $items_to_check = [];
       $qty_map = []; // Map to track quantities for stock validation
   
       foreach ($detergents as $item) {
           // If it's an object with name property (new format from quantity selector)
           if (is_array($item) && isset($item['name'])) {
               $name = $item['name'];
               $qty = $item['qty'] ?? 1;
               if ($name !== 'Bring my own detergent' && $name !== 'Bring my own') {
                   $items_to_check[] = $name;
                   $qty_map[$name] = ($qty_map[$name] ?? 0) + $qty; // Sum quantities if item appears multiple times
               }
           } elseif (is_string($item)) {
               // Old format: plain string
               if ($item !== 'Bring my own detergent' && $item !== 'Bring my own') {
                   $items_to_check[] = $item;
                   $qty_map[$item] = ($qty_map[$item] ?? 0) + 1; // Assume qty=1 for old format
               }
           }
       }
    
    if (!empty($items_to_check)) {
        $placeholders = implode(',', array_fill(0, count($items_to_check), '?'));
        $types = str_repeat('s', count($items_to_check));
        
        $check_sql = "SELECT item_name, stock_quantity, price, item_type FROM inventory WHERE item_name IN ($placeholders)";
        $check_stmt = $conn->prepare($check_sql);
        
        if ($check_stmt) {
            $items_to_check_array = array_values($items_to_check);
            $check_stmt->bind_param($types, ...$items_to_check_array);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            $out_of_stock = [];
            $inventory_details = [];
            while ($row = $result->fetch_assoc()) {
                $inventory_details[$row['item_name']] = [
                    'stock' => $row['stock_quantity'],
                    'price' => $row['price'],
                    'item_type' => $row['item_type']
                ];
                   // Check if quantity from booking exceeds available stock
                   $requested_qty = $qty_map[$row['item_name']] ?? 0;
                   if ($row['stock_quantity'] <= 0) {
                       $out_of_stock[] = $row['item_name'] . " (out of stock)";
                   } elseif ($requested_qty > $row['stock_quantity']) {
                       $out_of_stock[] = $row['item_name'] . " (need $requested_qty, only $row[stock_quantity] available)";
                }
            }
            $check_stmt->close();
            
            if (!empty($out_of_stock)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'The following items are out of stock: ' . implode(', ', $out_of_stock)
                ]);
                exit;
            }
        }
    }
}

// Validate all services - store as "ID:Name" format
$validated_services = [];
$service_prices = [];

foreach ($services as $service) {
    $validated_service = validateServiceName($service, $services_from_db);
    $validated_services[] = $validated_service;
    
    if (strpos($validated_service, ':') !== false) {
        list($service_id, $service_name) = explode(':', $validated_service, 2);
        if (isset($services_from_db[$service_id])) {
            $service_prices[] = $services_from_db[$service_id]['price'];
        }
    }
}

// Convert to strings
// Handle detergents format: can be array of strings (old) or array of {name, qty} objects (new)
$detergent_parts = [];
foreach ($detergents as $det_item) {
    if (is_array($det_item) && isset($det_item['name'])) {
        // New format: {name, qty}
        $qty = intval($det_item['qty'] ?? 1);
        $detergent_parts[] = "{$qty}x {$det_item['name']}";
    } else {
        // Old format: string
        $detergent_parts[] = (string)$det_item;
    }
}
$detergent_str = implode(', ', $detergent_parts);
$service_str = implode(', ', $validated_services);
$machine_names = implode(', ', $machines);
$request_service_str = implode(', ', $data['request_service'] ?? []);
$machine_count = $data['machine_count'] ?? 1;
$queue_number = generateDailyQueueNumber($conn, $booking_date);
$queue_code = 'Q-' . str_pad((string)$queue_number, 3, '0', STR_PAD_LEFT);

// Log for debugging
error_log("=== BOOKING PROCESS START ===");
error_log("Original services: " . implode(', ', $services));
error_log("Validated services: " . $service_str);
error_log("Detergents: " . $detergent_str);
error_log("Service prices: " . implode(', ', $service_prices));

$status = 'Pending';
$points_claimed = 0;
$pickup_status = 'Waiting for Pick Up';
$order_stage = 'Pending / Booked';

// SQL INSERT
$sql = "INSERT INTO bookings (
    user_id, 
    booking_date, 
    service_type, 
    detergent, 
    status, 
    points_claimed, 
    machine_count, 
    time_slot, 
    request_service, 
    queue_number, 
    queue_code,
    pickup_status, 
    order_stage,
    machine_names,
    delivery_address,
    customer_first_name,
    customer_last_name,
    customer_mobile,
    customer_email,
    location_details,
    estimated_weight,
    guest_token
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    "issssiississssssssssds",
    $user_id,
    $booking_date,
    $service_str,
    $detergent_str,
    $status,
    $points_claimed,
    $machine_count,
    $time_slot,
    $request_service_str,
    $queue_number,
    $queue_code,
    $pickup_status,
    $order_stage,
    $machine_names,
    $delivery_address,
    $customer_first_name,
    $customer_last_name,
    $customer_mobile,
    $customer_email,
    $location_details,
    $estimated_weight,
    $guest_token
);

if ($stmt->execute()) {
    $booking_id = $stmt->insert_id;
    error_log("Booking created successfully. ID: $booking_id");
    
    // ========== INVENTORY DEDUCTION ==========
    $deducted_items = [];
    if (!empty($detergents)) {
        foreach ($detergents as $detergent_item) {
            // Handle both old format (string) and new format (array with name and qty)
            if (is_array($detergent_item) && isset($detergent_item['name'])) {
                $item_name = trim($detergent_item['name']);
                $qty = intval($detergent_item['qty'] ?? 1);
            } else {
                $item_name = trim($detergent_item);
                $qty = 1;
            }
            
            if ($item_name === 'Bring my own detergent' || $item_name === 'Bring my own') {
                error_log("Skipping inventory deduction for: $item_name");
                continue;
            }
            
            $item_price = getInventoryItemPrice($item_name, $inventory_items);
            $item_type = getInventoryItemType($item_name, $inventory_items);
            $item_type_display = $item_type === 'fabric_conditioner' ? 'Fabric Conditioner' : 'Detergent';
            
            // Deduct the quantity requested
            $inventory_update = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE item_name = ? AND stock_quantity > 0");
            if ($inventory_update) {
                $inventory_update->bind_param("is", $qty, $item_name);
                $inventory_update->execute();
                
                if ($inventory_update->affected_rows > 0) {
                    error_log("Inventory deducted: $qty × $item_name ($item_type_display) - Price: ₱$item_price");
                    $deducted_items[] = [
                        'name' => $item_name,
                        'quantity' => $qty,
                        'price' => $item_price,
                        'type' => $item_type_display
                    ];
                } else {
                    error_log("Failed to deduct inventory: $item_name - out of stock or not found");
                }
                $inventory_update->close();
            }
        }
    }
    // ========== END INVENTORY DEDUCTION ==========
    
    // NOTE: Machines are NOT marked as unavailable here anymore!
    // They will only become unavailable when admin clicks "Assign Machine" or "Auto Assign Next"
    // in the queue_management.php page
    
    // Create notification (registered users only — guests have no account to notify)
    if ($user_id) {
        $notification_message = "Your booking #$booking_id has been received. Queue Number: $queue_code. Stage: Pending / Booked.";
        $notification_sql = "INSERT INTO notifications (user_id, title, message, booking_id) VALUES (?, 'Booking Confirmed', ?, ?)";
        $notification_stmt = $conn->prepare($notification_sql);
        if ($notification_stmt) {
            $notification_stmt->bind_param("isi", $user_id, $notification_message, $booking_id);
            $notification_stmt->execute();
            $notification_stmt->close();
        }
    }
    
    // Generate booking reference
    $booking_ref = 'BOOK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Calculate total amount
    $total_amount = 0;
    
    // Add service prices
    foreach ($validated_services as $validated_service) {
        if (strpos($validated_service, ':') !== false) {
            list($service_id, $service_name) = explode(':', $validated_service, 2);
            if (isset($services_from_db[$service_id])) {
                $service_price = $services_from_db[$service_id]['price'];
                $total_amount += $service_price;
                error_log("Service: $service_name - ₱$service_price");
            }
        }
    }
    
    // Add detergent prices from inventory
       // Add detergent prices from inventory (now with quantity support)
       foreach ($detergents as $detergent_item) {
           // Handle both old format (string) and new format (array with name and qty)
           if (is_array($detergent_item) && isset($detergent_item['name'])) {
               $detergent = trim($detergent_item['name']);
               $qty = intval($detergent_item['qty'] ?? 1);
           } else {
               $detergent = trim($detergent_item);
               $qty = 1;
           }
       
           if (!empty($detergent) && $detergent !== 'Bring my own detergent' && $detergent !== 'Bring my own') {
               $detergent_price = getInventoryItemPrice($detergent, $inventory_items);
               $laundry_charge = $detergent_price * $qty;
               $total_amount += $laundry_charge;
               error_log("Addon: $qty × $detergent @ ₱$detergent_price = ₱$laundry_charge");
           }
       }
    
    error_log("Total amount calculated: ₱$total_amount");
    error_log("=== BOOKING PROCESS END ===");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Booking confirmed! Your booking ID is: ' . $booking_id . ' | Queue: ' . $queue_code,
        'booking_id' => $booking_id,
        'queue_number' => $queue_number,
        'queue_code' => $queue_code,
        'order_stage' => $order_stage,
        'booking_ref' => $booking_ref,
        'total_amount' => $total_amount,
        'redirect' => 'booking_confirmation.php?id=' . $booking_id . ($guest_token ? '&ref=' . $guest_token : ''),
        'debug' => [
            'services' => $validated_services,
            'service_prices' => $service_prices,
            'detergents' => $detergents,
            'deducted_items' => $deducted_items,
            'total_amount' => $total_amount
        ]
    ]);
    
} else {
    error_log("Execute error: " . $stmt->error);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to create booking: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
exit();
?>