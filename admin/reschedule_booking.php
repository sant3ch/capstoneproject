<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require '../config.php';
require '../includes/holiday-utils.php';
require_once '../includes/booking-functions.php';
require_once '../includes/booking-data.php';
require_once '../includes/api-handlers.php';

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

        case 'get_booked_days':
            if (isset($_GET['month'])) {
                $month_str = $_GET['month'];
                $booked_result = mysqli_query($conn,
                    "SELECT DISTINCT booking_date FROM bookings
                     WHERE DATE_FORMAT(booking_date,'%Y-%m') = '" . mysqli_real_escape_string($conn, $month_str) . "'
                     AND status <> 'Cancelled'"
                );
                $booked_days = [];
                while ($row = mysqli_fetch_assoc($booked_result)) {
                    $booked_days[] = $row['booking_date'];
                }
                echo json_encode($booked_days);
            } else {
                echo json_encode([]);
            }
            break;

        case 'get_holidays':
            if (isset($_GET['month'])) {
                $month_str = $_GET['month'];
                $parts = explode('-', $month_str);
                $year = intval($parts[0]);
                $month = intval($parts[1]);
                
                $holidays = getHolidaysForMonth($month, $year);
                echo json_encode($holidays);
            } else {
                echo json_encode([]);
            }
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    header("Location: booking_schedules.php?error=Invalid booking ID");
    exit();
}

// Fetch booking details
$booking_query = "
    SELECT b.*, u.first_name, u.last_name, u.id as user_id
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    header("Location: booking_schedules.php?error=Booking not found");
    exit();
}

// Parse current booking details
$selected_services = !empty($booking['service_type']) ? array_map('trim', explode(',', $booking['service_type'])) : [];
$selected_machines = !empty($booking['machine_names']) ? array_map('trim', explode(',', $booking['machine_names'])) : [];
$selected_laundry_items = [];

if (!empty($booking['detergent']) && $booking['detergent'] !== 'N/A') {
    $detergent_parts = explode(',', $booking['detergent']);
    foreach ($detergent_parts as $part) {
        $part = trim($part);
        if (!empty($part) && preg_match('/^(\d+)\s*x\s+(.+)$/i', $part, $matches)) {
            $selected_laundry_items[] = [
                'qty' => intval($matches[1]),
                'name' => trim($matches[2])
            ];
        } elseif (!empty($part)) {
            $selected_laundry_items[] = [
                'qty' => 1,
                'name' => $part
            ];
        }
    }
}

// Get available services
$services_query = "SELECT DISTINCT service_name FROM services ORDER BY service_name ASC";
$services_result = mysqli_query($conn, $services_query);
$services = [];
while ($service = mysqli_fetch_assoc($services_result)) {
    $services[] = $service;
}

// Get total and available machine counts
$total_machines = getTotalMachineCounts($conn); // Get total machine counts from database
$available_machines_status = getAvailableMachines($conn); // Get operational status

// Get all machines for the dropdown
$washingMachine = getWashingMachines($conn);
$washers = [];
$dryers = [];
foreach ($washingMachine as $machine) {
    if ($machine['machine_type'] === 'washer') {
        $washers[] = $machine;
    } elseif ($machine['machine_type'] === 'dryer') {
        $dryers[] = $machine;
    }
}

// Get inventory items
$inventory_query = "SELECT item_name, item_type, stock_quantity FROM inventory WHERE stock_quantity > 0 ORDER BY item_type, item_name";
$inventory_result = mysqli_query($conn, $inventory_query);
$detergents = [];
$fabric_conditioners = [];
while ($item = mysqli_fetch_assoc($inventory_result)) {
    if ($item['item_type'] === 'detergent') {
        $detergents[] = $item;
    } elseif ($item['item_type'] === 'fabric_conditioner') {
        $fabric_conditioners[] = $item;
    }
}

// Get available time slots
$time_slots = [
    "7:00 AM - 8:30 AM", "8:30 AM - 10:00 AM", "10:00 AM - 11:30 AM",
    "1:00 PM - 2:30 PM", "2:30 PM - 4:00 PM", "4:00 PM - 5:30 PM", 
    "5:30 PM - 7:00 PM"
];
$selected_request_services = !empty($booking['request_service']) ? array_map('trim', explode(',', $booking['request_service'])) : [];

// Get calendar dates for data
$bookingDate = new DateTime($booking['booking_date']);
$currentMonth = (int)$bookingDate->format('n');
$currentYear = (int)$bookingDate->format('Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Booking - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link rel="stylesheet" href="../assets/lib/css/select2.min.css">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link rel="stylesheet" href="../assets/css/reschedule.css">
    <style>
        /* Wizard Header Styling */
        .booking-wizard-header {
            background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%);
            border-radius: 8px;
            padding: 30px 20px;
            color: var(--white);
            margin-bottom: 30px !important;
        }

        .booking-wizard-header h2 {
            color: var(--white);
            margin-bottom: 20px;
        }

        .step-indicator {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .step-progress {
            width: 100%;
            height: 4px;
            background-color: rgba(255,255,255,0.3);
            border-radius: 2px;
            overflow: hidden;
        }

        .step-progress .progress-bar {
            background-color: var(--white);
            height: 100%;
            transition: width 0.3s ease;
        }

        .step-circles {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .step-circle {
            flex: 1;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }

        .step-circle.active {
            opacity: 1;
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255,255,255,0.3);
            border-radius: 50%;
            font-weight: bold;
            font-size: 1.1rem;
            border: 2px solid var(--white);
        }

        .step-circle.active .step-number {
            background-color: var(--white);
            color: var(--purple-primary);
        }

        .step-title {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .booking-wizard-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .booking-step {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Time Slot Styling */
        .time-slot {
            cursor: pointer;
            padding: 12px;
            margin: 8px 0;
            border: 2px solid var(--gray-medium);
            border-radius: 8px;
            transition: all 0.3s;
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
            background-color: var(--purple-primary);
            color: var(--white);
            border-color: var(--purple-dark);
        }

        .time-slot.selected .machine-available {
            color: rgba(255,255,255,0.9);
        }

        .machine-available {
            font-size: 0.9em;
            color: var(--gray-dark);
        }

        /* Laundry Items Styling */
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

        .laundry-item-row select,
        .laundry-item-row input[type="number"] {
            flex: 1;
        }

        .laundry-item-row .btn {
            white-space: nowrap;
        }

        .selected-laundry-items {
            background-color: var(--purple-primary);
            border: 1px solid var(--purple-dark);
            border-radius: 8px;
            padding: 15px;
            min-height: 50px;
            color: var(--white);
        }

        .selected-laundry-items.empty {
            text-align: center;
            font-style: italic;
        }

        .laundry-item-badge {
            display: inline-block;
            background-color: var(--purple-dark);
            color: var(--white);
            padding: 8px 12px;
            border-radius: 20px;
            margin: 5px 5px 5px 0;
            font-size: 0.9em;
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

        /* Form Controls */
        .form-control, .form-select {
            border-color: var(--gray-medium) !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--purple-primary) !important;
            box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25) !important;
        }

        .form-check-input:checked {
            background-color: var(--purple-primary) !important;
            border-color: var(--purple-primary) !important;
        }

        .form-check-input:focus {
            border-color: var(--purple-primary) !important;
            box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25) !important;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.95em;
            font-weight: 500;
        }

        .status-badge.available {
            background-color: var(--purple-primary);
            color: var(--white);
        }

        .status-badge.total {
            background-color: var(--purple-primary);
            color: var(--white);
        }

        /* Button Styling */
        #prev-btn {
            background-color: var(--gray-dark);
            color: var(--white);
            border-color: var(--gray-dark);
        }

        #prev-btn:hover {
            background-color: var(--gray-dark);
            border-color: var(--gray-dark);
        }

        #submit-btn {
            background-color: var(--purple-primary) !important;
            border-color: var(--purple-primary) !important;
            color: var(--white) !important;
        }

        #submit-btn:hover {
            background-color: var(--purple-dark) !important;
            border-color: var(--purple-dark) !important;
        }

        #add-laundry-btn {
            background-color: var(--purple-primary);
            color: var(--white);
            border-color: var(--purple-primary);
        }

        #add-laundry-btn:hover {
            background-color: var(--purple-dark);
            border-color: var(--purple-dark);
        }

        /* Center the next button */
        .button-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #next-btn {
            min-width: 150px;
        }
    </style>
</head>
<body>
<section>
<!-- Sidebar Navigation -->
<div class="sidebar d-flex flex-column justify-content-between">
    
    <!-- Top Section -->
    <div>
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <h4><i class="fas fa-cogs me-2"></i> Admin Panel</h4>
            <small class="sidebar-subtitle">Jorish Express Laundry</small>
        </div>

        <!-- Navigation Links -->
        <ul class="nav flex-column gap-1">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link" href="admin_home.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>

            <!-- Management Menu -->
            <li class="nav-item">
                <a class="nav-link dropdown-toggle" data-bs-toggle="collapse" href="#managementMenu" role="button">
                    <i class="fas fa-cogs"></i> Management
                </a>
                <ul class="collapse show list-unstyled ps-4" id="managementMenu">
                        <li><a class="nav-link py-1" href="manage_services.php"><i class="fas fa-tags me-2"></i> Services &amp; Pricing</a></li>
                        <li><a class="nav-link py-1" href="manage_blog.php"><i class="fas fa-newspaper me-2"></i> Blog / News</a></li>
                        <li><a class="nav-link py-1" href="manage_about.php"><i class="fas fa-info-circle me-2"></i> About Page</a></li>
                        <li><a class="nav-link py-1" href="manage_testimonials.php"><i class="fas fa-comment-dots me-2"></i> Testimonials</a></li>
                        <li><a class="nav-link py-1" href="manage_why_choose_us.php"><i class="fas fa-thumbs-up me-2"></i> Why Choose Us</a></li>
                    <li><a class="nav-link py-1" href="manage_users.php"><i class="fas fa-user me-2"></i> Registered Users</a></li>
                    <li><a class="nav-link py-1" href="manage_machines.php"><i class="fas fa-tools me-2"></i> Machine Management</a></li>
                    <li><a class="nav-link py-1" href="manage_inventory.php"><i class="fas fa-box me-2"></i> Inventory Management</a></li>
                    <li><a class="nav-link py-1" href="booking_schedules.php"><i class="fas fa-calendar-alt me-2"></i> Booked Schedules</a></li>
                        <li><a class="nav-link py-1" href="completion_calendar.php"><i class="fas fa-calendar-check me-2"></i> Completion Calendar</a></li>
                    <li><a class="nav-link py-1" href="queue_management.php"><i class="fas fa-people-arrows me-2"></i> Queue Management</a></li>
                        <li><a class="nav-link py-1" href="manage_walkins.php"><i class="fas fa-user-plus me-2"></i> Walk-in Customers</a></li>
                    <li><a class="nav-link py-1" href="payment_requests-management.php"><i class="fas fa-money-bill-wave me-2"></i> Payment Requests</a></li>
                    <li><a class="nav-link py-1" href="claimed_rewards.php"><i class="fas fa-gift me-2"></i> Claimed Rewards</a></li>
                    <li><a class="nav-link py-1" href="admin_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a></li>
                </ul>
            </li>

            <!-- Reports Menu -->
            <li class="nav-item">
                <a class="nav-link dropdown-toggle" data-bs-toggle="collapse" href="#reportsMenu" role="button">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <ul class="collapse list-unstyled ps-4" id="reportsMenu">
                    <li><a class="nav-link py-1" href="reports.php"><i class="fas fa-file-invoice-dollar me-2"></i> Sales Report</a></li>
                    <li><a class="nav-link py-1" href="transaction_report.php"><i class="fas fa-exchange-alt me-2"></i> Transaction Report</a></li>
                </ul>
            </li>
        </ul>
    </div>

    <!-- Bottom Section -->
    <div class="sidebar-footer">
        <a class="nav-link text-danger d-flex align-items-center" href="#" onclick="return confirmLogout(event)">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="container reschedule-page">
    <h2 class="text-center mb-4">Reschedule Your Booking</h2>

    <div class="machine-status mb-4 p-4" style="background: #ffffff; border: 1px solid #e3edf6; border-radius: 16px; box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);">
        <div class="row">
            <div class="col-md-6">
                <span class="status-badge available" style="background-color: var(--purple-primary); color: white; padding: 8px 15px; border-radius: 20px; display: inline-block;">
                    <i class="fas fa-calendar-check"></i> Booking #<?php echo $booking['id']; ?>
                </span>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="status-badge total" style="background-color: var(--gray-dark); color: white; padding: 8px 15px; border-radius: 20px; display: inline-block;">
                    <i class="fas fa-calendar-day"></i> Current: <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?> at <?php echo htmlspecialchars($booking['time_slot']); ?>
                </span>
            </div>
        </div>
    </div>

    <form id="rescheduleForm" action="reschedule_process.php" method="POST">
        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
        <input type="hidden" name="booking_date" id="booking_date" value="<?php echo htmlspecialchars($booking['booking_date']); ?>">
        <input type="hidden" name="time_slot" id="time_slot" value="<?php echo htmlspecialchars($booking['time_slot']); ?>">
        <input type="hidden" name="selected_laundry_supplies_json" id="selected_laundry_supplies_json" value="<?php echo htmlspecialchars(json_encode($selected_laundry_items ?? []), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="selected_services_json" id="selected_services_json" value="<?php echo htmlspecialchars(json_encode($selected_services ?? []), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="selected_machines_json" id="selected_machines_json" value="<?php echo htmlspecialchars(json_encode($selected_machines ?? []), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="machine_count" value="<?php echo (int)$booking['machine_count']; ?>">
        <?php foreach ($selected_request_services as $rs): ?>
            <input type="hidden" name="request_service[]" value="<?php echo htmlspecialchars($rs); ?>">
        <?php endforeach; ?>

        <div class="booking-wizard-container container-box p-4">
            <!-- Date & Time Selection -->
            <div class="booking-step" id="step-1-content">
                <div class="row g-4">
                    <!-- Calendar Column -->
                    <div class="col-md-6">
                        <h4 class="mb-3">Select New Date</h4>
                        <div class="calendar-container text-center">
                            <h5>
                                <button type="button" id="prev-month" class="btn btn-sm btn-outline-primary"><i class="fas fa-chevron-left"></i></button>
                                <span id="current-month-year"></span>
                                <button type="button" id="next-month" class="btn btn-sm btn-outline-primary"><i class="fas fa-chevron-right"></i></button>
                            </h5>
                            <table class="table table-bordered mb-2">
                                <thead>
                                    <tr>
                                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
                                        <th>Thu</th><th>Fri</th><th>Sat</th>
                                    </tr>
                                </thead>
                                <tbody id="calendar-body"></tbody>
                            </table>
                            <div class="calendar-legend d-flex justify-content-center gap-3 mt-2" style="font-size: 0.85rem;">
                                <div class="d-flex align-items-center gap-1">
                                    <div class="legend-dot" style="width: 10px; height: 10px; background-color: #6366f1; border-radius: 50%;"></div>
                                    <span>Holiday</span>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <div class="legend-dot" style="width: 10px; height: 10px; background-color: #ef4444; border-radius: 50%;"></div>
                                    <span>Booked</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Time Slots Column -->
                    <div class="col-md-6">
                        <h4 class="mb-3">Select Preferred Time</h4>
                        <div class="time-slots-container" style="max-height:500px;overflow-y:auto;">
                            <div id="time-slots">
                                <?php foreach ($time_slots as $slot): ?>
                                    <div class="time-slot <?php echo $booking['time_slot'] === $slot ? 'selected' : ''; ?>"
                                        onclick="selectTimeSlot(event)"
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

            <!-- Submit Button -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-lg" id="submit-btn" onclick="return submitReschedule()" style="background-color: #6366f1; color: white; border: none; padding: 12px 32px; font-weight: 500; transition: all 0.3s ease;">
                            <i class="fas fa-check"></i> Update Booking
                        </button>
                    </div>
                </div>
        </div>
    </form>
</div>
</div>
</section>

<!-- Scripts -->
<script src="../assets/lib/js/jquery-3.7.1.min.js"></script>
<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
<script src="../assets/lib/js/select2.full.min.js"></script>
<script src="../assets/lib/js/sweetalert2.min.js"></script>
<script src="../assets/js/confirmlogout.js"></script>
<script>
    // Single-step reschedule state
    window.selectedLaundryItems = <?php echo json_encode($selected_laundry_items ?? []); ?>;

    window.rescheduleData = {
        currentMonth: <?php echo $currentMonth - 1; ?>,
        currentYear: <?php echo $currentYear; ?>,
        selectedDate: '<?php echo $booking["booking_date"]; ?>',
        selectedLaundryItems: <?php echo json_encode($selected_laundry_items ?? []); ?>,
        selectedMachines: <?php echo json_encode($selected_machines ?? []); ?>
    };

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Set pre-selected time slot
        const selectedTimeSlot = '<?php echo htmlspecialchars($booking['time_slot']); ?>';
        const timeSlotElement = document.querySelector(`.time-slot[data-slot="${selectedTimeSlot}"]`);
        if (timeSlotElement) {
            timeSlotElement.classList.add('selected');
        }
        
        // Load initial availability for the selected date
        const bookingDateField = document.getElementById('booking_date').value;
        const selectedDate = bookingDateField || window.rescheduleData?.selectedDate;
        
        if (selectedDate) {
            console.log("Loading availability for date:", selectedDate);
            // Small delay to ensure DOM is fully ready
            setTimeout(() => {
                updateRescheduleTimeSlots(selectedDate);
            }, 100);
        }
    });

    function submitReschedule() {
        const blockedStages = ['Ready for Pickup', 'Delivering', 'Completed / Picked Up', 'Missed Pickup'];
        const currentStage = '<?php echo $booking['order_stage']; ?>';
        
        if (blockedStages.includes(currentStage)) {
            Swal.fire({
                title: 'Reschedule Unavailable',
                text: 'This booking has already been processed and cannot be rescheduled anymore.',
                icon: 'error',
                confirmButtonColor: '#6f42c1'
            });
            return false;
        }

        const dateValue = document.getElementById('booking_date').value;
        const timeSlotValue = document.getElementById('time_slot').value;
        
        if (!dateValue) {
            Swal.fire({
                icon: 'warning',
                title: 'Select Date',
                text: 'Please select a date for your booking.',
                confirmButtonColor: '#6366f1'
            });
            return false;
        }

        if (!timeSlotValue) {
            Swal.fire({
                icon: 'warning',
                title: 'Select Time',
                text: 'Please select a time slot.',
                confirmButtonColor: '#6366f1'
            });
            return false;
        }

        // Show confirmation dialog
        Swal.fire({
            title: 'Confirm Reschedule?',
            text: 'Are you sure you want to reschedule this booking to ' + dateValue + ' at ' + timeSlotValue + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Reschedule',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('rescheduleForm').submit();
            }
        });
        
        return false;
    }

    // Time slot selection (matches user version — receives event)
    function selectTimeSlot(event) {
        if (!event || !event.currentTarget) return;
        event.preventDefault();
        event.stopPropagation();

        const element = event.currentTarget;

        if (element.classList.contains('fully-booked')) {
            Swal.fire({
                title: 'Fully Booked',
                text: 'This time slot is completely full. Please select another time.',
                icon: 'warning',
                timer: 2000,
                confirmButtonColor: '#6f42c1'
            });
            return;
        }

        // Remove selected class from all slots
        document.querySelectorAll('.time-slot').forEach(slot =>
            slot.classList.remove('selected')
        );

        element.classList.add('selected');

        const timeSlot = element.dataset.slot || element.querySelector('.slot-time')?.textContent?.trim() || '';
        document.getElementById('time_slot').value = timeSlot;
    }

    // Update time slots with availability data
    async function updateRescheduleTimeSlots(selectedDate) {
        const timeSlotElements = document.querySelectorAll(".time-slot");
        try {
            console.log("Fetching availability for reschedule date:", selectedDate);
            
            const response = await fetch(`?action=get_availability&date=${selectedDate}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const availabilityData = await response.json();
            
            if (availabilityData.error) {
                throw new Error("API Error: " + availabilityData.error);
            }
            
            if (!availabilityData || Object.keys(availabilityData).length === 0) {
                timeSlotElements.forEach(slot => {
                    const machineAvailableElement = slot.querySelector(".machine-available");
                    if (machineAvailableElement) {
                        machineAvailableElement.textContent = "(6/6 Washers, 6/6 Dryers available)";
                    }
                    slot.classList.remove("booked-slot", "fully-booked");
                    slot.style.pointerEvents = "auto";
                    slot.style.opacity = "1";
                });
                return;
            }
            
            timeSlotElements.forEach(slot => {
                const slotName = slot.getAttribute('data-slot');
                const slotData = availabilityData[slotName];
                const machineAvailableElement = slot.querySelector('.machine-available');
                const chipsEl = slot.querySelector('.machine-chips');

                if (slotData) {
                    const availableWashers = slotData.washers?.available ?? 6;
                    const availableDryers = slotData.dryers?.available ?? 6;
                    const totalWashers   = slotData.washers?.total ?? 6;
                    const totalDryers    = slotData.dryers?.total ?? 6;
                    const washerMachines = slotData.washers?.machines ?? [];
                    const dryerMachines  = slotData.dryers?.machines ?? [];
                    const fullyBooked    = (availableWashers <= 0 && availableDryers <= 0)
                                          || (slotData.total?.has_any_booking ?? false);

                    if (machineAvailableElement) {
                        machineAvailableElement.textContent = fullyBooked
                            ? 'Fully Booked'
                            : `${availableWashers} Washer${availableWashers !== 1 ? 's' : ''} \u2022 ${availableDryers} Dryer${availableDryers !== 1 ? 's' : ''} free`;
                    }

                    if (chipsEl) {
                        if (fullyBooked) {
                            chipsEl.innerHTML = '';
                        } else {
                            let html = '';
                            washerMachines.forEach(m => {
                                html += `<span class="mchip mchip-washer"><i class="fas fa-drum-steelpan"></i>${m}</span>`;
                            });
                            dryerMachines.forEach(m => {
                                html += `<span class="mchip mchip-dryer"><i class="fas fa-wind"></i>${m}</span>`;
                            });
                            chipsEl.innerHTML = html;
                        }
                    }

                    slot.classList.remove('booked-slot', 'fully-booked');
                    slot.style.pointerEvents = fullyBooked ? 'none' : 'auto';
                    slot.style.opacity = fullyBooked ? '0.5' : '1';

                    if (fullyBooked) {
                        slot.classList.add('fully-booked');
                    } else if (availableWashers < totalWashers || availableDryers < totalDryers) {
                        slot.classList.add('booked-slot');
                    }

                    slot.dataset.availableWashers = availableWashers;
                    slot.dataset.availableDryers  = availableDryers;
                    slot.dataset.totalWashers     = totalWashers;
                    slot.dataset.totalDryers      = totalDryers;
                }
            });
            
        } catch (error) {
            console.error("Error fetching reschedule availability:", error);
            Swal.fire({
                title: "Error Loading Availability",
                text: "Could not load time slot availability.",
                icon: "error",
                timer: 3000,
                confirmButtonColor: '#6f42c1'
            });
        }
    }

    // Override reschedule.js updateTimeSlots to use the reschedule-specific version
    window.updateTimeSlots = updateRescheduleTimeSlots;

</script>
<script>
    // Tell reschedule.js to use this page's own API for holidays & booked days
    window.RESCHEDULE_API_BASE = '/jorishlaundry/admin/reschedule_booking.php';
</script>
<script src="../assets/js/reschedule.js"></script>
</body>
</html>
