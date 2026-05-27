<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require '../config.php';
require_once '../includes/auth-check-admin.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $rewardId = intval($_POST['reward_id']);
    $newStatus = $_POST['new_status'];

    // Start transaction to ensure atomicity
    $conn->begin_transaction();

    try {
        // Fetch reward info first
        $info_stmt = $conn->prepare("SELECT user_id, reward_name FROM claimed_rewards WHERE id = ?");
        $info_stmt->bind_param("i", $rewardId);
        $info_stmt->execute();
        $reward_info = $info_stmt->get_result()->fetch_assoc();
        $info_stmt->close();

        if ($reward_info) {
            $user_id = $reward_info['user_id'];
            $reward_name = $reward_info['reward_name'];

            // Points refund logic if rejected
            if ($newStatus === 'Rejected') {
                $points_to_refund = 0;
                switch ($reward_name) {
                    case 'Free Wash Load':
                        $points_to_refund = 6;
                        break;
                    case 'Free Dry Load':
                        $points_to_refund = 12;
                        break;
                    case 'Free Wash & Dry Load':
                        $points_to_refund = 18;
                        break;
                }

                if ($points_to_refund > 0) {
                    $refund_stmt = $conn->prepare("UPDATE users SET user_points = user_points + ? WHERE id = ?");
                    $refund_stmt->bind_param("ii", $points_to_refund, $user_id);
                    $refund_stmt->execute();
                    $refund_stmt->close();
                }
            }

            // Update status
            $stmt = $conn->prepare("UPDATE claimed_rewards SET status = ?, approved_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $rewardId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $_SESSION['success'] = "Reward status updated successfully!";
        } else {
            throw new Exception("Reward record not found.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating reward status: " . $e->getMessage();
    }
    
    header("Location: claimed_rewards.php");
    exit();
}

// Fetch all claimed rewards
$sql = "SELECT cr.id, cr.reward_name, cr.status, cr.claimed_at, cr.approved_at,
               u.first_name, u.last_name, u.email, u.phone, u.user_points
        FROM claimed_rewards cr
        JOIN users u ON cr.user_id = u.id
        ORDER BY cr.claimed_at DESC";

$result = $conn->query($sql);

// Get counts for stats
$total_claims = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM claimed_rewards"));
$pending_claims = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM claimed_rewards WHERE status = 'Pending'"));
$approved_claims = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM claimed_rewards WHERE status = 'Claimed'"));
$rejected_claims = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM claimed_rewards WHERE status = 'Rejected'"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claimed Rewards - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/manage-rewards.css">
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
                        <li><a class="nav-link py-1" href="payment_requests-management.php"><i class="fas fa-money-bill-wave me-2"></i> Payment Requests</a></li>
                        <li><a class="nav-link py-1 active" href="claimed_rewards.php"><i class="fas fa-gift me-2"></i> Claimed Rewards</a></li>
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
                                <i class="fas fa-gift"></i>
                            </div>
                            <div>
                                <h1>Claimed Rewards</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Management</a></li>
                                        <li class="breadcrumb-item active">Claimed Rewards</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Manage and approve customer reward claims from earned points</p>
                    </div>
                    <div class="header-action-section">
                        <div class="d-flex align-items-center gap-3">
                            <div class="current-date">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                            <?php if ($pending_claims > 0): ?>
                            <div class="pending-alert">
                                <span class="badge" style="background-color: #6366f1; color: white;">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <?php echo $pending_claims; ?> Pending
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
                            <div class="stat-icon" style="background-color: #6366f1; color: white;">
                                <i class="fas fa-gift"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $total_claims; ?></h3>
                                <p>Total Claims</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background-color: #6366f1; color: white;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $pending_claims; ?></h3>
                                <p>Pending Approval</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background-color: #6366f1; color: white;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $approved_claims; ?></h3>
                                <p>Approved Claims</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background-color: #6366f1; color: white;">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $rejected_claims; ?></h3>
                                <p>Rejected Claims</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rewards Table Card -->
        <div class="card rewards-table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5><i class="fas fa-list me-2"></i> Claimed Rewards List</h5>
                    <small class="text-muted">Review and manage customer reward claims</small>
                </div>
                <div class="table-actions">
                    <div class="input-group input-group-sm" style="width: 200px;">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control form-control-sm" placeholder="Search claims..." id="searchInput">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="rewardsTable">
                        <thead>
                            <tr>
                                <th width="20%">Customer</th>
                                <th width="15%">Contact</th>
                                <th width="20%">Reward Claimed</th>
                                <th width="15%">Status</th>
                                <th width="15%">Claimed Date</th>
                                <th width="15%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): 
                                while($row = $result->fetch_assoc()): 
                                    $status = $row['status'];
                                    $statusClass = '';
                                    $statusIcon = '';
                                    
                                    switch($status) {
                                        case 'Claimed':
                                            $statusClass = 'success';
                                            $statusIcon = 'fa-check-circle';
                                            break;
                                        case 'Rejected':
                                            $statusClass = 'danger';
                                            $statusIcon = 'fa-times-circle';
                                            break;
                                        case 'Pending':
                                        default:
                                            $statusClass = 'warning';
                                            $statusIcon = 'fa-clock';
                                    }
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="customer-icon me-3">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                                <small class="text-muted">
                                                    <i class="fas fa-star me-1"></i>
                                                    <?php echo $row['user_points']; ?> points
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($row['phone']); ?>
                                        </small>
                                        <small class="text-muted d-block mt-1">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($row['email']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: #6366f1; color: white;">
                                            <i class="fas fa-gift me-1"></i>
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['reward_name']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="status-indicator me-2 <?php echo $statusClass; ?>"></div>
                                            <span class="badge" style="background-color: #6b7280; color: white;">
                                                <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                                <?php echo $status; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date("M d, Y", strtotime($row['claimed_at'])); ?>
                                        </span>
                                        <div>
                                            <small class="text-muted"><?php echo date("g:i A", strtotime($row['claimed_at'])); ?></small>
                                        </div>
                                        <?php if ($row['approved_at']): ?>
                                        <div class="mt-1">
                                            <small class="text-success">
                                                <i class="fas fa-check me-1"></i>
                                                <?php echo date("M d, Y", strtotime($row['approved_at'])); ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($status === 'Pending'): ?>
                                            <div class="action-buttons">
                                                <form method="post" class="d-inline-block">
                                                    <input type="hidden" name="reward_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="new_status" value="Claimed">
                                                    <button type="submit" name="update_status" class="btn btn-sm px-3" style="background-color: #6366f1; border-color: #6366f1; color: white;" title="Approve Claim">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline-block">
                                                    <input type="hidden" name="reward_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="new_status" value="Rejected">
                                                    <button type="submit" name="update_status" class="btn btn-sm px-3" style="background-color: #6b7280; border-color: #6b7280; color: white;" title="Reject Claim">
                                                        Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-check-circle text-muted"></i>
                                                Processed
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-gift fa-3x mb-3 text-muted"></i>
                                            <h5 class="mb-2">No rewards claimed yet</h5>
                                            <p class="text-muted mb-0">Customers haven't claimed any rewards yet</p>
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

    <!-- Include SweetAlert2 -->
    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#rewardsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
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
                color: '#333'
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
    </script>
</body>
</html>







