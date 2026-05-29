<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once '../includes/auth-check-admin.php';

// Get counts for stats
// Get counts for stats - using a combined unique list by booking_id where possible to avoid duplicates
$total_transactions_query = "
    SELECT COUNT(*) as count FROM (
        SELECT booking_id FROM transactions 
        UNION 
        SELECT booking_id FROM gcash_requests WHERE status IN ('approved', 'completed')
    ) as combined_count";
$total_transactions = mysqli_fetch_assoc(mysqli_query($conn, $total_transactions_query))['count'] ?? 0;

$today_transactions_query = "
    SELECT COUNT(*) as count FROM (
        SELECT t.booking_id FROM transactions t 
        LEFT JOIN bookings b ON t.booking_id = b.id 
        WHERE DATE(COALESCE(t.transaction_date, b.process_completed_at, b.picked_up_at)) = CURDATE()
        UNION
        SELECT id FROM gcash_requests 
        WHERE status IN ('approved', 'completed') AND DATE(COALESCE(payment_date, approved_at, requested_at)) = CURDATE()
    ) as combined_today";
$today_transactions = mysqli_fetch_assoc(mysqli_query($conn, $today_transactions_query))['count'] ?? 0;

// Cash from transactions table (only if NOT in gcash_requests to avoid double counting if any)
// Cash payments are handled via IN_STORE in gcash_requests now
$total_cash = $instore_amount ?? 0;

// GCASH from gcash_requests (official)
$gcash_query = "SELECT SUM(amount) as total, COUNT(*) as count FROM gcash_requests WHERE payment_method = 'GCASH' AND status IN ('approved', 'completed')";
$gcash_data = mysqli_fetch_assoc(mysqli_query($conn, $gcash_query));
$gcash_amount = $gcash_data['total'] ?? 0;
$gcash_count = $gcash_data['count'] ?? 0;

// COD from gcash_requests table
$cod_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count, SUM(amount) as total FROM gcash_requests WHERE payment_method = 'Cash on Delivery' AND status IN ('approved', 'completed')"));
$cod_count = $cod_data['count'] ?? 0;
$cod_amount = $cod_data['total'] ?? 0;

// In-Store from gcash_requests table
$instore_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count, SUM(amount) as total FROM gcash_requests WHERE payment_method = 'IN_STORE' AND status IN ('approved', 'completed')"));
$instore_count = $instore_data['count'] ?? 0;
$instore_amount = $instore_data['total'] ?? 0;

// Get all transactions from official source (gcash_requests)
$transactions = mysqli_query($conn, 
    "SELECT gr.id, CONCAT(u.first_name, ' ', u.last_name) as customer_name, b.service_type, 
            gr.amount as total_amount, COALESCE(gr.payment_date, gr.approved_at, gr.requested_at) as transaction_date,
            'payment_request' as source, gr.payment_method as actual_payment_method,
            gr.is_holiday, gr.holiday_name
     FROM gcash_requests gr
     JOIN bookings b ON gr.booking_id = b.id
     LEFT JOIN users u ON gr.user_id = u.id
     WHERE gr.status IN ('approved', 'completed')
     ORDER BY transaction_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Report - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link rel="stylesheet" href="../assets/css/manage-transactions.css">
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
                        <li><a class="nav-link py-1" href="reports.php"><i class="fas fa-file-invoice-dollar me-2"></i> Sales Report</a></li>
                        <li><a class="nav-link py-1 active" href="transaction_report.php"><i class="fas fa-exchange-alt me-2"></i> Transaction Report</a></li>
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
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div>
                                <h1>Transaction Report</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Reports</a></li>
                                        <li class="breadcrumb-item active">Transaction Report</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">View and manage all financial transactions in the system</p>
                    </div>
                    <div class="header-action-section">
                        <div class="d-flex align-items-center gap-3">
                            <div class="current-date">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="header-stats">
                <div class="row g-3">
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
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-calendar-day" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $today_transactions; ?></h3>
                                <p>Today's Transactions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-money-bill-wave" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3>₱<?php echo number_format($total_cash, 2); ?></h3>
                                <p>Total Cash</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-credit-card" style="color: white;"></i>
                            </div>
                            <div class="stat-content">
                                <h3>₱<?php echo number_format($gcash_amount + $cod_amount + $instore_amount, 2); ?></h3>
                                <p>Payments</p>
                                <small style="font-size: 0.7rem; color: var(--gray-600);">GCash: <?php echo $gcash_count; ?> | COD: <?php echo $cod_count; ?> | In-Store: <?php echo $instore_count; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table Card -->
        <div class="card transactions-table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5><i class="fas fa-list me-2"></i> Transaction History</h5>
                    <small class="text-muted">View all payment transactions and print receipts</small>
                </div>
                <div class="table-actions">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control form-control-sm" placeholder="Search transactions..." id="searchInput">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="transactionTable">
                        <thead>
                            <tr>
                                <th width="22%">Customer Name</th>
                                <th width="18%">Transaction Date</th>
                                <th width="15%">Amount</th>
                                <th width="17%">Service Type</th>
                                <th width="10%">Payment Method</th>
                                <th width="10%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($transaction = mysqli_fetch_assoc($transactions)) : ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="customer-icon me-3">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <strong class="d-block"><?php echo htmlspecialchars($transaction['customer_name']); ?></strong>
                                                    <?php if ($transaction['is_holiday']): ?>
                                                        <span class="badge bg-danger" style="font-size: 0.65rem;" title="<?php echo htmlspecialchars($transaction['holiday_name']); ?>">
                                                            <i class="fas fa-holly-berry me-1"></i>Holiday
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">Transaction ID: #<?php echo $transaction['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="d-block">
                                            <i class="fas fa-calendar me-1 text-muted"></i>
                                            <?php echo ($transaction['transaction_date']) ? date("M d, Y", strtotime($transaction['transaction_date'])) : 'N/A'; ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo ($transaction['transaction_date']) ? date("g:i A", strtotime($transaction['transaction_date'])) : ''; ?>
                                        </small>
                                    </td>
                                    <td>
                                        ₱<?php echo number_format($transaction['total_amount'], 2); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($transaction['service_type']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $paymentMethod = $transaction['actual_payment_method'];
                                        if ($paymentMethod === 'Cash') {
                                            $methodLabel = 'Cash';
                                        } elseif ($paymentMethod === 'GCASH') {
                                            $methodLabel = 'GCASH';
                                        } elseif ($paymentMethod === 'Cash on Delivery') {
                                            $methodLabel = 'COD';
                                        } elseif ($paymentMethod === 'IN_STORE') {
                                            $methodLabel = 'In-Store';
                                        } else {
                                            $methodLabel = $paymentMethod ?? 'Unknown';
                                        }
                                        $badgeClass = 'bg-primary';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $methodLabel; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <a href="print_receipt.php?id=<?php echo $transaction['id']; ?>&source=<?php echo $transaction['source']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-3" title="Print Receipt">
                                            <i class="fas fa-print me-1"></i> Print
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#transactionTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
    </script>
</body>
</html>







