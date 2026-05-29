<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/content-helpers.php';

$about_title   = getSetting($conn, 'about_title', 'ABOUT US');
$about_tagline = getSetting($conn, 'about_tagline', 'Your Trusted Partner in Laundry Care');
$about_desc    = getSetting($conn, 'about_description', '');
$about_image   = getSetting($conn, 'about_image', 'assets/images/aboutus.png');

$values = [];
$res = mysqli_query($conn, "SELECT * FROM core_values ORDER BY sort_order ASC, id ASC");
if ($res) while ($r = mysqli_fetch_assoc($res)) $values[] = $r;
$total = count($values);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Page - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
</head>
<body>
    <div class="sidebar d-flex flex-column justify-content-between">
        <div>
            <div class="sidebar-header"><h4><i class="fas fa-cogs me-2"></i> Admin Panel</h4><small class="sidebar-subtitle">Jorish Express Laundry</small></div>
            <ul class="nav flex-column gap-1">
                <li class="nav-item"><a class="nav-link" href="admin_home.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle active" data-bs-toggle="collapse" href="#managementMenu" role="button"><i class="fas fa-cogs"></i> Management</a>
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
                        <li><a class="nav-link py-1" href="manage_services.php"><i class="fas fa-tags me-2"></i> Services &amp; Pricing</a></li>
                        <li><a class="nav-link py-1" href="manage_blog.php"><i class="fas fa-newspaper me-2"></i> Blog / News</a></li>
                        <li><a class="nav-link py-1 active" href="manage_about.php"><i class="fas fa-info-circle me-2"></i> About Page</a></li>
                        <li><a class="nav-link py-1" href="manage_testimonials.php"><i class="fas fa-comment-dots me-2"></i> Testimonials</a></li>
                        <li><a class="nav-link py-1" href="manage_why_choose_us.php"><i class="fas fa-thumbs-up me-2"></i> Why Choose Us</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="collapse" href="#reportsMenu" role="button"><i class="fas fa-chart-bar"></i> Reports</a>
                    <ul class="collapse list-unstyled ps-4" id="reportsMenu">
                        <li><a class="nav-link py-1" href="reports.php"><i class="fas fa-file-invoice-dollar me-2"></i> Sales Report</a></li>
                        <li><a class="nav-link py-1" href="transaction_report.php"><i class="fas fa-exchange-alt me-2"></i> Transaction Report</a></li>
                    </ul>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer"><a class="nav-link text-danger d-flex align-items-center" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="page-header-combined">
            <div class="header-main">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="header-title-section">
                        <div class="d-flex align-items-center mb-2">
                            <div class="header-icon"><i class="fas fa-info-circle"></i></div>
                            <div><h1>About Page</h1>
                                <nav aria-label="breadcrumb"><ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                    <li class="breadcrumb-item">Management</li>
                                    <li class="breadcrumb-item active">About Page</li>
                                </ol></nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Edit the public About Us page content and core values.</p>
                    </div>
                    <div class="header-action-section">
                        <button type="button" class="btn" style="background-color:#7c3aed;border-color:#7c3aed;color:#fff;" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus me-1"></i> Add Core Value</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- About content -->
        <div class="card mb-4" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-header bg-white" style="border-radius:14px 14px 0 0;"><h6 class="mb-0"><i class="fas fa-pen me-2"></i> About Content</h6></div>
            <div class="card-body">
                <form action="save_about.php" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3"><label class="form-label">Title</label><input type="text" class="form-control" name="about_title" value="<?php echo htmlspecialchars($about_title); ?>"></div>
                            <div class="mb-3"><label class="form-label">Tagline</label><input type="text" class="form-control" name="about_tagline" value="<?php echo htmlspecialchars($about_tagline); ?>"></div>
                            <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="about_description" rows="4"><?php echo htmlspecialchars($about_desc); ?></textarea></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Main Image</label>
                            <div class="border rounded p-2 mb-2 text-center" style="background:#f8f9fa;">
                                <img src="../<?php echo htmlspecialchars($about_image); ?>" alt="About" style="max-width:100%;max-height:160px;object-fit:cover;" onerror="this.style.display='none'">
                            </div>
                            <input type="file" class="form-control" name="about_image" accept="image/*">
                            <small class="text-muted">JPG/PNG/WebP, max 4MB. Leave empty to keep current.</small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end"><button type="submit" name="save_about" class="btn" style="background-color:#7c3aed;color:#fff;"><i class="fas fa-save me-1"></i> Save About Content</button></div>
                </form>
            </div>
        </div>

        <!-- Core values -->
        <div class="card" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-header bg-white" style="border-radius:14px 14px 0 0;"><h6 class="mb-0"><i class="fas fa-star me-2"></i> Core Values</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th width="10%">Icon</th><th width="25%">Title</th><th width="38%">Description</th><th width="10%">Status</th><th width="7%">Order</th><th width="10%" class="text-center">Actions</th></tr></thead>
                        <tbody>
                        <?php if ($total > 0): foreach ($values as $v): ?>
                            <tr>
                                <td><i class="<?php echo htmlspecialchars($v['icon_class']); ?>"></i></td>
                                <td><strong><?php echo htmlspecialchars($v['title']); ?></strong></td>
                                <td><span class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($v['description'], 0, 90, '…')); ?></span></td>
                                <td><?php echo $v['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>'; ?></td>
                                <td><?php echo (int)$v['sort_order']; ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editModal<?php echo (int)$v['id']; ?>"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-secondary px-2" onclick="confirmDelete(<?php echo (int)$v['id']; ?>, '<?php echo addslashes($v['title']); ?>')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No core values yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Core Value Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add Core Value</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form action="add_core_value.php" method="POST">
                    <div class="mb-3"><label class="form-label">Title <span class="text-danger">*</span></label><input type="text" class="form-control" name="title" required></div>
                    <div class="mb-3"><label class="form-label">Icon class</label><input type="text" class="form-control" name="icon_class" value="fas fa-star"></div>
                    <div class="mb-3"><label class="form-label">Description <span class="text-danger">*</span></label><textarea class="form-control" name="description" rows="3" required></textarea></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="0"></div>
                        <div class="col-6 mb-3"><label class="form-label">Status</label><select class="form-control" name="is_active"><option value="1">Active</option><option value="0">Hidden</option></select></div>
                    </div>
                    <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_core_value" class="btn" style="background-color:#7c3aed;color:#fff;"><i class="fas fa-plus me-1"></i> Add</button></div>
                </form>
            </div>
        </div></div>
    </div>

    <!-- Edit Core Value Modals -->
    <?php foreach ($values as $v): ?>
    <div class="modal fade" id="editModal<?php echo (int)$v['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Core Value</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form action="edit_core_value.php" method="POST">
                    <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                    <div class="mb-3"><label class="form-label">Title <span class="text-danger">*</span></label><input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($v['title']); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Icon class</label><input type="text" class="form-control" name="icon_class" value="<?php echo htmlspecialchars($v['icon_class']); ?>"></div>
                    <div class="mb-3"><label class="form-label">Description <span class="text-danger">*</span></label><textarea class="form-control" name="description" rows="3" required><?php echo htmlspecialchars($v['description']); ?></textarea></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="<?php echo (int)$v['sort_order']; ?>"></div>
                        <div class="col-6 mb-3"><label class="form-label">Status</label><select class="form-control" name="is_active"><option value="1" <?php echo $v['is_active']?'selected':''; ?>>Active</option><option value="0" <?php echo !$v['is_active']?'selected':''; ?>>Hidden</option></select></div>
                    </div>
                    <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="edit_core_value" class="btn" style="background-color:#7c3aed;color:#fff;"><i class="fas fa-save me-1"></i> Update</button></div>
                </form>
            </div>
        </div></div>
    </div>
    <?php endforeach; ?>

    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
        function confirmDelete(id, name) {
            Swal.fire({title:"Are you sure?",text:`Delete core value "${name}"?`,icon:"warning",showCancelButton:true,confirmButtonColor:"#7c3aed",cancelButtonColor:"#6b7280",confirmButtonText:"Yes, delete it!"}).then(r=>{
                if(r.isConfirmed){const f=document.createElement('form');f.method='POST';f.action='delete_core_value.php';f.innerHTML=`<input type="hidden" name="id" value="${id}"><input type="hidden" name="delete_core_value" value="1">`;document.body.appendChild(f);f.submit();}
            });
        }
        <?php if (isset($_SESSION['success'])): ?>Swal.fire({title:"Success!",text:"<?php echo addslashes($_SESSION['success']); ?>",icon:"success"});<?php unset($_SESSION['success']); endif; ?>
        <?php if (isset($_SESSION['error'])): ?>Swal.fire({title:"Error!",text:"<?php echo addslashes($_SESSION['error']); ?>",icon:"error"});<?php unset($_SESSION['error']); endif; ?>
    </script>
</body>
</html>
