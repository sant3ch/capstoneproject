<?php
// user/reschedule_booking.php

include '../config.php';
require '../includes/reschedule-init.php';

// Call the function to initialize everything
$booking_id = $_GET['booking_id'] ?? null;

// Use the direct version that sets global variables
initReschedulePageDirect($conn, $booking_id);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Booking - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link rel="stylesheet" href="../assets/lib/css/select2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/reschedule.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/user-profile.css">
    <link rel="stylesheet" href="../assets/css/notification.css">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <style>
        /* Additional wizard-specific styles */
        .booking-wizard-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .booking-step {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .button-container {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .time-slots-container {
            max-height: 500px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
<section>
<!-- ====== NAVBAR ====== -->
<nav class="jl-nav" id="jlNav">
  <a href="../index.php" class="jl-logo">
    <img src="../assets/images/logo.png" alt="Jorish Express Laundry">
  </a>
  <ul class="jl-nav-links">
    <li><a href="../index.php">Home</a></li>
    <li><a href="../index.php#why">About Us</a></li>
    <li><a href="../service-and-pricing.php">Services</a></li>
    <li><a href="../contact-and-map-view.php">Find Location</a></li>

    <li><a href="../index.php#news">Blog</a></li>
  </ul>
  <div class="jl-nav-right">
    <?php if (isset($_SESSION['user_id'])): ?>
      <button class="btn-notif" data-bs-toggle="modal" data-bs-target="#notificationModal">
        <i class="fas fa-bell"></i>
        <?php if (count($notifications) > 0): ?>
          <span class="notif-badge"><?php echo count($notifications); ?></span>
        <?php endif; ?>
      </button>
      <div class="dropdown">
        <button class="btn-user dropdown-toggle" data-bs-toggle="dropdown">
          <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="./user-profile.php"><i class="fas fa-user-cog me-2"></i>Manage Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="#" onclick="confirmLogout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    <?php else: ?>
      <a href="../login.php" class="btn-nav-outline">Login</a>
      <a href="../register.php" class="btn-nav-solid">Register</a>
    <?php endif; ?>
  </div>
  <button class="jl-nav-toggler" id="navToggler"><i class="fas fa-bars"></i></button>
</nav>
<!-- Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationModalLabel">Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="closeNotificationModal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group notification-list">
                    <?php if (!empty($notifications) && count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <li class="list-group-item notification-item <?php echo ($notification['is_read'] ?? 0) ? 'read-notification' : ''; ?>" 
                                data-id="<?php echo $notification['id']; ?>">
                                <strong><?php echo htmlspecialchars($notification['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?>
                                <small class="text-muted d-block"><?php 
                                    if (isset($notification['created_at'])) {
                                        echo date("F j, Y, g:i A", strtotime($notification['created_at']));
                                    }
                                ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted">No new notifications.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="markAllReadBtn">Mark All as Read</button>
            </div>
        </div>
    </div>
</div>
<!-- Page Header -->
<section class="profile-header">
  <div class="profile-header-inner">
    <div>
      <h1><i class="fas fa-calendar-alt me-2"></i>Reschedule Your Booking</h1>
      <p>Adjust your laundry schedule to a more convenient date and time</p>
    </div>
    <a href="user-profile.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Profile</a>
  </div>
</section>

<!-- Main Content Area -->
<div class="container my-5">
    <div class="booking-wizard-container container-box p-5">
        <!-- Original Booking Summary -->
        <div class="machine-status mb-5" style="background: #f8fbff; border: 1px solid #e3edf6; border-radius: 12px; padding: 15px 20px;">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="status-badge" style="display: inline-block; background-color: #3b82f6; color: white; padding: 8px 15px; border-radius: 20px; font-weight: 600;">
                        <i class="fas fa-hashtag me-2"></i>Booking #<?php echo $booking['id']; ?>
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="status-badge" style="display: inline-block; background-color: #64748b; color: white; padding: 8px 15px; border-radius: 20px; font-weight: 600;">
                        <i class="fas fa-calendar-alt me-2"></i>Original: <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?> at <?php echo htmlspecialchars($booking['time_slot']); ?>
                    </span>
                </div>
            </div>
        </div>

        <form id="rescheduleForm" action="reschedule_process.php" method="POST">
            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
            <input type="hidden" name="booking_date" id="booking_date" value="<?php echo htmlspecialchars($booking['booking_date']); ?>">
            <input type="hidden" name="time_slot" id="time_slot" value="<?php echo htmlspecialchars($booking['time_slot']); ?>">
            <input type="hidden" name="selected_laundry_supplies_json" id="selected_laundry_supplies_json" value="<?php echo htmlspecialchars(json_encode($selected_laundry_items ?? []), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="selected_services_json" id="selected_services_json" value="<?php echo htmlspecialchars(json_encode($selected_services ?? []), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="selected_machines_json" id="selected_machines_json" value="<?php echo htmlspecialchars(json_encode($selected_machines ?? []), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="machine_count" value="<?php echo (int)$booking['machine_count']; ?>">


            <!-- STEP 1: Date & Time Selection -->
            <div class="booking-step" id="step-1-content">
                <div class="row g-4">
                    <!-- Calendar Column -->
                    <div class="col-md-6">
                        <h4 class="mb-3">Select New Date</h4>
                        <div class="calendar-container text-center">
                            <h5>
                                <button type="button" id="prev-month" class="btn btn-sm btn-outline-primary"><i class="fas fa-chevron-left"></i></button>
                                <span id="current-month-year"></span>
                                <button type="button" id="next-month" class="btn btn-sm btn-outline-primary"><i class="fas fa-chevron-right"></i></button>
                            </h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
                                        <th>Thu</th><th>Fri</th><th>Sat</th>
                                    </tr>
                                </thead>
                                <tbody id="calendar-body"></tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Time Slots Column -->
                    <div class="col-md-6">
                        <h4 class="mb-3">Select Preferred Time</h4>
                        <div class="time-slots-container">
                            <div id="time-slots">
                                <?php foreach ($time_slots as $slot): ?>
                                    <div class="time-slot <?php echo $booking['time_slot'] === $slot ? 'selected' : ''; ?>" 
                                        onclick="selectTimeSlot(event)" 
                                        data-slot="<?php echo htmlspecialchars($slot); ?>"
                                        data-available-washers="<?php echo $total_machines['washers']; ?>"
                                        data-available-dryers="<?php echo $total_machines['dryers']; ?>"
                                        data-total-washers="<?php echo $total_machines['washers']; ?>"
                                        data-total-dryers="<?php echo $total_machines['dryers']; ?>">
                                        <div class="slot-top-row">
                                            <span class="slot-time"><?php echo $slot; ?></span>
                                            <span class="machine-available">
                                                <?php 
                                                echo $total_machines['washers'] . " Washers &bull; " .
                                                     $total_machines['dryers'] . " Dryers free";
                                                ?>
                                            </span>
                                        </div>
                                        <div class="machine-chips"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button (Single Step) -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-lg" onclick="submitReschedule()" style="background-color: #6366f1; color: white; border: none; padding: 12px 32px; font-weight: 500; transition: all 0.3s ease;">
                        Update Booking
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
</section>
<!-- ====== FOOTER ====== -->
<footer class="jl-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <img src="../assets/images/logo.png" alt="Jorish Express Laundry">
      <span>Jorish Express Laundry</span>
    </div>
    <ul class="footer-links">
      <li><a href="../index.php">Home</a></li>
      <li><a href="../index.php#why">About Us</a></li>
      <li><a href="../service-and-pricing.php">Services</a></li>
      <li><a href="../contact-and-map-view.php">Find Location</a></li>

      <li><a href="../index.php#news">Blog</a></li>
    </ul>
    <div class="footer-social">
      <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank"><i class="fab fa-facebook-f"></i></a>
      <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank"><i class="fab fa-facebook-messenger"></i></a>
    </div>
  </div>
  <div class="footer-copy">
    &copy; <?php echo date("Y"); ?> Jorish Express Laundry. All Rights Reserved.
  </div>
</footer>

<!-- Floating Social -->
<div class="floating-social">
  <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank" class="fs-item">
    <i class="fab fa-facebook-f"></i><span>Facebook</span>
  </a>
  <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank" class="fs-item">
    <i class="fab fa-facebook-messenger"></i><span>Messenger</span>
  </a>
</div>
<!-- Scripts -->
<script src="../assets/lib/js/jquery-3.7.1.min.js"></script>
<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
<script src="../assets/lib/js/select2.full.min.js"></script>
<script src="../assets/lib/js/sweetalert2.min.js"></script>
<script src="../assets/js/confirmlogout-user.js"></script>
<script>
    // Single-step reschedule form
    window.selectedLaundryItems = <?php echo json_encode($selected_laundry_items ?? []); ?>;

    window.rescheduleData = {
        currentMonth: <?php echo $currentMonth - 1; ?>,
        currentYear: <?php echo $currentYear; ?>,
        selectedDate: '<?php echo $booking["booking_date"]; ?>',
        selectedLaundryItems: <?php echo json_encode($selected_laundry_items ?? []); ?>,
        selectedMachines: <?php echo json_encode($selected_machines ?? []); ?>
    };

    // Submit reschedule form
    function submitReschedule() {
        const selectedDate = document.getElementById('booking_date').value;
        
        if (!selectedDate || selectedDate === '') {
            Swal.fire({
                title: 'Select Date',
                text: 'Please select a date for your booking',
                icon: 'warning',
                confirmButtonColor: '#3b82f6'
            });
            return false;
        }

        // Show confirmation dialog
        Swal.fire({
            title: 'Confirm Reschedule?',
            text: 'Your booking will be rescheduled to the new date and time.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Reschedule',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('rescheduleForm').submit();
            }
        });
        
        return false;
    }

    // Attach nav toggler listener
    document.addEventListener('DOMContentLoaded', function() {

        // Nav toggler for mobile
        const navToggler = document.getElementById('navToggler');
        const jlNav = document.getElementById('jlNav');
        if (navToggler && jlNav) {
            navToggler.addEventListener('click', function() {
                jlNav.classList.toggle('open');
            });
        }
    });
</script> 
<script src="../assets/js/reschedule.js"></script>
<?php if (isset($_SESSION['user_id'])): ?>
<script src="../assets/js/notifications.js"></script>
<?php endif; ?>
</body>
</html>


