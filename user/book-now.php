<?php
// user/book-now.php

session_start();

// Include configuration and helper files
require '../config.php';
require '../includes/auth-check.php';
requireLogin();
require '../includes/booking-data.php';
require '../includes/api-handlers.php';
require '../includes/holiday-utils.php';

// ✅ Ensure $conn is available (fixes Intelephense undefined variable warnings)
if (!isset($conn)) {
    die("Database connection failed");
}

// Handle API requests first (they should exit early)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    switch ($action) {
        case 'get_availability':
            if (isset($_GET['date'])) {
                $result = handleDateAvailabilityRequest($conn, $_GET['date']);
                echo json_encode($result);
            } else {
                echo json_encode(['error' => 'Date parameter required']);
            }
            break;
            
        case 'get_slot_availability':
            if (isset($_GET['date']) && isset($_GET['time_slot'])) {
                $result = handleSlotAvailabilityRequest($conn, $_GET['date'], $_GET['time_slot']);
                echo json_encode($result);
            } else {
                echo json_encode(['error' => 'Date and time_slot parameters required']);
            }
            break;
            
        case 'get_booked_slots':
            if (isset($_GET['date'])) {
                $result = handleDateSlotsRequest($conn, $_GET['date']);
                echo json_encode($result);
            } else {
                echo json_encode(['error' => 'Date parameter required']);
            }
            break;
            
        case 'get_booked_days':
            if (isset($_GET['month'])) {
                $result = handleMonthRequest($conn, $_GET['month']);
                echo json_encode($result);
            } else {
                echo json_encode(['error' => 'Month parameter required']);
            }
            break;
            
        case 'get_holidays':
            if (isset($_GET['month'])) {
                $parts = explode('-', $_GET['month']);
                $year = intval($parts[0]);
                $month = intval($parts[1]);
                $holidays = getHolidaysForMonth($month, $year);
                echo json_encode($holidays);
            } else {
                echo json_encode(['error' => 'Month parameter required']);
            }
            break;
            
        case 'get_inventory':
            $result = handleInventoryRequest($conn);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

// Handle legacy requests without action parameter (for backward compatibility)
if (isset($_GET['date'])) {
    header('Content-Type: application/json');
    $date = $_GET['date'];
    $result = handleDateSlotsRequest($conn, $date);
    echo json_encode($result);
    exit;
}

if (isset($_GET['month'])) {
    header('Content-Type: application/json');
    $month = $_GET['month'];
    $result = handleMonthRequest($conn, $month);
    echo json_encode($result);
    exit;
}

// ✅ Initial values for page load (not API requests)
$total_machines = getTotalMachineCounts($conn); // Get total machine counts
$available_machines_status = getAvailableMachines($conn); // Get operational status

// Fetch user profile data for autofill
$user_profile = null;
$claimed_rewards = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_query = $conn->prepare("SELECT first_name, last_name, phone, email FROM users WHERE id = ?");
    $user_query->bind_param('i', $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();
    if ($user_result->num_rows > 0) {
        $user_profile = $user_result->fetch_assoc();
    }
    $user_query->close();
    
    // Fetch claimed rewards for the user
    $rewards_query = $conn->prepare("SELECT id, reward_name, status, claimed_at FROM claimed_rewards WHERE user_id = ? AND status = 'Claimed' ORDER BY claimed_at DESC");
    $rewards_query->bind_param('i', $user_id);
    $rewards_query->execute();
    $rewards_result = $rewards_query->get_result();
    while ($reward = $rewards_result->fetch_assoc()) {
        $claimed_rewards[] = $reward;
    }
    $rewards_query->close();
}

// Force cast to integers to ensure they're numbers
$total_machines['washers'] = (int)$total_machines['washers'];
$total_machines['dryers'] = (int)$total_machines['dryers'];

// Time slots definition
$time_slots = [
    "7:00 AM - 8:30 AM", "8:30 AM - 10:00 AM", "10:00 AM - 11:30 AM",
    "1:00 PM - 2:30 PM", "2:30 PM - 4:00 PM", "4:00 PM - 5:30 PM", 
    "5:30 PM - 7:00 PM"
];

// Fetch data for the page
$services = getServices($conn);
$inventory_items = getInventoryByType($conn);
$washingMachine = getWashingMachines($conn);
$notifications = getNotifications($conn, $_SESSION['user_id'] ?? null);

// Separate machines by type
$washers = [];
$dryers = [];

foreach ($washingMachine as $machine) {
    if ($machine['machine_type'] == 'washer') {
        $washers[] = $machine;
    } elseif ($machine['machine_type'] == 'dryer') {
        $dryers[] = $machine;
    }
}

// Separate detergents and fabric conditioners for display purposes
$detergents = [];
$fabric_conditioners = [];

foreach ($inventory_items as $item) {
    if ($item['item_type'] == 'detergent') {
        $detergents[] = $item;
    } elseif ($item['item_type'] == 'fabric_conditioner') {
        $fabric_conditioners[] = $item;
    }
}

// Fetch booked dates for calendar highlighting
$booked_dates = getBookedDatesForCalendar($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Now - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="../assets/lib/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/book-now.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/notification.css">
    <style>
        /* Additional styles for time slots */
        .time-slot {
            cursor: pointer;
            padding: 12px;
            margin: 8px 0;
            border: 2px solid var(--gray-medium);
            border-radius: 8px;
            transition: var(--transition);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--gray-light);
        }
        
        .time-slot:hover:not(.fully-booked) {
            background-color: var(--gray-light);
            border-color: var(--gray-medium);
        }
        
        .time-slot.selected {
            background-color: #6c757d;
            color: #ffffff;
            border-color: #5a6268;
        }
        
        .time-slot.selected .machine-available {
            color: rgba(255,255,255,0.9);
        }
        
        .time-slot.booked-slot {
            background-color: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .time-slot.fully-booked {
            background-color: var(--danger-light);
            border-color: var(--danger);
            color: var(--danger-dark);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .time-slot.fully-booked .machine-available {
            color: var(--danger-dark);
        }
        
        .machine-available {
            font-size: 0.9em;
            color: var(--gray-dark);
        }
        
        .calendar-day {
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }
        
        .calendar-day:hover {
            background-color: var(--gray-light);
        }
        
        .calendar-day.selected {
            background-color: #6c757d;
            color: #ffffff;
        }
        
        .calendar-day.booked-day {
            background-color: #f3f4f6;
        }
        
        .calendar-day.today {
            font-weight: bold;
            border: 2px solid #6c757d;
        }

        /* Step title white text styling */
        .step-title {
            color: #000000 !important;
        }
        
        /* Calendar table header styling */
        .calendar-container table thead {
            background-color: transparent !important;
        }
        
        .calendar-container table thead th {
            color: #4b5563 !important;
            background-color: #f9fafb !important;
            border-color: #e5e7eb !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        
        .booking-indicator {
            width: 6px;
            height: 6px;
            background-color: #9ca3af;
            border-radius: 50%;
        }

        .holiday-indicator {
            width: 6px;
            height: 6px;
            background-color: #6366f1; /* Blue for holiday */
            border-radius: 50%;
        }

        .indicator-container {
            display: flex;
            justify-content: center;
            gap: 4px;
            margin-top: 2px;
        }

        .calendar-day.holiday-day {
            background-color: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            font-weight: 600;
        }

        .calendar-day.holiday-day.selected {
            background-color: #6c757d;
            color: #ffffff;
        }
        
        /* Machine status badges */
        .machine-status {
            background-color: #ffffff;
            border: 1px solid var(--gray-medium);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            color: #000000;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            margin-right: 10px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .status-badge.available {
            background-color: #6366f1;
            color: #000000;
        }
        
        .status-badge.total {
            background-color: #6366f1;
            color: #000000;
        }

        /* Laundry Supplies Selector Styles */
        .laundry-item-selector {
            background-color: var(--gray-light);
            border: 1px solid var(--gray-medium);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .laundry-item-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            margin-bottom: 10px;
        }

        .laundry-item-row select {
            flex: 1;
        }

        .laundry-item-row input[type="number"] {
            width: 80px;
            height: 50px !important;
        }

        .laundry-item-row .btn {
            white-space: nowrap;
            height: 50px;
            display: flex;
            align-items: center;
        }

        .selected-laundry-items {
            background-color: #ffffff;
            border: 1px solid var(--gray-medium);
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
            min-height: 60px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .selected-laundry-items.empty {
            text-align: center;
            color: var(--gray-dark);
            font-style: italic;
        }

        .laundry-item-badge {
            display: inline-flex;
            align-items: center;
            background-color: #6366f1;
            color: var(--white);
            padding: 0 16px;
            height: 50px;
            border-radius: 25px;
            margin: 5px 8px 5px 0;
            font-size: 1rem;
            gap: 12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .supply-badge-image {
            width: 36px;
            height: 36px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            padding: 4px;
        }

        .supply-badge-content {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .laundry-item-badge .qty {
            font-weight: bold;
            margin-right: 5px;
        }

        .laundry-item-badge .remove-btn {
            cursor: pointer;
            margin-left: 8px;
            padding: 0 5px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.3);
            border: none;
            color: var(--white);
            font-size: 1.1em;
            line-height: 1;
            vertical-align: middle;
            transition: background-color 0.2s;
        }

        .laundry-item-badge .remove-btn:hover {
            background-color: rgba(255, 255, 255, 0.6);
        }

        .laundry-summary {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--gray-medium);
            font-size: 0.95em;
            color: var(--black);
            font-weight: 500;
        }

        /* Additional color rule for alerts in modal */
        .alert {
            border-radius: 8px;
        }

        /* Select2 custom styling for dropdown highlight */
        .select2-container--open .select2-dropdown {
            background-color: white;
        }

        .select2-container--open .select2-results__option--highlighted {
            background-color: #6c757d !important;
            color: #ffffff !important;
        }

        .select2-container--open .select2-results__option--selected {
            background-color: #6c757d !important;
            color: #ffffff !important;
        }

        .select2-results__option--highlighted[aria-selected=true] {
            background-color: #6c757d !important;
            color: #ffffff !important;
        }

        /* 2x2 Grid layout for Step 2 sections */
        #step-2-content .row.g-4 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem !important;
        }

        /* Remove col-md-6 from grid layout flow */
        #step-2-content .row.g-4 > .col-md-6 {
            display: contents;
        }

        /* Position each section in the 2x2 grid */
        #step-2-content .row.g-4 > .mb-4:nth-of-type(1) {
            grid-column: 1;
            grid-row: 1;
        }

        #step-2-content .row.g-4 > .mb-4:nth-of-type(2) {
            grid-column: 1;
            grid-row: 2;
        }

        #step-2-content .row.g-4 > .mb-4:nth-of-type(3) {
            grid-column: 2;
            grid-row: 1;
        }

        #step-2-content .row.g-4 > .mb-4:nth-of-type(4) {
            grid-column: 2;
            grid-row: 2;
        }

        #step-2-content h4 {
            margin-bottom: 1rem !important;
            font-size: 1.1rem;
            font-weight: 600;
        }

        #step-2-content small.text-muted {
            margin-top: 0.75rem !important;
            display: block !important;
        }

        /* Style for laundry item select dropdown */
        #laundry-item-select {
            border-color: #6c757d !important;
            border: 2px solid #6c757d !important;
        }

        /* Style for laundry item quantity input */
        #laundry-item-qty {
            border-color: #6c757d !important;
            border: 2px solid #6c757d !important;
            height: 50px !important;
        }

        /* Match Select2 height with number input */
        #laundry-item-select + .select2-container .select2-selection--single {
            height: 50px !important;
            border: 2px solid #6c757d !important;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }

        #laundry-item-select + .select2-container .select2-selection__arrow {
            height: 48px !important;
        }

        #laundry-item-select + .select2-container .select2-selection__rendered {
            padding-left: 10px;
            width: 100%;
        }

        /* Style for machine count input */
        #machine-count {
            border-color: #6c757d !important;
            border: 2px solid #6c757d !important;
        }

        /* Purple checkbox styling */
        .form-check-input:checked {
            background-color: #6366f1 !important;
            border-color: #6366f1 !important;
        }

        .form-check-input:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25) !important;
        }

        /* Gray form control styling */
        .form-control {
            border-color: #6c757d !important;
            border: 1px solid #6c757d !important;
        }

        .form-control:focus {
            border-color: #6c757d !important;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25) !important;
        }
    </style>
</head>
<body>
<!-- ====== NAVBAR ====== -->
<nav class="jl-nav" id="jlNav">
  <a href="../index.php" class="jl-logo">
    <img src="../assets/images/logo.png" alt="Jorish Express Laundry">
  </a>
  <ul class="jl-nav-links">
    <li><a href="../index.php">Home</a></li>
    <li><a href="../index.php#why">About Us</a></li>
    <li><a href="../service-and-pricing.php">Services</a></li>
    <li><a href="../contact-and-map-view.php">Find Location</a></li>

    <li><a href="../index.php#news">Blog</a></li>
  </ul>
  <div class="jl-nav-right">
    <?php if (isset($_SESSION['user_id'])): ?>
      <button class="btn-notif" data-bs-toggle="modal" data-bs-target="#notificationModal">
        <i class="fas fa-bell"></i>
        <?php if (count($notifications) > 0): ?>
          <span class="notif-badge"><?php echo count($notifications); ?></span>
        <?php endif; ?>
      </button>
      <div class="dropdown">
        <button class="btn-user dropdown-toggle" data-bs-toggle="dropdown">
          <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="./user-profile.php"><i class="fas fa-user-cog me-2"></i>Manage Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="#" onclick="confirmLogout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    <?php else: ?>
      <a href="../login.php" class="btn-nav-outline">Login</a>
      <a href="../register.php" class="btn-nav-solid">Register</a>
    <?php endif; ?>
  </div>
  <button class="jl-nav-toggler" id="navToggler"><i class="fas fa-bars"></i></button>
</nav>
<section>

<!-- Main Booking Form -->
<div class="container mt-5">
    <!-- Step Indicator -->
    <div class="booking-wizard-header mb-4">
        <h2 class="text-center mb-3">Book Your Laundry Service</h2>
        <div class="step-indicator">
            <div class="step-progress">
                <div class="progress-bar" id="progress-bar" style="width: 33.33%;"></div>
            </div>
            <div class="step-circles">
                <div class="step-circle active" id="step-1-circle">
                    <span class="step-number">1</span>
                    <span class="step-title">Date & Time</span>
                </div>
                <div class="step-circle" id="step-2-circle">
                    <span class="step-number">2</span>
                    <span class="step-title">Services & Items</span>
                </div>
                <div class="step-circle" id="step-3-circle">
                    <span class="step-number">3</span>
                    <span class="step-title">Pickup & Delivery Details</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Machine Status Summary -->
    <div class="machine-status mb-4">
        <div class="row">
            <div class="col-md-6">
                <span class="status-badge available">
                    <i class="fas fa-check-circle"></i> Available Now: 
                    <?php echo $available_machines_status['washers']; ?> Washers, 
                    <?php echo $available_machines_status['dryers']; ?> Dryers
                </span>
            </div>
            <div class="col-md-6">
                <div style="color: #000000;">
                    <i class="fas fa-gift"></i> <strong>Claimed Rewards</strong>
                    <?php if (count($claimed_rewards) > 0): ?>
                        <div style="margin-top: 10px; font-size: 0.9em;">
                            <?php foreach (array_slice($claimed_rewards, 0, 3) as $reward): ?>
                                <div style="margin: 8px 0;">
                                    • <?php echo htmlspecialchars($reward['reward_name']); ?> <span style="color: rgba(0,0,0,0.6);">(<?php echo date('M d, Y', strtotime($reward['claimed_at'])); ?>)</span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($claimed_rewards) > 3): ?>
                                <div style="margin-top: 12px;">
                                    <a href="../user/claimed-rewards.php" style="color: #6366f1; text-decoration: underline; font-size: 0.85em;">View all (<?php echo count($claimed_rewards); ?> total)</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 8px; font-size: 0.9em; font-style: italic;">
                            No rewards claimed yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <form id="booking-form" action="booking_process.php" method="POST">
        <!-- Step 1 & 2 Data -->
        <input type="hidden" name="booking_date" id="booking_date" value="">
        <input type="hidden" name="time_slot" id="selected_time_slot" value="">
        <input type="hidden" name="selected_services_json" id="selected_services_json" value="">
        <input type="hidden" name="selected_laundry_supplies_json" id="selected_laundry_supplies_json" value="">
        <input type="hidden" name="selected_machines_json" id="selected_machines_json" value="">
        <input type="hidden" name="machine_count" id="hidden_machine_count" value="">
        
        <!-- Step 3 Data -->
        <input type="hidden" name="customer_first_name" id="customer_first_name" value="">
        <input type="hidden" name="customer_last_name" id="customer_last_name" value="">
        <input type="hidden" name="customer_mobile" id="customer_mobile" value="">
        <input type="hidden" name="customer_email" id="customer_email" value="">
        <input type="hidden" name="pickup_address" id="pickup_address" value="">
        <input type="hidden" name="delivery_address" id="delivery_address" value="">
        
        <!-- User Profile Data (for auto-fill on self-service) -->
        <input type="hidden" id="user_first_name" value="<?php echo htmlspecialchars($user_profile['first_name'] ?? ''); ?>">
        <input type="hidden" id="user_last_name" value="<?php echo htmlspecialchars($user_profile['last_name'] ?? ''); ?>">
        <input type="hidden" id="user_phone" value="<?php echo htmlspecialchars($user_profile['phone'] ?? ''); ?>">
        <input type="hidden" id="user_email" value="<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?>">
        
        <!-- Step Containers -->
        <div class="booking-wizard-container container-box p-4">
            <!-- STEP 1: Calendar & Time Selection -->
            <div class="booking-step" id="step-1-content">
                <div class="row g-4">
                    <!-- Calendar Column -->
                    <div class="col-md-6">
                        <h4 class="mb-3">Select Date</h4>
                        <div class="calendar-container text-center">
                            <h5>
                                <button type="button" id="prev-month" class="btn btn-sm btn-outline-primary"><i class="fas fa-chevron-left"></i></button>
                                <span id="current-month-year"></span>
                                <button type="button" id="next-month" class="btn btn-sm btn-outline-primary"><i class="fas fa-chevron-right"></i></button>
                            </h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
                                        <th>Thu</th><th>Fri</th><th>Sat</th>
                                    </tr>
                                </thead>
                                <tbody id="calendar-body"></tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Time Slots Column -->
                    <div class="col-md-6">
                        <h4 class="mb-3">Select Preferred Time</h4>
                        <div class="time-slots-container">
                            <div id="time-slots">
                                <?php foreach ($time_slots as $slot): ?>
                                    <div class="time-slot"
                                        onclick="selectTimeSlot(this)"
                                        data-slot="<?php echo htmlspecialchars($slot); ?>"
                                        data-available-washers="<?php echo $total_machines['washers']; ?>"
                                        data-available-dryers="<?php echo $total_machines['dryers']; ?>"
                                        data-total-washers="<?php echo $total_machines['washers']; ?>"
                                        data-total-dryers="<?php echo $total_machines['dryers']; ?>">
                                        <div class="slot-top-row">
                                            <span class="slot-time"><?php echo $slot; ?></span>
                                            <span class="machine-available">
                                                <?php
                                                echo $total_machines['washers'] . " Washers &bull; " .
                                                     $total_machines['dryers'] . " Dryers free";
                                                ?>
                                            </span>
                                        </div>
                                        <div class="machine-chips"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- STEP 2: Services & Items Selection -->
            <div class="booking-step" id="step-2-content" style="display: none;">
                <div class="row g-4">
                    <!-- Service Type & Laundry Items Column -->
                    <div class="col-md-6">
                        <!-- Service Type Section -->
                        <div class="mb-4">
                            <h4>Service</h4>
                            <select id="service-type" name="service_type[]" class="form-control" multiple="multiple" required>
                                <?php foreach ($services as $service): 
                                    // Service descriptions for tooltips
                                    $descriptions = [
                                        'Full-Service - Wash & Dry' => 'Staff handles washing and drying for a hassle-free experience.',
                                        'Self-Service - Washer' => 'Use our washing machines and manage your own laundry.',
                                        'Self-Service - Dryer' => 'Use our dryers to dry your clothes easily and efficiently.',
                                        'Fold' => 'We neatly fold your clean and dry clothes for you.'
                                    ];
                                    $description = $descriptions[$service['service_name']] ?? '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($service['service_name']); ?>"
                                            data-name="<?php echo htmlspecialchars($service['service_name']); ?>"
                                            data-price="<?php echo number_format((float)$service['price'], 2, '.', ''); ?>"
                                            title="<?php echo htmlspecialchars($description); ?>">
                                        <?php echo htmlspecialchars($service['service_name']); ?> — ₱<?php echo number_format((float)$service['price'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block mt-2">Select one or more services</small>
                        </div>
                        
                        <!-- Laundry To Use Section -->
                        <div class="mb-4">
                            <h4>Supplies</h4>
                            <div class="laundry-item-selector">
                                <div class="laundry-item-row">
                                    <select id="laundry-item-select" class="form-control">
                                        <option value="">- Select a detergent or fabric conditioner -</option>
                                        
                                        <?php if (!empty($detergents)): ?>
                                            <optgroup label="Detergents">
                                                <?php foreach ($detergents as $detergent): ?>
                                                    <option value="<?php echo htmlspecialchars($detergent['item_name']); ?>"
                                                            data-stock="<?php echo (int)$detergent['stock_quantity']; ?>"
                                                            data-price="<?php echo number_format((float)$detergent['price'], 2, '.', ''); ?>"
                                                            data-image="<?php echo htmlspecialchars($detergent['item_name']); ?>">
                                                        <?php echo htmlspecialchars($detergent['item_name']); ?> — ₱<?php echo number_format((float)$detergent['price'], 2); ?> (<?php echo (int)$detergent['stock_quantity']; ?> available)
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($fabric_conditioners)): ?>
                                            <optgroup label="Fabric Conditioners">
                                                <?php foreach ($fabric_conditioners as $conditioner): ?>
                                                    <option value="<?php echo htmlspecialchars($conditioner['item_name']); ?>"
                                                            data-stock="<?php echo (int)$conditioner['stock_quantity']; ?>"
                                                            data-price="<?php echo number_format((float)$conditioner['price'], 2, '.', ''); ?>"
                                                            data-image="<?php echo htmlspecialchars($conditioner['item_name']); ?>">
                                                        <?php echo htmlspecialchars($conditioner['item_name']); ?> — ₱<?php echo number_format((float)$conditioner['price'], 2); ?> (<?php echo (int)$conditioner['stock_quantity']; ?> available)
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <input type="number" id="laundry-item-qty" class="form-control" min="1" value="1" placeholder="Qty">
                                    <button type="button" class="btn" id="add-laundry-btn" onclick="addLaundryItem()" style="background-color: #6366f1; color: white; border-color: #6366f1;">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-2">Optional: Select an item, enter quantity, and click Add</small>
                            </div>

                            <!-- Selected Laundry Items Display -->
                            <div id="selected-laundry-items-display" class="selected-laundry-items empty mt-3">
                                <em>No items selected yet</em>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Machine Selection Column -->
                    <div class="col-md-6">
                        <!-- Machine to Use Section -->
                        <div class="mb-4">
                            <h4>Machines</h4>
                            <select id="machine-type" name="machine_type[]" class="form-control" multiple="multiple" style="width: 100%;" required>
                                
                                <?php if (!empty($washers)): ?>
                                    <optgroup label="Washers">
                                        <?php foreach ($washers as $washer): ?>
                                            <option value="<?php echo htmlspecialchars($washer['machine_name']); ?>" 
                                                    data-machineId="<?php echo $washer['id']; ?>"
                                                    data-machineType="washer">
                                                <?php echo htmlspecialchars($washer['machine_name']); ?> 
                                                <?php if (!empty($washer['machine_model'])): ?>
                                                    (<?php echo htmlspecialchars($washer['machine_model']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                
                                <?php if (!empty($dryers)): ?>
                                    <optgroup label="Dryers">
                                        <?php foreach ($dryers as $dryer): ?>
                                            <option value="<?php echo htmlspecialchars($dryer['machine_name']); ?>" 
                                                    data-machineId="<?php echo $dryer['id']; ?>"
                                                    data-machineType="dryer">
                                                <?php echo htmlspecialchars($dryer['machine_name']); ?>
                                                <?php if (!empty($dryer['machine_model'])): ?>
                                                    (<?php echo htmlspecialchars($dryer['machine_model']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">Select one or more machines</small>
                        </div>
                        
                        <!-- Number of Machines Section -->
                        <div class="mb-4">
                            <h4>No. of Machines</h4>
                            <input type="number" id="machine-count" class="form-control" min="1" max="2" value="1" placeholder="Enter number of machines" required>
                            <small class="text-muted">Max: 2 machines per booking</small>
                        </div>
                    </div>
                </div>

                <!-- Price Summary Card -->
                <div class="price-summary-card" id="price-summary-card">
                    <div class="price-summary-header">
                        <i class="fas fa-receipt"></i>
                        <span>Price Estimate</span>
                        <span class="price-summary-note">per machine load</span>
                    </div>
                    <div class="price-summary-body" id="price-summary-body">
                        <div class="price-empty-state">
                            <i class="fas fa-tags"></i>
                            <span>Select services to see price estimate</span>
                        </div>
                    </div>
                    <div class="price-summary-footer" id="price-summary-footer" style="display:none;">
                        <div class="price-total-row">
                            <span class="price-total-label"><i class="fas fa-peso-sign"></i> Estimated Total</span>
                            <span class="price-total-amount" id="price-total-amount">₱0.00</span>
                        </div>
                        <p class="price-disclaimer">* Final price may vary based on actual weight and additional services.</p>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Delivery Details -->
            <div class="booking-step" id="step-3-content" style="display: none;">
                <!-- Request Service Section -->
                <div class="mb-4">
                    <h5>Additional Services</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="request_service[]" id="pickup" value="Pickup" onchange="togglePickupDeliveryFields()">
                        <label class="form-check-label" for="pickup">
                            Pickup Service
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="request_service[]" id="delivery" value="Delivery" onchange="togglePickupDeliveryFields()">
                        <label class="form-check-label" for="delivery">
                            Delivery Service
                        </label>
                    </div>
                    <small class="text-muted d-block mt-2">Select services for pickup and/or delivery</small>
                </div>
                
                <!-- Address Information Row -->
                <div class="row g-4 mt-4" id="address-section-group" style="display: none;">
                    <div class="col-12">
                        <h4 class="mb-4">Pickup and Delivery Address</h4>
                        
                        <div class="mb-3" id="pickup-address-group" style="display: none;">
                            <label for="step3-pickup-address" class="form-label">Pickup Address</label>
                            <input type="text" class="form-control" id="step3-pickup-address" placeholder="Cavite">
                            <small class="text-danger d-none" id="error-pickup-address">Pickup address is required</small>
                        </div>
                        
                        <div class="mb-3" id="delivery-address-group" style="display: none;">
                            <label for="step3-delivery-address" class="form-label">Delivery Address</label>
                            <input type="text" class="form-control" id="step3-delivery-address" placeholder="Cavite">
                            <small class="text-danger d-none" id="error-delivery-address">Delivery address is required</small>
                        </div>
                    </div>
                </div>

                <!-- Location Details Section -->
                <div class="row mt-4" id="location-details-group" style="display: none;">
                    <div class="col-12">
                        <h5 class="mb-3">Location Details</h5>
                        <textarea class="form-control" id="location-details" name="location_details" rows="4" placeholder="Landmark, Unit Number, Building, etc." style="border-color: #6c757d; border: 2px solid #6c757d;"></textarea>
                        <small class="text-muted d-block mt-2">Provide additional details to help us locate your address easily</small>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Buttons -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn" id="prev-btn" onclick="goToPreviousStep()" style="background-color: #6c757d; color: white; border-color: #6c757d; display: none;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <div id="button-spacer"></div>
                        <button type="button" class="btn btn-primary" id="next-btn" onclick="goToNextStep()">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                        <button type="button" class="btn btn-lg" id="submit-btn" onclick="submitBooking()" style="background-color: #6366f1; color: white; border-color: #6366f1; display: none;">
                            <i class="fas fa-check"></i> Submit Booking
                        </button>
                    </div>
                </div>
            </div>
        </div> <!-- End booking-wizard-container -->
    </form> 
</div> 
</section>

<!-- Self-Service Booking Confirmation Modal -->
<div class="modal fade" id="selfServiceConfirmationModal" tabindex="-1" aria-labelledby="selfServiceConfirmationLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                <h5 class="modal-title" id="selfServiceConfirmationLabel" style="color: white; font-weight: 600;">
                    <i class="fas fa-tools me-2"></i> Self-Service Booking Confirmation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="cancelSelfServiceBooking()"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> You've selected a self-service option. This means you'll be visiting our shop and managing your own laundry. No pickup or delivery service is needed.
                </div>
                
                <h6 class="mb-3" style="color: #374151; font-weight: 600;">
                    <i class="fas fa-check-circle me-2" style="color: #10b981;"></i> Booking Summary
                </h6>
                
                <div style="background-color: #f3f4f6; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                    <div class="row mb-2">
                        <div class="col-6">
                            <span style="color: #6b7280; font-size: 0.9rem;">Selected Services:</span>
                        </div>
                        <div class="col-6 text-end">
                            <span id="confirmServicesList" style="font-weight: 500; color: #1f2937;"></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <span style="color: #6b7280; font-size: 0.9rem;">Date & Time:</span>
                        </div>
                        <div class="col-6 text-end">
                            <span id="confirmDateTime" style="font-weight: 500; color: #1f2937;"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background-color: #6c757d; color: white;" onclick="cancelSelfServiceBooking()" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Go Back
                </button>
                <button type="button" class="btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none;" onclick="confirmSelfServiceBooking()">
                    <i class="fas fa-check me-2"></i> Confirm Booking
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notification Modal - Placed outside section for proper z-index stacking -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationModalLabel">Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="closeNotificationModal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group notification-list">
                    <?php if (!empty($notifications) && count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <li class="list-group-item notification-item <?php echo ($notification['is_read'] ?? 0) ? 'read-notification' : ''; ?>" 
                                data-id="<?php echo $notification['id']; ?>">
                                <strong><?php echo htmlspecialchars($notification['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?>
                                <small class="text-muted d-block"><?php 
                                    if (isset($notification['created_at'])) {
                                        echo date("F j, Y, g:i A", strtotime($notification['created_at']));
                                    }
                                ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted">No new notifications.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="markAllReadBtn">Mark All as Read</button>
            </div>
        </div>
    </div>
</div>

<!-- ====== FOOTER ====== -->
<footer class="jl-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <img src="../assets/images/logo.png" alt="Jorish Express Laundry">
      <span>Jorish Express Laundry</span>
    </div>
    <ul class="footer-links">
      <li><a href="../index.php">Home</a></li>
      <li><a href="../index.php#why">About Us</a></li>
      <li><a href="../service-and-pricing.php">Services</a></li>
      <li><a href="../contact-and-map-view.php">Find Location</a></li>

      <li><a href="../index.php#news">Blog</a></li>
    </ul>
    <div class="footer-social">
      <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank"><i class="fab fa-facebook-f"></i></a>
      <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank"><i class="fab fa-facebook-messenger"></i></a>
    </div>
  </div>
  <div class="footer-copy">
    &copy; <?php echo date("Y"); ?> Jorish Express Laundry. All Rights Reserved.
  </div>
</footer>

<!-- Floating Social -->
<div class="floating-social">
  <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank" class="fs-item">
    <i class="fab fa-facebook-f"></i><span>Facebook</span>
  </a>
  <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank" class="fs-item">
    <i class="fab fa-facebook-messenger"></i><span>Messenger</span>
  </a>
</div>

<script src="../assets/lib/js/jquery-3.7.1.min.js"></script>
<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
<script src="../assets/lib/js/select2.full.min.js"></script>
<link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
<script src="../assets/lib/js/sweetalert2.min.js"></script>
<script>
  // Navbar toggler for mobile
  document.getElementById('navToggler').addEventListener('click', function() {
    document.getElementById('jlNav').classList.toggle('open');
  });
</script>


<script>
    // Global array to store laundry items with quantities
    window.selectedLaundryItems = [];

    /* ── Price estimate ─────────────────────────────────────────────────── */
    // Supply price lookup: built from PHP data so we don't need extra fetches
    window._supplyPrices = {};
    <?php
    foreach (array_merge($detergents, $fabric_conditioners) as $item):
    ?>
    window._supplyPrices[<?= json_encode($item['item_name']) ?>] = <?= (float)$item['price'] ?>;
    <?php endforeach; ?>

    function calcPrice() {
        const bodyEl   = document.getElementById('price-summary-body');
        const footerEl = document.getElementById('price-summary-footer');
        const totalEl  = document.getElementById('price-total-amount');
        if (!bodyEl) return;

        // ── Services ──────────────────────────────────────────────────────
        const serviceOpts = Array.from(
            document.querySelectorAll('#service-type option:checked')
        );

        let total = 0;
        let html  = '';

        if (serviceOpts.length > 0) {
            html += `<div class="price-section-label"><i class="fas fa-concierge-bell me-1"></i>Services</div>`;
            serviceOpts.forEach(opt => {
                const name  = opt.getAttribute('data-name') || opt.text.split(' — ')[0].trim();
                const price = parseFloat(opt.getAttribute('data-price')) || 0;
                const sub   = price;
                total += sub;
                html += `
                <div class="price-line-row">
                    <span class="price-line-name">${name}</span>
                    <span class="price-line-amount">₱${sub.toFixed(2)}</span>
                </div>`;
            });
        }

        // ── Supplies ──────────────────────────────────────────────────────
        if (window.selectedLaundryItems.length > 0) {
            html += `<div class="price-section-label" style="margin-top:.75rem"><i class="fas fa-soap me-1"></i>Supplies</div>`;
            window.selectedLaundryItems.forEach(item => {
                const price = window._supplyPrices[item.name] || 0;
                const sub   = price * item.qty;
                total += sub;
                html += `
                <div class="price-line-row">
                    <span class="price-line-name">${item.name}
                        <span class="price-line-meta">× ${item.qty}</span>
                    </span>
                    <span class="price-line-amount">₱${sub.toFixed(2)}</span>
                </div>`;
            });
        }

        if (!html) {
            bodyEl.innerHTML = `<div class="price-empty-state"><i class="fas fa-tags"></i><span>Select services to see price estimate</span></div>`;
            footerEl.style.display = 'none';
            return;
        }

        bodyEl.innerHTML = html;
        totalEl.textContent = `₱${total.toFixed(2)}`;
        footerEl.style.display = 'block';
    }

    function addLaundryItem() {
        const select = document.getElementById('laundry-item-select');
        const qtyInput = document.getElementById('laundry-item-qty');
        
        const itemName = select.value;
        const quantity = parseInt(qtyInput.value) || 1;
        
        if (!itemName) {
            Swal.fire({
                icon: 'warning',
                title: 'No Item Selected',
                text: 'Please select a laundry item first.',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (quantity < 1) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Quantity',
                text: 'Quantity must be at least 1.',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Get stock info
        const selectedOption = select.options[select.selectedIndex];
        const availableStock = parseInt(selectedOption.dataset.stock) || 0;

        if (quantity > availableStock) {
            Swal.fire({
                icon: 'warning',
                title: 'Insufficient Stock',
                text: `Only ${availableStock} units available for ${itemName}.`,
                confirmButtonText: 'OK'
            });
            return;
        }

        // Add to selected items (allow duplicates with different or same quantities)
        window.selectedLaundryItems.push({
            name: itemName,
            qty: quantity,
            timestamp: Date.now() // Unique identifier for removing duplicates
        });

        // Update display
        updateLaundryDisplay();

        // Reset inputs
        select.value = '';
        qtyInput.value = '1';
    }

    function removeLaundryItem(timestamp) {
        window.selectedLaundryItems = window.selectedLaundryItems.filter(item => item.timestamp !== timestamp);
        updateLaundryDisplay();
        calcPrice();
    }

    function updateLaundryDisplay() {
        const displayDiv = document.getElementById('selected-laundry-items-display');
        const jsonField = document.getElementById('selected_laundry_supplies_json');

        if (window.selectedLaundryItems.length === 0) {
            displayDiv.className = 'selected-laundry-items empty';
            displayDiv.innerHTML = '<em>No items selected yet</em>';
            jsonField.value = '';
            return;
        }

        displayDiv.className = 'selected-laundry-items';
        
        let badgesHTML = '';
        let totalQty = 0;
        
        window.selectedLaundryItems.forEach(item => {
            totalQty += item.qty;
            const imagePath = getSupplyImage(item.name);
            badgesHTML += `
                <div class="laundry-item-badge">
                    <img src="${imagePath}" alt="${item.name}" class="supply-badge-image">
                    <div class="supply-badge-content">
                        <span class="qty">${item.qty}x</span>
                        <span>${item.name}</span>
                    </div>
                    <button type="button" class="remove-btn" onclick="removeLaundryItem(${item.timestamp})" title="Remove this item">
                        ×
                    </button>
                </div>
            `;
        });

        badgesHTML += `
            <div class="laundry-summary">
                Total Items: <strong>${totalQty}</strong>
            </div>
        `;

        displayDiv.innerHTML = badgesHTML;

        // Update the hidden JSON field for form submission
        jsonField.value = JSON.stringify(window.selectedLaundryItems.map(item => ({
            name: item.name,
            qty: item.qty
        })));

        calcPrice();
    }

    // Allow pressing Enter to add item
    document.getElementById('laundry-item-qty').addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addLaundryItem();
        }
    });

    // Validate machine count - maximum 2 machines per booking
    document.getElementById('machine-count').addEventListener('change', function() {
        const machineCount = parseInt(this.value) || 0;
        if (machineCount > 2) {
            Swal.fire({
                icon: 'warning',
                title: 'No. of Machines Exceeded',
                text: 'You can only use a maximum of 2 machines per booking.',
                confirmButtonText: 'OK'
            });
            this.value = 2;
        }
        calcPrice();
    });

    // Re-calculate price whenever service selection changes (Select2 fires 'change')
    document.addEventListener('DOMContentLoaded', function () {
        $('#service-type').on('change', calcPrice);
        // Listen for service changes to detect self-service and update buttons
        $('#service-type').on('change', function() {
            const selectedServices = $(this).val() || [];
            const isSelfService = selectedServices.some(service => 
                service.toLowerCase().includes('self-service') || service.toLowerCase().includes('self service')
            );
            
            // Update booking data and button display
            bookingData.step2.isSelfService = isSelfService;
            updateButtons();
            
            // Update confirmation modal with current selections
            updateSelfServiceConfirmationModal();
        });
        calcPrice(); // initial render
    });

    $(document).ready(function() {
        // Function to get product image path based on item name
        window.getSupplyImage = function(itemName) {
            const itemLower = itemName.toLowerCase();
            const imageDir = '../assets/images/';
            
            // Map product names to image files
            const imageMap = {
                'champion antibacterial': 'champion%20antibacterial.png',
                'champion antibac': 'champion%20antibacterial.png',
                'champion': 'champion.png',
                'downy antibacterial': 'downyantibac.png',
                'downy antibac': 'downyantibac.png',
                'downy kontrakulob': 'downykontrakulob.png',
                'downy kontra kulob': 'downykontrakulob.png',
                'downy sunrise fresh': 'downysunrisefresh.png',
                'downy': 'downysunrisefresh.png',
                'del': 'del.png',
                'ariel': 'ariel.png',
                'breeze': 'breeze.png',
                'surf downy': 'surfdowny.png',
                'surf': 'surf.png'
            };
            
            // Check for exact matches first
            for (const [key, image] of Object.entries(imageMap)) {
                if (itemLower === key.toLowerCase()) {
                    return imageDir + image;
                }
            }
            
            // Check for partial matches
            for (const [key, image] of Object.entries(imageMap)) {
                if (itemLower.includes(key.toLowerCase())) {
                    return imageDir + image;
                }
            }
            
            // Return default image if no match found
            return imageDir + 'default.png';
        };
        
        // Initialize Select2 on laundry item dropdown with images
        $('#laundry-item-select').select2({
            placeholder: "Select a detergent or fabric conditioner",
            allowClear: true,
            width: '100%',
            templateResult: formatLaundryOption,
            templateSelection: formatLaundrySelection
        });
        
        // Custom formatter for laundry options to show images
        function formatLaundryOption(option) {
            if (!option.id) {
                return option.text;
            }
            
            const imagePath = window.getSupplyImage(option.text);
            const $option = $(
                '<div style="display: flex; align-items: center;">' +
                    '<img src="' + imagePath + '" alt="' + option.text + '" style="width: 40px; height: 40px; object-fit: contain; margin-right: 10px; background: #f5f5f5; border-radius: 4px; padding: 4px;">' +
                    '<span>' + option.text + '</span>' +
                '</div>'
            );
            
            return $option;
        }
        
        function formatLaundrySelection(option) {
            if (!option.id) {
                return option.text;
            }
            
            // Extract only the item name (everything before the dash)
            const itemName = option.text.split(' — ')[0].trim();
            const imagePath = window.getSupplyImage(itemName);
            
            const $selection = $(
                '<div style="display: flex; align-items: center;">' +
                    '<img src="' + imagePath + '" alt="' + itemName + '" style="width: 36px; height: 36px; object-fit: contain; margin-right: 10px; background: #f5f5f5; border-radius: 4px; padding: 3px;">' +
                    '<span style="font-weight: 500; font-size: 1.05rem;">' + itemName + '</span>' +
                '</div>'
            );
            
            return $selection;
        }
        
        // Initialize Select2 on machine type dropdown with organized groups
        $('#machine-type').select2({
            placeholder: "Select machines",
            allowClear: true,
            width: '100%',
            templateResult: formatMachineOption,
            templateSelection: formatMachineSelection
        });
        
        // Initialize Select2 on service type dropdown
        $('#service-type').select2({
            placeholder: "Select services",
            allowClear: true,
            width: '100%'
        });
        
        // Custom formatter for machine options to show images
        function formatMachineOption(machine) {
            if (!machine.id) {
                return machine.text;
            }
            
            // Check if it's a washer or dryer based on the optgroup
            var optgroup = machine.parent && machine.parent.label ? machine.parent.label : '';
            var imageFile = optgroup.includes('Washers') ? 'washer.png' : 'dryer.png';
            var imagePath = '../assets/images/' + imageFile;
            
            var $machine = $(
                '<div style="display: flex; align-items: center;">' +
                    '<img src="' + imagePath + '" alt="' + imageFile.replace('.png', '') + '" style="width: 40px; height: 40px; object-fit: contain; margin-right: 10px; background: #f5f5f5; border-radius: 4px; padding: 4px;">' +
                    '<span>' + machine.text + '</span>' +
                '</div>'
            );
            
            return $machine;
        }
        
        function formatMachineSelection(machine) {
            if (!machine.id) {
                return machine.text;
            }
            
            // Check if it's a washer or dryer based on the optgroup
            var optgroup = machine.parent && machine.parent.label ? machine.parent.label : '';
            var imageFile = optgroup.includes('Washers') ? 'washer.png' : 'dryer.png';
            var imagePath = '../assets/images/' + imageFile;
            
            var $selection = $(
                '<div style="display: flex; align-items: center;">' +
                    '<img src="' + imagePath + '" alt="' + imageFile.replace('.png', '') + '" style="width: 32px; height: 32px; object-fit: contain; margin-right: 8px; background: #f5f5f5; border-radius: 4px; padding: 3px;">' +
                    '<span>' + machine.text + '</span>' +
                '</div>'
            );
            
            return $selection;
        }
        
        // Add counter for selected machines to help with machine count validation
        $('#machine-type').on('change', function() {
            var selectedCount = $(this).val() ? $(this).val().length : 0;
            var machineCountInput = $('#machine-count');
            var currentValue = parseInt(machineCountInput.val()) || 0;
            
            // Optional: Update placeholder or show hint
            if (selectedCount > 0) {
                $('#machine-type').next('.select2-container').find('.select2-selection').attr('title', selectedCount + ' machine(s) selected');
            }
            
            // Trigger validation with the currently selected time slot if any
            var selectedSlot = document.getElementById("selected_time_slot")?.value;
            if (selectedSlot && window.getAvailableMachinesForSlot) {
                var slotElement = document.querySelector(`.time-slot[data-slot="${selectedSlot}"]`);
                if (slotElement) {
                    var maxAvailable = window.getAvailableMachinesForSlot(slotElement);
                    if (currentValue > maxAvailable) {
                        machineCountInput.val(maxAvailable);
                    }
                }
            }
        });
    });

    // Add debug function to test API
    async function testAvailabilityAPI() {
        const today = new Date().toISOString().split('T')[0];
        console.log("Testing API for date:", today);
        try {
            const response = await fetch(`book-now.php?action=get_availability&date=${today}`);
            const data = await response.json();
            console.log("API Response:", data);
            return data;
        } catch (error) {
            console.error("API Test failed:", error);
        }
    }

    // Run test after page loads
    setTimeout(testAvailabilityAPI, 2000);

    // Show/hide Pickup/Delivery address and location details fields
    function togglePickupDeliveryFields() {
        var pickupChecked = document.getElementById('pickup').checked;
        var deliveryChecked = document.getElementById('delivery').checked;
        // Pickup Address
        document.getElementById('pickup-address-group').style.display = pickupChecked ? '' : 'none';
        // Delivery Address
        document.getElementById('delivery-address-group').style.display = deliveryChecked ? '' : 'none';
        // Address Section Header and fields (show if either is checked)
        document.getElementById('address-section-group').style.display = (pickupChecked || deliveryChecked) ? '' : 'none';
        // Location Details (show if either is checked)
        document.getElementById('location-details-group').style.display = (pickupChecked || deliveryChecked) ? '' : 'none';
    }
    window.addEventListener('DOMContentLoaded', function() {
        togglePickupDeliveryFields(); // Set initial state
    });

    // Function to update self-service confirmation modal with booking details
    function updateSelfServiceConfirmationModal() {
        // Get selected services using jQuery/Select2 (returns array)
        const selectedServices = $('#service-type').val() || [];
        
        // Get selected date and time
        const bookingDate = document.getElementById('booking_date').value;
        const selectedTimeSlot = document.getElementById('selected_time_slot').value;
        
        // Format date
        let dateTimeText = '';
        if (bookingDate && selectedTimeSlot) {
            const date = new Date(bookingDate + 'T00:00:00');
            const formattedDate = date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            dateTimeText = `${formattedDate} at ${selectedTimeSlot}`;
        } else {
            dateTimeText = 'Not yet selected';
        }
        
        // Format services list (ensure it's an array)
        const serviceArray = Array.isArray(selectedServices) ? selectedServices : [selectedServices].filter(s => s);
        const servicesList = serviceArray.length > 0 ? serviceArray.join(', ') : 'None selected';
        
        // Update modal content
        const servicesElement = document.getElementById('confirmServicesList');
        const dateTimeElement = document.getElementById('confirmDateTime');
        
        if (servicesElement) {
            servicesElement.textContent = servicesList;
        }
        if (dateTimeElement) {
            dateTimeElement.textContent = dateTimeText;
        }
    }

</script>
<script src="../assets/js/booking-form.js"></script>
<script src="../assets/js/booking-wizard.js"></script>
<link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
<script src="../assets/lib/js/sweetalert2.min.js"></script>

<?php if (isset($_SESSION['user_id'])): ?>
<script src="../assets/js/notifications.js"></script>
<script src="../assets/js/confirmlogout-user.js"></script>
<?php endif; ?>
</body>
</html>




