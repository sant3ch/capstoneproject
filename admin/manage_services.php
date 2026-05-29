<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/content-helpers.php';

// Services list (shared with the booking flow)
$services = [];
$res = mysqli_query($conn, "SELECT * FROM services ORDER BY id ASC");
if ($res) while ($r = mysqli_fetch_assoc($res)) $services[] = $r;
$total_services = count($services);

// Marketing display settings
$svc_self_desc          = getSetting($conn, 'svc_self_desc', '');
$svc_full_desc          = getSetting($conn, 'svc_full_desc', '');
$svc_capacity_note      = getSetting($conn, 'svc_capacity_note', '8kg per load capacity');
$svc_wdf_label          = getSetting($conn, 'svc_wash_dry_fold_label', 'Wash, dry & fold');
$svc_wdf_price          = getSetting($conn, 'svc_wash_dry_fold_price', '175');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services & Pricing - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
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
                    <a class="nav-link" href="admin_home.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
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
                        <li><a class="nav-link py-1" href="completion_calendar.php"><i class="fas fa-calendar-check me-2"></i> Completion Calendar</a></li>
                        <li><a class="nav-link py-1" href="queue_management.php"><i class="fas fa-people-arrows me-2"></i> Queue Management</a></li>
                        <li><a class="nav-link py-1" href="manage_walkins.php"><i class="fas fa-user-plus me-2"></i> Walk-in Customers</a></li>
                        <li><a class="nav-link py-1" href="payment_requests-management.php"><i class="fas fa-money-bill-wave me-2"></i> Payment Requests</a></li>
                        <li><a class="nav-link py-1" href="claimed_rewards.php"><i class="fas fa-gift me-2"></i> Claimed Rewards</a></li>
                        <li><a class="nav-link py-1" href="admin_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a></li>
                        <li><a class="nav-link py-1 active" href="manage_services.php"><i class="fas fa-tags me-2"></i> Services &amp; Pricing</a></li>
                        <li><a class="nav-link py-1" href="manage_blog.php"><i class="fas fa-newspaper me-2"></i> Blog / News</a></li>
                        <li><a class="nav-link py-1" href="manage_about.php"><i class="fas fa-info-circle me-2"></i> About Page</a></li>
                        <li><a class="nav-link py-1" href="manage_testimonials.php"><i class="fas fa-comment-dots me-2"></i> Testimonials</a></li>
                        <li><a class="nav-link py-1" href="manage_why_choose_us.php"><i class="fas fa-thumbs-up me-2"></i> Why Choose Us</a></li>
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
        <div class="page-header-combined">
            <div class="header-main">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="header-title-section">
                        <div class="d-flex align-items-center mb-2">
                            <div class="header-icon"><i class="fas fa-tags"></i></div>
                            <div>
                                <h1>Services &amp; Pricing</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Management</a></li>
                                        <li class="breadcrumb-item active">Services &amp; Pricing</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Manage the services and prices shown on the public site. These prices also drive the booking page.</p>
                    </div>
                    <div class="header-action-section">
                        <div class="d-flex align-items-center gap-3">
                            <div class="current-date">
                                <span class="badge bg-light text-dark"><i class="fas fa-calendar-day me-1"></i><?php echo date('F j, Y'); ?></span>
                            </div>
                            <button type="button" class="btn" style="background-color:#7c3aed;border-color:#7c3aed;color:#fff;" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                                <i class="fas fa-plus me-1"></i> Add Service
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-warning d-flex align-items-center" role="alert" style="border-radius:10px;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>Service names &amp; prices here are also used by the customer <strong>booking page</strong>. Renaming or deleting a service affects what customers can book.</div>
        </div>

        <!-- Services table -->
        <div class="card" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius:14px 14px 0 0;">
                <h6 class="mb-0"><i class="fas fa-list me-2"></i> Bookable Services (<?php echo $total_services; ?>)</h6>
                <div class="input-group input-group-sm" style="width:250px;">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="svcSearch" class="form-control form-control-sm" placeholder="Search services...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="servicesTable">
                        <thead>
                            <tr>
                                <th width="30%">Service Name</th>
                                <th width="15%">Price</th>
                                <th width="40%">Description</th>
                                <th width="15%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_services > 0): foreach ($services as $svc): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($svc['service_name']); ?></strong><br><small class="text-muted">ID: #<?php echo (int)$svc['id']; ?></small></td>
                                <td><span style="color:#000;">&#8369;<?php echo number_format((float)$svc['price'], 2); ?></span></td>
                                <td><span class="text-muted"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editServiceModal<?php echo (int)$svc['id']; ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-secondary px-2" onclick="confirmDelete(<?php echo (int)$svc['id']; ?>, '<?php echo addslashes($svc['service_name']); ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No services yet. Click "Add Service" to create one.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Marketing display settings -->
        <div class="card mt-4" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-header bg-white" style="border-radius:14px 14px 0 0;">
                <h6 class="mb-0"><i class="fas fa-bullhorn me-2"></i> Public Page Display Settings</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">These control descriptive text and the marketing-only "Wash, dry &amp; fold" price on the public Services page. The Wash-dry-fold price is <strong>not</strong> a bookable service.</p>
                <form action="save_service_settings.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Self-Service card description</label>
                            <textarea class="form-control" name="svc_self_desc" rows="2"><?php echo htmlspecialchars($svc_self_desc); ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full-Service card description</label>
                            <textarea class="form-control" name="svc_full_desc" rows="2"><?php echo htmlspecialchars($svc_full_desc); ?></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Capacity note</label>
                            <input type="text" class="form-control" name="svc_capacity_note" value="<?php echo htmlspecialchars($svc_capacity_note); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Wash-dry-fold label</label>
                            <input type="text" class="form-control" name="svc_wash_dry_fold_label" value="<?php echo htmlspecialchars($svc_wdf_label); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Wash-dry-fold price (&#8369;)</label>
                            <input type="number" class="form-control" name="svc_wash_dry_fold_price" value="<?php echo htmlspecialchars($svc_wdf_price); ?>" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" name="save_service_settings" class="btn" style="background-color:#7c3aed;color:#fff;border-color:#7c3aed;"><i class="fas fa-save me-1"></i> Save Display Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div class="modal fade" id="addServiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="add_service.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Service Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="service_name" required placeholder="e.g., Self-Service - Washer">
                            <small class="text-muted">Shown to customers on the booking page.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (&#8369;) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">&#8369;</span>
                                <input type="number" class="form-control" name="price" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_service" class="btn" style="background-color:#7c3aed;color:#fff;border-color:#7c3aed;"><i class="fas fa-plus me-1"></i> Add Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Service Modals -->
    <?php foreach ($services as $svc): ?>
    <div class="modal fade" id="editServiceModal<?php echo (int)$svc['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="edit_service.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo (int)$svc['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Service Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="service_name" value="<?php echo htmlspecialchars($svc['service_name']); ?>" required>
                            <small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Used by bookings — change with care.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (&#8369;) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">&#8369;</span>
                                <input type="number" class="form-control" name="price" value="<?php echo htmlspecialchars($svc['price']); ?>" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_service" class="btn" style="background-color:#7c3aed;color:#fff;border-color:#7c3aed;"><i class="fas fa-save me-1"></i> Update Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
        function confirmDelete(id, name) {
            Swal.fire({
                title: "Are you sure?",
                text: `Delete service "${name}"? This affects the booking page too. This cannot be undone.`,
                icon: "warning", showCancelButton: true,
                confirmButtonColor: "#7c3aed", cancelButtonColor: "#6b7280",
                confirmButtonText: "Yes, delete it!", cancelButtonText: "Cancel",
                background: '#fff', color: '#333'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST'; form.action = 'delete_service.php';
                    form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="delete_service" value="1">`;
                    document.body.appendChild(form); form.submit();
                }
            });
        }
        document.getElementById('svcSearch').addEventListener('keyup', function () {
            const v = this.value.toLowerCase();
            document.querySelectorAll('#servicesTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(v) ? '' : 'none';
            });
        });
        <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({title:"Success!",text:"<?php echo addslashes($_SESSION['success']); ?>",icon:"success",confirmButtonText:"OK",background:'#fff',color:'#333'});
        <?php unset($_SESSION['success']); endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({title:"Error!",text:"<?php echo addslashes($_SESSION['error']); ?>",icon:"error",confirmButtonText:"OK",background:'#fff',color:'#333'});
        <?php unset($_SESSION['error']); endif; ?>
    </script>
</body>
</html>
