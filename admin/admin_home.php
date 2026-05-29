<?php

require '../config.php';
require '../includes/dashboard_stats.php';
require '../includes/sales_charts.php';
require '../includes/maintenance-functions.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/admin-notifications.php';


// Fetch all dashboard stats
$stats = getDashboardStats($conn);

// Access individual stats like this:
$total_users = $stats['total_users'];
$today_profit = $stats['today_profit'];
$total_profit = $stats['total_profit'];
$today_bookings = $stats['today_bookings'];
$total_bookings = $stats['total_bookings'];
$total_machines = $stats['total_machines'];
$booked_machines = $stats['booked_machines'];
$available_machines = $stats['available_machines'];
$weekly_sales = getWeeklySalesData($conn);
$monthly_sales = getMonthlySalesData($conn);
$service_sales = getServiceTypeSalesData($conn);
$sales_growth = getTodaySalesGrowth($conn);
$monthly_total = getMonthlyTotalSales($conn);

// Fetch recent bookings
$recent_bookings = getRecentBookings($conn, 6);

// Handle notification actions
if (isset($_GET['mark_read'])) {
    markAdminNotificationAsRead($_GET['mark_read']);
    header("Location: admin_home.php");
    exit();
}

if (isset($_GET['mark_all_read'])) {
    markAllAdminNotificationsAsRead();
    header("Location: admin_home.php");
    exit();
}

// Get unread notifications count - MUST COME BEFORE using $unread_count
$unread_count = getAdminUnreadCount();

// Get pending GCASH requests count for the badge
$pending_requests_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM gcash_requests WHERE status = 'pending'");
$pending_requests_data = mysqli_fetch_assoc($pending_requests_result);
$pending_requests = $pending_requests_data['count'] ?? 0;

// Get maintenance statistics
$maintenance_stats = getMaintenanceStats($conn);
$machines_needing_maintenance = getMachinesNeedingMaintenance($conn);
$maintenance_alerts_count = $maintenance_stats['total_needs_maintenance'] ?? 0;

// Get inventory low stock items - using your existing code
$inventory_query = "SELECT item_name, stock_quantity FROM inventory WHERE stock_quantity < 5";
$inventory_result = mysqli_query($conn, $inventory_query);
$lowStockItems = [];

if ($inventory_result && mysqli_num_rows($inventory_result) > 0) {
    while ($row = mysqli_fetch_assoc($inventory_result)) {
        $lowStockItems[] = [
            'item_name' => $row['item_name'],
            'stock_quantity' => $row['stock_quantity']
        ];
    }
}

// Get admin notifications - MUST COME BEFORE using $admin_notifications
$admin_notifications = getAdminNotifications(10, true);

// Calculate total alerts - NOW $unread_count is defined
$total_alerts = $unread_count + count($lowStockItems);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <style>
      body { font-family: 'Poppins', sans-serif !important; }
      .sidebar-header h4 { font-family: 'Poppins', sans-serif; }
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
                    <a class="nav-link active" href="admin_home.php">
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
            <a class="nav-link text-danger d-flex align-items-center" href="#" onclick="confirmLogout(event)">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h1>
                    <p class="text-muted mb-0">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Administrator'); ?></strong>! Here's an overview of your laundry business today.</p>
                    <nav aria-label="breadcrumb" class="mt-2">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </nav>
                </div>
                <div class="header-action-section">
                    <div class="d-flex align-items-center gap-3">
                       <!-- Notifications dropdown -->
                <div class="dropdown">
                    <button class="notif-bell-btn position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <?php if ($total_alerts > 0): ?>
                        <span class="notif-count"><?php echo $total_alerts; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notif-panel p-0" aria-labelledby="notificationsDropdown">
                        <!-- Panel header -->
                        <div class="notif-panel-head">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-bell"></i>
                                <span>Notifications</span>
                                <?php if ($total_alerts > 0): ?>
                                <span class="notif-head-count"><?php echo $total_alerts; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($unread_count > 0): ?>
                            <a href="admin_home.php?mark_all_read=1" class="notif-mark-all">Mark all as read</a>
                            <?php endif; ?>
                        </div>

                        <div class="notif-scroll">
                            <!-- Inventory Alerts -->
                            <?php if (!empty($lowStockItems)): ?>
                            <div class="notif-section-label">
                                <i class="fas fa-boxes me-1"></i> Inventory Alerts
                            </div>
                            <?php foreach ($lowStockItems as $item): ?>
                            <a href="manage_inventory.php" class="notif-item notif-item-warning">
                                <div class="notif-icon-wrap notif-icon-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="notif-body">
                                    <span class="notif-title"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                    <span class="notif-msg">Only <?php echo intval($item['stock_quantity']); ?> unit(s) left — restock needed</span>
                                </div>
                                <span class="notif-badge-warn">Low</span>
                            </a>
                            <?php endforeach; ?>
                            <div class="notif-divider"></div>
                            <?php endif; ?>

                            <!-- System Notifications -->
                            <?php if (isset($admin_notifications) && $admin_notifications->num_rows > 0): ?>
                                <?php while($notification = $admin_notifications->fetch_assoc()):
                                    $messageData = json_decode($notification['message'], true);
                                    $isUnread = $notification['status'] == 'unread';
                                    $type = $notification['type'] ?? 'system';
                                ?>
                                <a class="notif-item <?php echo $isUnread ? 'notif-item-unread' : ''; ?>"
                                   href="admin_home.php?mark_read=<?php echo $notification['id']; ?>">
                                    <div class="notif-icon-wrap notif-icon-<?php echo $type; ?>">
                                        <?php if ($type == 'gcash_request'): ?>
                                            <i class="fas fa-mobile-alt"></i>
                                        <?php elseif ($type == 'gcash_approved'): ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php elseif ($type == 'gcash_rejected'): ?>
                                            <i class="fas fa-times-circle"></i>
                                        <?php elseif ($type == 'inventory_low'): ?>
                                            <i class="fas fa-exclamation-triangle"></i>
                                        <?php elseif ($type == 'booking'): ?>
                                            <i class="fas fa-calendar-check"></i>
                                        <?php else: ?>
                                            <i class="fas fa-bell"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notif-body">
                                        <span class="notif-title"><?php echo htmlspecialchars($notification['title']); ?></span>
                                        <span class="notif-msg"><?php echo htmlspecialchars($messageData['message'] ?? $notification['message']); ?></span>
                                        <span class="notif-time"><i class="fas fa-clock me-1"></i><?php echo date("M d, Y h:i A", strtotime($notification['created_at'])); ?></span>
                                    </div>
                                    <?php if ($isUnread): ?>
                                    <span class="notif-badge-new">New</span>
                                    <?php endif; ?>
                                </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <?php if (empty($lowStockItems)): ?>
                                <div class="notif-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    <span>You're all caught up!</span>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Panel footer -->
                        <a href="admin_notifications.php" class="notif-panel-footer">
                            <i class="fas fa-list-ul me-2"></i> View all notifications
                        </a>
                    </div>
                </div>
                        <div class="current-date">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-calendar-day me-1"></i>
                                <?php echo date('F j, Y'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <!-- Registered Users -->
            <div class="col-md-6 col-lg-3">
                <div class="stats-card users">
                    <div class="card-icon">
                        <i class="fas fa-users" style="color: white;"></i>
                    </div>
                    <div class="card-content">
                        <h6>REGISTERED USERS</h6>
                        <h4><?php echo $total_users; ?></h4>
                        <p class="card-subtitle">Total active accounts</p>
                    </div>
                    <a href="manage_users.php" class="btn btn-sm btn-outline-primary mt-3">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Profit -->
            <div class="col-md-6 col-lg-3">
                <div class="stats-card profit">
                    <div class="card-icon">
                        <i class="fas fa-chart-line" style="color: white;"></i>
                    </div>
                    <div class="card-content">
                        <h6>TODAY'S REVENUE</h6>
                        <h4 class="text-dark">₱<?php echo number_format($today_profit, 2); ?></h4>
                        <p class="card-subtitle">Daily earnings</p>
                    </div>
                    <div class="card-footer">
                        <span>Total: ₱<?php echo number_format($total_profit, 2); ?></span>
                        <i class="fas fa-money-bill-wave" style="color: #6366f1;"></i>
                    </div>
                    <a href="reports.php" class="btn btn-sm btn-outline-primary mt-3">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Bookings -->
            <div class="col-md-6 col-lg-3">
                <div class="stats-card bookings">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check" style="color: white;"></i>
                    </div>
                    <div class="card-content">
                        <h6>TODAY'S BOOKINGS</h6>
                        <h4 class="text-dark"><?php echo $today_bookings; ?></h4>
                        <p class="card-subtitle">Reservations today</p>
                    </div>
                    <div class="card-footer">
                        <span>Total: <?php echo $total_bookings; ?></span>
                        <i class="fas fa-bookmark" style="color: #6366f1;"></i>
                    </div>
                    <a href="booking_schedules.php" class="btn btn-sm btn-outline-primary mt-3">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Available Machines -->
            <div class="col-md-6 col-lg-3">
                <div class="stats-card machines">
                    <div class="card-icon">
                        <i class="fas fa-tools" style="color: white;"></i>
                    </div>
                    <div class="card-content">
                        <h6>MACHINE STATUS</h6>
                        <h4><span class="text-primary"><?php echo $available_machines; ?></span>/<?php echo $total_machines; ?></h4>
                        <div class="progress mt-2">
                            <div class="progress-bar" 
                                 style="width: <?php echo ($available_machines / $total_machines) * 100; ?>%;">
                            </div>
                        </div>
                        <!-- Maintenance Alert Badge -->
                        <?php if ($maintenance_alerts_count > 0): ?>
                        <div class="mt-2">
                            <span class="badge bg-secondary" style="font-size: 0.85rem; color: white;">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <?php echo $maintenance_alerts_count; ?> Need<?php echo ($maintenance_alerts_count > 1) ? ' ' : 's '; ?>Maintenance
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($maintenance_stats['currently_under_maintenance'] > 0): ?>
                        <div class="mt-1">
                            <span class="badge bg-secondary" style="font-size: 0.85rem; color: white;">
                                <i class="fas fa-tools me-1"></i>
                                <?php echo $maintenance_stats['currently_under_maintenance']; ?> Under Maintenance
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <span><?php echo $booked_machines; ?> booked</span>
                        <i class="fas fa-cogs" style="color: #6366f1;"></i>
                    </div>
                    <a href="manage_machines.php" class="btn btn-sm btn-outline-primary mt-3">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Maintenance Alert Section -->
        <?php if ($maintenance_alerts_count > 0 && $machines_needing_maintenance && is_object($machines_needing_maintenance) && $machines_needing_maintenance->num_rows > 0): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-12">
                <div class="card" style="box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); background-color: #ffffff; border: none;">
                    <div class="card-header" style="background-color: #ffffff; border-bottom: none;">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2" style="color: #6366f1;"></i>
                            Maintenance Required
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Machine</th>
                                        <th>Type</th>
                                        <th>Usage</th>
                                        <th>Reason</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maintenance_count = 0;
                                    if (is_object($machines_needing_maintenance)) {
                                        mysqli_data_seek($machines_needing_maintenance, 0);
                                        while (($mach = $machines_needing_maintenance->fetch_assoc()) && $maintenance_count < 5): 
                                            $maintenance_count++;
                                            $usage_threshold = 10;
                                            $days_since = isset($mach['days_since_maintenance']) ? $mach['days_since_maintenance'] : null;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($mach['machine_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo ucfirst($mach['machine_type']); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo $mach['usage_count']; ?> / <?php echo $usage_threshold; ?></strong>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($mach['usage_count'] >= $usage_threshold) {
                                                echo 'Usage Threshold Exceeded';
                                            } elseif ($days_since && $days_since >= 180) {
                                                echo '6+ Months Since Maintenance';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="manage_machines.php" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit me-1"></i>Update
                                            </a>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($maintenance_alerts_count > 5): ?>
                        <div class="text-center mt-3">
                            <a href="manage_machines.php" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-list me-1"></i>View All <?php echo $maintenance_alerts_count; ?> Machines
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Bookings Table -->
        <div class="recent-bookings">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5><i class="fas fa-history me-2" style="color: #6366f1;"></i> Recent Bookings</h5>
                        <small class="text-muted">Latest 6 customer reservations</small>
                    </div>
                    <a href="booking_schedules.php" class="btn btn-primary">
                        <i class="fas fa-eye me-1"></i> View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Time Slot</th>
                                    <th>Service Type</th>
                                    <th>Request</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = mysqli_fetch_assoc($recent_bookings)) : ?>
                                    <tr>
                                        <td class="fw-semibold">
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar">
                                                    <i class="fas fa-user-circle"></i>
                                                </div>
                                                <div>
                                                    <?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="d-block"><?php echo !empty($booking['booking_date']) ? date("M j, Y", strtotime($booking['booking_date'])) : 'No date'; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo !empty($booking['time_slot']) ? htmlspecialchars($booking['time_slot']) : 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td class="text-capitalize">
                                            <span class="badge service-badge" style="color: #000000; background-color: #f8f9fa;">
                                                <?php 
                                                // Use service_display from the query
                                                echo isset($booking['service_display']) ? htmlspecialchars($booking['service_display']) : 
                                                    (isset($booking['service_type']) ? htmlspecialchars($booking['service_type']) : 'N/A');
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo !empty($booking['request_service']) ? htmlspecialchars($booking['request_service']) : 'Standard'; ?>
                                        </td>
                                        <td>
                                            <span class="badge status-badge bg-secondary text-white">
                                                <i class="fas fa-circle me-1" style="font-size: 0.6em;"></i>
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Charts Section -->
        <div class="sales-charts mt-5">
            <div class="row g-4">
                <!-- Daily Sales Card -->
                <div class="col-md-4">
                    <div class="card chart-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i> Daily Sales</h5>
                        </div>
                        <div class="card-body">
                            <div class="sales-info">
                                <h3 class="text-dark mb-2">₱<?php echo number_format($sales_growth['today_sales'], 2); ?></h3>
                                <p class="mb-3">
                                    <?php if ($sales_growth['growth'] >= 0): ?>
                                        <span class="text-dark">
                                            <i class="fas fa-arrow-up me-1"></i>
                                            <?php echo $sales_growth['growth']; ?>% increase
                                        </span>
                                    <?php else: ?>
                                        <span class="text-danger">
                                            <i class="fas fa-arrow-down me-1"></i>
                                            <?php echo abs($sales_growth['growth']); ?>% decrease
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="chart-update">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Updated <?php 
                                        $update_minutes = rand(1, 10);
                                        echo $update_minutes . ' minute' . ($update_minutes > 1 ? 's' : '') . ' ago';
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Weekly Sales Chart -->
                <div class="col-md-4">
                    <div class="card chart-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar-week me-2" style="color: #6366f1;"></i> Weekly Sales</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="weeklyChart"></canvas>
                            </div>
                            <div class="chart-info">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Last 7 days performance
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Sales Chart -->
                <div class="col-md-4">
                    <div class="card chart-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2" style="color: #6366f1;"></i> Monthly Sales</h5>
                        </div>
                        <div class="card-body">
                            <div class="monthly-summary">
                                <h3 class="text-dark mb-1">₱<?php echo number_format($monthly_total, 2); ?></h3>
                                <p class="text-muted">Total this month</p>
                            </div>
                            <div class="chart-container">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                            <div class="chart-info">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Current month overview
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Type Sales Chart (Full Width) -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card chart-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-concierge-bell me-2" style="color: #6366f1;"></i> Service Type Sales</h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="chart-container">
                                        <canvas id="serviceChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="service-stats">
                                        <h6 class="text-muted mb-3">Sales Distribution</h6>
                                        <?php 
                                        $total_sales = array_sum($service_sales);
                                        $color_map = [
                                            'Full Service' => 'danger',
                                            'Self Service' => 'primary',
                                            'Fold Service' => 'warning',
                                            'Other Services' => 'info'
                                        ];
                                        
                                        $icon_map = [
                                            'Full Service' => 'fas fa-concierge-bell',
                                            'Self Service' => 'fas fa-user-cog',
                                            'Fold Service' => 'fas fa-tshirt',
                                            'Other Services' => 'fas fa-question-circle'
                                        ];
                                        
                                        foreach ($service_sales as $type => $amount): 
                                            $percentage = $total_sales > 0 ? ($amount / $total_sales) * 100 : 0;
                                            $color = $color_map[$type] ?? 'secondary';
                                            $icon = $icon_map[$type] ?? 'fas fa-circle';
                                        ?>
                                            <div class="stat-item mb-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="service-icon me-2">
                                                        <i class="<?php echo $icon; ?> text-<?php echo $color; ?>"></i>
                                                    </div>
                                                    <div class="d-flex justify-content-between w-100">
                                                        <span class="fw-medium"><?php echo $type; ?></span>
                                                        <span class="fw-bold">₱<?php echo number_format($amount, 2); ?></span>
                                                    </div>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-<?php echo $color; ?>"
                                                        style="width: <?php echo $percentage; ?>%">
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?php echo round($percentage, 1); ?>% of total</small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
        <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    
    <script src="../assets/js/confirmlogout.js"></script>
    <script src="../assets/lib/js/chart.umd.js"></script>
    <script src="../assets/js/charts.js"></script>
    <script>
// Prepare data for charts
const weeklyChartData = {
    labels: <?php echo json_encode(array_keys($weekly_sales)); ?>,
    values: <?php echo json_encode(array_values($weekly_sales)); ?>
};

const monthlyChartData = {
    labels: <?php echo json_encode(array_keys($monthly_sales)); ?>,
    values: <?php echo json_encode(array_values($monthly_sales)); ?>
};

const serviceChartData = {
    labels: <?php echo json_encode(array_keys($service_sales)); ?>,
    values: <?php echo json_encode(array_values($service_sales)); ?>
};

// Initialize charts when document is ready
document.addEventListener('DOMContentLoaded', function() {
    dashboardCharts.initCharts(weeklyChartData, monthlyChartData, serviceChartData);
});

function refreshCharts() {
    
    fetch('/api/sales-data').then(response => response.json())
    .then(data => {
         dashboardCharts.updateWeeklyChart(data.weekly);
        dashboardCharts.updateMonthlyChart(data.monthly);
         dashboardCharts.updateServiceChart(data.service);
 });
}

// Auto refresh notifications every 30 seconds
setInterval(function() {
    // Check if notifications dropdown is not open
    const dropdown = document.getElementById('notificationsDropdown');
    if (dropdown) {
        const isOpen = dropdown.getAttribute('aria-expanded') === 'true';
        
        if (!isOpen) {
            // Refresh page to update notification count
            window.location.reload();
        }
    }
}, 30000);
    </script>
</body>
</html>






