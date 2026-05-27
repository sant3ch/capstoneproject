<?php
session_start();
require '../config.php';
require_once '../includes/admin-notifications.php';
require_once '../includes/booking-functions.php';

/** @var mysqli $conn */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get booking ID from URL or session
$booking_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];

try {
    // Get user info
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get booking info
    if ($booking_id) {
        // Get specific booking by ID
        $booking_query = "SELECT * FROM bookings WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($booking_query);
        $stmt->bind_param('ii', $booking_id, $user_id);
    } else {
        // Get latest booking
        $booking_query = "SELECT * FROM bookings WHERE user_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($booking_query);
        $stmt->bind_param('i', $user_id);
    }
    
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        // No booking found
        header('Location: book-now.php');
        exit();
    }

    // Check if rescheduled
    $isReschedule = ($booking['status'] === 'Rescheduled');

    // Format data from database
    $formatted_date = isset($booking['booking_date']) ? date('F j, Y', strtotime($booking['booking_date'])) : 'N/A';
    
    // Handle services - now stored as "ID:Name" format
    $services = [];
    $service_ids = [];
    if (!empty($booking['service_type'])) {
        $services_raw = array_map('trim', explode(',', $booking['service_type']));
        
        // Parse services if stored as "ID:Name" format
        foreach ($services_raw as $service_item) {
            if (strpos($service_item, ':') !== false) {
                list($service_id, $service_name) = explode(':', $service_item, 2);
                $service_ids[] = trim($service_id);
                $services[] = trim($service_name);
            } else {
                $services[] = $service_item;
            }
        }
    } else {
        $services = ['N/A'];
    }
    
    // Handle detergent
    $detergent = [];
    if (!empty($booking['detergent'])) {
        $detergent = array_map('trim', explode(',', $booking['detergent']));
    } else {
        $detergent = ['N/A'];
    }
    
    // Handle machine names
    $machine_names = [];
    if (!empty($booking['machine_names'])) {
        $machine_names = array_map('trim', explode(',', $booking['machine_names']));
    }

    // Fetch all services from database for price lookup
    $all_services = [];
    $service_query = "SELECT id, service_name, price FROM services";
    $service_result = $conn->query($service_query);
    if ($service_result) {
        while ($row = $service_result->fetch_assoc()) {
            $all_services[$row['id']] = $row;
            $all_services[$row['service_name']] = $row;
        }
        error_log("Loaded " . count($all_services) . " services for price lookup");
    } else {
        error_log("Error fetching services: " . $conn->error);
    }

    // Fetch inventory items with prices
    $inventory_items = [];
    $inventory_query = "SELECT id, item_name, price, item_type FROM inventory";
    $inventory_result = $conn->query($inventory_query);
    if ($inventory_result) {
        while ($row = $inventory_result->fetch_assoc()) {
            $inventory_items[$row['item_name']] = [
                'price' => $row['price'],
                'item_type' => $row['item_type'],
                'id' => $row['id']
            ];
        }
        error_log("Loaded " . count($inventory_items) . " inventory items");
    } else {
        error_log("Error fetching inventory: " . $conn->error);
    }

    // Calculate estimated amount using database prices (with inventory prices)
    $estimated_amount = calculateBookingAmountFromDB($booking, $all_services);
    
    // Format the amount for display
    $formatted_amount = '₱' . number_format($estimated_amount, 2);

    // Define flags for payment button logic based on service and delivery status
    $rawServiceType = $booking['service_type'] ?? '';
    $isSelfService = (stripos($rawServiceType, 'Self-Service') !== false);
    $isFullService = (stripos($rawServiceType, 'Full-Service') !== false);
    
    $rawRequestService = $booking['request_service'] ?? '';
    $hasDelivery = (stripos($rawRequestService, 'Delivery') !== false);
    
    // Final Logic Rules:
    // 1. GCash is always available.
    // 2. COD is ONLY for Full-Service + Delivery.
    // 3. In-Store is for Self-Service OR Full-Service without Delivery.
    $canUseCOD = ($isFullService && $hasDelivery);
    $canUseInStore = ($isSelfService || ($isFullService && !$hasDelivery));
    
    // Get applicable voucher types based on services (needed for JavaScript later)
    $applicable_vouchers = getApplicableVoucherTypes($services);

    // Fetch notifications for the logged-in user
    $sql = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch claimed rewards for the user
    $claimed_rewards = [];
    $rewards_query = $conn->prepare("SELECT id, reward_name, status, claimed_at FROM claimed_rewards WHERE user_id = ? AND status = 'Claimed' ORDER BY claimed_at DESC");
    $rewards_query->bind_param('i', $user_id);
    $rewards_query->execute();
    $rewards_result = $rewards_query->get_result();
    while ($reward = $rewards_result->fetch_assoc()) {
        $claimed_rewards[] = $reward;
    }
    $rewards_query->close();

} catch (Exception $e) {
    error_log("Database error in booking_confirmation: " . $e->getMessage());
    $booking = null;
    $user = null;
    $notifications = [];
    $claimed_rewards = [];
    $services = ['N/A'];
    $detergent = ['N/A'];
    $machine_names = [];
    $formatted_date = 'N/A';
    $isReschedule = false;
    $estimated_amount = 0;
    $formatted_amount = '₱0.00';
    $all_services = [];
    $inventory_items = [];
}

if (!$booking) {
    header('Location: book-now.php');
    exit();
}

/**
 * Get inventory item price with type-based fallback
 */
function getInventoryItemPriceForBreakdown($item_name, $inventory_items) {
    if (isset($inventory_items[$item_name])) {
        return $inventory_items[$item_name]['price'];
    }
    
    // Default fallback based on item type detection from name
    $item_name_lower = strtolower($item_name);
    if (strpos($item_name_lower, 'fabric') !== false || 
        strpos($item_name_lower, 'softener') !== false || 
        strpos($item_name_lower, 'conditioner') !== false ||
        strpos($item_name_lower, 'downy') !== false) {
        return 11.00;
    }
    
    return 16.00; // Default for detergents
}

/**
 * Get inventory item type
 */
function getInventoryItemTypeForBreakdown($item_name, $inventory_items) {
    if (isset($inventory_items[$item_name])) {
        return $inventory_items[$item_name]['item_type'];
    }
    
    // Detect from name
    $item_name_lower = strtolower($item_name);
    if (strpos($item_name_lower, 'fabric') !== false || 
        strpos($item_name_lower, 'softener') !== false || 
        strpos($item_name_lower, 'conditioner') !== false ||
        strpos($item_name_lower, 'downy') !== false) {
        return 'fabric_conditioner';
    }
    return 'detergent';
}

/**
 * Determine which vouchers are applicable based on selected services
 */
function getApplicableVoucherTypes($services) {
    $applicable = [];
    
    // Check if services contain full-service or fold
    $has_full_service = false;
    $has_fold = false;
    $has_washer_only = false;
    $has_dryer_only = false;
    
    foreach ($services as $service) {
        $service_lower = strtolower(trim($service));
        
        if (strpos($service_lower, 'wash & dry') !== false || strpos($service_lower, 'wash&dry') !== false) {
            $has_full_service = true;
        } elseif (strpos($service_lower, 'fold') !== false) {
            $has_fold = true;
        } elseif (strpos($service_lower, 'washer') !== false && strpos($service_lower, 'self') !== false) {
            $has_washer_only = true;
        } elseif (strpos($service_lower, 'dryer') !== false && strpos($service_lower, 'self') !== false) {
            $has_dryer_only = true;
        }
    }
    
    // Determine applicable vouchers
    if ($has_full_service || $has_fold) {
        // Full service or fold includes both wash and dry
        $applicable[] = 'free wash load';
        $applicable[] = 'free dry load';
        $applicable[] = 'free wash & dry';
    } else if ($has_washer_only && !$has_dryer_only) {
        // Only washer service
        $applicable[] = 'free wash load';
    } else if ($has_dryer_only && !$has_washer_only) {
        // Only dryer service
        $applicable[] = 'free dry load';
    } else if ($has_washer_only && $has_dryer_only) {
        // Both washer and dryer selected separately
        $applicable[] = 'free wash load';
        $applicable[] = 'free dry load';
    }
    
    return $applicable;
}

/**
 * Check if a booking has any self-service services
 */
function hasSelfServiceBooking($service_type) {
    if (empty($service_type)) {
        return false;
    }
    
    $services_lower = strtolower($service_type);
    return strpos($services_lower, 'self-service') !== false || strpos($services_lower, 'self service') !== false;
}

/**
 * Check if reward is applicable based on services
 */
function isRewardApplicable($reward_name, $applicable_vouchers) {
    $reward_lower = strtolower(trim($reward_name));
    
    foreach ($applicable_vouchers as $voucher) {
        if (strpos($reward_lower, $voucher) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Calculate booking amount using actual database prices
 */
function calculateBookingAmountFromDB($booking, $all_services) {
    global $inventory_items;
    $amount = 0;
    
    // Get service types and their IDs
    $service_items = [];
    if (!empty($booking['service_type'])) {
        $raw_services = explode(',', $booking['service_type']);
        foreach ($raw_services as $service_item) {
            $service_item = trim($service_item);
            
            if (strpos($service_item, ':') !== false) {
                list($service_id, $service_name) = explode(':', $service_item, 2);
                $service_items[] = ['id' => trim($service_id), 'name' => trim($service_name)];
            } else {
                $service_items[] = ['id' => null, 'name' => $service_item];
            }
        }
    }
    
    // Calculate service total using database prices
    foreach ($service_items as $service) {
        $service_price = 0;
        
        if ($service['id'] && isset($all_services[$service['id']])) {
            $service_price = $all_services[$service['id']]['price'];
        } elseif (isset($all_services[$service['name']])) {
            $service_price = $all_services[$service['name']]['price'];
        } else {
            $service_lower = strtolower($service['name']);
            foreach ($all_services as $key => $svc) {
                if (is_array($svc) && isset($svc['service_name'])) {
                    $db_service_lower = strtolower($svc['service_name']);
                    if (strpos($db_service_lower, $service_lower) !== false || 
                        strpos($service_lower, $db_service_lower) !== false) {
                        $service_price = $svc['price'];
                        break;
                    }
                }
            }
        }
        
        if ($service_price == 0) {
            $service_lower = strtolower($service['name']);
            if (strpos($service_lower, 'wash') !== false && strpos($service_lower, 'dry') !== false) {
                $service_price = 145.00;
            } elseif (strpos($service_lower, 'wash') !== false) {
                $service_price = 65.00;
            } elseif (strpos($service_lower, 'dry') !== false) {
                $service_price = 80.00;
            } elseif (strpos($service_lower, 'fold') !== false) {
                $service_price = 30.00;
            } else {
                $service_price = 65.00;
            }
        }
        
        $amount += $service_price;
    }
    
    // Add detergent cost using inventory prices
    if (!empty($booking['detergent']) && $booking['detergent'] !== 'N/A') {
        $detergents = explode(',', $booking['detergent'] ?? '');
        foreach ($detergents as $detergent_entry) {
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
            
            $item_price = getInventoryItemPriceForBreakdown($item_name, $GLOBALS['inventory_items'] ?? []);
            $amount += $item_price * $qty;
        }
    }
    
    error_log("Database Calculation - Booking #{$booking['id']}: ₱" . number_format($amount, 2));
    
    return $amount;
}

/**
 * Get service price from database
 */
function getServicePrice($service_name, $service_id, $all_services) {
    if ($service_id && isset($all_services[$service_id])) {
        return $all_services[$service_id]['price'];
    }
    
    if (isset($all_services[$service_name])) {
        return $all_services[$service_name]['price'];
    }
    
    $service_lower = strtolower($service_name);
    foreach ($all_services as $svc) {
        if (is_array($svc) && isset($svc['service_name'])) {
            $db_lower = strtolower($svc['service_name']);
            if (strpos($db_lower, $service_lower) !== false || strpos($service_lower, $db_lower) !== false) {
                return $svc['price'];
            }
        }
    }
    
    return 0;
}

/**
 * Get breakdown items for display
 */
function getBreakdownItems($booking, $all_services, $services_list) {
    global $inventory_items;
    $breakdown_items = [];
    $total = 0;
    
    // Process services
    if (!empty($booking['service_type'])) {
        $raw_services = explode(',', $booking['service_type']);
        foreach ($raw_services as $service_item) {
            $service_item = trim($service_item);
            $service_id = null;
            $service_name = $service_item;
            
            if (strpos($service_item, ':') !== false) {
                list($service_id, $service_name) = explode(':', $service_item, 2);
                $service_name = trim($service_name);
            }
            
            $price = getServicePrice($service_name, $service_id, $all_services);
            
            if ($price > 0) {
                $breakdown_items[] = [
                    'name' => $service_name,
                    'price' => $price,
                    'type' => 'service'
                ];
                $total += $price;
            }
        }
    }
    
    // Process addons using inventory prices
    if (!empty($booking['detergent']) && $booking['detergent'] !== 'N/A') {
        $detergents = explode(',', $booking['detergent']);
        foreach ($detergents as $detergent_entry) {
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
            
            // Look up the item in inventory to get correct price and type
            $addon_price = getInventoryItemPriceForBreakdown($item_name, $inventory_items);
            $addon_type = getInventoryItemTypeForBreakdown($item_name, $inventory_items);
            $addon_display_name = $addon_type === 'fabric_conditioner' ? 'Fabric Conditioner' : 'Detergent';
            
            // Calculate total for this item (price × quantity)
            $item_total = $addon_price * $qty;
            
            // Display with quantity if qty > 1
            $display_name = ($qty > 1) ? "{$qty}x {$item_name}" : $item_name;
            
            $breakdown_items[] = [
                'name' => $display_name . ' (' . $addon_display_name . ')',
                'price' => $item_total,
                'type' => 'addon'
            ];
            $total += $item_total;
        }
    }
    
    return ['items' => $breakdown_items, 'total' => $total];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Booking Confirmation - Jorish Express Laundry</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Load Bootstrap CSS first -->
    <link href="../assets/lib/css/bootstrap.min.css" rel="stylesheet">
    <!-- Then load Font Awesome -->
    <link rel="stylesheet" href="../assets/lib/css/all.min.css" />
    <!-- Then load Tailwind CSS (Local) -->
    <link rel="stylesheet" href="../assets/lib/css/all-tailwind-classes-full-min.css">
    <!-- Global Color Theme -->
    <link rel="stylesheet" href="../assets/css/colors.css">
    <style>
        .modal-content {
        border-radius: 1rem;
        }
        nav.navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030; 
        }
        main {
            padding-top: 80px;
        }
        .navbar-brand img { height: 50px; }
        .booking-id {
            background-color: var(--purple-primary);
            border: 2px dashed var(--white);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        .booking-id h3 {
            color: var(--white);
            margin-bottom: 5px;
        }
        .booking-id .id-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--white);
        }
        .amount-highlight {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--white);
            background-color: var(--purple-primary);
            padding: 10px 15px;
            border-radius: 10px;
            border: 2px solid var(--purple-dark);
            text-align: center;
        }
        .calculation-breakdown {
            background-color: var(--gray-light);
            border-left: 4px solid var(--purple-primary);
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid var(--gray-medium);
        }
        .breakdown-item:last-child {
            border-bottom: none;
            font-weight: bold;
            color: var(--info-dark);
            font-size: 1.1rem;
        }
        .breakdown-subitem {
            display: flex;
            justify-content: space-between;
            padding: 3px 0 3px 15px;
            font-size: 0.9rem;
            color: var(--gray-dark);
        }
        
        /* File Input Styling */
        .form-control:focus {
            border-color: var(--purple-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25) !important;
        }
        
        /* Dashed file input styling for payment proof */
        input[type="file"].form-control {
            transition: all 0.3s ease;
        }
        
        input[type="file"].form-control:hover {
            background-color: #eef2ff;
            border-color: var(--purple-dark) !important;
        }
        
        /* File preview animation */
        #gcashFilePreview {
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Clear button hover */
        .btn-outline-danger:hover {
            background-color: var(--purple-primary) !important;
            color: white !important;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <img src="../assets/images/logo.png" alt="Jorish Express Laundry">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link active" href="../index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="../index.php#why">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="../service-and-pricing.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="../contact-and-map-view.php">Find Location</a></li>

                <li class="nav-item"><a class="nav-link" href="../index.php#news">Blog</a></li>
            </ul>

            <!-- Notifications Button -->
            <button type="button" class="btn btn-outline-primary position-relative me-3" data-bs-toggle="modal" data-bs-target="#notificationModal">
                <i class="fas fa-bell"></i>
                <?php if (count($notifications) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo count($notifications); ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- User Profile Dropdown -->
            <div class="dropdown">
                <button class="btn btn-primary text-white dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="user-profile.php"><i class="fas fa-user-cog"></i> Manage Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationModalLabel">Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="markAllNotificationsRead()"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                    <li class="list-group-item notification-item" data-id="<?php echo $notification['id']; ?>">
                        <strong><?php echo htmlspecialchars($notification['title'] ?? ''); ?></strong><br>
                        <?php echo htmlspecialchars($notification['message']); ?>
                        <small class="text-muted d-block"><?php echo date("F j, Y, g:i A", strtotime($notification['created_at'])); ?></small>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="list-group-item text-center text-muted">No new notifications.</li>
            <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

    <!-- Main Content -->
    <main class="pt-20 pb-10 px-4">
        <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-md overflow-hidden animate-fade-in">
            <div class="h-48 bg-blue-50 flex items-center justify-center">
                <img src="../assets/images/background.jpg" class="w-full h-full object-cover" alt="Laundry Service" />
            </div>

            <div class="p-8">
                <!-- Booking ID Display -->
                <div class="booking-id mb-6">
                    <h3>Your Booking ID</h3>
                    <div class="id-number">#<?php echo htmlspecialchars($booking['id']); ?></div>
                    <small style="color: white;">Please keep this ID for reference</small>
                </div>

                <div class="text-center mb-8">
                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-blue-600 text-4xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <?php echo $isReschedule ? 'Booking Rescheduled!' : 'Booking Confirmed!'; ?>
                    </h1>
                    <p class="text-gray-600 mt-2">
                        <?php echo $isReschedule
                            ? 'Your booking has been successfully updated'
                            : 'Thank you for your booking!'; ?>
                    </p>
                </div>

                <!-- Estimated Amount Display -->
                <div class="mb-8">
                    <div class="amount-highlight text-center mb-6">
                        <div class="text-sm mb-1" style="color: white;">Estimated Total Amount</div>
                        <div class="amount-value" id="displayAmount"><?php echo $formatted_amount; ?></div>
                        <small class="d-block mt-2" style="color: white;">
                            <i class="fas fa-info-circle me-1"></i>
                            Based on selected services and addons
                        </small>
                    </div>
                    
                    <!-- Rewards Voucher Section -->
                    <?php if (count($claimed_rewards) > 0): ?>
                    <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(129, 140, 248, 0.08) 100%); border: 2px solid var(--purple-primary); border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                        <h6 class="fw-bold mb-3" style="color: #333;">
                            <i class="fas fa-ticket-alt me-2" style="color: var(--purple-primary);"></i> Apply Rewards Voucher
                        </h6>
                        <select id="rewardsVoucher" class="form-control" style="border: 2px solid var(--purple-primary); border-radius: 8px;" onchange="applyReward()">
                            <option value="">-- Select a Reward --</option>
                            <?php 
                            // Get applicable vouchers based on services
                            $applicable_vouchers = getApplicableVoucherTypes($services);
                            
                            foreach ($claimed_rewards as $reward): 
                                // Only show rewards that are applicable
                                if (isRewardApplicable($reward['reward_name'], $applicable_vouchers)):
                            ?>
                            <option value="<?php echo htmlspecialchars($reward['reward_name']); ?>" 
                                    data-reward-id="<?php echo $reward['id']; ?>"
                                    data-reward-name="<?php echo htmlspecialchars($reward['reward_name']); ?>"
                                    data-applicable="true">
                                <?php echo htmlspecialchars($reward['reward_name']); ?>
                            </option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                        <small class="text-muted d-block mt-2">
                            Select a claimed reward to apply discount
                        </small>
                        <div id="rewardAppliedAlert" class="alert" style="background-color: var(--purple-primary); border: 1px solid var(--purple-dark); color: white; margin-top: 12px; display: none;">
                            <strong id="rewardAppliedText">Reward applied!</strong>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                        <i class="fas fa-info-circle me-2" style="color: #ff6b6b;"></i>
                        <span style="color: #333;">You don't have any claimed rewards yet. Visit the rewards page to earn and claim rewards!</span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Hidden field to store selected reward -->
                    <input type="hidden" id="selectedRewardId" name="selected_reward_id" value="">
                    <input type="hidden" id="selectedRewardName" name="selected_reward_name" value="">
                    <input type="hidden" id="finalAmount" name="final_amount" value="<?php echo $estimated_amount; ?>">
                    
                    <!-- Calculation Breakdown -->
                    <div class="calculation-breakdown">
                        <h6 class="fw-bold mb-3">Amount Breakdown:</h6>
                        
                        <?php
                        // Get breakdown items
                        $breakdown = getBreakdownItems($booking, $all_services, $services);
                        $total_calculated = 0;
                        
                        // Show each breakdown item
                        foreach ($breakdown['items'] as $item):
                            $total_calculated += $item['price'];
                        ?>
                        <div class="breakdown-item">
                            <span><?php echo htmlspecialchars($item['name']); ?>:</span>
                            <span>₱<?php echo number_format($item['price'], 2); ?></span>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($breakdown['items'])): ?>
                        <div class="breakdown-item">
                            <span>No items to display</span>
                            <span>₱0.00</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="breakdown-item mt-2 pt-2 border-top" style="color: black;">
                            <span>Total Estimated Amount:</span>
                            <span>₱<?php echo number_format($breakdown['total'], 2); ?></span>
                        </div>
                        <div id="mainVoucherRow" class="breakdown-item" style="display: none; color: #dc3545;">
                            <span>Voucher Discount:</span>
                            <span id="mainVoucherAmount">-₱0.00</span>
                        </div>
                        <div id="mainFinalAmountRow" class="breakdown-item mt-2 pt-2 border-top" style="display: none; color: #0d6efd; font-weight: bold; font-size: 1.2rem;">
                            <span>Final Payable Amount:</span>
                            <span id="mainFinalAmount">₱0.00</span>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mb-4">
                        Booking Details
                    </h2>
                    <div class="space-y-4">
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Customer:</span>
                            <span class="text-gray-800">
                                <?php echo htmlspecialchars(
                                    ($user['first_name'] ?? '') .
                                        ' ' .
                                        ($user['last_name'] ?? '')
                                ); ?>
                            </span>
                        </div>

                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Service(s):</span>
                            <span class="text-gray-800">
                                <?php echo htmlspecialchars(implode(', ', $services)); ?>
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Detergent/Addons:</span>
                            <span class="text-gray-800">
                                <?php echo htmlspecialchars(implode(', ', $detergent)); ?>
                            </span>
                        </div>

                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Date:</span>
                            <span class="text-gray-800"><?php echo $formatted_date; ?></span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Time:</span>
                            <span class="text-gray-800">
                                <?php echo htmlspecialchars($booking['time_slot'] ?? 'N/A'); ?>
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Number of Machines:</span>
                            <span class="text-gray-800">
                                <?php echo htmlspecialchars($booking['machine_count'] ?? 'N/A'); ?>
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Machines Selected:</span>
                            <span class="text-gray-800">
                                <?php echo !empty($machine_names) ? htmlspecialchars(implode(', ', $machine_names)) : 'N/A'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Queue Number:</span>
                            <span class="text-gray-800 font-semibold">
                                <?php
                                    $queueCode = $booking['queue_code'] ?? null;
                                    if (empty($queueCode) && !empty($booking['queue_number'])) {
                                        $queueCode = 'Q-' . str_pad((string)$booking['queue_number'], 3, '0', STR_PAD_LEFT);
                                    }
                                    echo htmlspecialchars($queueCode ?? 'N/A');
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Order Stage:</span>
                            <span class="text-gray-800">
                                <?php echo htmlspecialchars($booking['order_stage'] ?? 'Pending / Booked'); ?>
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Estimated Start:</span>
                            <span class="text-gray-800">
                                <?php echo !empty($booking['estimated_start_time']) ? date('F j, Y h:i A', strtotime($booking['estimated_start_time'])) : 'TBD'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-gray-600 font-medium">Estimated Completion:</span>
                            <span class="text-gray-800">
                                <?php echo !empty($booking['estimated_completion_time']) ? date('F j, Y h:i A', strtotime($booking['estimated_completion_time'])) : 'TBD'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 font-medium">Status:</span>
                            <span
                                class="px-3 py-1 rounded-full text-sm font-medium
                                <?php
                                echo match ($booking['status'] ?? '') {
                                    'Pending' => 'bg-gray-600 text-white',
                                    'Completed' => 'bg-gray-600 text-white',
                                    'Rescheduled' => 'bg-gray-600 text-white',
                                    'Cancelled' => 'bg-gray-600 text-white',
                                    default => 'bg-gray-600 text-white',
                                };
                                ?>"
                            >
                                <?php echo htmlspecialchars($booking['status'] ?? 'Pending'); ?>
                            </span>
                        </div>
                    </div>
                </div>

<!-- Payment Method Selection Container -->
<div class="max-w-md mx-auto bg-white rounded-xl shadow-md p-6 mt-1">
  <h2 class="text-lg font-semibold text-gray-700 mb-4 text-center">
    Choose Your Payment Method
  </h2>
  <div class="flex justify-center gap-4">
    <button
      type="button"
      class="text-white font-semibold px-6 py-2 rounded-lg shadow"
      style="background-color: #6366f1; border: none;"
      onmouseover="this.style.backgroundColor='#4338ca'"
      onmouseout="this.style.backgroundColor='#6366f1'"
      data-bs-toggle="modal"
      data-bs-target="#gcashModal"
    >
      GCASH QR
    </button>
    
    <!-- Cash on Delivery Button -->
    <?php if ($canUseCOD): ?>
    <button
      type="button"
      class="text-white font-semibold px-6 py-2 rounded-lg shadow"
      style="background-color: #6366f1; border: none;"
      onmouseover="this.style.backgroundColor='#4338ca'"
      onmouseout="this.style.backgroundColor='#6366f1'"
      data-bs-toggle="modal"
      data-bs-target="#cashModal"
    >
      Cash on Delivery
    </button>
    <?php else: ?>
    <button
      type="button"
      class="text-white font-semibold px-6 py-2 rounded-lg shadow opacity-50"
      style="background-color: #6366f1; border: none; cursor: not-allowed;"
      data-bs-toggle="tooltip"
      title="Cash on Delivery is only available for Full-Service bookings with Delivery."
    >
      Cash on Delivery
    </button>
    <?php endif; ?>

    <!-- In-Store Payment Button -->
    <?php if ($canUseInStore): ?>
    <button
      type="button"
      class="text-white font-semibold px-6 py-2 rounded-lg shadow"
      style="background-color: #6366f1; border: none;"
      onmouseover="this.style.backgroundColor='#4338ca'"
      onmouseout="this.style.backgroundColor='#6366f1'"
      data-bs-toggle="modal"
      data-bs-target="#inStoreModal"
    >
      In-Store Payment
    </button>
    <?php else: ?>
    <button
      type="button"
      class="text-white font-semibold px-6 py-2 rounded-lg shadow opacity-50"
      style="background-color: #6366f1; border: none; cursor: not-allowed;"
      data-bs-toggle="tooltip"
      title="In-Store payment is not available for bookings with Delivery service."
    >
      In-Store Payment
    </button>
    <?php endif; ?>
  </div>
  <div class="text-center mt-4 text-sm text-gray-600">
    <i class="fas fa-money-bill-wave me-1"></i>
    Estimated Total: <strong id="paymentMethodDisplayAmount"><?php echo $formatted_amount; ?></strong>
  </div>
</div>

<div class="flex flex-col space-y-3 gap-1 mt-4">
    <a
        href="../index.php"
        class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-4 rounded-lg text-center transition duration-300"
        ><i class="fas fa-home mr-2"></i> Return to Home</a
    >
    <a
        href="user-profile.php"
        class="font-medium py-3 px-4 rounded-lg text-center transition duration-300"
        style="background-color: #6366f1; color: white; border: 1px solid #6366f1;"
        onmouseover="this.style.backgroundColor='#4338ca'"
        onmouseout="this.style.backgroundColor='#6366f1'"
        ><i class="fas fa-calendar-alt mr-2"></i> View My Bookings</a
    >
</div>
            </div>
        </div>
    </main>

    <!-- GCASH Payment Modal with QR Code -->
<div class="modal fade" id="gcashModal" tabindex="-1" aria-labelledby="gcashModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
            <!-- Header with Blue Primary Color -->
            <div class="modal-header" style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); border-radius: 12px 12px 0 0; border: none;">
                <h5 class="modal-title" id="gcashModalLabel" style="color: white; font-weight: 600;">
                    <i class="fas fa-mobile-alt me-2"></i> GCASH Payment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body" style="padding: 24px;">
                <div class="row g-4">
                    <!-- QR Code Section -->
                    <div class="col-md-6 text-center" style="border-right: 1px solid #e9ecef;">
                        <h6 class="fw-bold mb-3" style="color: #333;">Scan QR Code to Pay</h6>
                        <img src="../assets/images/gcash-qr-code.png" alt="GCASH QR Code" class="img-fluid" style="max-width: 250px; margin: 0 auto; display: block; border-radius: 12px; border: 3px solid var(--purple-primary); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);">
                        <p class="text-muted small mt-3" style="font-size: 0.85rem;">
                            <i class="fas fa-qrcode me-2" style="color: var(--purple-primary);"></i> Scan with your GCASH app
                        </p>
                        <p class="fw-bold mt-3" style="font-size: 1rem; color: var(--purple-primary);">
                            09928483072
                        </p>
                        <p class="text-muted small mt-2" style="font-size: 0.85rem;">
                            Or you can use this number
                        </p>
                    </div>

                    <!-- Payment Details Section -->
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3" style="color: #333;">Payment Details</h6>
                        
                        <!-- Amount Display -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" style="color: var(--purple-primary);">Amount to Pay</label>
                            <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(129, 140, 248, 0.08) 100%); padding: 16px 12px; border-radius: 10px; border-left: 4px solid var(--purple-primary);">
                                <h4 class="fw-bold mb-0" id="gcashModalAmount" style="color: var(--purple-dark); font-size: 1.8rem;"><?php echo $formatted_amount; ?></h4>
                                <small style="color: #888;">Estimated total</small>
                            </div>
                        </div>
                        
                        <!-- Voucher Applied Display -->
                        <div class="mb-3" id="gcashVoucherSection" style="display: none;">
                            <div style="background: #6c757d; border: 1px solid #5a6268; border-radius: 8px; padding: 12px; border-left: 4px solid #495057;">
                                <small style="color: white;">
                                    <strong id="gcashVoucherText">Voucher Applied</strong>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Booking Reference -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" style="color: #666;">Booking Reference</label>
                            <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; border: 1px solid #dee2e6;">
                                <p class="mb-0 fw-semibold" style="color: #333; font-size: 1.1rem;">#<?php echo htmlspecialchars($booking['id']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Proof Section -->
                <div style="margin-top: 24px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <h6 class="fw-bold mb-3" style="color: #333;">
                        Payment Proof <span class="badge" style="background: #6c757d; color: white; font-size: 0.75rem; vertical-align: top;">Required</span>
                    </h6>
                    <p class="text-muted small mb-3">Upload a screenshot or file proving your GCASH payment:</p>

                    <!-- File Upload Input with Better Styling -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="color: #333;">
                            <i class="fas fa-file-upload me-2" style="color: var(--purple-primary);"></i> Upload Proof of Payment
                        </label>
                        <div style="position: relative;">
                            <input type="file" class="form-control" id="gcashProofFile" accept=".jpg,.jpeg,.png,.pdf" required 
                                   style="border: 2px dashed var(--purple-primary); border-radius: 8px; padding: 12px; background-color: #f8fafc; cursor: pointer;" 
                                   onchange="handleGcashFileSelect(event)">
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-check me-1" style="color: #198754;"></i>Accepted: JPG, PNG, PDF | Max: 25MB
                            </small>
                        </div>
                    </div>

                    <!-- File Preview Section (Hidden by default) -->
                    <div id="gcashFilePreview" style="display: none; margin-top: 16px; padding: 16px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid var(--purple-primary);">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center flex-grow-1">
                                <i class="fas fa-check-circle me-3" style="color: #198754; font-size: 1.3rem;"></i>
                                <div style="flex-grow: 1;">
                                    <h6 class="mb-1 fw-semibold" style="color: #333;" id="gcashFileName">File selected</h6>
                                    <small class="text-muted d-block" id="gcashFileInfo">Waiting for upload...</small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm" onclick="clearGcashFile()" style="background-color: var(--purple-primary); color: white; border: none; border-radius: 6px; padding: 6px 12px;">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                        
                        <!-- Image Preview (for image files) -->
                        <div id="gcashImagePreview" style="margin-top: 12px; display: flex; justify-content: center;"></div>
                    </div>

                    <!-- Warning Alert -->
                    <div class="alert" style="background-color: #6c757d; border: 1px solid #6c757d; border-radius: 8px; margin-top: 12px;">
                        <i class="fas fa-exclamation-triangle me-2" style="color: white;"></i>
                        <span style="color: white;">A screenshot or proof file is required before submitting your payment request.</span>
                    </div>
                </div>
            </div>

            <!-- Footer with Improved Button Styling -->
            <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 16px 24px; background-color: #f8f9fa;">
                <button type="button" class="btn" id="cancelGcashBtn" data-bs-dismiss="modal" 
                        style="background-color: #6c757d; color: white; border: none; border-radius: 6px; font-weight: 500; padding: 8px 16px;">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <button type="button" class="btn" id="submitGcashPaymentBtn" 
                        style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); color: white; border: none; border-radius: 6px; font-weight: 500; padding: 8px 20px;">
                    <i class="fas fa-check me-2"></i> Complete Payment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- GCASH Payment Success Modal -->
<div class="modal fade" id="gcashSuccessModal" tabindex="-1" aria-labelledby="gcashSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); border-radius: 12px 12px 0 0; border: none;">
                <h5 class="modal-title" id="gcashSuccessModalLabel" style="color: white; font-weight: 600;">
                    <i class="fas fa-check-circle me-2"></i> Payment Confirmed!
                </h5>
            </div>
            
            <div class="modal-body text-center" style="padding: 32px 24px;">
                <!-- Success Animation Circle -->
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(129, 140, 248, 0.08) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; border: 3px solid var(--purple-primary);">
                    <i class="fas fa-check fa-2x" style="color: var(--purple-primary);"></i>
                </div>
                
                <h5 class="fw-bold" style="color: #333; font-size: 1.5rem; margin-bottom: 8px;">Payment Received!</h5>
                <p class="text-muted" style="margin-bottom: 20px; font-size: 0.95rem;">Your GCASH payment has been submitted for verification</p>
                
                <!-- Next Steps Alert -->
                <div style="background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 16px; margin-bottom: 20px; text-align: left;">
                    <h6 class="fw-bold" style="color: var(--purple-dark); margin-bottom: 12px;">
                        <i class="fas fa-tasks me-2"></i> What happens next?
                    </h6>
                    <div style="color: var(--purple-dark); font-size: 0.9rem;">
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-check-circle me-2" style="color: var(--purple-primary);"></i> <strong>Payment Request Submitted</strong><br>
                            <span style="margin-left: 24px;">Your proof of payment is now under review</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-hourglass-half me-2" style="color: var(--purple-primary);"></i> <strong>Admin Verification</strong><br>
                            <span style="margin-left: 24px;">Our admin staff will verify your payment within 24 hours</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-bell me-2" style="color: var(--purple-primary);"></i> <strong>Notification</strong><br>
                            <span style="margin-left: 24px;">You'll receive a notification once approved</span>
                        </div>
                        <div>
                            <i class="fas fa-book me-2" style="color: var(--purple-primary);"></i> <strong>Track Your Booking</strong><br>
                            <span style="margin-left: 24px;">Check your booking details in your profile</span>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction Summary -->
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dee2e6;">
                        <span style="color: #666; font-weight: 500;">Booking ID:</span>
                        <span style="color: #333; font-weight: 600; font-size: 1.1rem;" id="successGcashBookingId">#<?php echo htmlspecialchars($booking['id']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #666; font-weight: 500;">Amount Submitted:</span>
                        <span style="color: var(--purple-primary); font-weight: 700; font-size: 1.2rem;" id="successGcashAmount"><?php echo $formatted_amount; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 16px 24px; background-color: #f8f9fa;">
                <button type="button" class="btn" id="gcashSuccessOkBtn" 
                        style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); color: white; border: none; border-radius: 6px; font-weight: 500; padding: 8px 24px; width: 100%;">
                    <i class="fas fa-arrow-right me-2"></i> View My Bookings
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Cash on Delivery Invoice Modal -->
<div class="modal fade" id="cashModal" tabindex="-1" aria-labelledby="cashModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
            <!-- Header with Blue Primary Color -->
            <div class="modal-header" style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); border-radius: 12px 12px 0 0; border: none;">
                <h5 class="modal-title" id="cashModalLabel" style="color: white; font-weight: 600;">
                    <i class="fas fa-money-bill-wave me-2"></i> Cash on Delivery Invoice
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 24px;">
                <!-- Customer Information Section -->
                <div class="mb-4 pb-3" style="border-bottom: 1px solid #e9ecef;">
                    <h6 class="fw-bold mb-3" style="color: #333;">Customer Information</h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold" style="color: var(--purple-primary);">Name</label>
                            <p class="fs-5 fw-semibold" style="color: #333;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold" style="color: var(--purple-primary);">Phone Number</label>
                            <p class="fs-5 fw-semibold" style="color: #333;"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                        </div>
                    </div>

                    <!-- Delivery Address -->
                    <?php if (!hasSelfServiceBooking($booking['service_type'] ?? '')): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold" style="color: var(--purple-primary); margin-bottom: 0;">Delivery Address</label>
                            <button type="button" class="btn btn-sm" id="editAddressBtn" style="background: transparent; color: var(--purple-primary); border: 1px solid var(--purple-primary); padding: 4px 8px;" data-bs-toggle="tooltip" title="Edit address">
                                <i class="fas fa-edit me-1"></i> Edit
                            </button>
                        </div>
                        <div id="addressDisplay" style="padding: 12px; background: #f1f5f9; border-radius: 8px; border-left: 4px solid var(--purple-primary);">
                            <p class="mb-0" id="addressText" style="color: #333;"><?php echo htmlspecialchars($user['address'] ?? 'No address provided'); ?></p>
                        </div>
                        <div id="addressEditForm" style="display: none; padding: 12px; background: #f1f5f9; border-radius: 8px; margin-top: 8px;">
                            <textarea class="form-control" id="addressInput" rows="3" placeholder="Enter your complete address" style="border: 1px solid var(--purple-primary);"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm" id="saveAddressBtn" style="background: var(--purple-primary); color: white; border: none; padding: 6px 12px; margin-right: 8px;">
                                    <i class="fas fa-check me-1"></i> Save
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" id="cancelAddressBtn">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Self-Service Booking - No Delivery Needed -->
                    <div class="alert alert-info mb-3" style="border-left: 4px solid #10b981; background-color: #ecfdf5;">
                        <i class="fas fa-info-circle me-2" style="color: #10b981;"></i>
                        <span style="color: #065f46;"><strong>Self-Service Booking:</strong> No pickup or delivery service is required. You will manage your laundry on-site.</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Booking Details Section -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3" style="color: #333;">Booking Details</h6>
                    
                    <!-- Services -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold" style="color: #666;">Service(s)</label>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px;">
                            <?php foreach ($services as $service): ?>
                                <span class="badge me-1 mb-1" style="background: #6c757d; color: white;"><?php echo htmlspecialchars($service); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Detergent/Addons -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold" style="color: #666;">Detergent/Add-ons</label>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px;">
                            <?php 
                                $addon_list = [];
                                if (!empty($detergent) && $detergent[0] !== 'N/A'):
                                    foreach ($detergent as $item):
                                        $item = trim($item);
                                        if (!empty($item) && $item !== 'Bring my own detergent' && $item !== 'Bring my own'):
                                            $addon_list[] = $item;
                                        endif;
                                    endforeach;
                                endif;
                                
                                if (!empty($addon_list)):
                                    foreach ($addon_list as $addon):
                            ?>
                                <span class="badge me-1 mb-1" style="background: #6c757d; color: white;"><?php echo htmlspecialchars($addon); ?></span>
                            <?php 
                                    endforeach;
                                else:
                            ?>
                                <span style="color: #888;">None selected</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Scheduled Date & Time -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold" style="color: #666;">Scheduled Date & Time</label>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; color: #333;">
                            <p class="mb-0"><i class="fas fa-calendar me-2" style="color: var(--purple-primary);"></i><?php echo $formatted_date; ?></p>
                            <?php if (!empty($booking['time_slot'])): ?>
                                <p class="mb-0"><i class="fas fa-clock me-2" style="color: var(--purple-primary);"></i><?php echo htmlspecialchars($booking['time_slot']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Machines Used -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold" style="color: #666;">Machines Used</label>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px;">
                            <?php if (!empty($machine_names)): ?>
                                <?php foreach ($machine_names as $machine): ?>
                                    <span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($machine); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: #888;">Not yet assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Instructions -->
                <div style="background: #6c757d; border: 1px solid #6c757d; border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 0.9rem; color: white;">
                    <i class="fas fa-info-circle me-2" style="color: white;"></i>
                    <strong>Payment Instructions:</strong><br>
                    <div style="margin-left: 24px; margin-top: 8px;">
                        1. Confirm your delivery address above<br>
                        2. We'll deliver your laundry to your location<br>
                        3. Pay the exact amount to our delivery staff<br>
                        4. Please have the correct amount or prepare for change
                    </div>
                </div>

                <!-- Amount Breakdown Section -->
                <div class="mb-4 pb-3" style="border-bottom: 1px solid #e9ecef;">
                    <h6 class="fw-bold mb-3" style="color: #333;">Amount Breakdown</h6>
                    <div class="calculation-breakdown" id="breakdownContainer" style="border-left-color: var(--purple-primary);">
                        <?php 
                            $breakdown = getBreakdownItems($booking, $all_services, $services);
                            foreach ($breakdown['items'] as $item):
                        ?>
                            <div class="breakdown-item">
                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                                <span>₱<?php echo number_format($item['price'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="breakdown-item" style="font-size: 1.1rem; font-weight: bold; margin-top: 10px; color: black;">
                            <span>TOTAL ESTIMATED AMOUNT:</span>
                            <span>₱<?php echo number_format($breakdown['total'], 2); ?></span>
                        </div>
                        <div id="cashVoucherRow" class="breakdown-item" style="display: none; color: #dc3545;">
                            <span>Voucher Discount:</span>
                            <span id="cashVoucherAmount">-₱0.00</span>
                        </div>
                        <div id="cashFinalAmountRow" class="breakdown-item mt-2 pt-2 border-top" style="display: none; color: #0d6efd; font-weight: bold; font-size: 1.2rem;">
                            <span>Final Payable Amount:</span>
                            <span id="cashModalTotalAmount">₱<?php echo number_format($estimated_amount, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Voucher Applied Display for Cash Modal -->
                    <div id="cashVoucherSection" style="display: none; margin-top: 12px; padding: 12px; background: var(--purple-primary); border: 1px solid var(--purple-dark); border-radius: 8px; border-left: 4px solid var(--purple-dark);">
                        <small style="color: white;">
                            <strong id="cashVoucherText">Voucher Applied</strong>
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Footer with Improved Button Styling -->
            <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 16px 24px; background-color: #f8f9fa;">
                <button type="button" class="btn" id="cancelCashBtn" data-bs-dismiss="modal" 
                        style="background-color: #6c757d; color: white; border: none; border-radius: 6px; font-weight: 500; padding: 8px 16px;">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <button type="button" class="btn" id="submitCashPaymentBtn" 
                        style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); color: white; border: none; border-radius: 6px; font-weight: 500; padding: 8px 20px;">
                    <i class="fas fa-check me-2"></i> Confirm Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cash on Delivery Success Modal -->
<div class="modal fade" id="cashSuccessModal" tabindex="-1" aria-labelledby="cashSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); border-radius: 12px 12px 0 0; border: none;">
                <h5 class="modal-title" id="cashSuccessModalLabel" style="color: white; font-weight: 600;">
                    <i class="fas fa-check-circle me-2"></i> Order Confirmed!
                </h5>
            </div>
            
            <div class="modal-body text-center" style="padding: 32px 24px;">
                <!-- Success Animation Circle -->
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(129, 140, 248, 0.08) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; border: 3px solid var(--purple-primary);">
                    <i class="fas fa-check fa-2x" style="color: var(--purple-primary);"></i>
                </div>
                
                <h5 class="fw-bold" style="color: #333; font-size: 1.5rem; margin-bottom: 8px;">Order Received!</h5>
                <p class="text-muted" style="margin-bottom: 20px; font-size: 0.95rem;">Your cash on delivery order has been submitted for confirmation</p>
                
                <!-- Next Steps Alert -->
                <div style="background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 16px; margin-bottom: 20px; text-align: left;">
                    <h6 class="fw-bold" style="color: var(--purple-dark); margin-bottom: 12px;">
                        <i class="fas fa-tasks me-2"></i> What happens next?
                    </h6>
                    <div style="color: var(--purple-dark); font-size: 0.9rem;">
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-check-circle me-2" style="color: var(--purple-primary);"></i> <strong>Order Confirmed</strong><br>
                            <span style="margin-left: 24px;">Your order has been scheduled and confirmed</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-truck me-2" style="color: var(--purple-primary);"></i> <strong>Delivery Scheduled</strong><br>
                            <span style="margin-left: 24px;">We'll deliver to your confirmed address</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-coins me-2" style="color: var(--purple-primary);"></i> <strong>Payment on Delivery</strong><br>
                            <span style="margin-left: 24px;">Pay <span id="paymentAmountInModal" class="fw-bold"></span> to our delivery staff</span>
                        </div>
                        <div>
                            <i class="fas fa-bell me-2" style="color: var(--purple-primary);"></i> <strong>Stay Updated</strong><br>
                            <span style="margin-left: 24px;">You'll get notifications about your delivery</span>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction Summary -->
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dee2e6;">
                        <span style="color: #666; font-weight: 500;">Booking ID:</span>
                        <span style="color: #333; font-weight: 600; font-size: 1.1rem;" id="successCashBookingId"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="color: #666; font-weight: 500;">Payment Method:</span>
                        <span style="color: #333; font-weight: 600;">Cash on Delivery</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #666; font-weight: 500;">Amount Due:</span>
                        <span style="color: var(--purple-primary); font-weight: 700; font-size: 1.2rem;" id="successCashAmount"></span>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 16px 24px; background-color: #f8f9fa;">
                <button type="button" class="btn" id="cashSuccessOkBtn" 
                        style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); color: white; border: none; border-radius: 6px; font-weight: 500; padding: 8px 24px; width: 100%;">
                    <i class="fas fa-arrow-right me-2"></i> View My Bookings
                </button>
            </div>
        </div>
    </div>
</div>

<!-- In-Store Payment Confirmation Modal -->
<div class="modal fade" id="inStoreModal" tabindex="-1" aria-labelledby="inStoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
            <!-- Header with Blue Primary Color -->
            <div class="modal-header" style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); border-radius: 12px 12px 0 0; border: none;">
                <h5 class="modal-title" id="inStoreModalLabel" style="color: white; font-weight: 600;">
                    <i class="fas fa-store me-2"></i> In-Store Payment Confirmation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body" style="padding: 24px;">
                <!-- Booking Summary Section -->
                <div class="mb-4 pb-3" style="border-bottom: 1px solid #e9ecef;">
                    <h6 class="fw-bold mb-3" style="color: #333;">Booking Summary</h6>
                    
                    <!-- Services -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold" style="color: #666;">Service(s)</label>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px;">
                            <?php foreach ($services as $service): ?>
                                <span class="badge me-1 mb-1" style="background: #6c757d; color: white;"><?php echo htmlspecialchars($service); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Scheduled Date & Time -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold" style="color: #666;">Scheduled Date & Time</label>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; color: #333;">
                            <p class="mb-0"><i class="fas fa-calendar me-2" style="color: var(--purple-primary);"></i><?php echo $formatted_date; ?></p>
                            <?php if (!empty($booking['time_slot'])): ?>
                                <p class="mb-0"><i class="fas fa-clock me-2" style="color: var(--purple-primary);"></i><?php echo htmlspecialchars($booking['time_slot']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Machines Used -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold" style="color: #666;">Machines Used</label>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 8px;">
                            <?php if (!empty($machine_names)): ?>
                                <?php foreach ($machine_names as $machine): ?>
                                    <span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($machine); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: #888;">Not yet assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Instructions -->
                <div style="background: #6c757d; border: 1px solid #6c757d; border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 0.9rem; color: white;">
                    <i class="fas fa-info-circle me-2" style="color: white;"></i>
                    <strong>In-Store Payment:</strong><br>
                    <div style="margin-left: 24px; margin-top: 8px;">
                        This booking will be completed in-store. Payment will be processed when you arrive at our location with your laundry.
                    </div>
                </div>

                <!-- Amount Breakdown Section -->
                <div class="mb-4 pb-3" style="border-bottom: 1px solid #e9ecef;">
                    <h6 class="fw-bold mb-3" style="color: #333;">Payment Amount</h6>
                    <div class="calculation-breakdown" id="inStoreBreakdownContainer" style="border-left-color: var(--purple-primary);">
                        <?php 
                            $breakdown = getBreakdownItems($booking, $all_services, $services);
                            foreach ($breakdown['items'] as $item):
                        ?>
                            <div class="breakdown-item">
                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                                <span>₱<?php echo number_format($item['price'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="breakdown-item" style="font-size: 1.1rem; font-weight: bold; margin-top: 10px; color: black;">
                            <span>TOTAL ESTIMATED AMOUNT:</span>
                            <span>₱<?php echo number_format($breakdown['total'], 2); ?></span>
                        </div>
                        <div id="inStoreVoucherRow" class="breakdown-item" style="display: none; color: #dc3545;">
                            <span>Voucher Discount:</span>
                            <span id="inStoreVoucherAmount">-₱0.00</span>
                        </div>
                        <div id="inStoreFinalAmountRow" class="breakdown-item mt-2 pt-2 border-top" style="display: none; color: #0d6efd; font-weight: bold; font-size: 1.2rem;">
                            <span>Final Payable Amount:</span>
                            <span id="inStoreModalTotalAmount">₱<?php echo number_format($estimated_amount, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Voucher Applied Display -->
                    <div id="inStoreVoucherSection" style="display: none; margin-top: 12px; padding: 12px; background: var(--purple-primary); border: 1px solid var(--purple-dark); border-radius: 8px; border-left: 4px solid var(--purple-dark);">
                        <small style="color: white;">
                            <strong id="inStoreVoucherText">Voucher Applied</strong>
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Footer with Improved Button Styling -->
            <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 16px 24px; background-color: #f8f9fa;">
                <button type="button" class="btn" id="cancelInStoreBtn" data-bs-dismiss="modal" 
                        style="background-color: #6c757d; color: white; border: none; border-radius: 6px; font-weight: 500; padding: 8px 16px;">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <button type="button" class="btn" id="submitInStorePaymentBtn" 
                        style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); color: white; border: none; border-radius: 6px; font-weight: 500; padding: 8px 20px;">
                    <i class="fas fa-check me-2"></i> Confirm & Proceed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- In-Store Payment Success Modal -->
<div class="modal fade" id="inStoreSuccessModal" tabindex="-1" aria-labelledby="inStoreSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); border-radius: 12px 12px 0 0; border: none;">
                <h5 class="modal-title" id="inStoreSuccessModalLabel" style="color: white; font-weight: 600;">
                    <i class="fas fa-check-circle me-2"></i> Booking Confirmed!
                </h5>
            </div>
            
            <div class="modal-body text-center" style="padding: 32px 24px;">
                <!-- Success Animation Circle -->
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(129, 140, 248, 0.08) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; border: 3px solid var(--purple-primary);">
                    <i class="fas fa-check fa-2x" style="color: var(--purple-primary);"></i>
                </div>
                
                <h5 class="fw-bold" style="color: #333; font-size: 1.5rem; margin-bottom: 8px;">Booking Confirmed!</h5>
                <p class="text-muted" style="margin-bottom: 20px; font-size: 0.95rem;">Your in-store booking has been confirmed successfully</p>
                
                <!-- Next Steps Alert -->
                <div style="background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 16px; margin-bottom: 20px; text-align: left;">
                    <h6 class="fw-bold" style="color: var(--purple-dark); margin-bottom: 12px;">
                        <i class="fas fa-tasks me-2"></i> What happens next?
                    </h6>
                    <div style="color: var(--purple-dark); font-size: 0.9rem;">
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-check-circle me-2" style="color: var(--purple-primary);"></i> <strong>Booking Confirmed</strong><br>
                            <span style="margin-left: 24px;">Your laundry booking is scheduled and ready</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-calendar-check me-2" style="color: var(--purple-primary);"></i> <strong>Arrive at Your Scheduled Time</strong><br>
                            <span style="margin-left: 24px;">Bring your laundry at the scheduled date and time</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-coins me-2" style="color: var(--purple-primary);"></i> <strong>Payment at Checkout</strong><br>
                            <span style="margin-left: 24px;">Pay <span id="paymentAmountInInStoreModal" class="fw-bold"></span> when you arrive</span>
                        </div>
                        <div>
                            <i class="fas fa-bell me-2" style="color: var(--purple-primary);"></i> <strong>Stay Updated</strong><br>
                            <span style="margin-left: 24px;">You'll get notifications about your booking</span>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction Summary -->
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dee2e6;">
                        <span style="color: #666; font-weight: 500;">Booking ID:</span>
                        <span style="color: #333; font-weight: 600; font-size: 1.1rem;" id="successInStoreBookingId"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="color: #666; font-weight: 500;">Payment Method:</span>
                        <span style="color: #333; font-weight: 600;">In-Store Payment</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #666; font-weight: 500;">Amount Due:</span>
                        <span style="color: var(--purple-primary); font-weight: 700; font-size: 1.2rem;" id="successInStoreAmount"></span>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 16px 24px; background-color: #f8f9fa;">
                <button type="button" class="btn" id="inStoreSuccessOkBtn" 
                        style="background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%); color: white; border: none; border-radius: 6px; font-weight: 500; padding: 8px 24px; width: 100%;">
                    <i class="fas fa-arrow-right me-2"></i> View My Bookings
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert for logout confirmation -->
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <script src="../assets/lib/js/sweetalert2.min.js"></script>

<script>
    // Store the original estimated amount
    const originalAmount = <?php echo $estimated_amount; ?>;
    
    // Reward value mappings based on reward names
    const rewardDiscounts = {
        'free wash load': 65.00,
        'free dry load': 80.00,
        'free wash & dry': 145.00,
        'free service': 65.00
    };
    
    // Get applicable voucher types from PHP
    const applicableVouchers = <?php echo json_encode($applicable_vouchers); ?>;
    
    /**
     * Check if a reward name is in the applicable vouchers list
     */
    function isRewardApplicableJS(rewardName) {
        const rewardLower = rewardName.toLowerCase();
        for (const voucher of applicableVouchers) {
            if (rewardLower.includes(voucher.toLowerCase())) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Apply the selected reward and calculate the discount
     */
    function applyReward() {
        const selectElement = document.getElementById('rewardsVoucher');
        const selectedValue = selectElement.value;
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const alertDiv = document.getElementById('rewardAppliedAlert');
        const hiddenIdField = document.getElementById('selectedRewardId');
        const hiddenNameField = document.getElementById('selectedRewardName');
        const finalAmountField = document.getElementById('finalAmount');
        const displayAmountDiv = document.getElementById('displayAmount');
        
        if (!selectedValue) {
            // No reward selected, show original amount
            alertDiv.style.display = 'none';
            hiddenIdField.value = '';
            hiddenNameField.value = '';
            finalAmountField.value = originalAmount;
            displayAmountDiv.innerHTML = '₱' + originalAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            updateModalAmounts(originalAmount, 0, '');
            return;
        }
        
        // Check if reward is applicable for the selected services
        if (!isRewardApplicableJS(selectedValue)) {
            Swal.fire({
                icon: 'warning',
                title: 'Reward Not Applicable',
                text: 'This reward cannot be applied to your selected services.',
                confirmButtonText: 'OK'
            });
            selectElement.value = '';
            alertDiv.style.display = 'none';
            return;
        }
        
        // Get reward ID and name
        const rewardId = selectedOption.getAttribute('data-reward-id');
        const rewardName = selectedValue;
        
        // Store selected reward info
        hiddenIdField.value = rewardId;
        hiddenNameField.value = rewardName;
        
        // Calculate discount based on reward name
        let discountAmount = 0;
        const rewardNameLower = rewardName.toLowerCase();
        
        // Check for matching discount
        for (const [key, value] of Object.entries(rewardDiscounts)) {
            if (rewardNameLower.includes(key)) {
                discountAmount = value;
                break;
            }
        }
        
        // If no specific discount found, check if it matches any service
        if (discountAmount === 0) {
            // Try to match with service prices from the breakdown
            if (rewardNameLower.includes('wash') && rewardNameLower.includes('dry')) {
                discountAmount = 145.00;
            } else if (rewardNameLower.includes('wash')) {
                discountAmount = 65.00;
            } else if (rewardNameLower.includes('dry')) {
                discountAmount = 80.00;
            } else {
                discountAmount = 50.00; // Default discount
            }
        }
        
        // Calculate final amount after discount
        const finalAmount = Math.max(0, originalAmount - discountAmount);
        
        // Update the hidden field with final amount
        finalAmountField.value = finalAmount;
        
        // Update the display amount
        displayAmountDiv.innerHTML = '₱' + finalAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        
        // Update modal amounts with discount info
        updateModalAmounts(finalAmount, discountAmount, rewardName);
        
        // Update alert message
        const alertText = document.getElementById('rewardAppliedText');
        alertText.innerHTML = `<i class="fas fa-check-circle me-1"></i> Reward Applied: ${rewardName} (-₱${discountAmount.toFixed(2)})`;
        
        // Show alert
        alertDiv.style.display = 'block';
        
        // Scroll to alert
        alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        console.log(`Reward applied: ${rewardName}, Discount: ₱${discountAmount.toFixed(2)}, Final Amount: ₱${finalAmount.toFixed(2)}`);
    }
    
    /**
     * Update the amount displayed in both GCASH and CASH modals
     */
    function updateModalAmounts(amount, discountAmount = 0, rewardName = '') {
        const formattedAmount = '₱' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        const formattedDiscount = '-₱' + discountAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");

        // Update GCASH modal amount
        const gcashAmountDisplay = document.getElementById('gcashModalAmount');
        if (gcashAmountDisplay) {
            gcashAmountDisplay.textContent = formattedAmount;
        }
        
        // Update Cash modal total amount
        const cashModalTotalAmount = document.getElementById('cashModalTotalAmount');
        if (cashModalTotalAmount) {
            cashModalTotalAmount.textContent = '₱' + amount.toFixed(2);
        }

        // Update In-Store modal total amount
        const inStoreModalTotalAmount = document.getElementById('inStoreModalTotalAmount');
        if (inStoreModalTotalAmount) {
            inStoreModalTotalAmount.textContent = '₱' + amount.toFixed(2);
        }
        
        // Update the payment method selection display amount
        const paymentMethodDisplay = document.getElementById('paymentMethodDisplayAmount');
        if (paymentMethodDisplay) {
            paymentMethodDisplay.textContent = formattedAmount;
        }

        // Update Main Page Breakdown rows
        const mainVoucherRow = document.getElementById('mainVoucherRow');
        const mainVoucherAmount = document.getElementById('mainVoucherAmount');
        const mainFinalAmountRow = document.getElementById('mainFinalAmountRow');
        const mainFinalAmount = document.getElementById('mainFinalAmount');
        
        if (discountAmount > 0) {
            if (mainVoucherRow) mainVoucherRow.style.display = 'flex';
            if (mainVoucherAmount) mainVoucherAmount.textContent = formattedDiscount;
            if (mainFinalAmountRow) mainFinalAmountRow.style.display = 'flex';
            if (mainFinalAmount) mainFinalAmount.textContent = formattedAmount;
        } else {
            if (mainVoucherRow) mainVoucherRow.style.display = 'none';
            if (mainFinalAmountRow) mainFinalAmountRow.style.display = 'none';
        }

        // Update Cash Modal Breakdown rows
        const cashVoucherRow = document.getElementById('cashVoucherRow');
        const cashVoucherAmount = document.getElementById('cashVoucherAmount');
        const cashFinalAmountRow = document.getElementById('cashFinalAmountRow');
        
        if (discountAmount > 0) {
            if (cashVoucherRow) cashVoucherRow.style.display = 'flex';
            if (cashVoucherAmount) cashVoucherAmount.textContent = formattedDiscount;
            if (cashFinalAmountRow) cashFinalAmountRow.style.display = 'flex';
        } else {
            if (cashVoucherRow) cashVoucherRow.style.display = 'none';
            if (cashFinalAmountRow) cashFinalAmountRow.style.display = 'none';
        }

        // Update In-Store Modal Breakdown rows
        const inStoreVoucherRow = document.getElementById('inStoreVoucherRow');
        const inStoreVoucherAmount = document.getElementById('inStoreVoucherAmount');
        const inStoreFinalAmountRow = document.getElementById('inStoreFinalAmountRow');
        
        if (discountAmount > 0) {
            if (inStoreVoucherRow) inStoreVoucherRow.style.display = 'flex';
            if (inStoreVoucherAmount) inStoreVoucherAmount.textContent = formattedDiscount;
            if (inStoreFinalAmountRow) inStoreFinalAmountRow.style.display = 'flex';
        } else {
            if (inStoreVoucherRow) inStoreVoucherRow.style.display = 'none';
            if (inStoreFinalAmountRow) inStoreFinalAmountRow.style.display = 'none';
        }
        
        // Update voucher display text boxes
        const gcashVoucherSection = document.getElementById('gcashVoucherSection');
        const gcashVoucherText = document.getElementById('gcashVoucherText');
        if (discountAmount > 0 && rewardName) {
            if (gcashVoucherText) gcashVoucherText.textContent = `${rewardName} (${formattedDiscount})`;
            if (gcashVoucherSection) gcashVoucherSection.style.display = 'block';
        } else {
            if (gcashVoucherSection) gcashVoucherSection.style.display = 'none';
        }
        
        const cashVoucherSection = document.getElementById('cashVoucherSection');
        const cashVoucherText = document.getElementById('cashVoucherText');
        if (discountAmount > 0 && rewardName) {
            if (cashVoucherText) cashVoucherText.textContent = `${rewardName} (${formattedDiscount})`;
            if (cashVoucherSection) cashVoucherSection.style.display = 'block';
        } else {
            if (cashVoucherSection) cashVoucherSection.style.display = 'none';
        }

        const inStoreVoucherSection = document.getElementById('inStoreVoucherSection');
        const inStoreVoucherText = document.getElementById('inStoreVoucherText');
        if (discountAmount > 0 && rewardName) {
            if (inStoreVoucherText) inStoreVoucherText.textContent = `${rewardName} (${formattedDiscount})`;
            if (inStoreVoucherSection) inStoreVoucherSection.style.display = 'block';
        } else {
            if (inStoreVoucherSection) inStoreVoucherSection.style.display = 'none';
        }
        
        // Store the amount for payment processing
        const finalAmountField = document.getElementById('finalAmount');
        if (finalAmountField) finalAmountField.value = amount;
    }
    
    // Initialize the final amount field on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('finalAmount').value = originalAmount;
        // Initialize modal amounts with no voucher
        updateModalAmounts(originalAmount, 0, '');
    });
</script>

<?php if (isset($_SESSION['user_id'])): ?>
    <script src="../assets/js/notifications.js"></script>
    <script src="../assets/js/confirmlogout-user.js"></script>
    <script src="../assets/js/gcash-payment.js"></script>
    <script src="../assets/js/cash-payment.js"></script>
    <script src="../assets/js/in-store-payment.js"></script>
<?php endif; ?>
</body>
</html>




