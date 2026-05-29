<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/admin-notifications.php';

/**
 * Generate a unique reference number for GCASH transactions
 */
function generateReferenceNumber($prefix = 'GCASH') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Send notification to user
 */
function sendUserNotification($conn, $user_id, $title, $message, $booking_id = null, $link = null) {
    $notification_sql = "INSERT INTO notifications (user_id, title, message, booking_id, link, is_read, created_at) 
                         VALUES (?, ?, ?, ?, ?, 0, NOW())";
    $notification_stmt = $conn->prepare($notification_sql);
    
    if ($notification_stmt) {
        $notification_stmt->bind_param("issis", $user_id, $title, $message, $booking_id, $link);
        $notification_stmt->execute();
        $notification_stmt->close();
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $requestId = intval($_POST['request_id']);
    $newStatus = $_POST['new_status'];
    $rejectionReason = $_POST['rejection_reason'] ?? null;
    
    // Get customer and booking details for notification
    $details_query = "SELECT gr.user_id, gr.amount, gr.booking_id, gr.reference_number, gr.payment_method,
                             COALESCE(u.first_name, b.customer_first_name) AS first_name,
                             COALESCE(u.last_name, b.customer_last_name) AS last_name,
                             COALESCE(u.email, b.customer_email) AS email,
                             COALESCE(u.phone, b.customer_mobile) AS phone,
                             b.service_type, b.booking_date, b.time_slot
                      FROM gcash_requests gr
                      LEFT JOIN users u ON gr.user_id = u.id
                      JOIN bookings b ON gr.booking_id = b.id
                      WHERE gr.id = ?";
    $details_stmt = $conn->prepare($details_query);
    $details_stmt->bind_param("i", $requestId);
    $details_stmt->execute();
    $details_result = $details_stmt->get_result();
    $request_details = $details_result->fetch_assoc();
    
    $customer_name = $request_details['first_name'] . ' ' . $request_details['last_name'];
    $amount = $request_details['amount'];
    $booking_id = $request_details['booking_id'];
    
    $stmt = $conn->prepare("UPDATE gcash_requests SET status = ?, approved_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $requestId);
    
    if ($stmt->execute()) {
        // Send notification based on status
        if ($newStatus === 'approved') {
            // Get reference number (either existing or generate new one)
            $paymentMethod = $request_details['payment_method'] ?? 'GCASH';
            $prefix = 'GCASH';
            
            if (strtoupper($paymentMethod) === 'CASH_ON_DELIVERY' || $paymentMethod === 'Cash on Delivery') {
                $prefix = 'COD';
            } elseif (strtoupper($paymentMethod) === 'IN_STORE' || $paymentMethod === 'In-Store') {
                $prefix = 'IN-STORE';
            }
            
            $reference_number = $request_details['reference_number'] ?? generateReferenceNumber($prefix);
            
            // Update reference number if not set
            if (empty($request_details['reference_number'])) {
                $update_ref = $conn->prepare("UPDATE gcash_requests SET reference_number = ? WHERE id = ?");
                $update_ref->bind_param("si", $reference_number, $requestId);
                $update_ref->execute();
                $update_ref->close();
            }
            
            // Add loyalty points to user when payment is approved
            $user_id = $request_details['user_id'];
            $points_to_add = 1; // 1 point per completed booking
            $update_points = $conn->prepare("UPDATE users SET user_points = user_points + ? WHERE id = ?");
            $update_points->bind_param("ii", $points_to_add, $user_id);
            $update_points->execute();
            $update_points->close();
            
            sendGCASHApprovalNotification($requestId, $customer_name, $amount, $reference_number, $booking_id);
            
            // Send a simple user notification
            $paymentMethod = $request_details['payment_method'] ?? 'GCASH';
            
            if (strtoupper($paymentMethod) === 'CASH_ON_DELIVERY' || $paymentMethod === 'Cash on Delivery') {
                $notifTitle = "Payment Confirmed";
                $notifMsg = "Your Cash on Delivery payment of ₱" . number_format($amount, 2) . " has been confirmed. Your booking is now active.";
            } else {
                $notifTitle = "Payment Confirmed";
                $notifMsg = "Your GCASH payment of ₱" . number_format($amount, 2) . " has been confirmed. Your booking is now active.";
            }
            
            sendUserNotification($conn, $request_details['user_id'], 
                $notifTitle,
                $notifMsg,
                $booking_id
            );
                
        } elseif ($newStatus === 'rejected') {
            sendGCASHRejectionNotification($requestId, $customer_name, $amount, $rejectionReason, $booking_id);
            
            // Also send a user notification
            $rejectionMessage = "Your payment request for ₱" . number_format($amount, 2) . " has been rejected.";
            if ($rejectionReason) {
                $rejectionMessage .= "\n\nReason: {$rejectionReason}";
            }
            
            sendUserNotification($conn, $request_details['user_id'], 
                "Payment Rejected", 
                $rejectionMessage,
                $booking_id);
        }
        
        $_SESSION['success'] = "GCASH request status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating GCASH request status: " . $conn->error;
    }
    
    $stmt->close();
    $details_stmt->close();
    header("Location: payment_requests-management.php");
    exit();
}

// Handle notification actions
if (isset($_GET['mark_read'])) {
    markAdminNotificationAsRead($_GET['mark_read']);
    header("Location: payment_requests-management.php");
    exit();
}

if (isset($_GET['mark_all_read'])) {
    markAllAdminNotificationsAsRead();
    header("Location: payment_requests-management.php");
    exit();
}

// Fetch all GCASH requests with formatted amount
$query = "
    SELECT gr.*, 
           b.service_type,
           b.booking_date,
           b.time_slot,
           b.machine_count,
           b.machine_names,
           b.detergent,
           b.cod_confirmation_photo,
           gr.amount as total_amount,
           COALESCE(u.email, b.customer_email) AS email,
           COALESCE(u.phone, b.customer_mobile) AS phone,
           COALESCE(u.first_name, b.customer_first_name) AS first_name,
           COALESCE(u.last_name, b.customer_last_name) AS last_name
    FROM gcash_requests gr
    JOIN bookings b ON gr.booking_id = b.id
    LEFT JOIN users u ON gr.user_id = u.id
    ORDER BY 
        CASE WHEN gr.status = 'pending' THEN 1
             WHEN gr.status = 'approved' THEN 2
             WHEN gr.status = 'completed' THEN 3
             WHEN gr.status = 'rejected' THEN 4
        END,
        gr.requested_at DESC
";

$result = mysqli_query($conn, $query);
$gcash_requests = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get counts for stats
$total_requests = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests"));
$pending_requests = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests WHERE status = 'pending'"));
$approved_requests = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests WHERE status = 'approved'"));
$completed_requests = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests WHERE status = 'completed'"));
$rejected_requests = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests WHERE status = 'rejected'"));

// Get total approved amount
$total_amount_result = mysqli_query($conn, "SELECT SUM(amount) as total FROM gcash_requests WHERE status IN ('approved', 'completed')");
$total_amount_data = mysqli_fetch_assoc($total_amount_result);
$total_amount = $total_amount_data['total'] ?? 0;

// Get unread notifications count
$unread_count = getAdminUnreadCount();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Requests - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link rel="stylesheet" href="../assets/css/manage-gcash.css">
    <style>
        .bg-purple {
            background-color: var(--primary-purple, #7c3aed) !important;
            color: white !important;
        }
        .notifications-dropdown {
            min-width: 350px;
            max-height: 500px;
            overflow-y: auto;
        }
        .notification-item {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .notification-item.unread {
            background-color: rgba(33, 150, 243, 0.05);
            border-left-color: var(--blue);
        }
        .notification-item:hover {
            background-color: var(--gray-50);
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .notification-icon.gcash_request {
            background: linear-gradient(135deg, var(--primary-purple), var(--purple-dark));
        }
        .notification-icon.gcash_approved {
            background: linear-gradient(135deg, var(--green), #2e7d32);
        }
        .notification-icon.gcash_rejected {
            background: linear-gradient(135deg, var(--red), #d32f2f);
        }
        .notification-icon.system {
            background: linear-gradient(135deg, var(--orange), #f57c00);
        }
        .dropdown-toggle::after {
            display: none;
        }
        
        /* Additional styles for better amount display */
        .amount-display {
            font-size: 1.1rem;
            font-weight: 600;
            color: #000000 !important;
        }
        .amount-display.approved {
            color: #000000 !important;
        }
        .amount-display.pending {
            color: #000000 !important;
        }
        .amount-display.rejected {
            color: #000000 !important;
            text-decoration: line-through;
        }
        .service-badge {
            display: inline-block;
            padding: 0;
            background-color: transparent !important;
            border-radius: 0;
            font-size: 0.75rem;
            color: #000000 !important;
            border: none !important;
            background: transparent !important;
        }
        
        #requestsTable .service-badge {
            background-color: transparent !important;
            background: transparent !important;
            color: #000000 !important;
        }
        .reference-number {
            font-family: monospace;
            font-size: 0.85rem;
            background-color: #f8f9fa;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            display: inline-block;
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
        .rejection-modal textarea {
            resize: vertical;
        }
        .supplies-list {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .supply-badge {
            display: inline-block;
            background-color: #f0f0f0;
            color: #000000;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            border-left: 2px solid #000000;
        }
        
        /* Table Column Width Constraints */
        #requestsTable {
            table-layout: fixed;
            width: 100%;
            border: none !important;
            border-collapse: collapse !important;
        }
        
        #requestsTable tbody {
            border: none !important;
        }
        
        #requestsTable th:nth-child(1),
        #requestsTable td:nth-child(1) {
            width: 14%;
            min-width: 140px;
        }
        
        #requestsTable th:nth-child(2),
        #requestsTable td:nth-child(2) {
            width: 12%;
            min-width: 110px;
        }
        
        #requestsTable th:nth-child(3),
        #requestsTable td:nth-child(3) {
            width: 9%;
            min-width: 90px;
        }
        
        #requestsTable th:nth-child(4),
        #requestsTable td:nth-child(4) {
            width: 10%;
            min-width: 100px;
        }
        
        #requestsTable th:nth-child(5),
        #requestsTable td:nth-child(5) {
            width: 11%;
            min-width: 110px;
        }
        
        #requestsTable th:nth-child(6),
        #requestsTable td:nth-child(6) {
            width: 12%;
            min-width: 120px;
        }
        
        #requestsTable th:nth-child(7),
        #requestsTable td:nth-child(7) {
            width: 9%;
            min-width: 90px;
        }
        
        #requestsTable th:nth-child(8),
        #requestsTable td:nth-child(8) {
            width: 8%;
            min-width: 80px;
        }
        
        #requestsTable th:nth-child(9),
        #requestsTable td:nth-child(9) {
            width: 9%;
            min-width: 90px;
        }
        
        #requestsTable th:nth-child(10),
        #requestsTable td:nth-child(10) {
            width: 10%;
            min-width: 100px;
        }
        
        #requestsTable th:nth-child(10),
        #requestsTable td:nth-child(10) {
            width: 8%;
            min-width: 80px;
            text-align: center;
        }
        
        /* Prevent text overflow in cells */
        #requestsTable td {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        /* Adjust customer icon spacing */
        #requestsTable .customer-icon {
            min-width: 35px;
            max-width: 35px;
            flex-shrink: 0;
        }
        
        /* Consistent row height and cell alignment */
        #requestsTable tbody tr {
            height: auto;
        }
        
        #requestsTable tbody td {
            vertical-align: middle;
            padding: 12px 8px !important;
            overflow: hidden;
        }
        
        /* Customer column text wrapping */
        #requestsTable td:nth-child(1) {
            word-break: break-word;
        }
        
        #requestsTable td:nth-child(1) strong {
            display: block;
            white-space: normal;
            word-wrap: break-word;
            max-width: 120px;
        }
        
        #requestsTable td:nth-child(1) small {
            display: block;
            white-space: normal;
            word-wrap: break-word;
            max-width: 120px;
        }
        
        /* Contact column text wrapping */
        #requestsTable td:nth-child(2) small {
            white-space: normal;
            word-wrap: break-word;
            display: block;
            max-width: 100px;
        }
        
        /* Service badge */
        #requestsTable td:nth-child(3) .service-badge {
            display: inline-block;
            white-space: normal;
            word-wrap: break-word;
            max-width: 85px;
            background-color: transparent !important;
            background: transparent !important;
            color: #000000 !important;
            border: none !important;
        }
        
        /* Machine badge */
        #requestsTable td:nth-child(4) .badge {
            display: inline-block;
            white-space: normal;
            word-wrap: break-word;
            max-width: 95px;
            word-break: break-word;
            background-color: transparent !important;
            background: transparent !important;
            color: #000000 !important;
        }
        
        /* Supplies badges */
        #requestsTable td:nth-child(5) .supplies-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-width: 105px;
        }
        
        #requestsTable td:nth-child(5) .supply-badge {
            white-space: normal;
            word-wrap: break-word;
            font-size: 0.75rem;
        }
        
        /* Date and time slot */
        #requestsTable td:nth-child(6) small {
            white-space: normal;
            word-wrap: break-word;
            display: block;
            max-width: 115px;
            font-size: 0.85rem;
        }
        
        /* Amount column */
        #requestsTable td:nth-child(7) {
            white-space: nowrap;
            text-align: center;
        }
        
        /* Proof column */
        #requestsTable td:nth-child(8) {
            text-align: center;
        }
        
        /* Reference & Status column */
        #requestsTable td:nth-child(9) {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        #requestsTable td:nth-child(9) .reference-number {
            display: block;
            white-space: normal;
            word-wrap: break-word;
            max-width: 95px;
            font-size: 0.8rem;
            margin-bottom: 6px;
        }
        
        /* Action buttons */
        #requestsTable td:nth-child(10) .action-buttons {
            display: flex;
            gap: 4px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        #requestsTable td:nth-child(10) .btn {
            font-size: 0.85rem;
            padding: 4px 6px;
            white-space: nowrap;
        }
        
        /* Custom button styling with root colors */
        .approve-btn {
            background-color: var(--primary-purple, #7c3aed);
            border-color: var(--primary-purple, #7c3aed);
            color: white;
        }
        
        .approve-btn:hover {
            background-color: var(--purple-dark, #6d28d9);
            border-color: var(--purple-dark, #6d28d9);
            color: white;
        }
        
        .approve-btn:active {
            background-color: var(--purple-dark, #6d28d9);
            border-color: var(--purple-dark, #6d28d9);
        }
        
        .reject-btn {
            background-color: #6b7280;
            border-color: #6b7280;
            color: white;
        }
        
        .reject-btn:hover {
            background-color: #4b5563;
            border-color: #4b5563;
            color: white;
        }
        
        .reject-btn:active {
            background-color: #4b5563;
            border-color: #4b5563;
        }
        
        /* Status badge colors with gray */
        .status-pending .badge {
            background-color: #6b7280 !important;
            color: #ffffff !important;
        }
        
        .status-approved .badge {
            background-color: #6b7280 !important;
            color: #ffffff !important;
        }
        
        .status-rejected .badge {
            background-color: #6b7280 !important;
            color: #ffffff !important;
        }
        
        /* Stats card badge colors */
        .header-stats .badge {
            background-color: #6b7280 !important;
            color: #ffffff !important;
        }
        
        /* Ensure table rows don't get cut off */
        .table-responsive {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }
        
        #requestsTable tbody tr {
            overflow: visible !important;
            display: table-row !important;
            height: auto !important;
            border: none !important;
            background-color: transparent !important;
            outline: none !important;
            box-shadow: none !important;
        }
        
        #requestsTable tbody td {
            display: table-cell !important;
            border-collapse: collapse;
            border: none !important;
            outline: none !important;
        }
        
        /* Fix badge sizing in cells */
        #requestsTable .badge {
            display: inline-block;
            white-space: nowrap;
            padding: 0.35em 0.65em;
            font-size: 0.85rem;
        }
        
        /* Ensure consistent button sizing */
        #requestsTable .action-buttons .btn {
            display: inline-block;
            margin: 2px;
        }
        
        /* Remove yellow outlines and borders from table rows */
        #requestsTable tbody tr:nth-child(n) {
            border: none !important;
            outline: none !important;
            border-left: none !important;
            border-right: none !important;
            border-top: none !important;
            border-bottom: none !important;
            background-color: transparent !important;
            box-shadow: none !important;
        }
        
        /* Remove borders from card if applied */
        .rewards-table-card #requestsTable tbody tr {
            border: none !important;
            outline: none !important;
        }
    </style>
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
                        <li><a class="nav-link py-1" href="booking_schedules.php"><i class="fas fa-calendar-alt me-2"></i> Booked Schedules</a></li>
                        <li><a class="nav-link py-1" href="completion_calendar.php"><i class="fas fa-calendar-check me-2"></i> Completion Calendar</a></li>
                        <li><a class="nav-link py-1" href="queue_management.php"><i class="fas fa-people-arrows me-2"></i> Queue Management</a></li>
                        <li><a class="nav-link py-1" href="manage_walkins.php"><i class="fas fa-user-plus me-2"></i> Walk-in Customers</a></li>
                        <li><a class="nav-link py-1 active" href="payment_requests-management.php"><i class="fas fa-money-bill-wave me-2"></i> Payment Requests</a></li>
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
            <a class="nav-link text-danger d-flex align-items-center" href="#" onclick="confirmLogout(event)">
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
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <h1>Payment Requests</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Management</a></li>
                                        <li class="breadcrumb-item active">Payment Requests</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Manage and approve GCASH and Cash on Delivery payment requests from customers</p>
                    </div>
                    <div class="header-action-section">
                        <div class="d-flex align-items-center gap-3">
                            <!-- Notifications dropdown -->
                            <div class="dropdown">
                                <button class="btn btn-light position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-bell"></i>
                                    <?php if ($unread_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $unread_count; ?>
                                        <span class="visually-hidden">unread notifications</span>
                                    </span>
                                    <?php endif; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end notifications-dropdown p-0" aria-labelledby="notificationsDropdown">
                                    <li class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2 bg-light">
                                        <strong>Notifications</strong>
                                        <?php if ($unread_count > 0): ?>
                                        <a href="payment_requests-management.php?mark_all_read=1" class="btn btn-sm btn-link p-0 text-decoration-none">Mark all as read</a>
                                        <?php endif; ?>
                                    </li>
                                    <?php
                                    $notifications = getAdminNotifications(10, true);
                                    if ($notifications->num_rows > 0): 
                                        while($notification = $notifications->fetch_assoc()): 
                                            $messageData = json_decode($notification['message'], true);
                                            $isUnread = $notification['status'] == 'unread';
                                    ?>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-start py-2 px-3 notification-item <?php echo $isUnread ? 'unread' : ''; ?>" 
                                           href="payment_requests-management.php?mark_read=<?php echo $notification['id']; ?>">
                                            <div class="me-2">
                                                <div class="notification-icon <?php echo $notification['type'] ?? 'system'; ?>">
                                                    <?php if ($notification['type'] == 'gcash_request'): ?>
                                                        <i class="fas fa-money-bill-wave"></i>
                                                    <?php elseif ($notification['type'] == 'gcash_approved'): ?>
                                                        <i class="fas fa-check-circle"></i>
                                                    <?php elseif ($notification['type'] == 'gcash_rejected'): ?>
                                                        <i class="fas fa-times-circle"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-bell"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <strong class="d-block"><?php echo $notification['title']; ?></strong>
                                                <small class="text-muted"><?php echo $messageData['message'] ?? $notification['message']; ?></small>
                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date("M d, Y h:i A", strtotime($notification['created_at'])); ?>
                                                </div>
                                            </div>
                                            <?php if ($isUnread): ?>
                                            <div class="ms-2">
                                                <span class="badge bg-primary rounded-pill">New</span>
                                            </div>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <li class="px-3 py-3 text-center text-muted">
                                        <i class="fas fa-bell-slash mb-2" style="font-size: 1.5rem;"></i>
                                        <div>No new notifications</div>
                                    </li>
                                    <?php endif; ?>
                                    <li class="dropdown-footer text-center py-2 bg-light">
                                        <a href="admin_notifications.php" class="text-decoration-none">
                                            <i class="fas fa-list me-1"></i> View all notifications
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="current-date">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                            <?php if ($pending_requests > 0): ?>
                            <div class="pending-alert">
                                <span class="badge" style="background-color: #7c3aed; color: white;">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <?php echo $pending_requests; ?> Pending
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
                            <div class="stat-icon bg-secondary text-white">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $total_requests; ?></h3>
                                <p>Total Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary text-white">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $pending_requests; ?></h3>
                                <p>Pending Approval</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary text-white">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $approved_requests; ?></h3>
                                <p>Approved Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary text-white">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-content">
                                <h3>₱<?php echo number_format($total_amount, 2); ?></h3>
                                <p>Total Amount</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GCASH Requests Table Card -->
        <div class="card rewards-table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5><i class="fas fa-list me-2"></i> Payment Requests List</h5>
                    <small class="text-muted">Review and manage customer GCASH and Cash on Delivery payment requests</small>
                </div>
                <div class="table-actions">
                    <div class="input-group input-group-sm" style="width: 200px;">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control form-control-sm" placeholder="Search requests..." id="searchInput">
                    </div>
                </div>
            </div>
            <div class="card-body p-0" style="overflow: visible;">
                <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
                    <table class="table table-hover mb-0" id="requestsTable">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Service</th>
                                <th>Machine</th>
                                <th>Supplies</th>
                                <th>Date & Time-Slot</th>

                                <th>Amount</th>
                                <th>Proof</th>
                                <th>Reference & Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($gcash_requests) > 0): 
                                foreach ($gcash_requests as $request): 
                                    $status = $request['status'];
                                    $statusClass = '';
                                    $statusIcon = '';
                                    $amountClass = '';
                                    
                                    switch($status) {
                                        case 'approved':
                                            $statusClass = 'success';
                                            $statusIcon = 'fa-check-circle';
                                            $amountClass = 'approved';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'danger';
                                            $statusIcon = 'fa-times-circle';
                                            $amountClass = 'rejected';
                                            break;
                                        case 'pending':
                                        default:
                                            $statusClass = 'warning';
                                            $statusIcon = 'fa-clock';
                                            $amountClass = 'pending';
                                    }
                            ?>
                                <tr class="status-<?php echo $status; ?>" data-request-id="<?php echo $request['id']; ?>">
                                    <!-- Customer -->
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="customer-icon me-2">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                                <small class="text-muted">
                                                    <i class="fas fa-receipt me-1"></i>
                                                    Request #<?php echo $request['id']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Contact -->
                                    <td>
                                        <small class="d-block">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($request['phone']); ?>
                                        </small>
                                        <small class="d-block mt-1">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($request['email']); ?>
                                        </small>
                                    </td>
                                    
                                    <!-- Service -->
                                    <td>
                                        <span class="service-badge">
                                            <?php 
                                            $service_display = $request['service_type'];
                                            if (strpos($service_display, ':') !== false) {
                                                $parts = explode(',', $service_display);
                                                $service_names = [];
                                                foreach ($parts as $part) {
                                                    if (strpos($part, ':') !== false) {
                                                        $service_names[] = explode(':', $part)[1];
                                                    } else {
                                                        $service_names[] = $part;
                                                    }
                                                }
                                                $service_display = implode(', ', $service_names);
                                            }
                                            echo htmlspecialchars($service_display);
                                            ?>
                                        </span>
                                    </td>
                                    
                                    <!-- Machine -->
                                    <td>
                                        <?php if (!empty($request['machine_names'])): ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-washer me-1"></i>
                                                <?php echo htmlspecialchars($request['machine_names']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Supplies -->
                                    <td>
                                        <?php if (!empty($request['detergent']) && $request['detergent'] !== 'N/A'): ?>
                                            <div class="supplies-list">
                                                <?php 
                                                $detergents = explode(',', $request['detergent']);
                                                $supplies_display = [];
                                                foreach ($detergents as $det_entry) {
                                                    $det_entry = trim($det_entry);
                                                    if (!empty($det_entry) && $det_entry !== 'Bring my own detergent' && $det_entry !== 'Bring my own') {
                                                        $qty = 1;
                                                        $item_name = $det_entry;
                                                        if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $det_entry, $matches)) {
                                                            $qty = intval($matches[1]);
                                                            $item_name = trim($matches[2]);
                                                        }
                                                        $supplies_display[] = ['qty' => $qty, 'name' => $item_name];
                                                    }
                                                }
                                                foreach ($supplies_display as $supply):
                                                ?>
                                                <span class="supply-badge">
                                                    <?php if ($supply['qty'] > 1): ?><strong><?php echo $supply['qty']; ?>x</strong><?php endif; ?>
                                                    <?php echo htmlspecialchars($supply['name']); ?>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Date & Time-Slot -->
                                    <td>
                                        <small class="d-block">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            <?php echo date('M j, Y', strtotime($request['booking_date'])); ?>
                                        </small>
                                        <small class="d-block mt-1">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo htmlspecialchars($request['time_slot']); ?>
                                        </small>
                                    </td>
                                    

                                    <!-- Amount -->
                                    <td>
                                        <strong class="amount-display <?php echo $amountClass; ?>">
                                            ₱<?php echo number_format($request['total_amount'], 2); ?>
                                        </strong>
                                    </td>
                                    
                                    <!-- Proof (GCASH & COD) -->
                                    <td class="text-center">
                                        <?php if ($request['payment_method'] === 'GCASH' && !empty($request['proof_image'])): ?>
                                            <button type="button" class="btn btn-sm btn-purple text-dark" onclick="viewProofImage('<?php echo htmlspecialchars($request['proof_image']); ?>', 'GCASH')">
                                                <i class="fas fa-image me-1"></i>View
                                            </button>
                                        <?php elseif ((strtoupper($request['payment_method']) === 'CASH_ON_DELIVERY' || $request['payment_method'] === 'Cash on Delivery') && !empty($request['cod_confirmation_photo'])): ?>
                                            <button type="button" class="btn btn-sm btn-purple text-dark" onclick="viewProofImage('<?php echo htmlspecialchars($request['cod_confirmation_photo']); ?>', 'COD')">
                                                <i class="fas fa-image me-1"></i>View
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Reference & Status -->
                                    <td>
                                        <div>
                                            <!-- Payment Method Badge -->
                                            <div class="mb-1">
                                                <?php 
                                                $pm = $request['payment_method'] ?? 'GCASH';
                                                $pm_class = 'bg-purple';
                                                $pm_display = $pm;
                                                
                                                if (strtoupper($pm) === 'CASH_ON_DELIVERY' || $pm === 'Cash on Delivery') {
                                                    $pm_class = 'bg-success';
                                                    $pm_display = 'COD';
                                                } elseif (strtoupper($pm) === 'IN_STORE' || $pm === 'In-Store') {
                                                    $pm_class = 'bg-info';
                                                    $pm_display = 'In-Store';
                                                }
                                                ?>
                                                <span class="badge <?php echo $pm_class; ?>" style="font-size: 0.7rem;">
                                                    <?php echo $pm_display; ?>
                                                </span>
                                            </div>

                                            <?php if (!empty($request['reference_number'])): ?>
                                                <small class="d-block reference-number">
                                                    <i class="fas fa-hashtag me-1"></i>
                                                    <?php echo htmlspecialchars($request['reference_number']); ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="d-block text-muted">Not generated</small>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <?php if ($status === 'pending'): ?>
                                                    <span class="badge" style="background-color: var(--primary-purple, #7c3aed);">
                                                        <i class="fas fa-clock me-1"></i> Pending
                                                    </span>
                                                <?php elseif ($status === 'approved' || $status === 'completed'): ?>
                                                    <span class="badge" style="background-color: #6b7280;">
                                                        <i class="fas fa-check-circle me-1"></i> Approved
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: #6b7280;">
                                                        <i class="fas fa-times-circle me-1"></i> Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Actions -->
                                    <td class="text-center">
                                        <?php if ($status === 'pending'): ?>
                                            <div class="action-buttons">
                                                <button type="button" 
                                                        class="btn btn-sm approve-btn" 
                                                        data-request-id="<?php echo $request['id']; ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>"
                                                        data-amount="<?php echo number_format($request['total_amount'], 2); ?>"
                                                        data-payment-method="<?php echo htmlspecialchars($request['payment_method'] ?? 'GCASH'); ?>"
                                                        title="Mark as Paid">
                                                     Mark as Paid
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm reject-btn" 
                                                        data-request-id="<?php echo $request['id']; ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>"
                                                        data-amount="<?php echo number_format($request['total_amount'], 2); ?>"
                                                        title="Reject Request">
                                                     Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-check-circle"></i> Approved
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-money-bill-wave fa-3x mb-3 text-muted"></i>
                                            <h5 class="mb-2">No payment requests yet</h5>
                                            <p class="text-muted mb-0">Customers haven't made any payment requests yet</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="rejectionModal" tabindex="-1" aria-labelledby="rejectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #6b7280; color: white;">
                    <h5 class="modal-title" id="rejectionModalLabel" style="color: white;">
                        <i class="fas fa-times-circle me-2" style="color: white;"></i> Reject Payment Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="rejectionForm">
                    <input type="hidden" name="request_id" id="rejectRequestId">
                    <input type="hidden" name="new_status" value="rejected">
                    <div class="modal-body">
                        <div class="alert" style="background-color: white; border: 1px solid #6b7280; color: #000;">
                            <i class="fas fa-exclamation-triangle me-2" style="color: #ffc107;"></i>
                            <strong style="color: #000;">Warning:</strong> <span style="color: #000;">This action cannot be undone.</span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Reason for Rejection (Optional)</label>
                            <textarea class="form-control" name="rejection_reason" id="rejectionReason" rows="3" 
                                      placeholder="Please provide a reason for rejecting this request..."></textarea>
                            <small class="text-muted">This reason will be sent to the customer.</small>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold" style="color: #000;">Customer:</span>
                                <span id="rejectCustomerName" style="color: #000;">-</span>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <span class="fw-bold" style="color: #000;">Amount:</span>
                                <span id="rejectAmount" style="color: #000; font-weight: bold;">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" style="background-color: #6b7280; border-color: #6b7280; color: white;" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn" style="background-color: #7c3aed; border-color: #7c3aed; color: white;">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include SweetAlert2 -->
        <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
        <script src="../assets/lib/js/sweetalert2.min.js"></script>

    
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
        // Ensure all scripts are loaded before attaching event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchValue = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#requestsTable tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchValue) ? '' : 'none';
                    });
                });
            }

            // Approve button handler
            document.querySelectorAll('.approve-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const requestId = this.getAttribute('data-request-id');
                    const customerName = this.getAttribute('data-customer-name');
                    const amount = this.getAttribute('data-amount');
                    const paymentMethod = this.getAttribute('data-payment-method') || 'GCASH';
                    
                    // Determine the approval message based on payment method
                    let approvalTitle = 'Mark Payment as Paid?';
                    let approvalNote = 'This will confirm the payment and update the booking status.';
                    
                    if (paymentMethod === 'CASH_ON_DELIVERY' || paymentMethod === 'Cash on Delivery') {
                        approvalTitle = 'Mark Cash on Delivery as Paid?';
                        approvalNote = 'The customer has confirmed payment in their profile.';
                    }
                    
                    Swal.fire({
                        title: approvalTitle,
                        html: `<div class="text-left">
                            <p><strong>Customer:</strong> ${customerName}</p>
                            <p><strong>Amount:</strong> ₱${amount}</p>
                            <p><strong>Request ID:</strong> #${requestId}</p>
                            <p><strong>Payment Method:</strong> ${paymentMethod}</p>
                            <p class="text-muted mt-2">${approvalNote}</p>
                        </div>`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-check me-2"></i> Yes, Mark as Paid',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#7c3aed',
                        cancelButtonColor: '#6c757d',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Create and submit form
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = window.location.pathname;
                            
                            const inputId = document.createElement('input');
                            inputId.type = 'hidden';
                            inputId.name = 'request_id';
                            inputId.value = requestId;
                            
                            const inputStatus = document.createElement('input');
                            inputStatus.type = 'hidden';
                            inputStatus.name = 'new_status';
                            inputStatus.value = 'approved';
                            
                            const inputUpdate = document.createElement('input');
                            inputUpdate.type = 'hidden';
                            inputUpdate.name = 'update_status';
                            inputUpdate.value = '1';
                            
                            form.appendChild(inputId);
                            form.appendChild(inputStatus);
                            form.appendChild(inputUpdate);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });
            
            // Reject button handler
            const rejectionModalEl = document.getElementById('rejectionModal');
            if (rejectionModalEl) {
                const rejectionModal = new bootstrap.Modal(rejectionModalEl);
                
                document.querySelectorAll('.reject-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const requestId = this.getAttribute('data-request-id');
                        const customerName = this.getAttribute('data-customer-name');
                        const amount = this.getAttribute('data-amount');
                        
                        document.getElementById('rejectRequestId').value = requestId;
                        document.getElementById('rejectCustomerName').textContent = customerName;
                        document.getElementById('rejectAmount').textContent = '₱' + amount;
                        document.getElementById('rejectionReason').value = '';
                        
                        rejectionModal.show();
                    });
                });
            }

            // Auto refresh notifications every 30 seconds
            let lastNotificationCount = <?php echo $unread_count; ?>;
            
            setInterval(function() {
                const dropdown = document.getElementById('notificationsDropdown');
                if (dropdown) {
                    const isOpen = dropdown.getAttribute('aria-expanded') === 'true';
                    
                    if (!isOpen) {
                        fetch('ajax/get_notification_count.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.count && data.count != lastNotificationCount) {
                                    window.location.reload();
                                }
                            })
                            .catch(error => console.error('Error fetching notification count:', error));
                    }
                }
            }, 30000);
        });

        // Function to view proof image for GCASH and COD
        window.viewProofImage = function(filename, type = 'GCASH') {
            let photoPath = '';
            let title = '';
            
            if (type === 'GCASH') {
                photoPath = '../uploads/payment_proofs/' + filename;
                title = 'GCASH Payment Proof';
            } else {
                photoPath = '../uploads/cod_confirmations/' + filename;
                title = 'COD Delivery Proof';
            }
            
            Swal.fire({
                title: title,
                html: `<img src="${photoPath}" alt="${title}" style="max-width: 100%; max-height: 500px; border-radius: 8px;">`,
                confirmButtonText: 'Close',
                confirmButtonColor: '#7c3aed'
            });
        };

        // Show success/error messages
        <?php if (isset($_SESSION['success'])) : ?>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: "Success!",
                    text: "<?php echo $_SESSION['success']; ?>",
                    icon: "success",
                    confirmButtonText: "OK",
                    background: '#fff',
                    color: '#333',
                    confirmButtonColor: '#7c3aed',
                    timer: 3000,
                    showConfirmButton: true
                });
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])) : ?>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: "Error!",
                    text: "<?php echo $_SESSION['error']; ?>",
                    icon: "error",
                    confirmButtonText: "OK",
                    background: '#fff',
                    color: '#333',
                    confirmButtonColor: '#7c3aed'
                });
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>







