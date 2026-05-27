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
function generateReferenceNumber() {
    return 'GCASH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
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
    $details_query = "SELECT gr.user_id, gr.amount, gr.booking_id, gr.reference_number,
                             u.first_name, u.last_name, u.email, u.phone,
                             b.service_type, b.booking_date, b.time_slot
                      FROM gcash_requests gr
                      JOIN users u ON gr.user_id = u.id
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
            $reference_number = $request_details['reference_number'] ?? generateReferenceNumber();
            
            // Update reference number if not set
            if (empty($request_details['reference_number'])) {
                $update_ref = $conn->prepare("UPDATE gcash_requests SET reference_number = ? WHERE id = ?");
                $update_ref->bind_param("si", $reference_number, $requestId);
                $update_ref->execute();
                $update_ref->close();
            }
            
            sendGCASHApprovalNotification($requestId, $customer_name, $amount, $reference_number, $booking_id);
            
            // Also send a user notification
            sendUserNotification($conn, $request_details['user_id'], 
            "GCASH Payment Approved", 
            "Your GCASH payment request for ₱" . number_format($amount, 2) . " has been approved.\n\nReference Number: {$reference_number}\n\nClick here to view payment details.",
            $booking_id,
            "user-profile.php?view_gcash_request={$requestId}"
        );
                
        } elseif ($newStatus === 'rejected') {
            sendGCASHRejectionNotification($requestId, $customer_name, $amount, $rejectionReason, $booking_id);
            
            // Also send a user notification
            $rejectionMessage = "Your GCASH payment request for ₱" . number_format($amount, 2) . " has been rejected.";
            if ($rejectionReason) {
                $rejectionMessage .= "\n\nReason: {$rejectionReason}";
            }
            
            sendUserNotification($conn, $request_details['user_id'], 
                "GCASH Payment Rejected", 
                $rejectionMessage,
                $booking_id);
        }
        
        $_SESSION['success'] = "GCASH request status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating GCASH request status: " . $conn->error;
    }
    
    $stmt->close();
    $details_stmt->close();
    header("Location: gcash_requests-management.php");
    exit();
}

// Handle notification actions
if (isset($_GET['mark_read'])) {
    markAdminNotificationAsRead($_GET['mark_read']);
    header("Location: gcash_requests-management.php");
    exit();
}

if (isset($_GET['mark_all_read'])) {
    markAllAdminNotificationsAsRead();
    header("Location: gcash_requests-management.php");
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
           gr.amount as total_amount,
           u.email,
           u.phone,
           u.first_name,
           u.last_name
    FROM gcash_requests gr
    JOIN bookings b ON gr.booking_id = b.id
    JOIN users u ON gr.user_id = u.id
    ORDER BY 
        CASE WHEN gr.status = 'pending' THEN 1
             WHEN gr.status = 'approved' THEN 2
             WHEN gr.status = 'rejected' THEN 3
        END,
        gr.requested_at DESC
";

$result = mysqli_query($conn, $query);
$gcash_requests = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get counts for stats
$total_requests = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests"));
$pending_requests = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests WHERE status = 'pending'"));
$approved_requests = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests WHERE status = 'approved'"));
$rejected_requests = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests WHERE status = 'rejected'"));

// Get total approved amount
$total_amount_result = mysqli_query($conn, "SELECT SUM(amount) as total FROM gcash_requests WHERE status = 'approved'");
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
    <title>GCASH Payment Requests - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link href="../assets/lib/fonts/roboto-fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/manage-gcash.css">
    <style>
        /* Notifications dropdown styles */
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
            color: #28a745;
        }
        .amount-display.approved {
            color: #28a745;
        }
        .amount-display.pending {
            color: #ffc107;
        }
        .amount-display.rejected {
            color: #dc3545;
            text-decoration: line-through;
        }
        .service-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background-color: #e9ecef;
            border-radius: 4px;
            font-size: 0.75rem;
            color: #495057;
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
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            border-left: 2px solid #1976d2;
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
                        <li><a class="nav-link py-1" href="manage_users.php"><i class="fas fa-user me-2"></i> Registered Users</a></li>
                        <li><a class="nav-link py-1" href="manage_machines.php"><i class="fas fa-tools me-2"></i> Machine Management</a></li>
                        <li><a class="nav-link py-1" href="manage_inventory.php"><i class="fas fa-box me-2"></i> Inventory Management</a></li>
                        <li><a class="nav-link py-1" href="booking_schedules.php"><i class="fas fa-calendar-alt me-2"></i> Booked Schedules</a></li>
                        <li><a class="nav-link py-1" href="queue_management.php"><i class="fas fa-people-arrows me-2"></i> Queue Management</a></li>
                        <li><a class="nav-link py-1 active" href="gcash_requests-management.php"><i class="fas fa-mobile-alt me-2"></i> GCASH Requests</a></li>
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
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div>
                                <h1>GCASH Payment Requests</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Management</a></li>
                                        <li class="breadcrumb-item active">GCASH Requests</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Manage and approve GCASH payment requests from customers</p>
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
                                        <a href="gcash_requests-management.php?mark_all_read=1" class="btn btn-sm btn-link p-0 text-decoration-none">Mark all as read</a>
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
                                           href="gcash_requests-management.php?mark_read=<?php echo $notification['id']; ?>">
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
                                <span class="badge bg-warning">
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
                            <div class="stat-icon bg-primary">
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
                            <div class="stat-icon bg-warning">
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
                            <div class="stat-icon bg-success">
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
                            <div class="stat-icon bg-info">
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
                    <h5><i class="fas fa-list me-2"></i> GCASH Payment Requests List</h5>
                    <small class="text-muted">Review and manage customer GCASH payment requests</small>
                </div>
                <div class="table-actions">
                    <div class="input-group input-group-sm" style="width: 200px;">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control form-control-sm" placeholder="Search requests..." id="searchInput">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="requestsTable">
                        <thead>
                            32
                                <th width="18%">Customer</th>
                                <th width="12%">Contact</th>
                                <th width="20%">Booking Details</th>
                                <th width="12%">Amount</th>
                                <th width="13%">Reference</th>
                                <th width="13%">Status</th>
                                <th width="12%" class="text-center">Actions</th>
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
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="customer-icon me-3">
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
                                    </div>
                                    <td>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($request['phone']); ?>
                                        </small>
                                        <small class="text-muted d-block mt-1">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($request['email']); ?>
                                        </small>
                                     </div>
                                    <td>
                                        <div class="booking-details">
                                            <small class="text-muted d-block">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                <?php echo date('M j, Y', strtotime($request['booking_date'])); ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo htmlspecialchars($request['time_slot']); ?>
                                            </small>
                                            <div class="mt-1">
                                                <span class="service-badge">
                                                    <?php 
                                                    // Parse service names if stored in ID:Name format
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
                                            </div>
                                            <?php if (!empty($request['machine_count'])): ?>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-washer me-1"></i>
                                                <?php echo $request['machine_count']; ?> machine(s)
                                            </small>
                                            <?php endif; ?>
                                            <?php if (!empty($request['detergent']) && $request['detergent'] !== 'N/A'): ?>
                                            <small class="text-muted d-block mt-2">
                                                <i class="fas fa-droplet me-1"></i>
                                                <strong>Supplies:</strong>
                                            </small>
                                            <div class="supplies-list mt-1">
                                                <?php 
                                                // Parse and display detergent items with quantities
                                                $detergents = explode(',', $request['detergent']);
                                                $supplies_display = [];
                                                foreach ($detergents as $det_entry) {
                                                    $det_entry = trim($det_entry);
                                                    if (!empty($det_entry) && $det_entry !== 'Bring my own detergent' && $det_entry !== 'Bring my own') {
                                                        // Parse "Qty x ItemName" format
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
                                                    <?php if ($supply['qty'] > 1): ?>
                                                        <strong><?php echo $supply['qty']; ?>x</strong> 
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($supply['name']); ?>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>                                        </div>
                                     </div>
                                    <td>
                                        <span class="amount-display <?php echo $amountClass; ?>">
                                            ₱<?php echo number_format($request['total_amount'], 2); ?>
                                        </span>
                                     </div>
                                    <td>
                                        <?php if (!empty($request['reference_number'])): ?>
                                            <span class="reference-number">
                                                <i class="fas fa-hashtag me-1"></i>
                                                <?php echo htmlspecialchars($request['reference_number']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not generated</span>
                                        <?php endif; ?>
                                     </div>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <div class="d-flex align-items-center mb-1">
                                                <div class="status-indicator me-2 <?php echo $statusClass; ?>"></div>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </div>
                                            <div class="mt-1">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Requested: <?php echo date("M d, Y", strtotime($request['requested_at'])); ?>
                                                </small>
                                            </div>
                                            <?php if ($request['approved_at'] && $status === 'approved'): ?>
                                            <div class="mt-1">
                                                <small class="text-success">
                                                    <i class="fas fa-check me-1"></i>
                                                    Approved: <?php echo date("M d, Y", strtotime($request['approved_at'])); ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                     </div>
                                    <td class="text-center">
                                        <?php if ($status === 'pending'): ?>
                                            <div class="action-buttons">
                                                <button type="button" 
                                                        class="btn btn-sm btn-success px-3 approve-btn" 
                                                        data-request-id="<?php echo $request['id']; ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>"
                                                        data-amount="<?php echo number_format($request['total_amount'], 2); ?>"
                                                        title="Approve Request">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger px-3 reject-btn" 
                                                        data-request-id="<?php echo $request['id']; ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>"
                                                        data-amount="<?php echo number_format($request['total_amount'], 2); ?>"
                                                        title="Reject Request">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-check-circle text-muted"></i>
                                                Processed
                                            </span>
                                        <?php endif; ?>
                                     </div>
                                 </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-mobile-alt fa-3x mb-3 text-muted"></i>
                                            <h5 class="mb-2">No GCASH requests yet</h5>
                                            <p class="text-muted mb-0">Customers haven't made any GCASH payment requests yet</p>
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
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectionModalLabel">
                        <i class="fas fa-times-circle me-2"></i> Reject GCASH Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="rejectionForm">
                    <input type="hidden" name="request_id" id="rejectRequestId">
                    <input type="hidden" name="new_status" value="rejected">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action cannot be undone.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Reason for Rejection (Optional)</label>
                            <textarea class="form-control" name="rejection_reason" id="rejectionReason" rows="3" 
                                      placeholder="Please provide a reason for rejecting this request..."></textarea>
                            <small class="text-muted">This reason will be sent to the customer.</small>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Customer:</span>
                                <span id="rejectCustomerName">-</span>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <span class="fw-bold">Amount:</span>
                                <span id="rejectAmount" class="text-danger fw-bold">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-danger">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include SweetAlert2 -->
        <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">

    
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#requestsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // Approve button handler
        document.querySelectorAll('.approve-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-request-id');
                const customerName = this.getAttribute('data-customer-name');
                const amount = this.getAttribute('data-amount');
                
                Swal.fire({
                    title: 'Approve GCASH Request?',
                    html: `<div class="text-left">
                        <p><strong>Customer:</strong> ${customerName}</p>
                        <p><strong>Amount:</strong> ?${amount}</p>
                        <p><strong>Request ID:</strong> #${requestId}</p>
                        <p class="text-muted mt-2">A reference number will be generated automatically.</p>
                    </div>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check me-2"></i> Yes, Approve',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Create and submit form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        
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
        const rejectionModal = new bootstrap.Modal(document.getElementById('rejectionModal'));
        
        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-request-id');
                const customerName = this.getAttribute('data-customer-name');
                const amount = this.getAttribute('data-amount');
                
                document.getElementById('rejectRequestId').value = requestId;
                document.getElementById('rejectCustomerName').textContent = customerName;
                document.getElementById('rejectAmount').textContent = '?' + amount;
                document.getElementById('rejectionReason').value = '';
                
                rejectionModal.show();
            });
        });

        // Show success/error messages
        <?php if (isset($_SESSION['success'])) : ?>
            Swal.fire({
                title: "Success!",
                text: "<?php echo $_SESSION['success']; ?>",
                icon: "success",
                confirmButtonText: "OK",
                background: '#fff',
                color: '#333',
                timer: 3000,
                showConfirmButton: true
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])) : ?>
            Swal.fire({
                title: "Error!",
                text: "<?php echo $_SESSION['error']; ?>",
                icon: "error",
                confirmButtonText: "OK",
                background: '#fff',
                color: '#333'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        // Auto refresh notifications every 30 seconds
        let lastNotificationCount = <?php echo $unread_count; ?>;
        
        setInterval(function() {
            const dropdown = document.getElementById('notificationsDropdown');
            const isOpen = dropdown.getAttribute('aria-expanded') === 'true';
            
            if (!isOpen) {
                fetch('ajax/get_notification_count.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.count != lastNotificationCount) {
                            window.location.reload();
                        }
                    })
                    .catch(error => console.error('Error fetching notification count:', error));
            }
        }, 30000);
    </script>
</body>
</html>







