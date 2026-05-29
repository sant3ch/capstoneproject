<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once '../includes/auth-check-admin.php';

// ----- Month being viewed (default current) -----
$monthParam = $_GET['month'] ?? date('Y-m');
$mObj = DateTime::createFromFormat('Y-m', $monthParam);
if (!$mObj) $mObj = new DateTime('first day of this month');
$mObj->modify('first day of this month');
$year  = (int)$mObj->format('Y');
$month = (int)$mObj->format('n');
$monthStart = $mObj->format('Y-m-d');
$monthEnd   = (clone $mObj)->modify('last day of this month')->format('Y-m-d');
$prevMonth  = (clone $mObj)->modify('-1 month')->format('Y-m');
$nextMonth  = (clone $mObj)->modify('+1 month')->format('Y-m');

// ----- Selected day for the detail panel (default today) -----
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) $selectedDate = date('Y-m-d');

// ----- Fetch this month's bookings, grouped by day -----
$byDay = [];
$stmt = mysqli_prepare($conn, "
    SELECT b.id,
           COALESCE(b.customer_first_name, u.first_name) AS fn,
           COALESCE(b.customer_last_name, u.last_name)  AS ln,
           b.service_type, b.time_slot, b.booking_date,
           b.estimated_completion_time, b.process_completed_at,
           b.order_stage, b.status
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.booking_date BETWEEN ? AND ?
      AND b.status <> 'Cancelled'
    ORDER BY b.booking_date ASC, COALESCE(b.estimated_completion_time, b.booking_date) ASC, b.id ASC");
mysqli_stmt_bind_param($stmt, "ss", $monthStart, $monthEnd);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $byDay[$row['booking_date']][] = $row;
}
mysqli_stmt_close($stmt);

// ----- Helpers -----
// Approximate completion hour from the end of a "7:00 AM - 8:30 AM" time slot.
function slotEndTime($slot) {
    if (strpos($slot, '-') === false) return '';
    $end = trim(substr($slot, strpos($slot, '-') + 1));
    return $end;
}
// Returns ['label'=>..., 'kind'=>'actual'|'estimated'|'slot'|'none']
function completionInfo($b) {
    if (!empty($b['process_completed_at']) && $b['process_completed_at'] !== '0000-00-00 00:00:00') {
        return ['label' => date('g:i A', strtotime($b['process_completed_at'])), 'kind' => 'actual'];
    }
    if (!empty($b['estimated_completion_time']) && $b['estimated_completion_time'] !== '0000-00-00 00:00:00') {
        return ['label' => date('g:i A', strtotime($b['estimated_completion_time'])), 'kind' => 'estimated'];
    }
    $end = slotEndTime($b['time_slot'] ?? '');
    if ($end !== '') return ['label' => $end . ' (from slot)', 'kind' => 'slot'];
    return ['label' => '—', 'kind' => 'none'];
}
function shortService($svc) {
    // service_type stored as "id:Name, id:Name" — strip ids for display
    $parts = array_map(function ($p) {
        $p = trim($p);
        return strpos($p, ':') !== false ? trim(substr($p, strpos($p, ':') + 1)) : $p;
    }, explode(',', (string)$svc));
    return implode(', ', array_filter($parts));
}

// ----- Calendar grid scaffolding -----
$daysInMonth = (int)$mObj->format('t');
$firstDow    = (int)$mObj->format('w'); // 0=Sun
$today       = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completion Calendar - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <style>
        .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; }
        .cal-dow { text-align:center; font-weight:600; color:#6b7280; padding:6px 0; font-size:.85rem; }
        .cal-cell { min-height:96px; border:1px solid #e5e7eb; border-radius:10px; padding:6px; background:#fff; text-decoration:none; color:inherit; display:flex; flex-direction:column; gap:4px; transition:box-shadow .15s; }
        .cal-cell:hover { box-shadow:0 2px 10px rgba(0,0,0,.08); }
        .cal-cell.empty { background:transparent; border:none; }
        .cal-cell.today { border-color:#6366f1; box-shadow:0 0 0 1px #6366f1 inset; }
        .cal-cell.selected { background:#eef2ff; border-color:#6366f1; }
        .cal-daynum { font-weight:600; font-size:.9rem; }
        .cal-count { align-self:flex-start; font-size:.72rem; background:#6366f1; color:#fff; border-radius:10px; padding:1px 8px; }
        .cal-chip { font-size:.72rem; padding:1px 6px; border-radius:6px; background:#f3f4f6; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cal-chip .dot { display:inline-block; width:7px; height:7px; border-radius:50%; margin-right:4px; }
        .dot-actual { background:#16a34a; } .dot-est { background:#f59e0b; } .dot-slot { background:#9ca3af; }
        .badge-actual { background:#16a34a; } .badge-est { background:#f59e0b; color:#1f2937; } .badge-slot { background:#9ca3af; }
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
                        <li><a class="nav-link py-1 active" href="completion_calendar.php"><i class="fas fa-calendar-check me-2"></i> Completion Calendar</a></li>
                        <li><a class="nav-link py-1" href="queue_management.php"><i class="fas fa-people-arrows me-2"></i> Queue Management</a></li>
                        <li><a class="nav-link py-1" href="manage_walkins.php"><i class="fas fa-user-plus me-2"></i> Walk-in Customers</a></li>
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
                            <div class="header-icon"><i class="fas fa-calendar-check"></i></div>
                            <div><h1>Completion Calendar</h1>
                                <nav aria-label="breadcrumb"><ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                    <li class="breadcrumb-item">Management</li>
                                    <li class="breadcrumb-item active">Completion Calendar</li>
                                </ol></nav>
                            </div>
                        </div>
                        <p class="header-subtitle">See when laundry orders are due to be completed. Click a day to view its completion hours.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Month nav + legend -->
        <div class="card mb-3" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="?month=<?php echo $prevMonth; ?>"><i class="fas fa-chevron-left"></i></a>
                    <h5 class="mb-0"><?php echo $mObj->format('F Y'); ?></h5>
                    <a class="btn btn-outline-secondary btn-sm" href="?month=<?php echo $nextMonth; ?>"><i class="fas fa-chevron-right"></i></a>
                    <a class="btn btn-sm" style="background:#6366f1;color:#fff;" href="?month=<?php echo date('Y-m'); ?>&date=<?php echo date('Y-m-d'); ?>">Today</a>
                </div>
                <div class="d-flex align-items-center gap-3 small text-muted">
                    <span><span class="cal-chip"><span class="dot dot-actual"></span></span> Actual</span>
                    <span><span class="cal-chip"><span class="dot dot-est"></span></span> Estimated</span>
                    <span><span class="cal-chip"><span class="dot dot-slot"></span></span> From slot</span>
                </div>
            </div>
        </div>

        <!-- Calendar grid -->
        <div class="card mb-4" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-body">
                <div class="cal-grid mb-1">
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?><div class="cal-dow"><?php echo $d; ?></div><?php endforeach; ?>
                </div>
                <div class="cal-grid">
                    <?php for ($i = 0; $i < $firstDow; $i++): ?><div class="cal-cell empty"></div><?php endfor; ?>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++):
                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $items = $byDay[$dateStr] ?? [];
                        $classes = 'cal-cell';
                        if ($dateStr === $today) $classes .= ' today';
                        if ($dateStr === $selectedDate) $classes .= ' selected';
                    ?>
                    <a class="<?php echo $classes; ?>" href="?month=<?php echo $monthParam; ?>&date=<?php echo $dateStr; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="cal-daynum"><?php echo $day; ?></span>
                            <?php if (count($items) > 0): ?><span class="cal-count"><?php echo count($items); ?></span><?php endif; ?>
                        </div>
                        <?php foreach (array_slice($items, 0, 2) as $b): $ci = completionInfo($b);
                            $dotClass = $ci['kind'] === 'actual' ? 'dot-actual' : ($ci['kind'] === 'estimated' ? 'dot-est' : 'dot-slot'); ?>
                            <span class="cal-chip" title="<?php echo htmlspecialchars(trim($b['fn'].' '.$b['ln'])); ?>"><span class="dot <?php echo $dotClass; ?>"></span><?php echo htmlspecialchars($ci['label']); ?></span>
                        <?php endforeach; ?>
                        <?php if (count($items) > 2): ?><span class="cal-chip">+<?php echo count($items) - 2; ?> more</span><?php endif; ?>
                    </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Day detail -->
        <?php $dayItems = $byDay[$selectedDate] ?? []; ?>
        <div class="card" style="border-radius:14px;border:none;box-shadow:0 2px 12px rgba(0,0,0,.06);">
            <div class="card-header bg-white" style="border-radius:14px 14px 0 0;">
                <h6 class="mb-0"><i class="fas fa-clock me-2"></i> Completions on <?php echo date('F j, Y', strtotime($selectedDate)); ?> (<?php echo count($dayItems); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr>
                            <th>Customer</th><th>Service</th><th>Time Slot</th><th>Est. Completion</th><th>Actual Completion</th><th>Stage / Status</th>
                        </tr></thead>
                        <tbody>
                        <?php if (count($dayItems) > 0): foreach ($dayItems as $b):
                            $est = (!empty($b['estimated_completion_time']) && $b['estimated_completion_time'] !== '0000-00-00 00:00:00')
                                ? date('g:i A', strtotime($b['estimated_completion_time']))
                                : (slotEndTime($b['time_slot'] ?? '') !== '' ? slotEndTime($b['time_slot']).' <span class="text-muted">(from slot)</span>' : '—');
                            $actual = (!empty($b['process_completed_at']) && $b['process_completed_at'] !== '0000-00-00 00:00:00')
                                ? date('g:i A', strtotime($b['process_completed_at'])) : null;
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars(trim(($b['fn'] ?? '').' '.($b['ln'] ?? '')) ?: 'Customer'); ?></strong><br><small class="text-muted">#<?php echo (int)$b['id']; ?></small></td>
                                <td><?php echo htmlspecialchars(shortService($b['service_type'])); ?></td>
                                <td><?php echo htmlspecialchars($b['time_slot']); ?></td>
                                <td><?php echo $est; ?></td>
                                <td><?php echo $actual ? '<span class="badge badge-actual">Done '.$actual.'</span>' : '<span class="text-muted">—</span>'; ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($b['order_stage'] ?: $b['status']); ?></span></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No orders scheduled for this day.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
</body>
</html>
