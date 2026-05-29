<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once '../includes/auth-check-admin.php';

$items = [];
$res = mysqli_query($conn, "SELECT * FROM testimonials ORDER BY sort_order ASC, id ASC");
if ($res) while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
$total = count($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials - Jorish Express Laundry</title>
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
            <div class="sidebar-header">
                <h4><i class="fas fa-cogs me-2"></i> Admin Panel</h4>
                <small class="sidebar-subtitle">Jorish Express Laundry</small>
            </div>
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
                        <li><a class="nav-link py-1" href="manage_about.php"><i class="fas fa-info-circle me-2"></i> About Page</a></li>
                        <li><a class="nav-link py-1 active" href="manage_testimonials.php"><i class="fas fa-comment-dots me-2"></i> Testimonials</a></li>
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
        <div class="sidebar-footer">
            <a class="nav-link text-danger d-flex align-items-center" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="page-header-combined">
            <div class="header-main">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="header-title-section">
                        <div class="d-flex align-items-center mb-2">
                            <div class="header-icon"><i class="fas fa-comment-dots"></i></div>
                            <div>
                                <h1>Testimonials</h1>
                                <nav aria-label="breadcrumb"><ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                    <li class="breadcrumb-item">Management</li>
                                    <li class="breadcrumb-item active">Testimonials</li>
                                </ol></nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Customer reviews shown on the homepage. Inactive ones are hidden.</p>
                    </div>
                    <div class="header-action-section">
                        <button type="button" class="btn" style="background-color:#7c3aed;border-color:#7c3aed;color:#fff;" data-bs-toggle="modal" data-bs-target="#addModal">
                            <i class="fas fa-plus me-1"></i> Add Testimonial
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr>
                            <th width="18%">Author</th>
                            <th width="8%">Rating</th>
                            <th width="44%">Quote</th>
                            <th width="10%">Status</th>
                            <th width="8%">Order</th>
                            <th width="12%" class="text-center">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($total > 0): foreach ($items as $t): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['author_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($t['initials']); ?></small></td>
                                <td><span class="text-warning"><?php echo str_repeat('★', (int)$t['rating']); ?></span></td>
                                <td><span class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($t['quote'], 0, 120, '…')); ?></span></td>
                                <td><?php echo $t['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>'; ?></td>
                                <td><?php echo (int)$t['sort_order']; ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary px-2" data-bs-toggle="modal" data-bs-target="#editModal<?php echo (int)$t['id']; ?>"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-secondary px-2" onclick="confirmDelete(<?php echo (int)$t['id']; ?>, '<?php echo addslashes($t['author_name']); ?>')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No testimonials yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add Testimonial</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form action="add_testimonial.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Author Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="author_name" required></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Initials</label><input type="text" class="form-control" name="initials" maxlength="4" placeholder="MR"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Rating</label>
                            <select class="form-control" name="rating"><?php for($i=5;$i>=1;$i--) echo "<option value=\"$i\">$i ★</option>"; ?></select>
                        </div>
                    </div>
                    <div class="mb-3"><label class="form-label">Quote <span class="text-danger">*</span></label><textarea class="form-control" name="quote" rows="3" required></textarea></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="0"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Status</label><select class="form-control" name="is_active"><option value="1">Active</option><option value="0">Hidden</option></select></div>
                    </div>
                    <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_testimonial" class="btn" style="background-color:#7c3aed;color:#fff;"><i class="fas fa-plus me-1"></i> Add</button></div>
                </form>
            </div>
        </div></div>
    </div>

    <!-- Edit Modals -->
    <?php foreach ($items as $t): ?>
    <div class="modal fade" id="editModal<?php echo (int)$t['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Testimonial</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form action="edit_testimonial.php" method="POST">
                    <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Author Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="author_name" value="<?php echo htmlspecialchars($t['author_name']); ?>" required></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Initials</label><input type="text" class="form-control" name="initials" maxlength="4" value="<?php echo htmlspecialchars($t['initials']); ?>"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Rating</label>
                            <select class="form-control" name="rating"><?php for($i=5;$i>=1;$i--){$s=((int)$t['rating']==$i)?'selected':'';echo "<option value=\"$i\" $s>$i ★</option>";} ?></select>
                        </div>
                    </div>
                    <div class="mb-3"><label class="form-label">Quote <span class="text-danger">*</span></label><textarea class="form-control" name="quote" rows="3" required><?php echo htmlspecialchars($t['quote']); ?></textarea></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="<?php echo (int)$t['sort_order']; ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Status</label><select class="form-control" name="is_active"><option value="1" <?php echo $t['is_active']?'selected':''; ?>>Active</option><option value="0" <?php echo !$t['is_active']?'selected':''; ?>>Hidden</option></select></div>
                    </div>
                    <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="edit_testimonial" class="btn" style="background-color:#7c3aed;color:#fff;"><i class="fas fa-save me-1"></i> Update</button></div>
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
            Swal.fire({title:"Are you sure?",text:`Delete testimonial by "${name}"?`,icon:"warning",showCancelButton:true,confirmButtonColor:"#7c3aed",cancelButtonColor:"#6b7280",confirmButtonText:"Yes, delete it!"}).then(r=>{
                if(r.isConfirmed){const f=document.createElement('form');f.method='POST';f.action='delete_testimonial.php';f.innerHTML=`<input type="hidden" name="id" value="${id}"><input type="hidden" name="delete_testimonial" value="1">`;document.body.appendChild(f);f.submit();}
            });
        }
        <?php if (isset($_SESSION['success'])): ?>Swal.fire({title:"Success!",text:"<?php echo addslashes($_SESSION['success']); ?>",icon:"success"});<?php unset($_SESSION['success']); endif; ?>
        <?php if (isset($_SESSION['error'])): ?>Swal.fire({title:"Error!",text:"<?php echo addslashes($_SESSION['error']); ?>",icon:"error"});<?php unset($_SESSION['error']); endif; ?>
    </script>
</body>
</html>
