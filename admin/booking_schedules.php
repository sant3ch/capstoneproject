<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require '../config.php';
require_once '../includes/booking-functions.php';
require_once '../includes/admin-notifications.php';
require_once '../includes/maintenance-functions.php';

/** @var mysqli $conn */

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

// Handle in-store payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_in_store_payment'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Get booking details
    $booking_query = "SELECT id, user_id, final_amount, status FROM bookings WHERE id = ?";
    $booking_stmt = $conn->prepare($booking_query);
    $booking_stmt->bind_param('i', $booking_id);
    $booking_stmt->execute();
    $booking = $booking_stmt->get_result()->fetch_assoc();
    $booking_stmt->close();
    
    if ($booking && strtoupper($booking['status']) !== 'COMPLETED') {
        // Update booking status to Confirmed - Paid
        $update_query = "UPDATE bookings SET status = 'Confirmed - Paid', final_amount = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('di', $booking['final_amount'], $booking_id);
        
        if ($update_stmt->execute()) {
            $update_stmt->close();
            
            // Update gcash_requests status to completed
            $gcash_update = "UPDATE gcash_requests SET status = 'completed', payment_date = NOW() WHERE booking_id = ? AND payment_method = 'IN_STORE'";
            $gcash_stmt = $conn->prepare($gcash_update);
            $gcash_stmt->bind_param('i', $booking_id);
            $gcash_stmt->execute();
            $gcash_stmt->close();
            
            // Create a transaction record
            $amount = $booking['final_amount'];
            $user_id = $booking['user_id'];
            $trans_query = "INSERT INTO transactions (booking_id, user_id, amount, payment_type, status, created_at) VALUES (?, ?, ?, 'IN_STORE', 'completed', NOW())";
            $trans_stmt = $conn->prepare($trans_query);
            $trans_stmt->bind_param('iid', $booking_id, $user_id, $amount);
            $trans_stmt->execute();
            $trans_stmt->close();
            
            // Get user info for notification
            $user_query = "SELECT first_name, last_name FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($user_query);
            $user_stmt->bind_param('i', $user_id);
            $user_stmt->execute();
            $user = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();
            
            $customer_name = $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Customer';
            
            // Send notification to customer
            $notif_title = "Payment Received - Booking Confirmed";
            $notif_message = "Your in-store payment of ₱" . number_format($amount, 2) . " for booking #" . $booking_id . " has been received and confirmed.";
            
            $notif_query = "INSERT INTO notifications (user_id, title, message, booking_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
            $notif_stmt = $conn->prepare($notif_query);
            $notif_stmt->bind_param('issi', $user_id, $notif_title, $notif_message, $booking_id);
            $notif_stmt->execute();
            $notif_stmt->close();
            
            // Send admin notification
            $admin_notif_title = "In-Store Payment Processed - Booking #" . $booking_id;
            $admin_notif_message = "In-store payment of ₱" . number_format($amount, 2) . " from " . $customer_name . " has been processed and confirmed.";
            
            $admin_notif_query = "INSERT INTO admin_notifications (title, message, type, related_id, status, created_at) VALUES (?, ?, 'in_store_payment_processed', ?, 'read', NOW())";
            $admin_notif_stmt = $conn->prepare($admin_notif_query);
            $admin_notif_stmt->bind_param('ssi', $admin_notif_title, $admin_notif_message, $booking_id);
            $admin_notif_stmt->execute();
            $admin_notif_stmt->close();
            
            // Redirect with success message
            header("Location: booking_schedules.php?success=" . urlencode("In-store payment processed successfully for booking #" . $booking_id));
            exit();
        } else {
            // Redirect with error
            header("Location: booking_schedules.php?error=" . urlencode("Failed to process in-store payment"));
            exit();
        }
    } else {
        header("Location: booking_schedules.php?error=" . urlencode("Booking not found or already completed"));
        exit();
    }
}

// Check for success/error messages
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Get counts for stats
$total_bookings = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM bookings"));
$pending_bookings = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM bookings WHERE status = 'Pending'"));
$completed_bookings = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM bookings WHERE status = 'Completed'"));
$cancelled_bookings = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM bookings WHERE status = 'Cancelled'"));
$confirmed_bookings = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM bookings WHERE status = 'Confirmed - Scheduled for Pickup'"));

$bookings = mysqli_query($conn, "
    SELECT
        b.id,
        COALESCE(b.customer_first_name, u.first_name) AS first_name,
        COALESCE(b.customer_last_name, u.last_name) AS last_name,
        b.booking_date,
        b.time_slot,
        b.status,
        b.service_type,
        b.detergent,
        b.cod_confirmation_photo,
        COALESCE(gr.proof_image, '') as gcash_proof_image,
        COALESCE(gr.payment_method, '') as payment_method,
        COALESCE(b.final_amount, t.total_amount, gr.amount, 0) as processed_amount
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN transactions t ON b.id = t.booking_id
    LEFT JOIN gcash_requests gr ON b.id = gr.booking_id 
        AND gr.id = (
            SELECT MAX(id) FROM gcash_requests 
            WHERE booking_id = b.id
        )
    ORDER BY b.booking_date DESC
");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Booked Schedules - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link rel="stylesheet" href="../assets/css/manage-booking.css">
</head>

<body>
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
                    <a class="nav-link dropdown-toggle active" data-bs-toggle="collapse" href="#managementMenu" role="button">
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
                        <li><a class="nav-link py-1 active" href="booking_schedules.php"><i class="fas fa-calendar-alt me-2"></i> Booked Schedules</a></li>
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
        <!-- Combined Page Header -->
        <div class="page-header-combined">
            <div class="header-main">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="header-title-section">
                        <div class="d-flex align-items-center mb-2">
                            <div class="header-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <h1>Booked Schedules</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Management</a></li>
                                        <li class="breadcrumb-item active">Booked Schedules</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">View and manage customer laundry booking schedules</p>
                    </div>
                    <div class="header-action-section">
                        <div class="d-flex align-items-center gap-3">
                            <div class="current-date">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                            <?php if ($pending_bookings > 0): ?>
                            <div class="pending-alert">
                                <span class="badge" style="background-color: #7c3aed; color: white;">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <?php echo $pending_bookings; ?> Pending
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="header-stats">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $total_bookings; ?></h3>
                                <p>Total Bookings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $pending_bookings; ?></h3>
                                <p>Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $completed_bookings; ?></h3>
                                <p>Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $cancelled_bookings; ?></h3>
                                <p>Cancelled</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages handled via SweetAlert2 at the bottom -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show mx-4 mt-3" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Bookings Table Card -->
        <div class="card bookings-table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5><i class="fas fa-list me-2"></i> Bookings List</h5>
                    <small class="text-muted">View and manage all customer bookings</small>
                </div>
                <div class="table-actions">
                    <div class="input-group input-group-sm" style="width: 200px;">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control form-control-sm" placeholder="Search bookings..." id="searchInput">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="bookingsTable">
                        <thead>
                            <tr>
                                <th width="16%">Customer Name</th>
                                <th width="9%">Booking Date</th>
                                <th width="9%">Time Slot</th>
                                <th width="10%">Service Type</th>
                                <th width="7%">Detergent</th>
                                <th width="8%">Amount</th>
                                <th width="8%">Booking Status</th>
                                <th width="8%" class="text-center">Proof</th>
                                <th width="15%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = mysqli_fetch_assoc($bookings)) : ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="customer-icon me-3">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                                <small class="text-muted">Booking ID: #<?php echo $booking['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="d-block">
                                            <i class="fas fa-calendar me-1 text-muted"></i>
                                            <?php echo $booking['booking_date'] ? date("M d, Y", strtotime($booking['booking_date'])) : 'N/A'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="d-block">
                                            <i class="fas fa-clock me-1 text-muted"></i>
                                            <?php echo htmlspecialchars($booking['time_slot']) ?? 'N/A'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $services = parseServiceNames($booking['service_type'] ?? '');
                                        if (empty($services)) {
                                            echo '<span class="text-muted">N/A</span>';
                                        } else {
                                            foreach ($services as $service) {
                                                echo '<span style="color: #000000;" class="me-1 mb-1">' . htmlspecialchars(ucwords($service)) . '</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $detergents = array_filter(array_map('trim', explode(',', $booking['detergent'] ?? '')));
                                        if (empty($detergents)) {
                                            echo '<span class="text-muted">N/A</span>';
                                        } else {
                                            foreach ($detergents as $detergent) {
                                                echo '<span style="color: #000000;" class="me-1 mb-1">' . htmlspecialchars(ucwords($detergent)) . '</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Calculate the final amount as in user/booking_confirmation.php
                                        $finalAmount = null;
                                        if ($booking['processed_amount'] > 0) {
                                            $finalAmount = $booking['processed_amount'];
                                        } else {
                                            // Calculate base amount
                                            $finalAmount = calculateBookingAmountFromDB([
                                                'service_type' => $booking['service_type'],
                                                'detergent' => $booking['detergent'],
                                                'id' => $booking['id']
                                            ]);

                                            // Check for claimed rewards (voucher) for this user and booking
                                            $reward_discount = 0;
                                            $reward_query = $conn->prepare("SELECT reward_name FROM claimed_rewards WHERE user_id = (SELECT user_id FROM bookings WHERE id = ?) AND status = 'Claimed' ORDER BY claimed_at DESC LIMIT 1");
                                            $reward_query->bind_param('i', $booking['id']);
                                            $reward_query->execute();
                                            $reward_result = $reward_query->get_result();
                                            if ($reward = $reward_result->fetch_assoc()) {
                                                $reward_name = strtolower($reward['reward_name']);
                                                if (strpos($reward_name, 'wash & dry') !== false) {
                                                    $reward_discount = 145.00;
                                                } elseif (strpos($reward_name, 'wash') !== false && strpos($reward_name, 'dry') === false) {
                                                    $reward_discount = 65.00;
                                                } elseif (strpos($reward_name, 'dry') !== false && strpos($reward_name, 'wash') === false) {
                                                    $reward_discount = 80.00;
                                                } elseif (strpos($reward_name, 'service') !== false) {
                                                    $reward_discount = 65.00;
                                                }
                                            }
                                            $reward_query->close();
                                            $finalAmount = max(0, $finalAmount - $reward_discount);
                                        }
                                        echo '₱' . number_format($finalAmount, 2);
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status = strtolower($booking['status']);
                                        $statusIcon = '';
                                        
                                        switch($status) {
                                            case 'pending':
                                                $statusIcon = 'fa-clock';
                                                break;
                                            case 'completed':
                                                $statusIcon = 'fa-check-circle';
                                                break;
                                            case 'cancelled':
                                                $statusIcon = 'fa-times-circle';
                                                break;
                                            default:
                                                $statusIcon = 'fa-question-circle';
                                        }
                                        ?>
                                        <span style="color: #000000;">
                                            <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                            $has_proof = false;
                                            $proof_type = '';
                                            $proof_file = '';
                                            
                                            // Check for COD proof
                                            if (!empty($booking['cod_confirmation_photo'])) {
                                                $has_proof = true;
                                                $proof_type = 'COD';
                                                $proof_file = $booking['cod_confirmation_photo'];
                                            }
                                            // Check for GCASH proof
                                            elseif (!empty($booking['gcash_proof_image'])) {
                                                $has_proof = true;
                                                $proof_type = 'GCASH';
                                                $proof_file = $booking['gcash_proof_image'];
                                            }
                                            
                                            if ($has_proof): 
                                        ?>
                                            <button type="button" class="btn btn-sm btn-purple text-dark" onclick="viewProofPhoto('<?php echo htmlspecialchars($proof_file); ?>', '<?php echo $proof_type; ?>')">
                                                <i class="fas fa-image"></i> View
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <!-- Edit Dropdown Button -->
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm px-3 mb-1" style="background-color: #6b7280; color: white; border: none; border-radius: 4px;" 
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                     Edit
                                                    <i class="fas fa-chevron-down ms-1"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end" style="background: white; border: 1px solid #dee2e6; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2" onclick="openStatusModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['status']); ?>', '<?php echo htmlspecialchars($booking['service_type']); ?>')">
                                                            <i class="fas fa-pencil-alt me-2" style="color: #6b7280;"></i> Change Status
                                                        </button>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a href="reschedule_booking.php?id=<?php echo $booking['id']; ?>" class="dropdown-item py-2">
                                                            <i class="fas fa-calendar-alt me-2" style="color: #6b7280;"></i> Reschedule
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 text-danger" onclick="openCancelModal(<?php echo $booking['id']; ?>, 'Booking #<?php echo $booking['id']; ?>')">
                                                            <i class="fas fa-times-circle me-2"></i> Cancel
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                            
                                            <!-- Conditional Process Payment Button -->
                                            <?php 
                                                $showProcessPayment = false;
                                                $payment_method = $booking['payment_method'];
                                                $status = $booking['status'];
                                                
                                                // Show if payment method is IN_STORE and status is Pending or Confirmed
                                                if (strtoupper($payment_method) === 'IN_STORE' && in_array($status, ['Pending', 'Confirmed - Scheduled for Pickup'])) {
                                                    $showProcessPayment = true;
                                                }
                                            ?>
                                            <?php if ($showProcessPayment): ?>
                                                <button type="button" class="btn btn-sm px-3 mb-1" style="background-color: #6366f1; color: white; border: none; border-radius: 4px;" 
                                                        onclick="openProcessPaymentModal(<?php echo $booking['id']; ?>, '<?php echo $booking['processed_amount']; ?>')">
                                                    Pay
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%); border: none;">
                    <h5 class="modal-title" id="statusModalLabel" style="color: white; font-weight: 600;">
                        <i class="fas fa-edit me-2"></i> Change Booking Status
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="bookingIdInput">
                    
                    <div class="mb-3">
                        <label for="statusSelect" class="form-label fw-bold">Select New Status:</label>
                        <select class="form-select" id="statusSelect" style="border: 2px solid #6366f1;">
                            <option value="">-- Choose Status --</option>
                            <option value="Pending">Pending</option>
                            <option value="Confirmed - Scheduled for Pickup">Confirmed - Scheduled for Pickup</option>
                            <option value="Confirmed - Paid">Confirmed - Paid</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Rescheduled">Rescheduled</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Current Status:</strong> <span id="currentStatusDisplay"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background-color: #6c757d; color: white;" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="button" class="btn" id="updateStatusBtn" style="background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%); color: white;">
                        <i class="fas fa-save me-2"></i> Update Status
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Booking Modal -->
    <div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-labelledby="cancelBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #3b82f6; border: none;">
                    <h5 class="modal-title" id="cancelBookingModalLabel" style="color: white; font-weight: 600;">
                        <i class="fas fa-exclamation-circle me-2"></i> Cancel Booking
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cancelBookingIdInput">
                    
                    <div class="alert" style="background-color: #6b7280; color: white; border-color: #6b7280;" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning!</strong> This action will permanently cancel the booking.
                    </div>
                    
                    <p class="mb-3">
                        <strong>Booking Reference:</strong> <span id="cancelBookingRefDisplay"></span>
                    </p>
                    
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label fw-bold">Reason for Cancellation (Optional):</label>
                        <textarea class="form-control" id="cancelReason" rows="3" placeholder="Enter reason for canceling this booking..."></textarea>
                        <small class="text-muted">This will help you track cancellation reasons</small>
                    </div>
                    
                    <div class="alert" style="background-color: #3b82f6; color: white; border-color: #3b82f6;" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        Customer will be notified of the cancellation.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background-color: #6b7280; color: white;" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Keep Booking
                    </button>
                    <button type="button" class="btn" id="confirmCancelBtn" style="background-color: #3b82f6; color: white;">
                        <i class="fas fa-trash me-2"></i> Yes, Cancel Booking
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Process In-Store Payment Modal -->
    <div class="modal fade" id="processPaymentModal" tabindex="-1" aria-labelledby="processPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); border: none;">
                    <h5 class="modal-title" id="processPaymentModalLabel" style="color: white; font-weight: 600;">
                        <i class="fas fa-credit-card me-2"></i> Process In-Store Payment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="processPaymentBookingIdInput">
                    
                    <!-- Customer Info -->
                    <div class="mb-3">
                        <label class="form-label fw-bold" style="color: #333;">Customer:</label>
                        <div style="background-color: #f9fafb; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 1rem; font-weight: 500;" id="processPaymentCustomerName">-</p>
                            <small class="text-muted">Booking ID: <span id="processPaymentBookingId" style="font-weight: 600;"></span></small>
                        </div>
                    </div>
                    
                    <!-- Service & Add-ons Breakdown -->
                    <div class="mb-3">
                        <label class="form-label fw-bold" style="color: #333;">Service & Add-ons Breakdown:</label>
                        <div style="overflow-x: auto;">
                            <table class="table table-sm" style="margin-bottom: 0; font-size: 0.9rem;">
                                <thead style="background-color: #f3f4f6; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">
                                    <tr>
                                        <th style="color: #333; font-weight: 600;">Item</th>
                                        <th style="text-align: right; color: #333; font-weight: 600;">Qty</th>
                                        <th style="text-align: right; color: #333; font-weight: 600;">Price</th>
                                        <th style="text-align: right; color: #333; font-weight: 600;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentItemsTableBody">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted" style="padding: 20px;">Loading items...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Total Section -->
                    <div class="mb-3" style="background-color: #f3f4f6; padding: 16px; border-radius: 8px; border-left: 4px solid #6366f1;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #333; font-weight: 500;">Total Amount:</span>
                            <span id="processPaymentAmount" style="color: #6366f1; font-weight: 700; font-size: 1.1rem;">₱0.00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #6b7280; font-size: 0.9rem;">Accumulated Points:</span>
                            <span id="processPaymentPoints" style="color: #6366f1; font-weight: 600;">0</span>
                        </div>
                    </div>
                    
                    <!-- Cash & Change -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold" style="color: #333;">Cash Received:</label>
                            <input type="number" id="paymentCashInput" class="form-control" placeholder="Enter cash amount" step="0.01" min="0" style="border: 1px solid #e5e7eb;">
                            <small class="text-muted">Press Tab or Enter to calculate change</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" style="color: #333;">Change:</label>
                            <div style="background-color: #f9fafb; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; height: 38px;">
                                <span id="processPaymentChange" style="color: #6366f1; font-weight: 700; font-size: 1.1rem;">₱0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Validation Alert -->
                    <div id="paymentValidationAlert" class="alert alert-warning" style="display: none; margin-bottom: 0;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="paymentValidationMessage"></span>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e5e7eb;">
                    <button type="button" class="btn" style="background-color: #6b7280; color: white;" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="button" class="btn" id="confirmProcessPaymentBtn" style="background-color: #6366f1; color: white;">
                        <i class="fas fa-check me-2"></i> Complete Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- In-Store Payment Confirmation Modal -->
    <div class="modal fade" id="paymentConfirmationModal" tabindex="-1" aria-labelledby="paymentConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); border: none;">
                    <h5 class="modal-title" id="paymentConfirmationModalLabel" style="color: white; font-weight: 600;">
                        <i class="fas fa-check-circle me-2"></i> Payment Confirmed
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success" style="background-color: #e0e7ff; border-color: #6366f1; color: #4338ca;">
                        <i class="fas fa-check-circle me-2"></i> In-store payment has been successfully processed!
                    </div>

                    <div style="background-color: #f9fafb; padding: 16px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
                            <label style="color: #6b7280; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Transaction ID</label>
                            <p style="margin: 4px 0 0 0; color: #333; font-weight: 600;" id="confirmTransactionId">-</p>
                        </div>
                        <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
                            <label style="color: #6b7280; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Customer</label>
                            <p style="margin: 4px 0 0 0; color: #333;" id="confirmCustomerName">-</p>
                        </div>
                        <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
                            <label style="color: #6b7280; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Amount Paid</label>
                            <p style="margin: 4px 0 0 0; color: #6366f1; font-weight: 700; font-size: 1.1rem;" id="confirmAmount">₱0.00</p>
                        </div>
                        <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
                            <label style="color: #6b7280; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Points Earned</label>
                            <p style="margin: 4px 0 0 0; color: #333;" id="confirmPointsEarned">-</p>
                        </div>
                        <div>
                            <label style="color: #6b7280; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">New Points Balance</label>
                            <p style="margin: 4px 0 0 0; color: #6366f1; font-weight: 600; font-size: 1rem;" id="confirmNewBalance">-</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e5e7eb;">
                    <button type="button" class="btn" id="paymentConfirmationOkBtn" style="background-color: #6366f1; color: white;">
                        <i class="fas fa-check me-2"></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
        // Function to open status modal
        function openStatusModal(bookingId, currentStatus, serviceType) {
            document.getElementById('bookingIdInput').value = bookingId;
            document.getElementById('statusSelect').value = '';
            
            // Check if this is a self-service booking
            const isSelfService = serviceType && (serviceType.toLowerCase().includes('self-service') || serviceType.toLowerCase().includes('self service'));
            
            // Update status dropdown options based on booking type
            const statusSelect = document.getElementById('statusSelect');
            
            // Find and update the "Confirmed - Scheduled for Pickup" option
            const pickupOption = Array.from(statusSelect.options).find(opt => opt.value === 'Confirmed - Scheduled for Pickup');
            
            if (isSelfService) {
                // For self-service bookings, replace "Confirmed - Scheduled for Pickup" with just "Confirmed"
                if (pickupOption) {
                    pickupOption.textContent = 'Confirmed';
                    pickupOption.value = 'Confirmed';
                }
            } else {
                // For regular bookings, restore the full text
                if (pickupOption) {
                    pickupOption.textContent = 'Confirmed - Scheduled for Pickup';
                    pickupOption.value = 'Confirmed - Scheduled for Pickup';
                }
            }
            
            document.getElementById('currentStatusDisplay').textContent = currentStatus;
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }

        document.getElementById('searchInput').addEventListener('keyup', function () {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#bookingsTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
        
        // Function to open cancel booking modal
        function openCancelModal(bookingId, bookingRef) {
            document.getElementById('cancelBookingIdInput').value = bookingId;
            document.getElementById('cancelBookingRefDisplay').textContent = bookingRef;
            document.getElementById('cancelReason').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('cancelBookingModal'));
            modal.show();
        }
        
        // Function to confirm booking cancellation
        document.getElementById('confirmCancelBtn').addEventListener('click', function() {
            const bookingId = document.getElementById('cancelBookingIdInput').value;
            const reason = document.getElementById('cancelReason').value;
            
            Swal.fire({
                title: 'Cancel Booking?',
                text: 'This booking will be marked as Cancelled. This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Cancel Booking',
                cancelButtonText: 'No, Keep It',
                background: '#fff',
                color: '#333'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('booking_id', bookingId);
                    formData.append('status', 'Cancelled');
                    formData.append('cancel_reason', reason);
                    
                    fetch('../admin/update_booking_status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Close the modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById('cancelBookingModal'));
                            if (modal) modal.hide();
                            
                            Swal.fire({
                                title: 'Cancelled!',
                                text: 'Booking has been cancelled successfully.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = 'booking_schedules.php?success=' + encodeURIComponent('Booking has been cancelled successfully.');
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.error || 'Failed to cancel booking',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred while cancelling the booking',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
        });
        
        // Function to view proof photo
        // Function to view proof photo
        function viewProofPhoto(photoFilename, proofType = 'COD') {
            let photoPath = '';
            let title = '';
            
            if (proofType === 'GCASH') {
                photoPath = '../uploads/payment_proofs/' + photoFilename;
                title = 'GCASH Payment Proof';
            } else {
                photoPath = '../uploads/cod_confirmations/' + photoFilename;
                title = 'COD Delivery Proof';
            }
            
            Swal.fire({
                title: title,
                html: `<img src="${photoPath}" alt="${title}" style="max-width: 100%; max-height: 500px; border-radius: 8px;">`,
                icon: 'info',
                confirmButtonText: 'Close',
                confirmButtonColor: '#3b82f6'
            });
        }
        
        
        // Update status button handler
        document.getElementById('updateStatusBtn').addEventListener('click', function() {
            const bookingId = document.getElementById('bookingIdInput').value;
            const newStatus = document.getElementById('statusSelect').value;
            
            if (!newStatus) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please select a status',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('status', newStatus);
            
            fetch('../admin/update_booking_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success',
                        text: 'Booking status updated successfully',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'booking_schedules.php?success=' + encodeURIComponent('Booking status updated successfully');
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.error || 'Failed to update booking status',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred while updating the booking status',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        });

        // Store payment data for validation
        let paymentData = {
            totalAmount: 0,
            accumulatedPoints: 0
        };
        
        // Function to open process payment modal
        function openProcessPaymentModal(bookingId, amount) {
            document.getElementById('processPaymentBookingIdInput').value = bookingId;
            document.getElementById('paymentCashInput').value = '';
            document.getElementById('processPaymentChange').textContent = '₱0.00';
            document.getElementById('paymentValidationAlert').style.display = 'none';
            
            // Fetch booking details via AJAX
            fetch('get_booking_details.php?booking_id=' + bookingId)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const bookingData = data.data;
                        paymentData.totalAmount = bookingData.totalAmount;
                        paymentData.accumulatedPoints = bookingData.accumulatedPoints;
                        
                        // Update modal with booking details
                        document.getElementById('processPaymentCustomerName').textContent = bookingData.userName;
                        document.getElementById('processPaymentBookingId').textContent = '#' + bookingData.bookingId;
                        document.getElementById('processPaymentAmount').textContent = '₱' + parseFloat(bookingData.totalAmount).toFixed(2);
                        document.getElementById('processPaymentPoints').textContent = bookingData.accumulatedPoints;
                        
                        // Populate items table
                        let tableHTML = '';
                        
                        // Services
                        if (bookingData.services.length > 0) {
                            bookingData.services.forEach(item => {
                                const amount = item.price * item.quantity;
                                tableHTML += `
                                    <tr>
                                        <td style="color: #333;">${item.name}</td>
                                        <td style="text-align: right; color: #333;">${item.quantity}</td>
                                        <td style="text-align: right; color: #333;">₱${parseFloat(item.price).toFixed(2)}</td>
                                        <td style="text-align: right; color: #333; font-weight: 600;">₱${parseFloat(amount).toFixed(2)}</td>
                                    </tr>
                                `;
                            });
                        }
                        
                        // Detergents/Add-ons
                        if (bookingData.detergents.length > 0) {
                            bookingData.detergents.forEach(item => {
                                const amount = item.price * item.quantity;
                                tableHTML += `
                                    <tr>
                                        <td style="color: #666;">${item.name}</td>
                                        <td style="text-align: right; color: #666;">${item.quantity}</td>
                                        <td style="text-align: right; color: #666;">₱${parseFloat(item.price).toFixed(2)}</td>
                                        <td style="text-align: right; color: #666; font-weight: 600;">₱${parseFloat(amount).toFixed(2)}</td>
                                    </tr>
                                `;
                            });
                        }
                        
                        if (tableHTML === '') {
                            tableHTML = '<tr><td colspan="4" class="text-center text-muted">No items</td></tr>';
                        }
                        
                        document.getElementById('paymentItemsTableBody').innerHTML = tableHTML;
                        
                        // Show modal
                        const modal = new bootstrap.Modal(document.getElementById('processPaymentModal'));
                        modal.show();
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to load booking details',
                            icon: 'error',
                            confirmButtonColor: '#6366f1'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to load booking details',
                        icon: 'error',
                        confirmButtonColor: '#6366f1'
                    });
                });
        }
        
        // Cash input change handler for real-time change calculation
        document.getElementById('paymentCashInput').addEventListener('input', function() {
            const cashAmount = parseFloat(this.value) || 0;
            const change = cashAmount - paymentData.totalAmount;
            document.getElementById('processPaymentChange').textContent = '₱' + change.toFixed(2);
            
            // Update button and validation
            if (cashAmount === 0) {
                document.getElementById('paymentValidationAlert').style.display = 'none';
            } else if (change < 0) {
                document.getElementById('paymentValidationAlert').style.display = 'block';
                document.getElementById('paymentValidationMessage').textContent = 'Insufficient cash. Minimum required: ₱' + paymentData.totalAmount.toFixed(2);
            } else {
                document.getElementById('paymentValidationAlert').style.display = 'none';
            }
        });
        
        // Function to confirm in-store payment
        document.getElementById('confirmProcessPaymentBtn').addEventListener('click', function() {
            const bookingId = document.getElementById('processPaymentBookingIdInput').value;
            const cashAmount = parseFloat(document.getElementById('paymentCashInput').value) || 0;
            
            // Validate cash input
            if (cashAmount === 0) {
                document.getElementById('paymentValidationAlert').style.display = 'block';
                document.getElementById('paymentValidationMessage').textContent = 'Please enter the cash amount received.';
                return;
            }
            
            if (cashAmount < paymentData.totalAmount) {
                document.getElementById('paymentValidationAlert').style.display = 'block';
                document.getElementById('paymentValidationMessage').textContent = 'Cash amount is less than total. Minimum required: ₱' + paymentData.totalAmount.toFixed(2);
                return;
            }
            
            const change = (cashAmount - paymentData.totalAmount).toFixed(2);
            
            Swal.fire({
                title: 'Confirm Payment?',
                html: `<div style="text-align: left;">
                    <p style="margin-bottom: 10px;"><strong>Total Amount:</strong> ₱${paymentData.totalAmount.toFixed(2)}</p>
                    <p style="margin-bottom: 10px;"><strong>Cash Received:</strong> ₱${cashAmount.toFixed(2)}</p>
                    <p style="margin-bottom: 0;"><strong>Change:</strong> ₱${change}</p>
                </div>`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Complete Payment',
                cancelButtonText: 'No, Cancel',
                background: '#fff',
                color: '#333'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Close the payment modal - move focus away first to avoid aria-hidden warning
                    document.activeElement.blur();
                    const paymentModal = bootstrap.Modal.getInstance(document.getElementById('processPaymentModal'));
                    if (paymentModal) paymentModal.hide();
                    
                    // Submit payment via AJAX
                    const formData = new FormData();
                    formData.append('booking_id', bookingId);
                    formData.append('cash_amount', cashAmount);
                    
                    fetch('process_in_store_payment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Show confirmation modal
                            document.getElementById('confirmTransactionId').textContent = '#' + data.data.transactionId;
                            document.getElementById('confirmCustomerName').textContent = data.data.customer;
                            document.getElementById('confirmAmount').textContent = '₱' + parseFloat(data.data.amount).toFixed(2);
                            document.getElementById('confirmPointsEarned').textContent = data.data.pointsEarned + ' point';
                            document.getElementById('confirmNewBalance').textContent = data.data.newBalance + ' points';
                            
                            const confirmModal = new bootstrap.Modal(document.getElementById('paymentConfirmationModal'));
                            confirmModal.show();
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Payment processing failed',
                                icon: 'error',
                                confirmButtonColor: '#6366f1'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'Payment processing failed',
                            icon: 'error',
                            confirmButtonColor: '#6366f1'
                        });
                    });
                }
            });
        });
        
        // Handle confirmation modal OK button
        document.getElementById('paymentConfirmationOkBtn').addEventListener('click', function() {
            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('paymentConfirmationModal'));
            if (confirmModal) confirmModal.hide();
            
            // Reload page with success parameter to show the SweetAlert modal
            setTimeout(() => {
                window.location.href = 'booking_schedules.php?success=' + encodeURIComponent('In-store payment has been processed and recorded successfully.');
            }, 300);
        });

        // Show success modal if success message exists in URL
        <?php if ($success_message): ?>
            Swal.fire({
                title: <?php 
                    if (strpos($success_message, 'rescheduled') !== false) {
                        echo "'Rescheduled Successfully!'";
                    } elseif (strpos($success_message, 'cancelled') !== false) {
                        echo "'Cancelled Successfully!'";
                    } else {
                        echo "'Success!'";
                    }
                ?>,
                text: '<?php echo $success_message; ?>',
                icon: 'success',
                confirmButtonColor: '#6366f1',
                timer: 3000,
                timerProgressBar: true,
                background: '#fff',
                color: '#333'
            });
        <?php endif; ?>
    </script>
</body>

</html>







