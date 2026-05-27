<?php
session_start();
require_once '../config.php';
require_once '../includes/edit-profile-functions.php';

/**
 * Variables from included files
 * @var mysqli $conn Database connection
 * @var array $page_data Page initialization data
 * @var int $user_id Current user ID
 * @var array $user Current user data
 * @var array $notifications User notifications array
 */

// Initialize the page
$page_data = initEditProfilePage($conn);

// Extract the data for use in the template
$user_id = $page_data['user_id'];
$user = $page_data['user'];
$notifications = $page_data['notifications'];
$success_message = $page_data['success_message'];
$error_message = $page_data['error_message'];
$should_redirect = false;

// Handle form submission for profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update_result = handleProfileUpdate($conn, $user_id, $_POST);
    $success_message = $update_result['success_message'];
    $error_message = $update_result['error_message'];
    if ($update_result['updated_user']) {
        $user = $update_result['updated_user'];
    }
}

// Handle profile picture upload separately
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload_result = handleProfilePictureUpload($conn, $user_id, $_FILES);
    if ($upload_result['success']) {
        $success_message = $upload_result['message'];
        // Refresh user data to show new profile picture
        $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
        $user = mysqli_fetch_assoc($user_query);
    } else {
        $error_message = $upload_result['message'];
    }
}

// Check if update was successful and redirect after delay
if (!empty($success_message) && empty($error_message)) {
    $should_redirect = true;
}

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $password_result = handlePasswordChange($conn, $user_id, $_POST);
    $success_message = $password_result['success_message'];
    $error_message = $password_result['error_message'];
}

// Handle profile picture removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_profile_picture'])) {
    // Update profile_picture to NULL or default.png
    $update_query = "UPDATE users SET profile_picture = 'default.png' WHERE id = '$user_id'";
    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['success' => true]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing profile picture.']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Jorish Express Laundry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user-profile.css">
    <link rel="stylesheet" href="../assets/css/edit-profile.css">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
</head>
<body>

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
    <button class="btn-notif" data-bs-toggle="modal" data-bs-target="#notificationModal">
      <i class="fas fa-bell"></i>
      <?php if (!empty($notifications)): ?>
        <span class="notif-badge"><?php echo count($notifications); ?></span>
      <?php endif; ?>
    </button>
    <div class="dropdown">
      <button class="btn-user dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="user-profile.php"><i class="fas fa-user-cog me-2"></i>Manage Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" onclick="confirmLogout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
  <button class="jl-nav-toggler" id="navToggler"><i class="fas fa-bars"></i></button>
</nav>
<section>

<!-- Notification Modal (same as before) -->
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

<!-- Page Header Section -->
<section class="profile-header">
    <div class="profile-header-inner">
        <div>
            <h1><i class="fas fa-user-edit me-2"></i>Edit Profile</h1>
            <p>Update your profile and change your password.</p>
        </div>
        <a href="user-profile.php" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Back to Profile
        </a>
    </div>
</section>

<div class="profile-body">

    <div class="profile-grid">
        <!-- Left Column: Edit Your Profile -->
        <div>
            <div class="jl-card h-100">
                <div class="card-head">
                    <h4 class="mb-0 text-white d-flex align-items-center">
                        <i class="fas fa-user-edit me-3"></i>Edit Your Profile
                    </h4>
                    <button type="button" class="btn-indigo btn-sm" onclick="confirmProfilePictureRemoval()" style="border-radius: 20px; font-size: 0.75rem; padding: 5px 15px;">
                        <i class="fas fa-image-slash me-1"></i>Remove Picture
                    </button>
                </div>
                <div class="card-body-pad">
                    <!-- Success/Error Messages for Profile -->
                    <?php if (!empty($success_message) && !isset($_POST['change_password'])) : ?>
                        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                            <i class="fas fa-check-circle me-3"></i>
                            <div><?php echo htmlspecialchars($success_message); ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message) && !isset($_POST['change_password'])) : ?>
                        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                            <i class="fas fa-exclamation-circle me-3"></i>
                            <div><?php echo htmlspecialchars($error_message); ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Picture Section -->
                    <div class="profile-picture-container mb-4">
                        <?php 
                        $profile_picture = $user['profile_picture'] ?? 'default.png';
                        $profile_path = '../uploads/profile_pictures/' . $profile_picture;
                        $profile_abs_path = realpath(dirname(__FILE__) . '/../uploads/profile_pictures/' . $profile_picture);
                        ?>
                        <div class="profile-pic-wrap mx-auto mb-3" style="width: 120px; height: 120px;">
                            <?php if (!empty($profile_picture) && $profile_picture !== 'default.png' && file_exists($profile_abs_path)): ?>
                                <img src="<?php echo htmlspecialchars($profile_path); ?>" alt="Profile" class="w-100 h-100 rounded-circle" style="object-fit: cover;">
                            <?php else: ?>
                                <span class="pic-icon" style="font-size: 2.5rem;"><i class="fas fa-user"></i></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small">Update your profile picture below</p>
                    </div>

                    <!-- Profile Update Form -->
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">First Name</label>
                                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label small">Delivery Address</label>
                            <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small">Update Profile Picture</label>
                            <input type="file" name="profile_picture" class="form-control" accept="image/*">
                        </div>

                        <div class="d-flex gap-3 pt-3 border-top">
                            <button type="submit" class="btn-indigo flex-grow-1">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                            <a href="user-profile.php" class="btn-outline-indigo flex-grow-1 text-center">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Change Password -->
        <div>
            <div class="jl-card h-100">
                <div class="card-head">
                    <h4 class="mb-0 text-white d-flex align-items-center">
                        <i class="fas fa-shield-alt me-3"></i>Security Settings
                    </h4>
                </div>
                <div class="card-body-pad">
                    <h5 class="fw-bold mb-3">Change Password</h5>
                    <p class="text-muted small mb-4">Keep your account secure by using a strong password.</p>

                    <!-- Success/Error Messages for Password -->
                    <?php if (!empty($success_message) && isset($_POST['change_password'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message) && isset($_POST['change_password'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label small">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>

                        <div class="points-box mb-4" style="padding: 15px;">
                            <div class="small fw-bold mb-2"><i class="fas fa-info-circle me-2"></i>Requirements:</div>
                            <ul class="mb-0 small text-muted" style="padding-left: 20px;">
                                <li>At least 8 characters long</li>
                                <li>Uppercase & lowercase letters</li>
                                <li>Numbers & symbols</li>
                            </ul>
                        </div>

                        <div class="d-grid pt-3 border-top">
                            <button type="submit" class="btn-indigo">
                                <i class="fas fa-key me-2"></i>Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====== FOOTER ====== -->
<footer class="jl-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <a href="../index.php"><img src="../assets/images/logo.png" alt="Jorish Express Laundry"></a>
      <p>Jorish Express Laundry</p>
    </div>
    <ul class="footer-links">
      <li><a href="../index.php">Home</a></li>
      <li><a href="../index.php#why">About Us</a></li>
      <li><a href="../service-and-pricing.php">Services</a></li>
      <li><a href="../contact-and-map-view.php">Find Location</a></li>

      <li><a href="../index.php#news">Blog</a></li>
    </ul>
    <div class="footer-social">
      <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank"><i class="fab fa-facebook"></i></a>
      <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank"><i class="fab fa-facebook-messenger"></i></a>
    </div>
  </div>
  <div class="footer-copy">&copy; <?php echo date("Y"); ?> Jorish Express Laundry. All Rights Reserved.</div>
</footer>

<!-- Floating Social -->
<div class="floating-social">
  <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank" class="fs-item">
    <i class="fab fa-facebook"></i>
  </a>
  <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank" class="fs-item">
    <i class="fab fa-facebook-messenger"></i>
  </a>
</div>

<script src="../assets/lib/js/jquery-3.7.1.min.js"></script>
<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
<script src="../assets/lib/js/sweetalert2.min.js"></script>
<script src="../assets/js/notification.js"></script>
<script src="../assets/js/confirmlogout-user.js"></script>
<script src="../assets/js/edit-profile.js"></script>
<script>
  document.getElementById('navToggler').addEventListener('click', function() {
    document.getElementById('jlNav').classList.toggle('open');
  });
</script>
<script>
    // Redirect to user-profile after successful save
    <?php if ($should_redirect): ?>
        setTimeout(function() {
            window.location.href = 'user-profile.php';
        }, 3000); // 3 second delay to show success message
    <?php endif; ?>

    // Confirm profile picture removal
    function confirmProfilePictureRemoval() {
        Swal.fire({
            title: 'Remove Profile Picture?',
            text: "Are you sure you want to remove your profile picture? It will be replaced with the default icon.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, remove it!',
            cancelButtonText: 'No, keep it'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Removing picture...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Send request to server
                $.ajax({
                    url: 'edit-profile.php',
                    type: 'POST',
                    data: { remove_profile_picture: 1 },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                'Removed!',
                                'Your profile picture has been removed.',
                                'success'
                            ).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                response.message || 'Error removing profile picture.',
                                'error'
                            );
                        }
                    },
                    error: function() {
                        Swal.fire(
                            'Error!',
                            'Could not connect to the server.',
                            'error'
                        );
                    }
                });
            }
        });
    }
</script>
</body>
</html>


