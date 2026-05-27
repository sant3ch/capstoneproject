<?php
// admin_notifications.php
session_start();
require '../config.php';

// Check if admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include admin notifications functions
require_once '../includes/admin-notifications.php';

// Handle mark as read
if (isset($_GET['mark_read'])) {
    markAdminNotificationAsRead($_GET['mark_read']);
    header("Location: admin_notifications.php");
    exit();
}

if (isset($_GET['mark_all_read'])) {
    markAllAdminNotificationsAsRead();
    header("Location: admin_notifications.php");
    exit();
}

// Get all admin notifications
$all_notifications = getAdminNotifications(100);

// Separate notifications by type
$gcash_notifications = [];
$inventory_notifications = [];
$booking_notifications = [];
$other_notifications = [];

if ($all_notifications->num_rows > 0) {
    while($notification = $all_notifications->fetch_assoc()) {
        $notification_type = $notification['type'] ?? '';
        
        if (strpos($notification_type, 'gcash') !== false) {
            $gcash_notifications[] = $notification;
        } elseif (strpos($notification_type, 'inventory') !== false) {
            $inventory_notifications[] = $notification;
        } elseif (strpos($notification_type, 'booking') !== false) {
            $booking_notifications[] = $notification;
        } else {
            $other_notifications[] = $notification;
        }
    }
}

// Get inventory low stock items from inventory table
$inventory_query = "SELECT id, item_name, stock_quantity, price FROM inventory WHERE stock_quantity < 5 ORDER BY stock_quantity ASC";
$inventory_result = mysqli_query($conn, $inventory_query);
$lowStockItems = [];

if ($inventory_result && mysqli_num_rows($inventory_result) > 0) {
    while ($row = mysqli_fetch_assoc($inventory_result)) {
        $lowStockItems[] = $row;
    }
}

// Calculate counts
$unread_count = getAdminUnreadCount();
$inventory_alert_count = count($lowStockItems);
$pending_gcash = 0;
$approved_gcash = 0;
$rejected_gcash = 0;
$total_amount_gcash = 0;

foreach ($gcash_notifications as $notification) {
    $messageData = json_decode($notification['message'], true);
    if ($notification['type'] == 'gcash_request') {
        $pending_gcash++;
    } elseif ($notification['type'] == 'gcash_approved') {
        $approved_gcash++;
        if (!empty($messageData['amount'])) {
            $total_amount_gcash += $messageData['amount'];
        }
    } elseif ($notification['type'] == 'gcash_rejected') {
        $rejected_gcash++;
    }
}

// Count pending Cash on Delivery payments
$pending_cod = 0;
$cod_query = "SELECT COUNT(*) as count FROM gcash_requests WHERE payment_method = 'Cash on Delivery' AND status = 'pending'";
$cod_result = mysqli_query($conn, $cod_query);
if ($cod_result) {
    $cod_row = mysqli_fetch_assoc($cod_result);
    $pending_cod = $cod_row['count'];
}

$total_alerts = $unread_count + $inventory_alert_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/manage-gcash.css">
    <style>
        :root {
            --primary-purple: #6366f1;
            --purple-light: #c7d2fe;
            --purple-dark: #4338ca;
            --purple-extra-light: rgba(99, 102, 241, 0.05);
            --green: #4caf50;
            --green-dark: #388e3c;
            --orange: #ff9800;
            --orange-dark: #f57c00;
            --red: #f44336;
            --blue: #2196f3;
            --blue-dark: #1976d2;
            --gray-50: #fafafa;
            --gray-100: #f5f5f5;
            --gray-200: #eeeeee;
            --gray-500: #9e9e9e;
            --gray-600: #757575;
            --sidebar-bg: linear-gradient(180deg, var(--primary-purple) 0%, var(--purple-dark) 100%);
        }

        body {
            background-color: var(--gray-50);
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar - Material Design Style */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            box-shadow: 0 16px 38px -12px rgba(0, 0, 0, 0.56), 
                        0 4px 25px 0px rgba(0, 0, 0, 0.12), 
                        0 8px 10px -5px rgba(0, 0, 0, 0.2);
            padding: 0;
            z-index: 1000;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 1rem;
        }

        .sidebar-header h4 {
            color: white;
            font-weight: 400;
            font-size: 1.5rem;
            margin: 0 0 0.5rem 0;
            letter-spacing: 0.5px;
        }

        .sidebar-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            font-weight: 300;
        }

        .sidebar .nav {
            padding: 0 0.5rem;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 4px;
            font-weight: 400;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            border-left: 3px solid transparent;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
            border-left-color: rgba(255, 255, 255, 0.3);
        }

        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: white;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: auto;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: var(--gray-50);
            min-height: 100vh;
        }

        .page-header-combined {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .header-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--primary-purple), var(--purple-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .header-icon i {
            font-size: 1.5rem;
            color: white;
        }

        h1 {
            color: var(--gray-800);
            font-size: 2rem;
            font-weight: 400;
            margin: 0 0 0.5rem 0;
        }

        .breadcrumb {
            margin: 0;
            font-size: 0.85rem;
        }

        .header-subtitle {
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon.bg-primary { 
            background: linear-gradient(135deg, var(--primary-purple), var(--purple-dark)); 
        }

        .stat-icon.bg-warning { 
            background: linear-gradient(135deg, var(--orange), var(--orange-dark)); 
        }

        .stat-icon.bg-info { 
            background: linear-gradient(135deg, var(--blue), var(--blue-dark)); 
        }

        .stat-icon.bg-success { 
            background: linear-gradient(135deg, var(--green), var(--green-dark)); 
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: white;
        }

        .stat-content h3 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 500;
            color: var(--gray-800);
        }

        .stat-content p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--gray-600);
            font-weight: 400;
        }

        /* Search Bar */
        .input-group {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border-radius: 10px;
            overflow: hidden;
        }

        .input-group-text {
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-500);
        }

        .form-control {
            border: 1px solid var(--gray-200);
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .section-card {
            margin-bottom: 2rem;
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            background: white;
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, var(--gray-50), var(--gray-100));
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-header > div:first-child {
            flex: 1;
            min-width: 300px;
        }

        .section-header h5 {
            color: var(--gray-800);
            font-weight: 500;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .section-header small {
            display: block;
            margin-top: 0.5rem;
            color: var(--gray-600);
            font-weight: 400;
            font-size: 0.85rem;
        }

        .section-header .btn {
            flex-shrink: 0;
        }

        .badge-notification-type {
            background: var(--primary-purple);
            color: white;
            font-size: 0.7rem;
            padding: 0.35rem 0.6rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .btn-purple {
            background: var(--primary-purple);
            color: white;
            border: none;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-purple:hover {
            background: var(--purple-dark);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .notification-item {
            transition: all 0.3s;
            border-left: 3px solid transparent;
            background: white;
        }

        .notification-item.unread {
            background-color: var(--purple-extra-light);
            border-left-color: var(--primary-purple);
        }

        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .notification-icon.gcash_request {
            background: linear-gradient(135deg, var(--primary-purple), var(--purple-dark));
            color: white;
        }

        .notification-icon.gcash_approved {
            background: linear-gradient(135deg, var(--primary-purple), var(--purple-dark));
            color: white;
        }

        .notification-icon.gcash_rejected {
            background: linear-gradient(135deg, var(--red), #c62828);
            color: white;
        }

        .notification-icon.inventory_low {
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            color: white;
        }

        .notification-icon.booking {
            background: linear-gradient(135deg, var(--primary-purple), var(--purple-dark));
            color: white;
        }

        .notification-icon.system {
            background: linear-gradient(135deg, var(--primary-purple), var(--purple-dark));
            color: white;
        }

        .notification-item h6 {
            color: var(--gray-800);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .notification-item p {
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .empty-section {
            text-align: center;
            padding: 3.5rem 2rem;
            color: var(--gray-600);
        }

        .empty-section i {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            opacity: 0.3;
            color: var(--primary-purple);
        }

        .empty-section h5 {
            color: var(--gray-800);
            font-weight: 500;
        }

        .amount-badge {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-purple);
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
        }

        .reference-badge {
            background: var(--gray-100);
            color: var(--gray-700);
            font-family: 'Courier New', monospace;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            display: inline-block;
            font-weight: 500;
        }

        .list-group-item {
            border-color: var(--gray-200);
        }

        .list-group-item.notification-item {
            border-bottom: 1px solid var(--gray-100);
            padding: 1.25rem !important;
        }

        .list-group-item.notification-item:last-child {
            border-bottom: none;
        }

        .badge {
            padding: 0.4rem 0.6rem;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .btn-outline-purple {
            color: var(--primary-purple);
            border-color: var(--primary-purple);
        }

        .btn-outline-purple:hover {
            background: var(--primary-purple);
            border-color: var(--primary-purple);
            color: white;
        }

        .card-body {
            padding: 0;
        }

        .card-body > .row {
            margin: 0;
        }

        .card-body .card {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            margin: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background: var(--gray-50);
            border-color: var(--gray-200);
            border-radius: 8px 8px 0 0 !important;
            padding: 1rem;
            font-weight: 500;
        }

        .card-header h6 {
            color: var(--gray-800);
            font-weight: 500;
            margin: 0;
        }

        .card-body .card-body {
            padding: 0;
        }

        .card.border-warning {
            border: 2px solid rgba(255, 152, 0, 0.3) !important;
            border-radius: 8px;
        }

        .card.border-warning .card-header {
            background: rgba(255, 152, 0, 0.05);
            border-bottom: 2px solid rgba(255, 152, 0, 0.2);
        }

        .view-details-btn {
            transition: all 0.3s;
        }

        .view-details-btn:hover {
            transform: translateY(-1px);
        }

        .header-action-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .current-date {
            display: flex;
            align-items: center;
        }

        .current-date .badge {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .pending-alert {
            display: flex;
            align-items: center;
        }

        .pending-alert .badge {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }
            
            .main-content {
                margin-left: 60px;
                padding: 1rem;
            }
            
            .page-header-combined {
                padding: 1rem;
            }
            
            .header-icon {
                width: 40px;
                height: 40px;
                margin-right: 0.75rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar d-flex flex-column justify-content-between">
        <div>
            <div class="sidebar-header">
                <h4><i class="fas fa-cogs me-2"></i> Admin Panel</h4>
                <small class="sidebar-subtitle">Jorish Express Laundry</small>
            </div>
            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link" href="admin_home.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
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
                        <li><a class="nav-link py-1" href="payment_requests-management.php"><i class="fas fa-money-bill-wave me-2"></i> Payment Requests</a></li>
                        <li><a class="nav-link py-1" href="claimed_rewards.php"><i class="fas fa-gift me-2"></i> Claimed Rewards</a></li>
                        <li><a class="nav-link py-1 active" href="admin_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a></li>
                    </ul>
                </li>
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
                                <i class="fas fa-bell"></i>
                            </div>
                            <div>
                                <h1>Admin Notifications</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Management</a></li>
                                        <li class="breadcrumb-item active">All Notifications</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Review and manage all system notifications and alerts</p>
                    </div>
                    <div class="header-action-section">
                        <div class="d-flex align-items-center gap-3">
                            <div class="current-date">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                            
                            <?php if ($pending_gcash > 0): ?>
                            <div class="pending-alert">
                                <span class="badge bg-warning">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <?php echo $pending_gcash; ?> Pending GCASH
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($unread_count > 0): ?>
                            <a href="admin_notifications.php?mark_all_read=1" class="btn btn-sm btn-primary">
                                <i class="fas fa-check-double me-1"></i> Mark All as Read
                            </a>
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
                                <i class="fas fa-bell" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $total_alerts; ?></h3>
                                <p>Total Alerts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-money-bill-wave" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $pending_gcash + $pending_cod; ?></h3>
                                <p>Pending Payments</p>
                                <small style="font-size: 0.7rem; color: var(--gray-600);">GCash: <?php echo $pending_gcash; ?> | COD: <?php echo $pending_cod; ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-exclamation-triangle" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $inventory_alert_count; ?></h3>
                                <p>Inventory Alerts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-check-circle" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3>₱<?php echo number_format($total_amount_gcash, 2); ?></h3>
                                <p>Approved Amount</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="mb-4">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" placeholder="Search notifications..." id="searchInput">
            </div>
        </div>

        <!-- Payment Requests Section (GCash & Cash on Delivery) -->
        <div class="card section-card">
            <div class="section-header">
                <div>
                    <h5 class="card-title fw-bold mb-0">
                        <i class="fas fa-money-bill-wave me-2" style="color: var(--primary-purple);"></i> Payment Requests
                    </h5>
                    <small class="text-muted mt-2" style="display: block; color: var(--gray-600); font-weight: 400;">GCash verification and Cash on Delivery payment requests from customers</small>
                </div>
                <a href="payment_requests-management.php" class="btn btn-sm btn-purple ms-auto">
                    <i class="fas fa-external-link-alt me-1"></i> Manage Requests
                </a>
            </div>
            <div class="card-body p-0">
                <?php 
                    // Fetch Cash on Delivery requests
                    $cash_requests = [];
                    $cash_query = "SELECT id, booking_id, user_id, amount, status, requested_at FROM gcash_requests WHERE payment_method = 'Cash on Delivery' ORDER BY requested_at DESC LIMIT 50";
                    $cash_result = mysqli_query($conn, $cash_query);
                    if ($cash_result && mysqli_num_rows($cash_result) > 0) {
                        while ($row = mysqli_fetch_assoc($cash_result)) {
                            $cash_requests[] = $row;
                        }
                    }
                    
                    // Get user info for cash requests
                    $user_info_map = [];
                    if (!empty($cash_requests)) {
                        $user_ids = array_unique(array_column($cash_requests, 'user_id'));
                        if (!empty($user_ids)) {
                            $user_ids_str = implode(',', $user_ids);
                            $user_query = "SELECT id, first_name, last_name FROM users WHERE id IN ($user_ids_str)";
                            $user_result = mysqli_query($conn, $user_query);
                            if ($user_result) {
                                while ($user = mysqli_fetch_assoc($user_result)) {
                                    $user_info_map[$user['id']] = $user['first_name'] . ' ' . $user['last_name'];
                                }
                            }
                        }
                    }
                    
                    $total_payment_requests = count($gcash_notifications) + count($cash_requests);
                ?>
                <?php if (!empty($gcash_notifications) || !empty($cash_requests)): ?>
                <div class="list-group list-group-flush">
                    <!-- GCash Notifications -->
                    <?php foreach ($gcash_notifications as $notification): 
                        $messageData = json_decode($notification['message'], true);
                        $isUnread = $notification['status'] == 'unread';
                        $iconClass = $notification['type'] ?? 'system';
                    ?>
                        <div class="list-group-item py-3 px-4 notification-item <?php echo $isUnread ? 'unread' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="notification-icon me-3 <?php echo $iconClass; ?>">
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
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <small class="badge bg-info text-white mt-1" style="font-size: 0.7rem;">GCash</small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block mb-2"><?php echo date("M d, Y h:i A", strtotime($notification['created_at'])); ?></small>
                                            <?php if ($notification['type'] == 'gcash_request' && !empty($messageData['request_id'])): ?>
                                            <a href="payment_requests-management.php" 
                                               class="btn btn-sm btn-outline-purple view-details-btn">
                                                <i class="fas fa-external-link-alt me-1"></i> View Details
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="mb-2"><?php echo htmlspecialchars($messageData['message'] ?? $notification['message']); ?></p>
                                    
                                    <?php if (!empty($messageData['request_id']) || !empty($messageData['amount'])): ?>
                                    <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                        <?php if (!empty($messageData['request_id'])): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-hashtag me-1"></i>
                                            Request #<?php echo $messageData['request_id']; ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($messageData['customer_name'])): ?>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($messageData['customer_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($messageData['amount'])): ?>
                                        <span class="amount-badge">
                                            <i class="fas fa-money-bill me-1"></i>
                                            ₱<?php echo number_format($messageData['amount'], 2); ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($messageData['reference_number'])): ?>
                                        <span class="reference-badge">
                                            <i class="fas fa-qrcode me-1"></i>
                                            Ref: <?php echo htmlspecialchars($messageData['reference_number']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($messageData['booking_id'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Booking ID: #<?php echo $messageData['booking_id']; ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-3 d-flex flex-column align-items-center">
                                    <?php if ($isUnread): ?>
                                    <span class="badge bg-primary rounded-pill mb-2">New</span>
                                    <?php endif; ?>
                                    <a href="admin_notifications.php?mark_read=<?php echo $notification['id']; ?>" 
                                       class="btn btn-sm btn-light" title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Cash on Delivery Requests -->
                    <?php foreach ($cash_requests as $cash_request): 
                        $customer_name = $user_info_map[$cash_request['user_id']] ?? 'Unknown Customer';
                        $status_lower = strtolower($cash_request['status']);
                    ?>
                        <div class="list-group-item py-3 px-4 notification-item">
                            <div class="d-flex align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="mb-0" style="color: #ffffff;">Cash on Delivery Order</h6>
                                            <small class="badge text-white mt-1" style="font-size: 0.7rem; background-color: var(--primary-purple);">COD</small>
                                        </div>
                                        <small class="text-muted"><?php echo date("M d, Y h:i A", strtotime($cash_request['requested_at'])); ?></small>
                                    </div>
                                    <p class="mb-2" style="color: var(--gray-600);">
                                        <i class="fas fa-hashtag me-1"></i>Request #<?php echo $cash_request['id']; ?> • 
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($customer_name); ?> • 
                                        <i class="fas fa-money-bill me-1"></i>₱<?php echo number_format($cash_request['amount'], 2); ?> • 
                                        <span style="color: var(--primary-purple); font-weight: 600;"><?php echo ucfirst($cash_request['status']); ?></span>
                                    </p>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Booking ID: #<?php echo $cash_request['booking_id']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-section">
                    <i class="fas fa-money-bill-wave text-muted"></i>
                    <h5>No Payment Requests</h5>
                    <p class="mb-0">You don't have any GCash or Cash on Delivery payment requests at the moment</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Inventory Alerts Section -->
        <div class="card section-card">
            <div class="section-header">
                <div>
                    <h5 class="card-title fw-bold mb-0">
                        <i class="fas fa-exclamation-triangle me-2" style="color: var(--primary-purple);"></i> Inventory Alerts
                    </h5>
                    <small class="text-muted mt-2" style="display: block; color: var(--gray-600); font-weight: 400;">Low stock items requiring attention</small>
                </div>
                <a href="manage_inventory.php" class="btn btn-sm ms-auto" style="background: var(--primary-purple); color: white;">
                    <i class="fas fa-box me-1"></i> Manage Inventory
                </a>
            </div>
            <div class="card-body p-5">
                <?php if (!empty($lowStockItems) || !empty($inventory_notifications)): ?>
                <div class="row g-4 justify-content-center">
                    <!-- Current Low Stock Items -->
                    <div class="col-md-6">
                        <div class="card border h-100" style="border-color: var(--primary-purple); border-width: 2px;">
                            <div class="card-header" style="background: rgba(99, 102, 241, 0.08); border-bottom: 2px solid rgba(99, 102, 241, 0.2);">
                                <h6 class="mb-0" style="color: black;"><i class="fas fa-box me-1" style="color: #6366f1;"></i> Current Low Stock Items</h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($lowStockItems)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($lowStockItems as $item): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                    <small class="text-muted d-block">
                                                        <?php 
                                                        // Get price, fallback based on item name detection if needed
                                                        $display_price = $item['price'] ?? null;
                                                        if ($display_price === null) {
                                                            $item_name_lower = strtolower($item['item_name']);
                                                            if (strpos($item_name_lower, 'detergent') !== false || strpos($item_name_lower, 'soap') !== false) {
                                                                $display_price = 16.00;
                                                            } elseif (strpos($item_name_lower, 'fabric') !== false || 
                                                                    strpos($item_name_lower, 'softener') !== false || 
                                                                    strpos($item_name_lower, 'conditioner') !== false) {
                                                                $display_price = 11.00;
                                                            } else {
                                                                $display_price = 16.00;
                                                            }
                                                        }
                                                        ?>
                                                        ₱<?php echo number_format($display_price, 2); ?> per unit
                                                    </small>
                                                </div>
                                                <div>
                                                    <?php if ($item['stock_quantity'] <= 0): ?>
                                                        <span class="badge" style="background: #757575; color: white;">Out of Stock</span>
                                                    <?php elseif ($item['stock_quantity'] < 3): ?>
                                                        <span class="badge" style="background: #757575; color: white;"><?php echo intval($item['stock_quantity']); ?> left</span>
                                                    <?php else: ?>
                                                        <span class="badge" style="background: #757575; color: white;"><?php echo intval($item['stock_quantity']); ?> left</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>   
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="p-4 text-center">
                                    <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                    <p class="mb-0 text-success">All inventory levels are good</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Inventory Notifications History -->
                    <div class="col-md-6">
                        <div class="card h-100" style="border: 2px solid rgba(99, 102, 241, 0.2);">
                            <div class="card-header" style="background: rgba(99, 102, 241, 0.08); border-bottom: 2px solid rgba(99, 102, 241, 0.2);">
                                <h6 class="mb-0" style="color: black;"><i class="fas fa-history me-1" style="color: #6366f1;"></i> Recent Inventory Alerts</h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($inventory_notifications)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($inventory_notifications as $notification): 
                                            $messageData = json_decode($notification['message'], true);
                                            $isUnread = $notification['status'] == 'unread';
                                        ?>
                                            <div class="list-group-item notification-item <?php echo $isUnread ? 'unread' : ''; ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="notification-icon me-2 inventory_low" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <small class="d-block"><?php echo htmlspecialchars($messageData['message'] ?? $notification['message']); ?></small>
                                                                <small class="text-muted"><?php echo date("M d, h:i A", strtotime($notification['created_at'])); ?></small>
                                                            </div>
                                                            <?php if ($isUnread): ?>
                                                            <a href="admin_notifications.php?mark_read=<?php echo $notification['id']; ?>" 
                                                               class="btn btn-sm btn-light ms-2" title="Mark as read">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 text-center">
                                        <p class="text-muted mb-0">No recent inventory notifications</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-section">
                    <i class="fas fa-check-circle fa-3x mb-3" style="color: var(--primary-purple); opacity: 0.3;"></i>
                    <h5 style="color: black;">All inventory levels are healthy!</h5>
                    <p class="text-muted mb-0">No low-stock items detected. All inventory items are above minimum threshold.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Booking Notifications Section -->
        <?php if (!empty($booking_notifications)): ?>
        <div class="card section-card">
            <div class="section-header">
                <div>
                    <h5 class="card-title fw-bold mb-0">
                        <i class="fas fa-calendar-check me-2" style="color: var(--blue);"></i> Booking Notifications
                        <span class="badge-notification-type" style="background: var(--blue);"><?php echo count($booking_notifications); ?></span>
                    </h5>
                    <small class="text-muted mt-2" style="display: block; color: var(--gray-600); font-weight: 400;">New bookings and status changes</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($booking_notifications as $notification): 
                        $messageData = json_decode($notification['message'], true);
                        $isUnread = $notification['status'] == 'unread';
                    ?>
                        <div class="list-group-item py-3 px-4 notification-item <?php echo $isUnread ? 'unread' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="notification-icon me-3 booking">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                        <small class="text-muted"><?php echo date("M d, Y h:i A", strtotime($notification['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-2"><?php echo htmlspecialchars($messageData['message'] ?? $notification['message']); ?></p>
                                    
                                    <?php if (!empty($messageData['booking_id'])): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-info">
                                            <i class="fas fa-hashtag me-1"></i>
                                            Booking #<?php echo $messageData['booking_id']; ?>
                                        </span>
                                        <?php if (!empty($messageData['total_amount'])): ?>
                                        <span class="amount-badge ms-2">
                                            ₱<?php echo number_format($messageData['total_amount'], 2); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-3 d-flex flex-column align-items-center">
                                    <?php if ($isUnread): ?>
                                    <span class="badge bg-primary rounded-pill mb-2">New</span>
                                    <?php endif; ?>
                                    <a href="admin_notifications.php?mark_read=<?php echo $notification['id']; ?>" 
                                       class="btn btn-sm btn-light" title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Other Notifications Section -->
        <?php if (!empty($other_notifications)): ?>
        <div class="card section-card">
            <div class="section-header">
                <div>
                    <h5 class="card-title fw-bold mb-0">
                        <i class="fas fa-bell me-2" style="color: #6366f1;"></i> Other Notifications
                    </h5>
                    <small class="text-muted mt-2" style="display: block; color: var(--gray-600); font-weight: 400;">System alerts and other notifications</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($other_notifications as $notification): 
                        $messageData = json_decode($notification['message'], true);
                        $isUnread = $notification['status'] == 'unread';
                    ?>
                        <div class="list-group-item py-3 px-4 notification-item <?php echo $isUnread ? 'unread' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="notification-icon me-3 system">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                        <small class="text-muted"><?php echo date("M d, Y h:i A", strtotime($notification['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($messageData['message'] ?? $notification['message']); ?></p>
                                </div>
                                <div class="ms-3 d-flex flex-column align-items-center">
                                    <?php if ($isUnread): ?>
                                    <span class="badge bg-primary rounded-pill mb-2">New</span>
                                    <?php endif; ?>
                                    <a href="admin_notifications.php?mark_read=<?php echo $notification['id']; ?>" 
                                       class="btn btn-sm btn-light" title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
        
        // Search functionality for all notifications
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchValue = this.value.toLowerCase();
                const notificationItems = document.querySelectorAll('.list-group-item.notification-item');
                let visibleCount = 0;
                
                notificationItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchValue)) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Show/hide sections based on visible items
                document.querySelectorAll('.section-card').forEach(section => {
                    const visibleItems = section.querySelectorAll('.list-group-item.notification-item[style!="display: none;"]');
                    const emptySection = section.querySelector('.empty-section');
                    
                    if (visibleItems.length === 0 && !emptySection) {
                        section.style.display = 'none';
                    } else if (visibleItems.length > 0) {
                        section.style.display = '';
                    }
                });
            });
        }
    </script>
</body>
</html>





