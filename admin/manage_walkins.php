<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once '../includes/auth-check-admin.php';
require_once '../includes/booking-data.php';

$time_slots = [
    "7:00 AM - 8:30 AM",
    "8:30 AM - 10:00 AM",
    "10:00 AM - 11:30 AM",
    "1:00 PM - 2:30 PM",
    "2:30 PM - 4:00 PM",
    "4:00 PM - 5:30 PM",
    "5:30 PM - 7:00 PM",
];

// Selected date (default today)
$selected_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) $selected_date = date('Y-m-d');

// Machine totals + per-slot availability (already includes online bookings + walk-ins)
$totals = getAvailableMachines($conn);
$tot_w = (int)$totals['washers'];
$tot_d = (int)$totals['dryers'];
$availability = getDetailedDateAvailability($conn, $selected_date);

// Walk-ins for the selected date (active + completed; hide cancelled)
$walkins = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM walkin_reservations WHERE booking_date = ? AND status <> 'Cancelled' ORDER BY time_slot ASC, id ASC");
mysqli_stmt_bind_param($stmt, "s", $selected_date);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) $walkins[] = $r;
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Customers - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <style>
        .slot-board { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px; }
        .slot-card { border:1px solid #e5e7eb; border-radius:12px; padding:14px; background:#fff; }
        .slot-card h6 { margin:0 0 8px; font-weight:600; }
        .slot-metric { display:flex; justify-content:space-between; font-size:.9rem; margin-bottom:4px; }
        .slot-metric .full { color:#dc2626; font-weight:600; }
        .slot-metric .ok { color:#16a34a; font-weight:600; }
        .slot-bar { height:6px; border-radius:4px; background:#e5e7eb; overflow:hidden; margin-top:2px; }
        .slot-bar > span { display:block; height:100%; background:#6366f1; }
    </style>
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
                        <li><a class="nav-link py-1 active" href="manage_walkins.php"><i class="fas fa-user-plus me-2"></i> Walk-in Customers</a></li>
                        <li><a class="nav-link py-1" href="payment_requests-management.php"><i class="fas fa-money-bill-wave me-2"></i> Payment Requests</a></li>
                        <li><a class="nav-link py-1" href="claimed_rewards.php"><i class="fas fa-gift me-2"></i> Claimed Rewards</a></li>
                        <li><a class="nav-link py-1" href="admin_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a></li>
                        <li><a class="nav-link py-1" href="manage_services.php"><i class="fas fa-tags me-2"></i> Services &amp; Pricing</a></li>
                        <li><a class="nav-link py-1" href="manage_blog.php"><i class="fas fa-newspaper me-2"></i> Blog / News</a></li>
                        <li><a class="nav-link py-1" href="manage_about.php"><i class="fas fa-info-circle me-2"></i> About Page</a></li>
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
                            <div class="header-icon"><i class="fas fa-user-plus"></i></div>
                            <div><h1>Walk-in Customers</h1>
                                <nav aria-label="breadcrumb"><ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                    <li class="breadcrumb-item">Management</li>
                                    <li class="breadcrumb-item active">Walk-in Customers</li>
                                </ol></nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Log walk-in machine usage so it reserves slots and never conflicts with online bookings.</p>
                    </div>
                    <div class="header-action-section">
                        <button type="button" class="btn" style="background-color:#7c3aed;border-color:#7c3aed;color:#fff;" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus me-1"></i> Add Walk-in</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date filter -->
        <form method="GET" class="card mb-3" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-body d-flex align-items-end gap-3 flex-wrap">
                <div>
                    <label class="form-label mb-1">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" onchange="this.form.submit()">
                </div>
                <div class="text-muted small">Showing availability and walk-ins for <strong><?php echo date('F j, Y', strtotime($selected_date)); ?></strong>. Total machines: <?php echo $tot_w; ?> washers, <?php echo $tot_d; ?> dryers.</div>
            </div>
        </form>

        <!-- Availability board -->
        <div class="card mb-4" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-header bg-white" style="border-radius:14px 14px 0 0;"><h6 class="mb-0"><i class="fas fa-th-large me-2"></i> Slot Availability (online bookings + walk-ins)</h6></div>
            <div class="card-body">
                <div class="slot-board">
                    <?php foreach ($time_slots as $slot):
                        $w = $availability[$slot]['washers'] ?? ['available'=>$tot_w,'total'=>$tot_w,'full'=>false];
                        $d = $availability[$slot]['dryers']  ?? ['available'=>$tot_d,'total'=>$tot_d,'full'=>false];
                        $wpct = $w['total'] > 0 ? max(0, ($w['available']/$w['total'])*100) : 0;
                        $dpct = $d['total'] > 0 ? max(0, ($d['available']/$d['total'])*100) : 0;
                    ?>
                    <div class="slot-card" data-slot="<?php echo htmlspecialchars($slot); ?>">
                        <h6><?php echo htmlspecialchars($slot); ?></h6>
                        <div class="slot-metric"><span>Washers</span><span class="w-free <?php echo $w['full']?'full':'ok'; ?>"><?php echo (int)$w['available']; ?>/<?php echo (int)$w['total']; ?> free</span></div>
                        <div class="slot-bar"><span class="w-bar" style="width:<?php echo $wpct; ?>%"></span></div>
                        <div class="slot-metric mt-2"><span>Dryers</span><span class="d-free <?php echo $d['full']?'full':'ok'; ?>"><?php echo (int)$d['available']; ?>/<?php echo (int)$d['total']; ?> free</span></div>
                        <div class="slot-bar"><span class="d-bar" style="width:<?php echo $dpct; ?>%"></span></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Walk-in list -->
        <div class="card" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-header bg-white" style="border-radius:14px 14px 0 0;"><h6 class="mb-0"><i class="fas fa-list me-2"></i> Walk-ins on <?php echo date('M j, Y', strtotime($selected_date)); ?> (<?php echo count($walkins); ?>)</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr>
                            <th>Customer</th><th>Time Slot</th><th>Washers</th><th>Dryers</th><th>Weight</th><th>Status</th><th>Notes</th><th class="text-center">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if (count($walkins) > 0): foreach ($walkins as $wk): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($wk['customer_name'] ?: 'Walk-in'); ?></strong><?php if ($wk['contact']): ?><br><small class="text-muted"><?php echo htmlspecialchars($wk['contact']); ?></small><?php endif; ?></td>
                                <td><?php echo htmlspecialchars($wk['time_slot']); ?></td>
                                <td><?php echo (int)$wk['washers_used']; ?></td>
                                <td><?php echo (int)$wk['dryers_used']; ?></td>
                                <td><?php echo $wk['estimated_weight'] !== null ? htmlspecialchars($wk['estimated_weight']).' kg' : '—'; ?></td>
                                <td><?php echo $wk['status']==='Completed' ? '<span class="badge bg-secondary">Completed</span>' : '<span class="badge bg-success">Active</span>'; ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($wk['notes'] ?: ''); ?></small></td>
                                <td class="text-center" style="white-space:nowrap;">
                                    <button class="btn btn-sm btn-outline-primary px-2" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#editModal<?php echo (int)$wk['id']; ?>"><i class="fas fa-edit"></i></button>
                                    <?php if ($wk['status'] !== 'Completed'): ?>
                                    <button class="btn btn-sm btn-outline-success px-2" title="Mark Completed" onclick="setStatus(<?php echo (int)$wk['id']; ?>,'Completed')"><i class="fas fa-check"></i></button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-secondary px-2" title="Cancel (frees the slot)" onclick="cancelWalkin(<?php echo (int)$wk['id']; ?>,'<?php echo addslashes($wk['customer_name'] ?: 'Walk-in'); ?>')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No walk-ins logged for this date.</td></tr>
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
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add Walk-in</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form action="add_walkin.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Customer Name</label><input type="text" class="form-control" name="customer_name" placeholder="Optional"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Contact</label><input type="text" class="form-control" name="contact" placeholder="Optional"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" class="form-control" name="booking_date" value="<?php echo htmlspecialchars($selected_date); ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Time Slot <span class="text-danger">*</span></label>
                            <select class="form-control" name="time_slot" required>
                                <?php foreach ($time_slots as $slot): ?><option value="<?php echo htmlspecialchars($slot); ?>"><?php echo htmlspecialchars($slot); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Washers used</label><input type="number" class="form-control" name="washers_used" min="0" max="<?php echo $tot_w; ?>" value="0"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Dryers used</label><input type="number" class="form-control" name="dryers_used" min="0" max="<?php echo $tot_d; ?>" value="0"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Est. Weight (kg)</label><input type="number" class="form-control" name="estimated_weight" min="0" step="0.5" placeholder="Optional"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Notes</label><input type="text" class="form-control" name="notes" placeholder="Optional"></div>
                    <small class="text-muted d-block mb-3">At least one machine must be used. The system rejects amounts exceeding what's free for that slot.</small>
                    <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_walkin" class="btn" style="background-color:#7c3aed;color:#fff;"><i class="fas fa-plus me-1"></i> Add Walk-in</button></div>
                </form>
            </div>
        </div></div>
    </div>

    <!-- Edit Modals -->
    <?php foreach ($walkins as $wk): ?>
    <div class="modal fade" id="editModal<?php echo (int)$wk['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Walk-in</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form action="edit_walkin.php" method="POST">
                    <input type="hidden" name="id" value="<?php echo (int)$wk['id']; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Customer Name</label><input type="text" class="form-control" name="customer_name" value="<?php echo htmlspecialchars($wk['customer_name']); ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Contact</label><input type="text" class="form-control" name="contact" value="<?php echo htmlspecialchars($wk['contact']); ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" class="form-control" name="booking_date" value="<?php echo htmlspecialchars($wk['booking_date']); ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Time Slot <span class="text-danger">*</span></label>
                            <select class="form-control" name="time_slot" required>
                                <?php foreach ($time_slots as $slot): ?><option value="<?php echo htmlspecialchars($slot); ?>" <?php echo $wk['time_slot']===$slot?'selected':''; ?>><?php echo htmlspecialchars($slot); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Washers used</label><input type="number" class="form-control" name="washers_used" min="0" max="<?php echo $tot_w; ?>" value="<?php echo (int)$wk['washers_used']; ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Dryers used</label><input type="number" class="form-control" name="dryers_used" min="0" max="<?php echo $tot_d; ?>" value="<?php echo (int)$wk['dryers_used']; ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Est. Weight (kg)</label><input type="number" class="form-control" name="estimated_weight" min="0" step="0.5" value="<?php echo $wk['estimated_weight']; ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Notes</label><input type="text" class="form-control" name="notes" value="<?php echo htmlspecialchars($wk['notes']); ?>"></div>
                    <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="edit_walkin" class="btn" style="background-color:#7c3aed;color:#fff;"><i class="fas fa-save me-1"></i> Update</button></div>
                </form>
            </div>
        </div></div>
    </div>
    <?php endforeach; ?>

    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
        function postWalkin(id, fields) {
            const f = document.createElement('form');
            f.method = 'POST'; f.action = 'delete_walkin.php';
            let html = `<input type="hidden" name="id" value="${id}">`;
            for (const k in fields) html += `<input type="hidden" name="${k}" value="${fields[k]}">`;
            f.innerHTML = html; document.body.appendChild(f); f.submit();
        }
        function cancelWalkin(id, name) {
            Swal.fire({title:"Cancel walk-in?",text:`Remove "${name}" and free its slot?`,icon:"warning",showCancelButton:true,confirmButtonColor:"#7c3aed",cancelButtonColor:"#6b7280",confirmButtonText:"Yes, cancel it"}).then(r=>{
                if(r.isConfirmed) postWalkin(id, {new_status:'Cancelled'});
            });
        }
        function setStatus(id, status) {
            postWalkin(id, {new_status: status});
        }

        // Live availability board — auto-refresh every 15s (reflects online bookings + other walk-ins)
        const WALKIN_DATE = "<?php echo htmlspecialchars($selected_date); ?>";
        async function refreshBoard() {
            try {
                const r = await fetch(`get_availability.php?date=${encodeURIComponent(WALKIN_DATE)}`);
                if (!r.ok) return;
                const data = await r.json();
                if (!data || data.error) return;
                document.querySelectorAll('.slot-card').forEach(card => {
                    const slot = card.getAttribute('data-slot');
                    const s = data[slot];
                    if (!s) return;
                    const w = s.washers, d = s.dryers;
                    const wfree = card.querySelector('.w-free'), dfree = card.querySelector('.d-free');
                    const wbar = card.querySelector('.w-bar'), dbar = card.querySelector('.d-bar');
                    if (wfree) { wfree.textContent = `${w.available}/${w.total} free`; wfree.className = 'w-free ' + (w.full ? 'full' : 'ok'); }
                    if (dfree) { dfree.textContent = `${d.available}/${d.total} free`; dfree.className = 'd-free ' + (d.full ? 'full' : 'ok'); }
                    if (wbar) wbar.style.width = (w.total ? Math.max(0, (w.available / w.total) * 100) : 0) + '%';
                    if (dbar) dbar.style.width = (d.total ? Math.max(0, (d.available / d.total) * 100) : 0) + '%';
                });
            } catch (e) { /* ignore transient errors */ }
        }
        setInterval(refreshBoard, 15000);
        <?php if (isset($_SESSION['success'])): ?>Swal.fire({title:"Success!",text:"<?php echo addslashes($_SESSION['success']); ?>",icon:"success"});<?php unset($_SESSION['success']); endif; ?>
        <?php if (isset($_SESSION['error'])): ?>Swal.fire({title:"Error!",text:"<?php echo addslashes($_SESSION['error']); ?>",icon:"error"});<?php unset($_SESSION['error']); endif; ?>
    </script>
</body>
</html>
