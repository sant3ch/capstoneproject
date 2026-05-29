<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require '../config.php'; // Database connection
require '../includes/sales_charts.php';

// Get filter parameters
$filter_month = $_GET['month'] ?? date('m');
$filter_year = $_GET['year'] ?? date('Y');

// Get filtered stats for the selected period
$period_where = "WHERE MONTH(COALESCE(approved_at, payment_date)) = " . (int)$filter_month . " AND YEAR(COALESCE(approved_at, payment_date)) = " . (int)$filter_year;

// Total Sales for the selected period
$total_sales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(amount), 0) as total FROM gcash_requests WHERE status IN ('approved', 'completed') AND MONTH(COALESCE(approved_at, payment_date)) = " . (int)$filter_month . " AND YEAR(COALESCE(approved_at, payment_date)) = " . (int)$filter_year))['total'] ?? 0;

// Fetch chart datasets from shared sales functions
$daily_sales = getTodaySalesGrowth($conn, $filter_month, $filter_year);
$today_sales = $daily_sales['today_sales'];
$monthly_sales_total = getMonthlyTotalSales($conn, $filter_month, $filter_year);
$monthly_sales = getMonthlySalesData($conn, $filter_month, $filter_year);

// Count transactions from official source (gcash_requests with all payment methods)
$total_transactions = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests $period_where AND status IN ('approved', 'completed') AND payment_method IN ('GCASH', 'Cash on Delivery', 'IN_STORE')"));

// GCASH from official gcash_requests table
$total_gcash = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM gcash_requests $period_where AND payment_method = 'GCASH' AND status IN ('approved', 'completed')"))['count'] ?? 0;
$total_gcash_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(amount), 0) as total FROM gcash_requests $period_where AND payment_method = 'GCASH' AND status IN ('approved', 'completed')"))['total'] ?? 0;

// Cash on Delivery from gcash_requests table
$total_cod = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM gcash_requests $period_where AND payment_method = 'Cash on Delivery' AND status IN ('approved', 'completed')"))['count'] ?? 0;
$total_cod_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(amount), 0) as total FROM gcash_requests $period_where AND payment_method = 'Cash on Delivery' AND status IN ('approved', 'completed')"))['total'] ?? 0;

// In-Store from gcash_requests table
$total_instore = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM gcash_requests $period_where AND payment_method = 'IN_STORE' AND status IN ('approved', 'completed')"))['count'] ?? 0;
$total_instore_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(amount), 0) as total FROM gcash_requests $period_where AND payment_method = 'IN_STORE' AND status IN ('approved', 'completed')"))['total'] ?? 0;

// Cash payments (mapped from In-Store in official records)
$total_cash = $total_instore;
$total_cash_amount = $total_instore_amount;

$weekly_sales = getWeeklySalesData($conn, $filter_month, $filter_year);
$holiday_sales = getHolidaySalesData($conn, $filter_month, $filter_year);
$weekend_sales = getWeekendSalesData($conn, $filter_month, $filter_year);
$service_sales = getServiceTypeSalesData($conn, $filter_month, $filter_year);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link rel="stylesheet" href="../assets/css/manage-reports.css">
    <script src="../assets/lib/js/chart.umd.js"></script>
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
                        <li><a class="nav-link py-1 active" href="payment_requests-management.php"><i class="fas fa-money-bill-wave me-2"></i> Payment Requests</a></li>
                        <li><a class="nav-link py-1" href="claimed_rewards.php"><i class="fas fa-gift me-2"></i> Claimed Rewards</a></li>
                        <li><a class="nav-link py-1" href="admin_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a></li>
                    </ul>
                </li>

                <!-- Reports Menu -->
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle active" data-bs-toggle="collapse" href="#reportsMenu" role="button">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <ul class="collapse show list-unstyled ps-4" id="reportsMenu">
                        <li><a class="nav-link py-1 active" href="reports.php"><i class="fas fa-file-invoice-dollar me-2"></i> Sales Report</a></li>
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
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h1>Sales Reports</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Reports</a></li>
                                        <li class="breadcrumb-item active">Sales Report</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Analyze sales performance and revenue trends</p>
                    </div>
                    <div class="header-action-section">
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <select name="month" class="form-select form-select-sm" style="width: auto;">
                                <?php
                                for ($m = 1; $m <= 12; $m++) {
                                    $monthName = date('F', mktime(0, 0, 0, $m, 1));
                                    $selected = ($m == $filter_month) ? 'selected' : '';
                                    echo "<option value='".sprintf("%02d", $m)."' $selected>$monthName</option>";
                                }
                                ?>
                            </select>
                            <select name="year" class="form-select form-select-sm" style="width: auto;">
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                    $selected = ($y == $filter_year) ? 'selected' : '';
                                    echo "<option value='$y' $selected>$y</option>";
                                }
                                ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <div class="current-date ms-2">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="header-stats">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-money-bill-wave" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3>₱<?php echo number_format($total_sales, 2); ?></h3>
                                <p>Monthly Total Sales</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-sun" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3>₱<?php echo number_format($today_sales, 2); ?></h3>
                                <p><?php echo ($filter_month == date('m') && $filter_year == date('Y')) ? "Today's Sales" : "Sales for " . date('M d, Y', strtotime($daily_sales['target_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-calendar-alt" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3>₱<?php echo number_format($monthly_sales_total, 2); ?></h3>
                                <p>Monthly Sales (<?php echo date('M Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)); ?>)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-receipt" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $total_transactions; ?></h3>
                                <p>Total Transactions</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Charts Section -->
        <div class="row g-4">
            <!-- Daily Sales Card -->
            <div class="col-lg-3">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-sun me-2" style="color: var(--primary-purple);"></i> Daily Sales</h5>
                        <small class="text-muted"><?php echo ($filter_month == date('m') && $filter_year == date('Y')) ? "Today's total" : "Total for " . date('M d, Y', strtotime($daily_sales['target_date'])); ?></small>
                    </div>
                    <div class="card-body">
                        <div class="sales-info">
                            <h3 class="text-dark mb-2">₱<?php echo number_format($daily_sales['today_sales'], 2); ?></h3>
                            <p class="mb-0">
                                <?php if ($daily_sales['growth'] >= 0): ?>
                                    <span class="text-dark">
                                        <i class="fas fa-arrow-up me-1" style="color: black;"></i>
                                        <?php echo $daily_sales['growth']; ?>% vs <?php echo ($filter_month == date('m') && $filter_year == date('Y')) ? "yesterday" : "prev. day"; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-danger">
                                        <i class="fas fa-arrow-down me-1"></i>
                                        <?php echo abs($daily_sales['growth']); ?>% vs <?php echo ($filter_month == date('m') && $filter_year == date('Y')) ? "yesterday" : "prev. day"; ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly Sales Chart -->
            <div class="col-lg-3">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-week me-2" style="color: var(--primary-purple);"></i> Weekly Sales</h5>
                        <small class="text-muted">Last 7 days</small>
                    </div>
                    <div class="card-body">
                        <canvas id="weeklyChart" style="height: 250px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Sales Chart -->
            <div class="col-lg-3">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-alt me-2" style="color: var(--primary-purple);"></i> Monthly Sales</h5>
                        <small class="text-muted"><?php echo date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)); ?> trend</small>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" style="height: 250px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Holiday Sales Chart -->
            <div class="col-lg-3">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-heart me-2" style="color: #DC2626;"></i> Holiday Sales</h5>
                        <small class="text-muted">Holiday vs Regular</small>
                    </div>
                    <div class="card-body">
                        <canvas id="holidayMiniChart" style="height: 250px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Transactions Section -->
            <div class="col-lg-4">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-mobile-alt me-2" style="color: var(--primary-purple);"></i> GCASH Payments</h5>
                        <small class="text-muted">Digital transactions</small>
                    </div>
                    <div class="card-body">
                        <div class="sales-info">
                            <h3 class="mb-2" style="color: black;">₱<?php echo number_format($total_gcash_amount, 2); ?></h3>
                            <p class="text-muted mb-0"><?php echo $total_gcash; ?> transactions</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-money-bill me-2" style="color: var(--primary-purple);"></i> Cash on Delivery</h5>
                        <small class="text-muted">Cash transactions</small>
                    </div>
                    <div class="card-body">
                        <div class="sales-info">
                            <h3 class="mb-2" style="color: black;">₱<?php echo number_format($total_cod_amount, 2); ?></h3>
                            <p class="text-muted mb-0"><?php echo $total_cod; ?> transactions</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-store me-2" style="color: var(--primary-purple);"></i> In-Store Payment</h5>
                        <small class="text-muted">Staff processed payments</small>
                    </div>
                    <div class="card-body">
                        <div class="sales-info">
                            <h3 class="mb-2" style="color: black;">₱<?php echo number_format($total_instore_amount, 2); ?></h3>
                            <p class="text-muted mb-0"><?php echo $total_instore; ?> transactions</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Holiday vs Regular Sales Chart -->
            <div class="col-lg-6">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-heart me-2" style="color: var(--primary-purple);"></i> Holiday vs Regular Sales</h5>
                        <small class="text-muted">All-time comparison</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-7">
                                <canvas id="holidayChart" style="height: 280px;"></canvas>
                            </div>
                            <div class="col-md-5 d-flex align-items-center">
                                <div class="chart-stats w-100">
                                    <?php
                                    $holiday_total = $holiday_sales['Holiday Sales'] ?? 0;
                                    $regular_total = $holiday_sales['Regular Sales'] ?? 0;
                                    $holiday_sum = $holiday_total + $regular_total;
                                    $holiday_pct = $holiday_sum > 0 ? round(($holiday_total / $holiday_sum) * 100, 1) : 0;
                                    $regular_pct = $holiday_sum > 0 ? round(($regular_total / $holiday_sum) * 100, 1) : 0;
                                    ?>
                                    <div class="stat-item mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="fw-semibold">Holiday Sales</span>
                                            <span style="color: black;">₱<?php echo number_format($holiday_total, 2); ?></span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-danger" style="width: <?php echo $holiday_pct; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $holiday_pct; ?>% of total</small>
                                    </div>
                                    <div class="stat-item">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="fw-semibold">Regular Sales</span>
                                            <span style="color: black;">₱<?php echo number_format($regular_total, 2); ?></span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $regular_pct; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $regular_pct; ?>% of total</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekend vs Weekday Sales Chart -->
            <div class="col-lg-6">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-day me-2" style="color: var(--primary-purple);"></i> Weekend vs Weekday Sales</h5>
                        <small class="text-muted">All-time comparison</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-7">
                                <canvas id="weekendChart" style="height: 280px;"></canvas>
                            </div>
                            <div class="col-md-5 d-flex align-items-center">
                                <div class="chart-stats w-100">
                                    <?php
                                    $weekend_total = $weekend_sales['Weekend Sales'] ?? 0;
                                    $weekday_total = $weekend_sales['Weekday Sales'] ?? 0;
                                    $weekend_sum = $weekend_total + $weekday_total;
                                    $weekend_pct = $weekend_sum > 0 ? round(($weekend_total / $weekend_sum) * 100, 1) : 0;
                                    $weekday_pct = $weekend_sum > 0 ? round(($weekday_total / $weekend_sum) * 100, 1) : 0;
                                    ?>
                                    <div class="stat-item mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="fw-semibold">Weekend Sales</span>
                                            <span style="color: black;">₱<?php echo number_format($weekend_total, 2); ?></span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $weekend_pct; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $weekend_pct; ?>% of total</small>
                                    </div>
                                    <div class="stat-item">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="fw-semibold">Weekday Sales</span>
                                            <span style="color: black;">₱<?php echo number_format($weekday_total, 2); ?></span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" style="width: <?php echo $weekday_pct; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $weekday_pct; ?>% of total</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Type Sales Chart -->
            <div class="col-lg-12">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-concierge-bell me-2" style="color: var(--primary-purple);"></i> Service Type Sales</h5>
                        <small class="text-muted">Sales comparison by laundry service category</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="serviceChart" style="height: 300px;"></canvas>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="chart-stats">
                                    <?php
                                    $service_total = array_sum($service_sales);
                                    $service_colors = [
                                        'Full Service' => 'danger',
                                        'Self Service' => 'primary',
                                        'Fold Service' => 'warning',
                                        'Other Services' => 'info'
                                    ];
                                    ?>
                                    <?php if (!empty($service_sales)): ?>
                                        <?php foreach ($service_sales as $service_name => $amount): ?>
                                            <?php
                                            $percentage = $service_total > 0 ? round(($amount / $service_total) * 100, 1) : 0;
                                            $color_class = $service_colors[$service_name] ?? 'secondary';
                                            ?>
                                            <div class="stat-item mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($service_name); ?></span>
                                                <span style="color: black;">₱<?php echo number_format($amount, 2); ?></span>
                                                </div>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-<?php echo $color_class; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?php echo $percentage; ?>% of total</small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No service sales data yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    
    <script>
        // Weekly Sales Bar Chart
        const weeklyData = {
            labels: <?php echo json_encode(array_keys($weekly_sales)); ?>,
            datasets: [{
                label: '₱ Sales',
                data: <?php echo json_encode(array_values($weekly_sales)); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        };

        new Chart(document.getElementById('weeklyChart'), {
            type: 'bar',
            data: weeklyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { 
                        mode: 'index', 
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString('en-US', {minimumFractionDigits: 0});
                            }
                        }
                    }
                }
            }
        });

        // Monthly Sales Line Chart
        const monthlyData = {
            labels: <?php echo json_encode(array_keys($monthly_sales)); ?>,
            datasets: [{
                label: '₱ Sales',
                data: <?php echo json_encode(array_values($monthly_sales)); ?>,
                fill: true,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                tension: 0.4
            }]
        };

        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: monthlyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { 
                        mode: 'nearest', 
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString('en-US', {minimumFractionDigits: 0});
                            }
                        }
                    }
                }
            }
        });

        // Holiday vs Regular Sales Doughnut Chart
        const holidayData = {
            labels: <?php echo json_encode(array_keys($holiday_sales)); ?>,
            datasets: [{
                label: '₱ Sales',
                data: <?php echo json_encode(array_values($holiday_sales)); ?>,
                backgroundColor: ['#DC2626', '#10B981'],
                borderColor: ['#DC2626', '#10B981'],
                borderWidth: 2
            }]
        };

        // Holiday vs Regular Sales Doughnut Chart (Mini)
        new Chart(document.getElementById('holidayMiniChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($holiday_sales)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($holiday_sales)); ?>,
                    backgroundColor: ['#DC2626', '#10B981'],
                    borderColor: ['#DC2626', '#10B981'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ₱${value.toLocaleString('en-US', {minimumFractionDigits: 2})} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Holiday vs Regular Sales Doughnut Chart (Large with Stats)
        new Chart(document.getElementById('holidayChart'), {
            type: 'doughnut',
            data: holidayData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 15,
                            color: '#333',
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ₱${value.toLocaleString('en-US', {minimumFractionDigits: 2})} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Weekend vs Weekday Sales Doughnut Chart
        const weekendData = {
            labels: <?php echo json_encode(array_keys($weekend_sales)); ?>,
            datasets: [{
                label: '₱ Sales',
                data: <?php echo json_encode(array_values($weekend_sales)); ?>,
                backgroundColor: ['#F59E0B', '#3B82F6'],
                borderColor: ['#F59E0B', '#3B82F6'],
                borderWidth: 2
            }]
        };

        new Chart(document.getElementById('weekendChart'), {
            type: 'doughnut',
            data: weekendData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 15,
                            color: '#333',
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ₱${value.toLocaleString('en-US', {minimumFractionDigits: 2})} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Service Type Sales Pie Chart
        const serviceData = {
            labels: <?php echo json_encode(array_keys($service_sales)); ?>,
            datasets: [{
                label: '₱ Sales',
                data: <?php echo json_encode(array_values($service_sales)); ?>,
                backgroundColor: ['#EF4444', '#3B82F6', '#F59E0B', '#6366F1'],
                borderColor: ['#EF4444', '#3B82F6', '#F59E0B', '#6366F1'],
                borderWidth: 1
            }]
        };

        new Chart(document.getElementById('serviceChart'), {
            type: 'pie',
            data: serviceData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 20,
                            color: '#333',
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ₱${value.toLocaleString('en-US', {minimumFractionDigits: 2})} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>





