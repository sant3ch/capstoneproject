<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require '../config.php';
require '../includes/maintenance-functions.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Pagination Logic
$machines_per_page = 10;
$total_machines_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM machines");
$total_machines_row = mysqli_fetch_assoc($total_machines_query);
$total_machines_all = $total_machines_row['count'];
$total_pages = ceil($total_machines_all / $machines_per_page);

$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $machines_per_page;

// Handle AJAX request for auto-refresh of machine status
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Return only the current page's machines for AJAX refresh
    $result_machines = mysqli_query($conn, "SELECT * FROM machines ORDER BY machine_name LIMIT $offset, $machines_per_page");
    
    if ($result_machines && mysqli_num_rows($result_machines) > 0): 
        $counter = 0;
        while ($machine = mysqli_fetch_assoc($result_machines)) : 
            $counter++;
            $usage_threshold = 10;
            $usage_percentage = ($machine['usage_count'] / $usage_threshold) * 100;
            $maintenance_needed = ($machine['usage_count'] >= $usage_threshold);
    ?>
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="machine-icon me-2" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #f5f5f5; border-radius: 4px; overflow: hidden;">
                        <?php 
                            $machineTypeImg = strtolower($machine['machine_type']);
                            $imageFile = ($machineTypeImg === 'washer') ? 'washer.png' : 'dryer.png';
                            $imagePath = '../assets/images/' . $imageFile;
                        ?>
                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($machine['machine_type']); ?>" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div>
                        <strong class="d-block"><?php echo htmlspecialchars($machine['machine_name']); ?></strong>
                        <small class="text-muted">#<?php echo $machine['id']; ?></small>
                    </div>
                </div>
            </td>
            <td>
                <small><?php echo ucfirst($machine['machine_type']); ?></small>
            </td>
            <td>
                <small>
                    <i class="fas fa-tag me-1 text-muted"></i>
                    <?php echo htmlspecialchars($machine['machine_model']) ?: 'N/A'; ?>
                </small>
            </td>
            <td>
                <div class="usage-cell">
                    <small><?php echo $machine['usage_count']; ?> / <?php echo $usage_threshold; ?></small>
                    <div class="progress mt-1" style="height: 20px;">
                        <div class="progress-bar" style="background-color: #7c3aed; width: <?php echo min($usage_percentage, 100); ?>%;  " 
                             title="<?php echo round($usage_percentage, 1); ?>%">
                            <?php echo round($usage_percentage, 1); ?>%
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <?php
                    // Phase-aware status check for "In Process" bookings
                    $isCurrentlyInUse = false;
                    $machineType = strtolower($machine['machine_type']);
                    
                    // Query for bookings in "In Process" stage using this machine
                    $inProcessQuery = "
                        SELECT estimated_start_time, estimated_completion_time 
                        FROM bookings 
                        WHERE order_stage = 'In Process' 
                          AND FIND_IN_SET('{$machine['machine_name']}', machine_names) > 0
                          AND status <> 'Cancelled'
                        LIMIT 1";
                    $inProcessResult = mysqli_query($conn, $inProcessQuery);

                    if ($inProcessResult && mysqli_num_rows($inProcessResult) > 0) {
                        $booking = mysqli_fetch_assoc($inProcessResult);
                        if (!empty($booking['estimated_start_time']) && !empty($booking['estimated_completion_time'])) {
                            $startTime = strtotime($booking['estimated_start_time']);
                            $endTime = strtotime($booking['estimated_completion_time']);
                            $nowTime = time();

                            // Calculate the midpoint to distinguish washing and drying phases
                            $midpointTime = $startTime + (($endTime - $startTime) / 2);

                            if ($machineType === 'washer' && ($nowTime >= $startTime && $nowTime < $midpointTime)) {
                                // Washer is in use during the first half
                                $isCurrentlyInUse = true;
                            } elseif ($machineType === 'dryer' && ($nowTime >= $midpointTime && $nowTime < $endTime)) {
                                // Dryer is in use during the second half
                                $isCurrentlyInUse = true;
                            }
                        }
                    }
                    
                    // Override status if machine is actively in a phase, otherwise use DB status
                    // If maintenance is needed, always show "needs maintenance" status
                    if ($maintenance_needed && !in_array(strtolower($machine['status']), ['under maintenance', 'maintenance'])) {
                        $status = 'needs maintenance';
                    } else {
                        $status = $isCurrentlyInUse ? 'in use' : strtolower($machine['status']);
                    }
                    $statusClass = '';
                    $statusIcon = '';
                    $statusColor = '';
                    
                    switch($status) {
                        case 'available':
                            $statusClass = 'success';
                            $statusIcon = 'fa-check-circle';
                            $statusColor = 'bg-success';
                            break;
                        case 'in use':
                            $statusClass = 'info';
                            $statusIcon = 'fa-cogs';
                            $statusColor = 'bg-info';
                            break;
                        case 'needs maintenance':
                            $statusClass = 'warning';
                            $statusIcon = 'fa-exclamation-triangle';
                            $statusColor = 'bg-warning';
                            break;
                        case 'under maintenance':
                        case 'maintenance':
                            $statusClass = 'warning';
                            $statusIcon = 'fa-tools';
                            $statusColor = 'bg-warning';
                            break;
                        case 'unavailable':
                            $statusClass = 'danger';
                            $statusIcon = 'fa-times-circle';
                            $statusColor = 'bg-danger';
                            break;
                        default:
                            $statusClass = 'secondary';
                            $statusIcon = 'fa-question-circle';
                            $statusColor = 'bg-secondary';
                    }
                ?>
                <div class="d-flex align-items-center gap-1">
                    <?php if ($maintenance_needed && !in_array($status, ['under maintenance', 'maintenance', 'needs maintenance'])): ?>
                    <span class="badge bg-danger" title="Maintenance Required">⚠️</span>
                    <?php endif; ?>
                    <span style="color: #000000;">
                        <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                        <?php echo ucfirst($status); ?>
                    </span>
                </div>
            </td>
            <td>
                <small>
                    <?php 
                    if ($machine['last_maintenance_date']) {
                        echo date('M d, Y', strtotime($machine['last_maintenance_date']));
                    } else {
                        echo 'Never';
                    }
                    ?>
                </small>
            </td>
            <td class="text-center">
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editMachineModal<?php echo $machine['id']; ?>" title="Edit Machine">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </td>
        </tr>
    <?php 
        endwhile; 
    else: 
    ?>
        <tr>
            <td colspan="7" class="text-center text-muted py-4">
                <div class="empty-state">
                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                    <p>No machines found. <a href="#addMachineModal" data-bs-toggle="modal">Add one now!</a></p>
                </div>
            </td>
        </tr>
    <?php endif; ?>
    <?php
    exit(); // Stop execution after AJAX response
}


// Handle form submission for adding a machine
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_machine'])) {
    $machine_name = trim($_POST['machine_name']);
    $machine_model = trim($_POST['machine_model']);
    $machine_type = trim($_POST['machine_type']);
    $availability = 'Available'; // New machines start as Available

    if (!empty($machine_name) && !empty($machine_type)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO machines (machine_name, machine_model, machine_type, status, usage_count, last_maintenance_date) VALUES (?, ?, ?, ?, 0, NULL)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssss", $machine_name, $machine_model, $machine_type, $availability);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Machine added successfully!";
                header("Location: manage_machines.php");
                exit();
            } else {
                $_SESSION['error'] = "Error executing query: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error'] = "Error preparing statement: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Please fill in all required fields.";
    }
}

// Handle form submission for editing a machine
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_machine'])) {
    $machine_id = $_POST['id'];
    $machine_name = trim($_POST['machine_name']);
    $machine_model = trim($_POST['machine_model']);
    $status = trim($_POST['availability']);
    $old_status_query = mysqli_query($conn, "SELECT status, usage_count FROM machines WHERE id = $machine_id");
    $old_machine = mysqli_fetch_assoc($old_status_query);
    $old_status = $old_machine['status'];

    if (!empty($machine_name) && !empty($status)) {
        // If setting to Available after Needs Maintenance/Under Maintenance, reset usage count
        if ($status === 'Available' && in_array($old_status, ['Needs Maintenance', 'Under Maintenance', 'Maintenance'])) {
            $current_time = date("Y-m-d H:i:s");
            $stmt = mysqli_prepare($conn, "UPDATE machines SET machine_name=?, machine_model=?, status=?, usage_count=0, last_maintenance_date=? WHERE id=?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssssi", $machine_name, $machine_model, $status, $current_time, $machine_id);
                if (mysqli_stmt_execute($stmt)) {
                    // Log maintenance completion
                    logMaintenanceEvent($machine_id, $machine_name, 'manual', $old_machine['usage_count'], 'Maintenance completed and machine returned to available status', 'completed', $conn);
                    
                    $_SESSION['success'] = "Machine updated successfully! Maintenance completed and usage count reset.";
                    header("Location: manage_machines.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Error executing query: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error'] = "Error preparing statement: " . mysqli_error($conn);
            }
        } else if (in_array($status, ['Needs Maintenance', 'Under Maintenance', 'Maintenance']) && !in_array($old_status, ['Needs Maintenance', 'Under Maintenance', 'Maintenance'])) {
            // Log when machine is set to maintenance
            $stmt = mysqli_prepare($conn, "UPDATE machines SET machine_name=?, machine_model=?, status=? WHERE id=?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sssi", $machine_name, $machine_model, $status, $machine_id);
                if (mysqli_stmt_execute($stmt)) {
                    // Log maintenance event
                    logMaintenanceEvent($machine_id, $machine_name, 'usage-based', $old_machine['usage_count'], 'Machine scheduled for maintenance after reaching usage threshold', 'scheduled', $conn);
                    
                    $_SESSION['success'] = "Machine updated successfully!";
                    header("Location: manage_machines.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Error executing query: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error'] = "Error preparing statement: " . mysqli_error($conn);
            }
        } else {
            // Regular update without resetting usage
            $stmt = mysqli_prepare($conn, "UPDATE machines SET machine_name=?, machine_model=?, status=? WHERE id=?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sssi", $machine_name, $machine_model, $status, $machine_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success'] = "Machine updated successfully!";
                    header("Location: manage_machines.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Error executing query: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error'] = "Error preparing statement: " . mysqli_error($conn);
            }
        }
    } else {
        $_SESSION['error'] = "Please fill in all required fields.";
    }
}

// Handle form submission for deleting a machine
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_machine'])) {
    $machine_id = $_POST['id'];

    $stmt = mysqli_prepare($conn, "DELETE FROM machines WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $machine_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Machine deleted successfully!";
            header("Location: manage_machines.php");
            exit();
        } else {
            $_SESSION['error'] = "Error executing query: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Error preparing statement: " . mysqli_error($conn);
    }
}

// Handle form submission for updating all machines
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_all_machines'])) {
    $new_status = trim($_POST['new_status']);
    
    if (!empty($new_status)) {
        $stmt = mysqli_prepare($conn, "UPDATE machines SET status = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $new_status);
            if (mysqli_stmt_execute($stmt)) {
                $affected_rows = mysqli_stmt_affected_rows($stmt);
                $_SESSION['success'] = "Successfully updated $affected_rows machines to '" . htmlspecialchars($new_status) . "' status!";
                header("Location: manage_machines.php");
                exit();
            } else {
                $_SESSION['error'] = "Error updating all machines: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error'] = "Error preparing statement: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Please select a status to update all machines.";
    }
}

// Ensure maintenance_logs table exists
$createMaintenanceLogsTable = "CREATE TABLE IF NOT EXISTS maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    machine_name VARCHAR(255) NOT NULL,
    machine_type VARCHAR(50) DEFAULT NULL,
    maintenance_type VARCHAR(50) NOT NULL,
    usage_count_at_maintenance INT DEFAULT 0,
    maintenance_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    maintenance_status VARCHAR(50) DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(machine_id),
    INDEX(maintenance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (!mysqli_query($conn, $createMaintenanceLogsTable)) {
    error_log("Error creating maintenance_logs table: " . mysqli_error($conn));
}

// Fetch machines for current page
$result_machines = mysqli_query($conn, "SELECT * FROM machines ORDER BY machine_name LIMIT $offset, $machines_per_page");

if (!$result_machines) {
    die("Query Failed: " . mysqli_error($conn));
}

// Get counts for stats
$total_machines = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM machines"));
$available_machines = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM machines WHERE status = 'Available'"));
$in_use_machines = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM machines WHERE status = 'In Use'"));
$needs_maintenance = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM machines WHERE status = 'Needs Maintenance'"));
$under_maintenance = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM machines WHERE status IN ('Under Maintenance', 'Maintenance')"));
$unavailable_machines = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM machines WHERE status = 'Unavailable'"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Machines - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/manage-machines.css">
    <style>
        /* Tab Styling */
        #machinesTabs .nav-link {
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }

        #machinesTabs .nav-link:hover {
            color: #6366f1;
            background-color: #f5f5f5;
        }

        #machinesTabs .nav-link.active {
            color: #6366f1;
            border-bottom: 3px solid #6366f1;
            background-color: transparent;
        }

        .tab-pane {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
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
                        <li><a class="nav-link py-1 active" href="manage_machines.php"><i class="fas fa-tools me-2"></i> Machine Management</a></li>
                        <li><a class="nav-link py-1" href="manage_inventory.php"><i class="fas fa-box me-2"></i> Inventory Management</a></li>
                        <li><a class="nav-link py-1" href="booking_schedules.php"><i class="fas fa-calendar-alt me-2"></i> Booked Schedules</a></li>
                        <li><a class="nav-link py-1" href="queue_management.php"><i class="fas fa-people-arrows me-2"></i> Queue Management</a></li>
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
        <!-- Combined Page Header -->
        <div class="page-header-combined">
            <div class="header-main">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="header-title-section">
                        <div class="d-flex align-items-center mb-2">
                            <div class="header-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div>
                                <h1>Machine Management</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Management</a></li>
                                        <li class="breadcrumb-item active">Machine Management</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">View, edit, and manage all washing machines in the system</p>
                    </div>
                    <div class="header-action-section">
                        <div class="d-flex align-items-center gap-3">
                            <div class="current-date">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                            <button type="button" class="btn" style="background-color: #7c3aed; border-color: #7c3aed; color: white;" data-bs-toggle="modal" data-bs-target="#addMachineModal">
                                <i class="fas fa-plus me-1"></i> Add New Machine
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="header-stats">
                <div class="row g-3">
                    <div class="col-md-2">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $total_machines; ?></h3>
                                <p>Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $available_machines; ?></h3>
                                <p>Available</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $in_use_machines; ?></h3>
                                <p>In Use</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $needs_maintenance; ?></h3>
                                <p>Needs Maintenance</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $under_maintenance; ?></h3>
                                <p>Under Maintenance</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $unavailable_machines; ?></h3>
                                <p>Unavailable</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Machines & Maintenance Tabs Card -->
        <div class="card machines-table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5><i class="fas fa-list me-2"></i> Machine Management</h5>
                    <small class="text-muted">View, edit, remove machines, or check maintenance history</small>
                </div>
                <div class="table-actions">
                    <div class="d-flex align-items-center">
                        <div class="input-group input-group-sm" style="width: 200px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control form-control-sm" placeholder="Search machines..." id="searchInput">
                        </div>
                        <div class="update-all-container ms-2">
                            <button type="button" class="btn btn-update-all btn-sm" data-bs-toggle="modal" data-bs-target="#updateAllModal">
                                <i class="fas fa-sync-alt me-1"></i> Update All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs nav-fill" id="machinesTabs" role="tablist" style="border-bottom: 2px solid #dee2e6;">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="machines-list-tab" data-bs-toggle="tab" data-bs-target="#machines-list-content" type="button" role="tab" aria-controls="machines-list-content" aria-selected="true" style="border: none; padding: 12px 20px; font-weight: 500; transition: all 0.3s;">
                        <i class="fas fa-tools me-2"></i> Machines List
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="maintenance-logs-tab" data-bs-toggle="tab" data-bs-target="#maintenance-logs-content" type="button" role="tab" aria-controls="maintenance-logs-content" aria-selected="false" style="border: none; padding: 12px 20px; font-weight: 500; transition: all 0.3s;">
                        <i class="fas fa-history me-2"></i> Maintenance Logs
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="machinesTabContent">
                
                <!-- Machines List Tab -->
                <div class="tab-pane fade show active" id="machines-list-content" role="tabpanel" aria-labelledby="machines-list-tab">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="machinesTable">
                                <thead>
                                    <tr>
                                        <th width="15%">Machine Name</th>
                                        <th width="8%">Type</th>
                                        <th width="15%">Machine Model</th>
                                        <th width="12%">Usage</th>
                                        <th width="18%">Status</th>
                                        <th width="15%">Last Maintenance</th>
                                        <th width="10%" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Reset pointer and fetch machines again
                                    mysqli_data_seek($result_machines, 0);
                                    if (mysqli_num_rows($result_machines) > 0): 
                                        $counter = 0;
                                        while ($machine = mysqli_fetch_assoc($result_machines)) : 
                                            $counter++;
                                            $usage_threshold = 10;
                                            $usage_percentage = ($machine['usage_count'] / $usage_threshold) * 100;
                                            $maintenance_needed = ($machine['usage_count'] >= $usage_threshold);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="machine-icon me-2" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #f5f5f5; border-radius: 4px; overflow: hidden;">
                                                        <?php 
                                                            $machineTypeImg = strtolower($machine['machine_type']);
                                                            $imageFile = ($machineTypeImg === 'washer') ? 'washer.png' : 'dryer.png';
                                                            $imagePath = '../assets/images/' . $imageFile;
                                                        ?>
                                                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($machine['machine_type']); ?>" style="width: 100%; height: 100%; object-fit: contain;">
                                                    </div>
                                                    <div>
                                                        <strong class="d-block"><?php echo htmlspecialchars($machine['machine_name']); ?></strong>
                                                        <small class="text-muted">#<?php echo $machine['id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo ucfirst($machine['machine_type']); ?></small>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="fas fa-tag me-1 text-muted"></i>
                                                    <?php echo htmlspecialchars($machine['machine_model']) ?: 'N/A'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="usage-cell">
                                                    <small><?php echo $machine['usage_count']; ?> / <?php echo $usage_threshold; ?></small>
                                                    <div class="progress mt-1" style="height: 20px;">
                                                        <div class="progress-bar" style="background-color: #7c3aed; width: <?php echo min($usage_percentage, 100); ?>%;  " 
                                                             title="<?php echo round($usage_percentage, 1); ?>%">
                                                            <?php echo round($usage_percentage, 1); ?>%
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                    // Phase-aware status check for "In Process" bookings
                                                    $isCurrentlyInUse = false;
                                                    $machineType = strtolower($machine['machine_type']);
                                                    
                                                    // Query for bookings in "In Process" stage using this machine
                                                    $inProcessQuery = "
                                                        SELECT estimated_start_time, estimated_completion_time 
                                                        FROM bookings 
                                                        WHERE order_stage = 'In Process' 
                                                          AND FIND_IN_SET('{$machine['machine_name']}', REPLACE(machine_names, ' ', '')) > 0
                                                          AND status <> 'Cancelled'
                                                        LIMIT 1";
                                                    $inProcessResult = mysqli_query($conn, $inProcessQuery);

                                                    if ($inProcessResult && mysqli_num_rows($inProcessResult) > 0) {
                                                        $booking = mysqli_fetch_assoc($inProcessResult);
                                                        if (!empty($booking['estimated_start_time']) && !empty($booking['estimated_completion_time'])) {
                                                            $startTime = strtotime($booking['estimated_start_time']);
                                                            $endTime = strtotime($booking['estimated_completion_time']);
                                                            $nowTime = time();

                                                            // Calculate the midpoint to distinguish washing and drying phases
                                                            $midpointTime = $startTime + (($endTime - $startTime) / 2);

                                                            if ($machineType === 'washer' && ($nowTime >= $startTime && $nowTime < $midpointTime)) {
                                                                // Washer is in use during the first half
                                                                $isCurrentlyInUse = true;
                                                            } elseif ($machineType === 'dryer' && ($nowTime >= $midpointTime && $nowTime < $endTime)) {
                                                                // Dryer is in use during the second half
                                                                $isCurrentlyInUse = true;
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Override status if machine is actively in a phase, otherwise use DB status
                                                    // If maintenance is needed, always show "needs maintenance" status
                                                    if ($maintenance_needed && !in_array(strtolower($machine['status']), ['under maintenance', 'maintenance'])) {
                                                        $status = 'needs maintenance';
                                                    } else {
                                                        $status = $isCurrentlyInUse ? 'in use' : strtolower($machine['status']);
                                                    }
                                                    $statusClass = '';
                                                    $statusIcon = '';
                                                    $statusColor = '';
                                                    
                                                    switch($status) {
                                                        case 'available':
                                                            $statusClass = 'success';
                                                            $statusIcon = 'fa-check-circle';
                                                            $statusColor = 'bg-success';
                                                            break;
                                                        case 'in use':
                                                            $statusClass = 'info';
                                                            $statusIcon = 'fa-cogs';
                                                            $statusColor = 'bg-info';
                                                            break;
                                                        case 'needs maintenance':
                                                            $statusClass = 'warning';
                                                            $statusIcon = 'fa-exclamation-triangle';
                                                            $statusColor = 'bg-warning';
                                                            break;
                                                        case 'under maintenance':
                                                        case 'maintenance':
                                                            $statusClass = 'warning';
                                                            $statusIcon = 'fa-tools';
                                                            $statusColor = 'bg-warning';
                                                            break;
                                                        case 'unavailable':
                                                            $statusClass = 'danger';
                                                            $statusIcon = 'fa-times-circle';
                                                            $statusColor = 'bg-danger';
                                                            break;
                                                        default:
                                                            $statusClass = 'secondary';
                                                            $statusIcon = 'fa-question-circle';
                                                            $statusColor = 'bg-secondary';
                                                    }
                                                ?>
                                                <div class="d-flex align-items-center gap-1">
                                                    <?php if ($maintenance_needed && !in_array($status, ['under maintenance', 'maintenance', 'needs maintenance'])): ?>
                                                    <span class="badge bg-danger" title="Maintenance Required">⚠️</span>
                                                    <?php endif; ?>
                                                    <span style="color: #000000;">
                                                        <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php 
                                                    if ($machine['last_maintenance_date']) {
                                                        echo date('M d, Y', strtotime($machine['last_maintenance_date']));
                                                    } else {
                                                        echo '<em class="text-muted">Never</em>';
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editMachineModal<?php echo $machine['id']; ?>" title="Edit Machine">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-secondary px-2" onclick="confirmDelete(<?php echo $machine['id']; ?>, '<?php echo addslashes($machine['machine_name']); ?>')" title="Delete Machine">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile; 
                                    else: 
                                    ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <div class="empty-state">
                                                    <img src="../assets/images/washer.png" alt="Washer" style="width: 80px; height: 80px; margin-bottom: 1rem; opacity: 0.6;">
                                                    <h5 class="mb-2">No machines found</h5>
                                                    <p class="text-muted mb-0">Add your first machine to get started</p>
                                                    <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addMachineModal">
                                                        <i class="fas fa-plus me-1"></i> Add First Machine
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ($total_machines_all > 0): ?>
                    <div class="card-footer bg-white py-3" style="border-top: 1px solid #dee2e6;">
                        <div class="d-flex justify-content-between align-items-center">
                            <p class="text-muted mb-0 small">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $machines_per_page, $total_machines_all); ?> of <?php echo $total_machines_all; ?> machines
                            </p>
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" style="color: #6366f1;"><i class="fas fa-chevron-left"></i></a>
                                    </li>
                                    <?php 
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                    ?>
                                    <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>" <?php echo $current_page == $i ? 'style="background-color: #6366f1; border-color: #6366f1;"' : 'style="color: #6366f1;"'; ?>><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" style="color: #6366f1;"><i class="fas fa-chevron-right"></i></a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Maintenance Logs Tab -->
                <div class="tab-pane fade" id="maintenance-logs-content" role="tabpanel" aria-labelledby="maintenance-logs-tab">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="maintenanceLogsTable">
                                <thead>
                                    <tr>
                                        <th width="20%">Machine Name</th>
                                        <th width="15%">Machine Type</th>
                                        <th width="15%">Maintenance Type</th>
                                        <th width="10%">Usage Count</th>
                                        <th width="15%">Maintenance Status</th>
                                        <th width="15%">Date</th>
                                        <th width="10%">Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Ensure table exists
                                    $createTableQuery = "CREATE TABLE IF NOT EXISTS maintenance_logs (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        machine_id INT NOT NULL,
                                        machine_name VARCHAR(255) NOT NULL,
                                        machine_type VARCHAR(50) DEFAULT NULL,
                                        maintenance_type VARCHAR(50) NOT NULL,
                                        usage_count_at_maintenance INT DEFAULT 0,
                                        maintenance_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                                        maintenance_status VARCHAR(50) DEFAULT 'scheduled',
                                        notes TEXT,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        INDEX(machine_id),
                                        INDEX(maintenance_date)
                                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                                    
                                    mysqli_query($conn, $createTableQuery);
                                    
                                    // Fetch maintenance logs
                                    $maintenance_logs_query = "SELECT * FROM maintenance_logs ORDER BY maintenance_date DESC LIMIT 100";
                                    $maintenance_logs_result = mysqli_query($conn, $maintenance_logs_query);
                                    
                                    if ($maintenance_logs_result && mysqli_num_rows($maintenance_logs_result) > 0):
                                        while ($log = mysqli_fetch_assoc($maintenance_logs_result)):
                                            $status_badge = '';
                                            $status_color = '';
                                            switch(strtolower($log['maintenance_status'])) {
                                                case 'scheduled':
                                                    $status_badge = '#7c3aed';
                                                    $status_icon = 'fa-calendar-check';
                                                    break;
                                                case 'in_progress':
                                                    $status_badge = '#6b7280';
                                                    $status_icon = 'fa-spinner fa-spin';
                                                    break;
                                                case 'completed':
                                                    $status_badge = '#7c3aed';
                                                    $status_icon = 'fa-check-circle';
                                                    break;
                                                default:
                                                    $status_badge = '#6b7280';
                                                    $status_icon = 'fa-question-circle';
                                            }
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($log['machine_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: #7c3aed; color: white;"><?php echo ucfirst($log['machine_type'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: #6b7280; color: white;">
                                                    <i class="fas fa-tag me-1"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $log['maintenance_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="badge" style="background-color: #7c3aed; color: white;"><?php echo $log['usage_count_at_maintenance']; ?> uses</small>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $status_badge; ?>; color: white;">
                                                    <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $log['maintenance_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y h:i A', strtotime($log['maintenance_date'])); ?></small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(substr($log['notes'] ?? 'N/A', 0, 50)); ?>
                                                    <?php if (strlen($log['notes'] ?? '') > 50): ?>
                                                    <span class="d-block">...</span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-history fa-3x mb-3 text-muted"></i>
                                                    <h5 class="mb-2">No Maintenance Logs Found</h5>
                                                    <p class="text-muted mb-0">Maintenance logs will appear here when machines are set for maintenance</p>
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
        </div>
    </div>

    <!-- Update All Machines Modal -->
<div class="modal fade" id="updateAllModal" tabindex="-1" aria-labelledby="updateAllModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-update">
                <h5 class="modal-title"><i class="fas fa-sync-alt me-2"></i> Update All Machines Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert" style="background-color: #6b7280; color: white; border-color: #6b7280;">
                    <i class="fas fa-info-circle me-2"></i>
                    This will update the status of <strong>all <?php echo $total_machines; ?> machines</strong> to the selected status.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">New Status for All Machines <span class="text-danger">*</span></label>
                    <select class="form-control form-select-lg" id="newStatusSelect">
                        <option value="">-- Select Status --</option>
                        <option value="Available">✓ Available - Ready for use</option>
                        <option value="Maintenance">⚙ Maintenance - Under repair/maintenance</option>
                        <option value="Unavailable">✕ Unavailable - Out of service</option>
                    </select>
                </div>
                <div class="alert" style="background-color: #6b7280; color: white; border-color: #6b7280;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All machines will be updated immediately.
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-update-all" onclick="confirmUpdateAll()">
                        <i class="fas fa-sync-alt me-1"></i> Update All Machines
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Add Machine Modal -->
    <div class="modal fade" id="addMachineModal" tabindex="-1" aria-labelledby="addMachineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Machine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Machine Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="machine_name" placeholder="e.g., Washer1, Dryer2" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Machine Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="machine_type" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="washer">?? Washer</option>
                                    <option value="dryer">?? Dryer</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Machine Model</label>
                                <input type="text" class="form-control" name="machine_model" placeholder="e.g., LG Front Load 10kg">
                            </div>
                        </div>
                        <div class="alert alert-info alert-sm" role="alert">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Note:</strong> New machines start with "Available" status and 0 usage count.
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_machine" class="btn" style="background-color: #7c3aed; color: white; border-color: #7c3aed;">
                                <i class="fas fa-plus me-1"></i> Add Machine
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Machine Modals -->
    <?php 
    // Reset pointer again for modals
    mysqli_data_seek($result_machines, 0);
    while ($machine = mysqli_fetch_assoc($result_machines)) : 
        $usage_threshold = 10;
        $usage_percentage = ($machine['usage_count'] / $usage_threshold) * 100;
        $maintenance_needed = ($machine['usage_count'] >= $usage_threshold) ? true : false;
    ?>
    <div class="modal fade" id="editMachineModal<?php echo $machine['id']; ?>" tabindex="-1" aria-labelledby="editMachineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #7c3aed; color: white;">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Machine: <?php echo htmlspecialchars($machine['machine_name']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Maintenance Information Card -->
                    <div class="card mb-3" style="border: 2px solid #7c3aed;">
                        <div class="card-header" style="background-color: #7c3aed; color: white;">
                            <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Maintenance Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Machine Type:</strong> 
                                        <span class="badge" style="background-color: #7c3aed; color: white;"><?php echo ucfirst($machine['machine_type']); ?></span>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Usage Count:</strong> 
                                        <span class="badge" style="background-color: #7c3aed; color: white;"><?php echo $machine['usage_count']; ?> / <?php echo $usage_threshold; ?></span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-0">
                                        <strong>Last Maintenance:</strong> 
                                        <?php echo ($machine['last_maintenance_date']) ? date('Y-m-d H:i', strtotime($machine['last_maintenance_date'])) : '<em class="text-muted">Never</em>'; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar" style="background-color: #7c3aed; width: <?php echo min($usage_percentage, 100); ?>%;" 
                                         role="progressbar" 
                                         style="width: <?php echo min($usage_percentage, 100); ?>%;" 
                                         aria-valuenow="<?php echo $usage_percentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo round($usage_percentage, 1); ?>%
                                    </div>
                                </div>
                            </div>
                            <?php if ($maintenance_needed): ?>
                            <div class="alert mt-2 mb-0" style="background-color: #6b7280; color: white; border-color: #6b7280;" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Maintenance Required!</strong> This machine has reached the usage threshold and needs maintenance.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                        // Phase-aware status check for "In Process" bookings
                        $isCurrentlyInUseModal = false;
                        $machineTypeModal = strtolower($machine['machine_type']);
                        
                        // Query for bookings in "In Process" stage using this machine
                        $inProcessQueryModal = "
                            SELECT estimated_start_time, estimated_completion_time 
                            FROM bookings 
                            WHERE order_stage = 'In Process' 
                                AND FIND_IN_SET('{$machine['machine_name']}', machine_names) > 0
                                AND status <> 'Cancelled'
                            LIMIT 1";
                        $inProcessResultModal = mysqli_query($conn, $inProcessQueryModal);

                        if ($inProcessResultModal && mysqli_num_rows($inProcessResultModal) > 0) {
                            $bookingModal = mysqli_fetch_assoc($inProcessResultModal);
                            if (!empty($bookingModal['estimated_start_time']) && !empty($bookingModal['estimated_completion_time'])) {
                                $startTimeModal = strtotime($bookingModal['estimated_start_time']);
                                $endTimeModal = strtotime($bookingModal['estimated_completion_time']);
                                $nowTimeModal = time();

                                // Calculate the midpoint to distinguish washing and drying phases
                                $midpointTimeModal = $startTimeModal + (($endTimeModal - $startTimeModal) / 2);

                                if ($machineTypeModal === 'washer' && ($nowTimeModal >= $startTimeModal && $nowTimeModal < $midpointTimeModal)) {
                                    $isCurrentlyInUseModal = true;
                                } elseif ($machineTypeModal === 'dryer' && ($nowTimeModal >= $midpointTimeModal && $nowTimeModal < $endTimeModal)) {
                                    $isCurrentlyInUseModal = true;
                                }
                            }
                        }
                        
                        // Determine display status for the modal
                        $displayStatus = $isCurrentlyInUseModal ? 'In Use' : $machine['status'];
                    ?>

                    <?php if ($isCurrentlyInUseModal): ?>
                    <div class="alert alert-info mb-3" role="alert">
                        <i class="fas fa-cogs me-2"></i>
                        <strong>Currently In Use:</strong> This machine is actively processing a booking.
                    </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <input type="hidden" name="id" value="<?php echo $machine['id']; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Machine Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="machine_name" value="<?php echo htmlspecialchars($machine['machine_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Machine Model</label>
                                <input type="text" class="form-control" name="machine_model" value="<?php echo htmlspecialchars($machine['machine_model']); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-control" name="availability" required>
                                <option value="Available" <?php echo ($displayStatus == 'Available') ? 'selected' : ''; ?>>Available</option>
                                <option value="In Use" <?php echo ($displayStatus == 'In Use') ? 'selected' : ''; ?> <?php echo $isCurrentlyInUseModal ? 'disabled' : ''; ?>>In Use</option>
                                <option value="Needs Maintenance" <?php echo ($displayStatus == 'Needs Maintenance') ? 'selected' : ''; ?>>Needs Maintenance</option>
                                <option value="Under Maintenance" <?php echo ($machine['status'] == 'Under Maintenance') ? 'selected' : ''; ?>>Under Maintenance</option>
                                <option value="Maintenance" <?php echo ($machine['status'] == 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="Unavailable" <?php echo ($machine['status'] == 'Unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                            </select>
                            <?php if (in_array($machine['status'], ['Needs Maintenance', 'Under Maintenance', 'Maintenance'])): ?>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                To reset usage count, perform maintenance from the "Actions" menu.
                            </small>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_machine" class="btn" style="background-color: #7c3aed; color: white; border-color: #7c3aed;">
                                <i class="fas fa-save me-1"></i> Update Machine
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>

    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
<script>
    function confirmUpdateAll() {
        const statusSelect = document.getElementById('newStatusSelect');
        const selectedStatus = statusSelect.value;
        
        if (!selectedStatus) {
            Swal.fire({
                title: "Status Required",
                text: "Please select a status for all machines.",
                icon: "warning",
                confirmButtonText: "OK"
            });
            return;
        }
        
        Swal.fire({
            title: "Update All Machines?",
            html: `You are about to update <strong>all <?php echo $total_machines; ?> machines</strong> to <strong>${selectedStatus}</strong> status.<br><br>This action cannot be undone.`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#6366f1",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Yes, Update All",
            cancelButtonText: "Cancel",
            background: '#fff',
            color: '#333'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create and submit the form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'new_status';
                statusInput.value = selectedStatus;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'update_all_machines';
                actionInput.value = '1';
                
                form.appendChild(statusInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('updateAllModal'));
                modal.hide();
                
                // Submit the form
                form.submit();
            }
        });
    }

    function confirmDelete(machineId, machineName) {
        Swal.fire({
            title: "Are you sure?",
            text: `You are about to delete machine: "${machineName}". This action cannot be undone.`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#6366f1",
            cancelButtonColor: "#6b7280",
            confirmButtonText: "Yes, delete it!",
            cancelButtonText: "Cancel",
            background: '#fff',
            color: '#333'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = machineId;
                
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_machine';
                deleteInput.value = '1';
                
                form.appendChild(idInput);
                form.appendChild(deleteInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#machinesTable tbody tr');
        
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
    
    // Initialize Bootstrap tabs
    document.addEventListener('DOMContentLoaded', function() {
        // Get all tab buttons
        const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
        
        // Add click event listener to each button
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get the target tab content ID
                const targetId = button.getAttribute('data-bs-target');
                if (!targetId) return;
                
                // Hide all tab panes
                const tabPanes = document.querySelectorAll('.tab-pane');
                tabPanes.forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                
                // Remove active class from all tab buttons
                document.querySelectorAll('[role="tab"]').forEach(btn => {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-selected', 'false');
                });
                
                // Show the target tab pane
                const targetPane = document.querySelector(targetId);
                if (targetPane) {
                    targetPane.classList.add('show', 'active');
                }
                
                // Mark this button as active
                button.classList.add('active');
                button.setAttribute('aria-selected', 'true');
            });
        });
        
        // Auto-refresh machine status every 3 seconds to catch dryer status changes during drying phase
        setInterval(function() {
            // Get only the machines table body
            const machinesTable = document.querySelector('#machinesTable tbody');
            if (!machinesTable) return;
            
            // Reload just the machines table via AJAX
            fetch(window.location.pathname + '?ajax=1')
                .then(response => response.text())
                .then(html => {
                    // Create a temporary div to hold the response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // Find the new machines table body
                    const newTable = tempDiv.querySelector('#machinesTable tbody');
                    if (newTable) {
                        // Replace only the status cells to avoid disrupting other interactions
                        const currentRows = machinesTable.querySelectorAll('tr');
                        const newRows = newTable.querySelectorAll('tr');
                        
                        currentRows.forEach((currentRow, index) => {
                            if (newRows[index]) {
                                // Refresh Usage cell (index 3)
                                const currentUsageCell = currentRow.cells[3];
                                const newUsageCell = newRows[index].cells[3];
                                if (currentUsageCell && newUsageCell) {
                                    currentUsageCell.innerHTML = newUsageCell.innerHTML;
                                }

                                // Refresh Status cell (index 4)
                                const currentStatusCell = currentRow.cells[4];
                                const newStatusCell = newRows[index].cells[4];
                                if (currentStatusCell && newStatusCell) {
                                    currentStatusCell.innerHTML = newStatusCell.innerHTML;
                                }
                            }
                        });
                    }
                })
                .catch(error => console.log('Auto-refresh failed:', error));
        }, 3000); // 3 second refresh interval
    });
</script>
</body>
</html>





