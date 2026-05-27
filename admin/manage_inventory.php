<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/inventory_operations.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/manage-inventory.css">
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
                        <li><a class="nav-link py-1 active" href="manage_inventory.php"><i class="fas fa-box me-2"></i> Inventory Management</a></li>
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
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div>
                                <h1>Inventory Management</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Management</a></li>
                                        <li class="breadcrumb-item active">Inventory Management</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Monitor and manage supply inventory levels by category for your laundry business</p>
                    </div>
                    <div class="header-action-section">
                        <div class="d-flex align-items-center gap-3">
                            <div class="current-date">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                            <button type="button" class="btn add-inventory-btn" style="background-color: #7c3aed; border-color: #7c3aed; color: white;" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                                <i class="fas fa-plus me-1"></i> Add New Item
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="header-stats">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $total_items; ?></h3>
                                <p>Total Items</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-soap"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $detergent_items; ?></h3>
                                <p>Detergents</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-wind"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $fabcon_items; ?></h3>
                                <p>Fabric Conditioners</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $good_stock_items; ?></h3>
                                <p>Good Stock</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Tabs -->
        <div class="category-tabs">
            <ul class="nav nav-tabs" id="inventoryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                        All Items <span class="badge bg-primary category-badge"><?php echo $total_items; ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="detergents-tab" data-bs-toggle="tab" data-bs-target="#detergents" type="button" role="tab">
                        Detergents <span class="badge category-badge" style="background-color: #7c3aed; color: white;"><?php echo $detergent_items; ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="fabcon-tab" data-bs-toggle="tab" data-bs-target="#fabcon" type="button" role="tab">
                        Fabric Conditioners <span class="badge category-badge" style="background-color: #7c3aed; color: white;"><?php echo $fabcon_items; ?></span>
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="inventoryTabsContent">
                <!-- All Items Tab -->
                <div class="tab-pane fade show active" id="all" role="tabpanel">
                    <?php if ($total_items > 0): ?>
                    <div class="card inventory-table-card">
                        <div class="table-category-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6><i class="fas fa-list me-2"></i> All Inventory Items (<?php echo $total_items; ?>)</h6>
                                <div class="table-actions">
                                    <div class="input-group input-group-sm" style="width: 250px;">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control form-control-sm search-input" placeholder="Search all items..." data-table="all">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="allTable">
                                    <thead>
                                            <th width="30%">Item Name</th>
                                            <th width="15%">Category</th>
                                            <th width="15%">Price</th>
                                            <th width="20%">Stock Quantity</th>
                                            <th width="10%" class="text-center">Last Updated</th>
                                            <th width="10%" class="text-center">Actions</th>
                                        </thead>
                                    <tbody>
                                        <?php 
                                        // Function to get product image for all items
                                        function getItemImage($itemName) {
                                            $itemLower = strtolower($itemName);
                                            $imageDir = '../assets/images/';
                                            
                                            // All available product images
                                            $imageMap = [
                                                'champion antibacterial' => 'champion antibacterial.png',
                                                'champion antibac' => 'champion antibacterial.png',
                                                'champion' => 'champion.png',
                                                'downy antibacterial' => 'downyantibac.png',
                                                'downy antibac' => 'downyantibac.png',
                                                'downy kontrakulob' => 'downykontrakulob.png',
                                                'downy kontra kulob' => 'downykontrakulob.png',
                                                'downy sunrise fresh' => 'downysunrisefresh.png',
                                                'downy' => 'downysunrisefresh.png',
                                                'del' => 'del.png',
                                                'ariel' => 'ariel.png',
                                                'breeze' => 'breeze.png',
                                                'surf downy' => 'surfdowny.png',
                                                'surf' => 'surf.png'
                                            ];
                                            
                                            // Check for exact matches first
                                            foreach ($imageMap as $key => $image) {
                                                if ($itemLower === strtolower($key)) {
                                                    return $imageDir . $image;
                                                }
                                            }
                                            
                                            // Check for partial matches
                                            foreach ($imageMap as $key => $image) {
                                                if (strpos($itemLower, strtolower($key)) !== false) {
                                                    return $imageDir . $image;
                                                }
                                            }
                                            
                                            // Return default image if no match found
                                            return $imageDir . 'default.png';
                                        }
                                        
                                        mysqli_data_seek($result_all, 0);
                                        while ($item = mysqli_fetch_assoc($result_all)) : 
                                            $qty = (int)$item['stock_quantity'];
                                            $price = isset($item['price']) ? $item['price'] : 16.00;
                                            $item_name = strtolower($item['item_name']);
                                            $productImage = getItemImage($item['item_name']);
                                            
                                            // Determine category from item_type field
                                            $item_type = isset($item['item_type']) ? $item['item_type'] : '';
                                            if ($item_type == 'detergent') {
                                                $category = 'Detergent';
                                                $category_badge = 'warning';
                                            } elseif ($item_type == 'fabric_conditioner') {
                                                $category = 'Fabric Conditioner';
                                                $category_badge = 'info';
                                            } else {
                                                // Fallback to name-based detection if item_type not set
                                                if (strpos($item_name, 'detergent') !== false) {
                                                    $category = 'Detergent';
                                                    $category_badge = 'warning';
                                                } elseif (strpos($item_name, 'fabric') !== false || 
                                                        strpos($item_name, 'softener') !== false || 
                                                        strpos($item_name, 'conditioner') !== false) {
                                                    $category = 'Fabric Conditioner';
                                                    $category_badge = 'info';
                                                } else {
                                                    $category = 'Other';
                                                    $category_badge = 'secondary';
                                                }
                                            }
                                            
                                            // Determine stock status
                                            if ($qty == 0) {
                                                $statusClass = 'danger';
                                                $statusIcon = 'fa-times-circle';
                                                $statusColor = 'bg-danger';
                                                $statusText = 'Out of Stock';
                                            } elseif ($qty <= 5) {
                                                $statusClass = 'warning';
                                                $statusIcon = 'fa-exclamation-triangle';
                                                $statusColor = 'bg-warning';
                                                $statusText = 'Low Stock';
                                            } else {
                                                $statusClass = 'success';
                                                $statusIcon = 'fa-check-circle';
                                                $statusColor = 'bg-success';
                                                $statusText = 'In Stock';
                                            }
                                        ?>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="inventory-icon me-3" style="background: #f5f5f5; border: 1px solid #e0e0e0; overflow: hidden;">
                                                            <img src="<?php echo $productImage; ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                        </div>
                                                        <div>
                                                            <strong class="d-block"><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                            <small class="text-muted">ID: #<?php echo $item['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="color: #000000;"><?php echo $category; ?></span>
                                                </td>
                                                <td>
                                                    <span style="color: #000000;">₱<?php echo number_format($price, 2); ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="status-indicator me-2 <?php echo $statusClass; ?>"></div>
                                                        <div>
                                                            <span class="badge bg-secondary text-white">
                                                                <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                                                <?php echo $qty; ?> units
                                                            </span>
                                                            <div class="mt-1">
                                                                <small class="text-muted"><?php echo $statusText; ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date("M d", strtotime($item['last_updated'])); ?>
                                                    </span>
                                                    <div>
                                                        <small class="text-muted"><?php echo date("g:i A", strtotime($item['last_updated'])); ?></small>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editInventoryModal<?php echo $item['id']; ?>" title="Edit Item">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-secondary px-2" onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>')" title="Delete Item">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="empty-category">
                            <i class="fas fa-box-open"></i>
                            <h5>No inventory items found</h5>
                            <p>Add your first inventory item to get started</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                                <i class="fas fa-plus me-1"></i> Add First Item
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Detergents Tab -->
                <div class="tab-pane fade" id="detergents" role="tabpanel">
                    <?php if ($detergent_items > 0): ?>
                    <div class="card inventory-table-card">
                        <div class="table-category-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6><i class="fas fa-soap me-2"></i> Detergents (<?php echo $detergent_items; ?>)</h6>
                                <div class="table-actions">
                                    <div class="input-group input-group-sm" style="width: 250px;">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control form-control-sm search-input" placeholder="Search detergents..." data-table="detergents">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="detergentsTable">
                                    <thead>
                                            <th width="35%">Detergent Name</th>
                                            <th width="15%">Price</th>
                                            <th width="25%">Stock Quantity</th>
                                            <th width="15%" class="text-center">Last Updated</th>
                                            <th width="10%" class="text-center">Actions</th>
                                        </thead>
                                    <tbody>
                                        <?php 
                                        // Function to get image path based on product name
                                        function getProductImage($itemName) {
                                            $itemLower = strtolower($itemName);
                                            $imageDir = '../assets/images/';
                                            
                                            // Map product names to image files
                                            $imageMap = [
                                                'champion antibacterial' => 'champion antibacterial.png',
                                                'champion antibac' => 'champion antibacterial.png',
                                                'champion' => 'champion.png',
                                                'downy antibacterial' => 'downyantibac.png',
                                                'downy antibac' => 'downyantibac.png',
                                                'downy kontrakulob' => 'downykontrakulob.png',
                                                'downy kontra kulob' => 'downykontrakulob.png',
                                                'downy sunrise fresh' => 'downysunrisefresh.png',
                                                'surf downy' => 'surfdowny.png',
                                                'del' => 'del.png',
                                                'ariel' => 'ariel.png',
                                                'breeze' => 'breeze.png',
                                                'surf' => 'surf.png'
                                            ];
                                            
                                            // Check for exact matches first
                                            foreach ($imageMap as $key => $image) {
                                                if ($itemLower === strtolower($key)) {
                                                    return $imageDir . $image;
                                                }
                                            }
                                            
                                            // Check for partial matches
                                            foreach ($imageMap as $key => $image) {
                                                if (strpos($itemLower, strtolower($key)) !== false) {
                                                    return $imageDir . $image;
                                                }
                                            }
                                            
                                            // Return default image if no match found
                                            return $imageDir . 'default.png';
                                        }
                                        
                                        mysqli_data_seek($result_detergents, 0);
                                        while ($detergent = mysqli_fetch_assoc($result_detergents)) : 
                                            $qty = (int)$detergent['stock_quantity'];
                                            $price = isset($detergent['price']) ? $detergent['price'] : 16.00;
                                            $productImage = getProductImage($detergent['item_name']);
                                            
                                            if ($qty == 0) {
                                                $statusClass = 'danger';
                                                $statusIcon = 'fa-times-circle';
                                                $statusColor = 'bg-danger';
                                                $statusText = 'Out of Stock';
                                            } elseif ($qty <= 5) {
                                                $statusClass = 'warning';
                                                $statusIcon = 'fa-exclamation-triangle';
                                                $statusColor = 'bg-warning';
                                                $statusText = 'Low Stock';
                                            } else {
                                                $statusClass = 'success';
                                                $statusIcon = 'fa-check-circle';
                                                $statusColor = 'bg-success';
                                                $statusText = 'In Stock';
                                            }
                                        ?>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="inventory-icon me-3" style="background: #f5f5f5; border: 1px solid #e0e0e0; overflow: hidden;">
                                                            <img src="<?php echo $productImage; ?>" alt="<?php echo htmlspecialchars($detergent['item_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                        </div>
                                                        <div>
                                                            <strong class="d-block"><?php echo htmlspecialchars($detergent['item_name']); ?></strong>
                                                            <small class="text-muted">ID: #<?php echo $detergent['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="color: #000000;">₱<?php echo number_format($price, 2); ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="status-indicator me-2 <?php echo $statusClass; ?>"></div>
                                                        <div>
                                                            <span class="badge bg-secondary text-white">
                                                                <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                                                <?php echo $qty; ?> units
                                                            </span>
                                                            <div class="mt-1">
                                                                <small class="text-muted"><?php echo $statusText; ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date("M d", strtotime($detergent['last_updated'])); ?>
                                                    </span>
                                                    <div>
                                                        <small class="text-muted"><?php echo date("g:i A", strtotime($detergent['last_updated'])); ?></small>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editInventoryModal<?php echo $detergent['id']; ?>" title="Edit Item">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-secondary px-2" onclick="confirmDelete(<?php echo $detergent['id']; ?>, '<?php echo addslashes($detergent['item_name']); ?>')" title="Delete Item">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="empty-category">
                            <i class="fas fa-soap"></i>
                            <h5>No detergents found</h5>
                            <p>Add detergent items to manage your laundry supplies</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                                <i class="fas fa-plus me-1"></i> Add Detergent
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Fabric Conditioners Tab -->
                <div class="tab-pane fade" id="fabcon" role="tabpanel">
                    <?php if ($fabcon_items > 0): ?>
                    <div class="card inventory-table-card">
                        <div class="table-category-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6><i class="fas fa-wind me-2"></i> Fabric Conditioners (<?php echo $fabcon_items; ?>)</h6>
                                <div class="table-actions">
                                    <div class="input-group input-group-sm" style="width: 250px;">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control form-control-sm search-input" placeholder="Search fabric conditioners..." data-table="fabcon">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="fabconTable">
                                    <thead>
                                            <th width="35%">Fabric Conditioner Name</th>
                                            <th width="15%">Price</th>
                                            <th width="25%">Stock Quantity</th>
                                            <th width="15%" class="text-center">Last Updated</th>
                                            <th width="10%" class="text-center">Actions</th>
                                        </thead>
                                    <tbody>
                                        <?php 
                                        // Function to get fabric conditioner image path
                                        function getFabricConditionerImage($itemName) {
                                            $itemLower = strtolower($itemName);
                                            $imageDir = '../assets/images/';
                                            
                                            // Map fabric conditioner names to image files
                                            $imageMap = [
                                                'champion antibacterial' => 'champion antibacterial.png',
                                                'del' => 'del.png',
                                                'downy antibacterial' => 'downyantibac.png',
                                                'downy antibac' => 'downyantibac.png',
                                                'downy kontrakulob' => 'downykontrakulob.png',
                                                'downy kontra kulob' => 'downykontrakulob.png',
                                                'downy sunrise fresh' => 'downysunrisefresh.png',
                                                'downy' => 'downysunrisefresh.png',
                                                'surf downy' => 'surfdowny.png'
                                            ];
                                            
                                            // Check for exact matches first
                                            foreach ($imageMap as $key => $image) {
                                                if ($itemLower === strtolower($key)) {
                                                    return $imageDir . $image;
                                                }
                                            }
                                            
                                            // Check for partial matches
                                            foreach ($imageMap as $key => $image) {
                                                if (strpos($itemLower, strtolower($key)) !== false) {
                                                    return $imageDir . $image;
                                                }
                                            }
                                            
                                            // Return default image if no match found
                                            return $imageDir . 'default.png';
                                        }
                                        
                                        mysqli_data_seek($result_fabcon, 0);
                                        while ($fabcon = mysqli_fetch_assoc($result_fabcon)) : 
                                            $qty = (int)$fabcon['stock_quantity'];
                                            $price = isset($fabcon['price']) ? $fabcon['price'] : 16.00;
                                            $productImage = getFabricConditionerImage($fabcon['item_name']);
                                            
                                            if ($qty == 0) {
                                                $statusClass = 'danger';
                                                $statusIcon = 'fa-times-circle';
                                                $statusColor = 'bg-danger';
                                                $statusText = 'Out of Stock';
                                            } elseif ($qty <= 5) {
                                                $statusClass = 'warning';
                                                $statusIcon = 'fa-exclamation-triangle';
                                                $statusColor = 'bg-warning';
                                                $statusText = 'Low Stock';
                                            } else {
                                                $statusClass = 'success';
                                                $statusIcon = 'fa-check-circle';
                                                $statusColor = 'bg-success';
                                                $statusText = 'In Stock';
                                            }
                                        ?>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="inventory-icon me-3" style="background: #f5f5f5; border: 1px solid #e0e0e0; overflow: hidden;">
                                                            <img src="<?php echo $productImage; ?>" alt="<?php echo htmlspecialchars($fabcon['item_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                        </div>
                                                        <div>
                                                            <strong class="d-block"><?php echo htmlspecialchars($fabcon['item_name']); ?></strong>
                                                            <small class="text-muted">ID: #<?php echo $fabcon['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="color: #000000;">₱<?php echo number_format($price, 2); ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="status-indicator me-2 <?php echo $statusClass; ?>"></div>
                                                        <div>
                                                            <span class="badge bg-secondary text-white">
                                                                <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                                                <?php echo $qty; ?> units
                                                            </span>
                                                            <div class="mt-1">
                                                                <small class="text-muted"><?php echo $statusText; ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date("M d", strtotime($fabcon['last_updated'])); ?>
                                                    </span>
                                                    <div>
                                                        <small class="text-muted"><?php echo date("g:i A", strtotime($fabcon['last_updated'])); ?></small>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editInventoryModal<?php echo $fabcon['id']; ?>" title="Edit Item">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-secondary px-2" onclick="confirmDelete(<?php echo $fabcon['id']; ?>, '<?php echo addslashes($fabcon['item_name']); ?>')" title="Delete Item">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="empty-category">
                            <i class="fas fa-wind"></i>
                            <h5>No fabric conditioners found</h5>
                            <p>Add fabric conditioner items to manage your laundry supplies</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                                <i class="fas fa-plus me-1"></i> Add Fabric Conditioner
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Inventory Modal -->
    <div class="modal fade" id="addInventoryModal" tabindex="-1" aria-labelledby="addInventoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Inventory Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="add_inventory.php" method="POST">
                        <!-- In the Add Inventory Modal, update the form -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="item_type" id="addItemType" required>
                                    <option value="">-- Select Item Type --</option>
                                    <option value="detergent">Detergent (?16.00)</option>
                                    <option value="fabric_conditioner">Fabric Conditioner (?11.00)</option>
                                </select>
                                <small class="text-muted">Price will be set automatically based on item type</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="stock_quantity" placeholder="0" min="0" required>
                                <small class="text-muted">Initial stock quantity</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="item_name" placeholder="e.g., Tide Ultra Detergent, Downy Fabric Softener" required>
                                <small class="text-muted">Enter the brand and product name</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_inventory" class="btn" style="background-color: #7c3aed; color: white; border-color: #7c3aed;">
                                <i class="fas fa-plus me-1"></i> Add Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Inventory Modals -->
    <?php 
    mysqli_data_seek($result_all, 0);
    while ($item = mysqli_fetch_assoc($result_all)) : 
        // Determine current item type from database
        $current_item_type = isset($item['item_type']) ? $item['item_type'] : '';
        $current_price = isset($item['price']) ? $item['price'] : 16.00;
        
        // Fallback: if no item_type in database, try to determine from name
        if (empty($current_item_type)) {
            $item_name_lower = strtolower($item['item_name']);
            if (strpos($item_name_lower, 'detergent') !== false) {
                $current_item_type = 'detergent';
            } elseif (strpos($item_name_lower, 'fabric') !== false || 
                      strpos($item_name_lower, 'softener') !== false || 
                      strpos($item_name_lower, 'conditioner') !== false) {
                $current_item_type = 'fabric_conditioner';
            }
        }
    ?>
    <div class="modal fade" id="editInventoryModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="editInventoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Item: <?php echo htmlspecialchars($item['item_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="edit_inventory.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="item_type" required>
                                    <option value="">-- Select Item Type --</option>
                                    <option value="detergent" <?php echo ($current_item_type == 'detergent') ? 'selected' : ''; ?>>Detergent</option>
                                    <option value="fabric_conditioner" <?php echo ($current_item_type == 'fabric_conditioner') ? 'selected' : ''; ?>>Fabric Conditioner</option>
                                </select>
                                <small class="text-muted">Select the type of inventory item</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" name="price" value="<?php echo $current_price; ?>" min="0" step="0.01" required>
                                </div>
                                <small class="text-muted">Price per unit</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="stock_quantity" value="<?php echo $item['stock_quantity']; ?>" min="0" required>
                                <small class="text-muted">Current stock quantity</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                                <small class="text-muted">Enter the brand and product name</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_inventory" class="btn" style="background-color: #7c3aed; color: white; border-color: #7c3aed;">
                                <i class="fas fa-save me-1"></i> Update Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>

    <!-- Include SweetAlert2 -->
    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
    // Add this script at the bottom of manage_inventory.php
document.getElementById('addItemType').addEventListener('change', function() {
    const type = this.value;
    let price = '';
    if (type === 'detergent') {
        price = '₱16.00';
    } else if (type === 'fabric_conditioner') {
        price = '₱11.00';
    }
    
    // Show price preview
    let pricePreview = document.getElementById('pricePreview');
    if (!pricePreview) {
        pricePreview = document.createElement('div');
        pricePreview.id = 'pricePreview';
        pricePreview.className = 'mt-2 text-muted small';
        this.parentNode.appendChild(pricePreview);
    }
    
    if (price) {
        pricePreview.innerHTML = `<i class="fas fa-tag me-1"></i> Price will be: ${price}`;
    } else {
        pricePreview.innerHTML = '';
    }
});
</script>
    <script>
        function confirmDelete(itemId, itemName) {
            Swal.fire({
                title: "Are you sure?",
                text: `You are about to delete item: "${itemName}". This action cannot be undone.`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#7c3aed",
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
                    form.action = 'delete_inventory.php';
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = itemId;
                    
                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_inventory';
                    deleteInput.value = '1';
                    
                    form.appendChild(idInput);
                    form.appendChild(deleteInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Search functionality for each table
        document.querySelectorAll('.search-input').forEach(input => {
            input.addEventListener('keyup', function() {
                const searchValue = this.value.toLowerCase();
                const tableId = this.dataset.table + 'Table';
                const table = document.getElementById(tableId);
                
                if (table) {
                    const rows = table.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchValue) ? '' : 'none';
                    });
                }
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

        // Store active tab in localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const triggerTabList = document.querySelectorAll('#inventoryTabs button');
            triggerTabList.forEach(triggerEl => {
                triggerEl.addEventListener('click', function(event) {
                    const tabId = this.getAttribute('data-bs-target').substring(1);
                    localStorage.setItem('activeInventoryTab', tabId);
                });
            });

            // Retrieve active tab from localStorage
            const activeTab = localStorage.getItem('activeInventoryTab');
            if (activeTab) {
                const triggerEl = document.querySelector(`#inventoryTabs button[data-bs-target="#${activeTab}"]`);
                if (triggerEl) {
                    bootstrap.Tab.getOrCreateInstance(triggerEl).show();
                }
            }
        });
    </script>
</body>
</html>







