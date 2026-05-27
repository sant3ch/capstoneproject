<?php
session_start();
require '../config.php';
require '../includes/user-profile-data.php';
require_once '../includes/admin-notifications.php';
require_once '../includes/booking-functions.php';

/**
 * Variables from included files
 * @var mysqli $conn Database connection
 * @var array $user Current user data
 * @var mysqli_result $booking_query User bookings query result
 * @var mysqli_result $claimed_rewards_query User claimed rewards query result
 * @var int $user_points User loyalty points
 * @var int $user_id Current user ID
 */

// Fetch all services from database for price calculation
$services_from_db = [];
$service_query = "SELECT id, service_name, price FROM services";
$service_result = $conn->query($service_query);
if ($service_result) {
    while ($row = $service_result->fetch_assoc()) {
        $services_from_db[$row['id']] = $row;
        $services_from_db[strtolower($row['service_name'])] = $row;
    }
}

// Fetch any unread rejected rewards for this user
$rejected_rewards = [];
if (isset($user_id)) {
    $rejected_query = $conn->prepare("SELECT id, reward_name FROM claimed_rewards WHERE user_id = ? AND status = 'Rejected' AND is_read_by_user = 0 LIMIT 1");
    $rejected_query->bind_param("i", $user_id);
    $rejected_query->execute();
    $rejected_result = $rejected_query->get_result();
    if ($row = $rejected_result->fetch_assoc()) {
        $rejected_rewards = $row;
    }
    $rejected_query->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Jorish Express Laundry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/user-profile.css">
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

<!-- Flash messages -->
<?php if (isset($_SESSION['error'])): ?>
  <div class="jl-alert jl-alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['success'])): ?>
  <div class="jl-alert jl-alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<!-- Page Header -->
<section class="profile-header">
  <div class="profile-header-inner">
    <div>
      <h1><i class="fas fa-user-circle me-2"></i>My Profile</h1>
      <p>Manage your account, track rewards and bookings</p>
    </div>
    <a href="book-now.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Booking</a>
  </div>
</section>

<div class="profile-body">
<div class="profile-grid">
    <!-- Personal Information Card -->
    <div>
        <div class="jl-card">
            <div class="card-head">
                <div>
                    <h4 class="mb-0 d-flex align-items-center text-white">
                        <i class="fas fa-user me-3 fs-4"></i>
                        Personal Information
                        <span class="ms-auto card-head-badge">
                            <i class="fas fa-star text-purple me-1"></i>
                            Member ID: #<?php echo str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?>
                        </span>
                    </h4>
                </div>
            </div>

            <div class="card-body-pad">
                <!-- Profile Section -->
                <div class="user-info mb-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="position-relative me-4">
                            <!-- Profile Picture with Border -->
                        <!-- Profile Picture with Border and Icon Fallback -->
                        <div class="profile-pic-wrap">
                            <?php 
                            $profile_pic = $user['profile_picture'] ?? 'default.png';
                            $profile_pic_path = '../uploads/profile_pictures/' . $profile_pic;
                            $profile_abs_path = realpath(dirname(__FILE__) . '/../uploads/profile_pictures/' . $profile_pic);
                            
                            // Check if file exists and is not empty
                            if (!empty($profile_pic) && $profile_pic != 'default.png' && file_exists($profile_abs_path)) {
                                ?>
                                <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" 
                                    alt="Profile Picture" 
                                    class="rounded-circle w-100 h-100"
                                    style="object-fit: cover;">
                                <?php
                            } else {
                                // Fallback to icon
                                ?>
                                <span class="pic-icon"><i class="fas fa-user fa-2x"></i></span>
                                <?php
                            }
                            ?>
                        </div>
                            <span class="online-dot" title="Active"></span>
                        </div>
                        <div>
                            <h3 class="mb-1 text-dark fw-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <span class="member-badge">
                                    <i class="fas fa-award"></i>
                                    Loyalty Member
                                </span>
                                <span class="since-badge">
                                    <i class="fas fa-calendar-check me-1"></i>
                                    Since <?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="list-group list-group-flush rounded">
                        <!-- Phone -->
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-0 border-0">
                            <div class="d-flex align-items-center">
                                <div class="info-icon-wrap me-3">
                                    <i class="fas fa-phone fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Phone Number</div>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($user['phone']); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Email -->
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-0 border-0">
                            <div class="d-flex align-items-center">
                                <div class="info-icon-wrap me-3">
                                    <i class="fas fa-envelope fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Email Address</div>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-purple" onclick="copyToClipboard('<?php echo htmlspecialchars($user['email']); ?>')"
                                        data-bs-toggle="tooltip" title="Copy Email">
                                    <i class="fas fa-file-alt"></i>
                                </button>
                                <span class="badge bg-secondary text-white ms-2" 
                                      data-bs-toggle="tooltip" 
                                      title="<?php echo ($user['email_verified_at'] ?? false) ? 'Email Verified' : 'Email Not Verified'; ?>">
                                    <i class="fas fa-<?php echo ($user['email_verified_at'] ?? false) ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Address -->
                        <div class="list-group-item py-3 px-0 border-0">
                            <div class="d-flex align-items-start mb-2">
                                <div class="info-icon-wrap" style="margin-top:2px;flex-shrink:0;margin-right:15px;">
                                    <i class="fas fa-map-marker-alt fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="text-muted small">Delivery Address</div>
                                    <div class="fw-semibold text-dark"><?php echo nl2br(htmlspecialchars($user['address'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-4 pt-3 border-top text-center">
                    <a href="edit-profile.php" class="btn-edit-profile">
                        <i class="fas fa-edit"></i>Edit Profile
                    </a>
                </div>

                <!-- Claimed Rewards Section -->
                <div class="claimed-rewards mt-4 pt-4 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 d-flex align-items-center">
                            <div class="info-icon-wrap" style="width:36px;height:36px;font-size:.85rem;margin-right:15px;flex-shrink:0;">
                                <i class="fas fa-gift"></i>
                            </div>
                            Claimed Rewards
                        </h5>
                        <span class="badge bg-secondary text-white">
                            <?php echo mysqli_num_rows($claimed_rewards_query); ?> Total
                        </span>
                    </div>

                    <?php
                    $available_rewards = [];
                    $used_rewards = [];
                    mysqli_data_seek($claimed_rewards_query, 0);
                    while ($row = mysqli_fetch_assoc($claimed_rewards_query)) {
                        if ($row['status'] === 'Used') {
                            $used_rewards[] = $row;
                        } else {
                            $available_rewards[] = $row;
                        }
                    }
                    ?>
                    
                    <!-- Reward Tabs -->
                    <ul class="nav nav-tabs mb-4 border-bottom-0 gap-2" id="rewardTabs" role="tablist" style="border: none;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill px-4" id="available-tab" data-bs-toggle="tab" data-bs-target="#available-rewards" type="button" role="tab" style="font-size: 0.8rem; border: 1px solid #e5e7eb;">
                                Available <span class="badge ms-1 <?php echo count($available_rewards) > 0 ? 'bg-indigo' : 'bg-light text-muted'; ?>"><?php echo count($available_rewards); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-4" id="used-tab" data-bs-toggle="tab" data-bs-target="#used-rewards" type="button" role="tab" style="font-size: 0.8rem; border: 1px solid #e5e7eb;">
                                Used <span class="badge ms-1 <?php echo count($used_rewards) > 0 ? 'bg-indigo' : 'bg-light text-muted'; ?>"><?php echo count($used_rewards); ?></span>
                            </button>
                        </li>
                    </ul>
                    
                    <div class="rewards-container">
                    <div class="tab-content" id="rewardTabsContent">
                        <!-- Available Rewards Tab -->
                        <div class="tab-pane fade show active" id="available-rewards" role="tabpanel">
                            <?php if (count($available_rewards) > 0): ?>
                                <div class="row g-3">
                                    <?php foreach ($available_rewards as $reward): 
                                        $card_bg = '';
                                        $status_badge = 'bg-secondary text-white';
                                        if ($reward['status'] === 'Claimed' || $reward['status'] === 'Approved') {
                                            $card_bg = 'border-success border-2';
                                            $status_badge = 'bg-secondary text-white';
                                        } elseif ($reward['status'] === 'Pending') {
                                            $card_bg = 'border-warning border-2';
                                            $status_badge = 'bg-warning text-dark';
                                        } elseif ($reward['status'] === 'Rejected') {
                                            $card_bg = 'border-danger border-2';
                                            $status_badge = 'bg-danger';
                                        }
                                    ?>
                                        <div class="col-12">
                                            <div class="card reward-card <?php echo $card_bg; ?> border-0 shadow-sm">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start w-100">
                                                        <div>
                                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($reward['reward_name']); ?></h6>
                                                            <div class="text-muted small">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                Claimed: <?php echo $reward['claimed_date']; ?>
                                                            </div>
                                                            <?php if ($reward['status'] !== 'Pending' && !empty($reward['approved_date'])): ?>
                                                                <div class="text-muted small mt-1">
                                                                    <i class="fas fa-check-circle me-1"></i>
                                                                    <?php echo ucfirst($reward['status']); ?>: <?php echo $reward['approved_date']; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <span class="badge <?php echo $status_badge; ?> px-3 py-2 fw-semibold">
                                                                <?php echo htmlspecialchars($reward['status']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 bg-light rounded-3 border border-dashed">
                                    <div class="mb-3">
                                        <i class="fas fa-ticket-alt text-muted" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                    </div>
                                    <h6 class="text-muted mb-2">No available vouchers</h6>
                                    <p class="text-muted small mb-0">Claim rewards to use them for discounts!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Used Rewards Tab -->
                        <div class="tab-pane fade" id="used-rewards" role="tabpanel">
                            <?php if (count($used_rewards) > 0): ?>
                                <div class="row g-3">
                                    <?php foreach ($used_rewards as $reward): ?>
                                        <div class="col-12">
                                            <div class="card reward-card status-used border-0 shadow-sm">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start w-100">
                                                        <div>
                                                            <h6 class="mb-1 fw-bold text-muted"><?php echo htmlspecialchars($reward['reward_name']); ?></h6>
                                                            <div class="text-muted small">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                Claimed: <?php echo $reward['claimed_date']; ?>
                                                            </div>
                                                            <?php if (!empty($reward['approved_date'])): ?>
                                                                <div class="text-muted small mt-1">
                                                                    <i class="fas fa-check-circle me-1"></i>
                                                                    Used: <?php echo $reward['approved_date']; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <span class="badge bg-dark text-white px-3 py-2 fw-semibold">
                                                                Used
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 bg-light rounded-3 border border-dashed">
                                    <div class="mb-3">
                                        <i class="fas fa-history text-muted" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                    </div>
                                    <h6 class="text-muted mb-2">No used vouchers yet</h6>
                                    <p class="text-muted small mb-0">Your used rewards will appear here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Rewards Card -->
    <div>
        <div class="jl-card">
            <div class="card-head">  
                <div>
                    <h4 class="mb-0 d-flex align-items-center text-white">
                        <i class="fas fa-award me-3 fs-4"></i>
                        My Rewards & Points
                        <span class="ms-auto card-head-badge">
                            <i class="fas fa-bolt text-purple me-1"></i>
                            Loyalty Program
                        </span>
                    </h4>
                </div>
            </div>

            <div class="card-body-pad">
                <!-- Points Display -->
                <div class="points-box">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="points-label">Available Points</div>
                            <div class="points-number"><?php echo $user_points; ?></div>
                            <div class="points-sub"><i class="fas fa-info-circle me-1"></i>Earn 1 point per wash load completed</div>
                        </div>
                        <div class="coins-icon"><i class="fas fa-coins fs-2"></i></div>
                    </div>
                        
                    <!-- Progress to next reward -->
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Progress to next reward</span>
                            <span class="fw-semibold" style="color:#6366f1"><?php echo $user_points; ?> / 6</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar-indigo"
                                 style="width:<?php echo min(100, ($user_points / 6) * 100); ?>%; height:8px;"
                                 role="progressbar"></div>
                        </div>
                    </div>
                </div>

                <!-- Reward Cards -->
                <div class="rewards-container">
                    <form id="rewardForm" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
                        
                        <!-- Reward 1 - Free Wash Load -->
                        <div class="card reward-card border-0 shadow-sm mb-3 <?php echo ($user_points >= 6) ? 'border-success border-2' : ''; ?>"
                             style="border-radius: 12px; overflow: hidden;">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start mb-3">
                                    <!-- Icon Section -->
                                    <div class="info-icon-wrap" style="width:56px;height:56px;font-size:1.2rem;flex-shrink:0;margin-right:20px;">
                                        <i class="fas fa-tint fs-3"></i>
                                    </div>
                                    
                                    <!-- Content Section -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1 fw-bold text-dark">
                                                    Free Wash Load
                                                </h5>
                                                <p class="text-muted small mb-0">
                                                    <i class="fas fa-coins me-1"></i>
                                                    Redeem for 6 points
                                                </p>
                                            </div>
                                            <button type="button"
                                                    class="btn-indigo claim-btn"
                                                    data-reward-type="free_wash"
                                                    data-points-needed="6"
                                                    <?php echo ($user_points < 6) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-gift me-1"></i>
                                                <?php echo ($user_points >= 6) ? 'Claim Now' : 'Locked'; ?>
                                            </button>
                                        </div>
                                        
                                        <!-- Progress Bar -->
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted small">Progress</span>
                                                <span class="text-dark fw-semibold">
                                                    <?php echo min($user_points, 6); ?>/6 points
                                                </span>
                                            </div>
                                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                                <div class="progress-bar-indigo"
                                                     role="progressbar"
                                                     style="width: <?php echo min(100, ($user_points / 6) * 100); ?>%; height:10px;"
                                                     aria-valuenow="<?php echo min($user_points, 6); ?>"
                                                     aria-valuemin="0" 
                                                     aria-valuemax="6">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Details Button -->
                                        <div class="mt-3">
                                            <button type="button" 
                                                    class="btn-outline-indigo"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#task1Modal">
                                                <i class="fas fa-info-circle me-1"></i> View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reward 2 - Free Dry Load -->
                        <div class="card reward-card border-0 shadow-sm mb-3 <?php echo ($user_points >= 12) ? 'border-warning border-2' : ''; ?>"
                             style="border-radius: 12px; overflow: hidden;">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start mb-3">
                                    <!-- Icon Section -->
                                    <div class="info-icon-wrap" style="width:56px;height:56px;font-size:1.2rem;flex-shrink:0;margin-right:20px;">
                                        <i class="fas fa-wind fs-3"></i>
                                    </div>
                                    
                                    <!-- Content Section -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1 fw-bold text-dark">
                                                    Free Dry Load
                                                </h5>
                                                <p class="text-muted small mb-0">
                                                    <i class="fas fa-coins me-1"></i>
                                                    Redeem for 12 points
                                                </p>
                                            </div>
                                            <button type="button"
                                                    class="btn-indigo claim-btn"
                                                    data-reward-type="free_dry"
                                                    data-points-needed="12"
                                                    <?php echo ($user_points < 12) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-gift me-1"></i>
                                                <?php echo ($user_points >= 12) ? 'Claim Now' : 'Locked'; ?>
                                            </button>
                                        </div>
                                        
                                        <!-- Progress Bar -->
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted small">Progress</span>
                                                <span class="text-dark fw-semibold">
                                                    <?php echo min($user_points, 12); ?>/12 points
                                                </span>
                                            </div>
                                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                                <div class="progress-bar-indigo"
                                                     role="progressbar"
                                                     style="width: <?php echo min(100, ($user_points / 12) * 100); ?>%; height:10px;"
                                                     aria-valuenow="<?php echo min($user_points, 12); ?>"
                                                     aria-valuemin="0"
                                                     aria-valuemax="12">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Details Button -->
                                        <div class="mt-3">
                                            <button type="button" 
                                                    class="btn-outline-indigo"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#task2Modal">
                                                <i class="fas fa-info-circle me-1"></i> View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reward 3 - Free Wash & Dry Load -->
                        <div class="card reward-card border-0 shadow-sm <?php echo ($user_points >= 18) ? 'border-success border-2' : ''; ?>"
                             style="border-radius: 12px; overflow: hidden;">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start mb-3">
                                    <!-- Icon Section -->
                                    <div class="info-icon-wrap" style="width:56px;height:56px;font-size:1.2rem;flex-shrink:0;margin-right:20px;">
                                        <i class="fas fa-recycle fs-3"></i>
                                    </div>
                                    
                                    <!-- Content Section -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1 fw-bold text-dark">
                                                    Free Wash & Dry Load
                                                </h5>
                                                <p class="text-muted small mb-0">
                                                    <i class="fas fa-coins me-1"></i>
                                                    Redeem for 18 points
                                                </p>
                                            </div>
                                            <button type="button"
                                                    class="btn-indigo claim-btn"
                                                    data-reward-type="free_wash_dry"
                                                    data-points-needed="18"
                                                    <?php echo ($user_points < 18) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-gift me-1"></i>
                                                <?php echo ($user_points >= 18) ? 'Claim Now' : 'Locked'; ?>
                                            </button>
                                        </div>
                                        
                                        <!-- Progress Bar -->
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted small">Progress</span>
                                                <span class="text-dark fw-semibold">
                                                    <?php echo min($user_points, 18); ?>/18 points
                                                </span>
                                            </div>
                                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                                <div class="progress-bar-indigo"
                                                     role="progressbar"
                                                     style="width: <?php echo min(100, ($user_points / 18) * 100); ?>%; height:10px;"
                                                     aria-valuenow="<?php echo min($user_points, 18); ?>"
                                                     aria-valuemin="0"
                                                     aria-valuemax="18">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Details Button -->
                                        <div class="mt-3">
                                            <button type="button" 
                                                    class="btn-outline-indigo"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#task3Modal">
                                                <i class="fas fa-info-circle me-1"></i> View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reward Details Modals -->
    <!-- Task 1 Modal - Free Wash Load -->
    <div class="modal fade" id="task1Modal" tabindex="-1" aria-labelledby="task1ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-purple text-white">
                    <h5 class="modal-title" id="task1ModalLabel">
                        <i class="fas fa-tint me-2"></i> Free Wash Load
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 class="fw-bold text-dark mb-2">Reward Details</h6>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-coins me-2 text-warning"></i>
                            <strong>Points Required:</strong> 6 Points
                        </p>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-tint me-2 text-primary"></i>
                            <strong>Service Included:</strong> One (1) Free Wash Load
                        </p>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            <strong>Details:</strong> Redeem this reward to get one free wash load at your next booking.
                        </p>
                    </div>
                    <div class="alert alert-info mb-0">
                        <small>
                            <i class="fas fa-lightbulb me-1"></i>
                            Earn points by completing wash load bookings. Each completed booking earns 1 point.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Task 2 Modal - Free Dry Load -->
    <div class="modal fade" id="task2Modal" tabindex="-1" aria-labelledby="task2ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-purple text-white">
                    <h5 class="modal-title" id="task2ModalLabel">
                        <i class="fas fa-wind me-2"></i> Free Dry Load
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 class="fw-bold text-dark mb-2">Reward Details</h6>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-coins me-2 text-warning"></i>
                            <strong>Points Required:</strong> 12 Points
                        </p>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-wind me-2 text-success"></i>
                            <strong>Service Included:</strong> One (1) Free Dry Load
                        </p>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            <strong>Details:</strong> Redeem this reward to get one free dry load at your next booking.
                        </p>
                    </div>
                    <div class="alert alert-info mb-0">
                        <small>
                            <i class="fas fa-lightbulb me-1"></i>
                            This is a premium reward that requires more points to unlock. Keep earning!
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Task 3 Modal - Free Wash & Dry Load -->
    <div class="modal fade" id="task3Modal" tabindex="-1" aria-labelledby="task3ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-purple text-white">
                    <h5 class="modal-title" id="task3ModalLabel">
                        <i class="fas fa-recycle me-2"></i> Free Wash & Dry Load
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 class="fw-bold text-dark mb-2">Reward Details</h6>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-coins me-2 text-warning"></i>
                            <strong>Points Required:</strong> 18 Points
                        </p>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-recycle me-2 text-success"></i>
                            <strong>Service Included:</strong> One (1) Free Wash & Dry Load
                        </p>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            <strong>Details:</strong> Redeem this premium reward to get one complete free wash and dry load at your next booking.
                        </p>
                    </div>
                    <div class="alert alert-info mb-0">
                        <small>
                            <i class="fas fa-star me-1 text-warning"></i>
                            This is our most valuable reward! Complete 18 bookings to unlock this exclusive benefit.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    </div><!-- end profile-grid -->

    <!-- Booking & Payment Section -->
    <div>
        <div class="container-box">
            <ul class="nav nav-tabs">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#schedule">Schedule</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#payment">Payment History</a></li>
            </ul>
            <div class="tab-content mt-3">
                <!-- Booking Schedule -->
                <div class="tab-pane fade show active" id="schedule">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="fw-semibold">Schedule</th>
                                            <th class="fw-semibold">Time Slot</th>
                                            <th class="fw-semibold">Machines</th>
                                            <th class="fw-semibold">Services</th>
                                            <th class="fw-semibold">Request</th>
                                            <th class="fw-semibold">Queue</th>
                                            <th class="fw-semibold">Pickup Status</th>
                                            <th class="fw-semibold">Status</th>
                                            <th class="fw-semibold text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php 
                                        if (is_object($booking_query) && mysqli_num_rows($booking_query) > 0) {
                                            $booking_count = 0;
                                            while ($booking = mysqli_fetch_assoc($booking_query)) : 
                                                $booking_count++;
                                                
                                                // Parse and format service types using the new function
                                                $service_names = parseServiceNames($booking['service_type']);
                                                
                                                // Calculate estimated amount using database prices
                                                $final_amount = !empty($booking['final_amount']) && $booking['final_amount'] > 0 ? $booking['final_amount'] : calculateBookingAmountFromDB($booking, $services_from_db);
                                                
                                                // Format booking date
                                                $booking_date_display = 'No Date';
                                                $booking_day_display = '';
                                                
                                                if (!empty($booking['booking_date']) && $booking['booking_date'] != '0000-00-00') {
                                                    $booking_date_display = date("F j, Y", strtotime($booking['booking_date']));
                                                    $booking_day_display = date("D", strtotime($booking['booking_date']));
                                                }
                                                
                                                // Parse request services
                                                $request_services = [];
                                                if (!empty($booking['request_service'])) {
                                                    $request_services = array_map('trim', explode(',', $booking['request_service']));
                                                    $request_services = array_filter($request_services);
                                                }
                                                
                                                // Determine status
                                                $status = strtolower(trim($booking['status']));
                                                $status_classes = [
                                                    'pending' => ['bg' => 'bg-secondary', 'text' => 'text-white', 'icon' => 'clock'],
                                                    'completed' => ['bg' => 'bg-secondary', 'text' => 'text-white', 'icon' => 'check-circle'],
                                                    'cancelled' => ['bg' => 'bg-secondary', 'text' => 'text-white', 'icon' => 'x-circle']
                                                ];
                                                
                                                $status_config = $status_classes[$status] ?? 
                                                                ['bg' => 'bg-secondary', 'text' => 'text-white', 'icon' => 'question-circle'];
                                                
                                                // Determine pickup status
                                                $pickup_status = strtolower(trim($booking['pickup_status'] ?? ''));
                                                $pickup_badge = '';
                                                
                                                if ($pickup_status == 'already picked') {
                                                    $pickup_badge = 'Picked Up';
                                                } elseif ($pickup_status == 'waiting for pick up') {
                                                    $pickup_badge = 'Waiting';
                                                } else {
                                                    $pickup_badge = 'N/A';
                                                }
                                                
                                                // Determine if booking can be modified
                                                // Show action buttons if booking is NOT in a final stage (not completed, cancelled, or missed)
                                                $final_stages = ['Completed / Picked Up', 'Missed Pickup'];
                                                $order_stage = $booking['order_stage'] ?? '';
                                                $payment_method = $booking['payment_method'] ?? 'GCASH';
                                                $cod_confirmation_photo = $booking['cod_confirmation_photo'] ?? '';
                                                
                                                // For COD payments: keep showing actions until photo is uploaded
                                                // For other payments: follow normal final stages logic
                                                if ($payment_method === 'Cash on Delivery' && $order_stage === 'Completed / Picked Up' && empty($cod_confirmation_photo)) {
                                                    // COD payment after delivery confirmed but photo not uploaded yet - show actions
                                                    $can_modify = true;
                                                } else {
                                                    // Normal logic for other payment methods
                                                    $can_modify = !in_array($order_stage, $final_stages) && ($status != 'cancelled');
                                                }
                                                
                                                $is_completed = in_array($order_stage, $final_stages) || ($status == 'completed');
                                                $is_cancelled = ($status == 'cancelled');
                                                
                                                // Determine row class
                                                $row_class = $is_cancelled ? 'text-muted bg-light' : '';
                                                
                                                // Determine machine icon color
                                                $machine_icon_color = $is_cancelled ? 'text-muted' : 'text-primary';
                                                
                                                // Check if points were claimed
                                                $points_claimed = !empty($booking['points_claimed']) ? $booking['points_claimed'] : 0;
                                        ?>
                                        <tr class="<?php echo $row_class; ?>" data-booking-id="<?php echo $booking['id']; ?>">
                                            <!-- Schedule Date -->
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium"><?php echo $booking_date_display; ?></span>
                                                    <?php if ($booking_day_display): ?>
                                                    <small class="text-muted"><?php echo $booking_day_display; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Time Slot -->
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?php echo htmlspecialchars($booking['time_slot'] ?? 'Not specified'); ?>
                                                </span>
                                            </td>
                                            
                                            <!-- Machines -->
                                            <td>
                                                <div class="d-flex align-items-center gap-1">
                                                    <div class="d-flex flex-column">
                                                        <span><?php echo $booking['machine_count'] ?? '1'; ?></span>
                                                        <?php if (!empty($booking['machine_names'])): ?>
                                                        <small class="text-muted" title="<?php echo htmlspecialchars($booking['machine_names']); ?>">
                                                            <i class="fas fa-info-circle"></i>
                                                        </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- Services -->
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php 
                                                    if (!empty($service_names)) {
                                                        foreach ($service_names as $service) {
                                                            echo '<span class="badge bg-light text-dark border">' . 
                                                                 htmlspecialchars($service) . '</span>';
                                                        }
                                                    } else {
                                                        echo '<span class="badge bg-light text-muted border">No services</span>';
                                                    }
                                                    ?>
                                                </div>
                                                <?php if ($final_amount > 0 && $status == 'pending'): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-money-bill-wave me-1"></i>
                                                            Est: ?<?php echo number_format($final_amount, 2); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Request Services -->
                                            <td>
                                                <?php if (!empty($request_services)): ?>
                                                    <div class="d-flex flex-column gap-1">
                                                        <?php foreach ($request_services as $request_service): ?>
                                                            <span><?php echo htmlspecialchars($request_service); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span>None</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Queue Number -->
                                            <td>
                                                <?php
                                                    $queue_code_display = $booking['queue_code'] ?? null;
                                                    if (empty($queue_code_display) && !empty($booking['queue_number'])) {
                                                        $queue_code_display = 'Q-' . str_pad((string)$booking['queue_number'], 3, '0', STR_PAD_LEFT);
                                                    }
                                                ?>
                                                <?php if (!empty($queue_code_display)): ?>
                                                    <?php echo htmlspecialchars($queue_code_display); ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Pickup Status -->
                                            <td>
                                                <?php echo $pickup_badge; ?>
                                            </td>
                                            
                                            <!-- Status -->
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <span><?php echo ucfirst($status); ?></span>
                                                    <small class="text-muted"><?php echo htmlspecialchars($booking['order_stage'] ?? 'Pending / Booked'); ?></small>
                                                    <small class="text-muted">ETA: <?php echo !empty($booking['estimated_completion_time']) ? date('M d, h:i A', strtotime($booking['estimated_completion_time'])) : 'TBD'; ?></small>
                                                    <?php if ($points_claimed): ?>
                                                        <small class="text-success">Points Claimed</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                    
                                            <!-- Actions -->
                                            <td class="text-center">
                                                <div class="d-flex gap-2 justify-content-center">
                                                    <?php if ($can_modify): ?>
                                                        <?php if ($payment_method === 'Cash on Delivery' && $order_stage === 'Completed / Picked Up' && empty($cod_confirmation_photo)): ?>
                                                            <!-- For COD after delivery: Show Upload Receipt button instead of normal actions -->
                                                            <button class="btn btn-sm btn-secondary text-white payBtn"
                                                                    onclick="openPaymentModal(event, '<?php echo $payment_method; ?>')"
                                                                    data-id="<?php echo $booking['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                                    data-service="<?php echo htmlspecialchars($booking['service_type'] ?? ''); ?>"
                                                                    data-detergent="<?php echo htmlspecialchars($booking['detergent'] ?? 'N/A'); ?>"
                                                                    data-machine-count="<?php echo htmlspecialchars($booking['machine_count'] ?? '1'); ?>"
                                                                    data-estimated-amount="<?php echo $final_amount; ?>"
                                                                    data-request="<?php echo htmlspecialchars($booking['request_service'] ?? ''); ?>"
                                                                    data-booking-date="<?php echo htmlspecialchars($booking['booking_date']); ?>"
                                                                    data-time-slot="<?php echo htmlspecialchars($booking['time_slot'] ?? ''); ?>"
                                                                    data-machines="<?php echo htmlspecialchars($booking['machine_names'] ?? ''); ?>"
                                                                    data-queue-code="<?php echo htmlspecialchars($booking['queue_code'] ?? ''); ?>"
                                                                    data-payment-method="<?php echo htmlspecialchars($payment_method); ?>"
                                                                    style="color: white !important;"
                                                                    data-bs-toggle="tooltip"
                                                                    title="Upload delivery receipt">
                                                                <i class="fas fa-upload me-1"></i>Upload Laundry Proof
                                                            </button>
                                                        <?php else: ?>
                                                            <!-- Reschedule Button -->
                                                            <?php if ($order_stage === 'Pending / Booked'): ?>
                                                                <a href="reschedule_booking.php?booking_id=<?php echo $booking['id']; ?>" 
                                                                class="btn btn-sm btn-secondary text-white"
                                                                data-bs-toggle="tooltip"
                                                                title="Reschedule Booking">
                                                                    Reschedule
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-secondary text-white disabled" 
                                                                        style="opacity: 0.6; cursor: not-allowed;"
                                                                        data-bs-toggle="tooltip" 
                                                                        title="The booking has already started and can no longer be rescheduled.">
                                                                    Reschedule
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Payment Button with estimated amount - Hidden for GCASH, IN_STORE, and COD payments -->
                                                            <?php if ($payment_method !== 'GCASH' && $payment_method !== 'IN_STORE' && $payment_method !== 'Cash on Delivery' && $payment_method !== 'COD'): ?>
                                                            <button class="btn btn-sm btn-secondary text-white payBtn"
                                                                    onclick="openPaymentModal(event, '<?php echo $payment_method; ?>')"
                                                                    data-id="<?php echo $booking['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                                    data-service="<?php echo htmlspecialchars($booking['service_type'] ?? ''); ?>"
                                                                    data-detergent="<?php echo htmlspecialchars($booking['detergent'] ?? 'N/A'); ?>"
                                                                    data-machine-count="<?php echo htmlspecialchars($booking['machine_count'] ?? '1'); ?>"
                                                                    data-estimated-amount="<?php echo $final_amount; ?>"
                                                                    data-request="<?php echo htmlspecialchars($booking['request_service'] ?? ''); ?>"
                                                                    data-booking-date="<?php echo htmlspecialchars($booking['booking_date']); ?>"
                                                                    data-time-slot="<?php echo htmlspecialchars($booking['time_slot'] ?? ''); ?>"
                                                                    data-machines="<?php echo htmlspecialchars($booking['machine_names'] ?? ''); ?>"
                                                                    data-queue-code="<?php echo htmlspecialchars($booking['queue_code'] ?? ''); ?>"
                                                                    data-payment-method="<?php echo htmlspecialchars($payment_method); ?>"
                                                                    style="color: white !important;">
                                                                Pay (₱<?php echo number_format($final_amount, 2); ?>)
                                                            </button>
                                                            <?php endif; ?>

                                                            <!-- Cancel Button (JavaScript only) -->
                                                            <button class="btn btn-sm btn-secondary text-white" 
                                                                    onclick="confirmCancel(<?php echo $booking['id']; ?>)"
                                                                    data-bs-toggle="tooltip"
                                                                    title="Cancel Booking">
                                                                Cancel
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                    <?php else: ?>
                                                        <!-- No actions for completed/cancelled bookings -->
                                                        <span class="text-muted small">No actions available</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php } else { ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-calendar-times fs-4 d-block mb-2"></i>
                                                <span class="d-block mb-1">No bookings found</span>
                                                <small class="text-muted">Start by making your first booking!</small>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                
                                <?php if (isset($booking_count) && $booking_count > 0): ?>
                                <div class="card-footer bg-transparent border-top-0 pt-3">
                                    <div class="text-muted text-center small">
                                        Showing <?php echo $booking_count; ?> booking<?php echo $booking_count == 1 ? '' : 's'; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Payment History -->
                <div class="tab-pane fade" id="payment">   
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="fw-semibold">ID</th>
                                    <th>Customer</th>
                                    <th>Services</th>
                                    <th>Addons</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $payment_query = mysqli_query($conn, "
                                    SELECT 
                                        t.id,
                                        t.customer_name,
                                        t.service_type,
                                        t.total_amount,
                                        t.transaction_date,
                                        t.points_earned,
                                        t.laundry_weight,
                                        b.id AS booking_id,
                                        b.service_type AS booked_services,
                                        b.detergent,
                                        b.machine_count,
                                        b.time_slot,
                                        b.booking_date,
                                        COALESCE(gr.payment_method, t.payment_method) AS payment_method,
                                        'transaction' AS record_type
                                    FROM transactions t
                                    LEFT JOIN bookings b ON t.booking_id = b.id
                                    LEFT JOIN gcash_requests gr ON t.booking_id = gr.booking_id
                                    WHERE t.user_id = '$user_id'
                                    
                                    UNION ALL
                                    
                                    SELECT 
                                        gr.id,
                                        CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                                        b.service_type,
                                        gr.amount AS total_amount,
                                        gr.approved_at AS transaction_date,
                                        0 AS points_earned,
                                        0 AS laundry_weight,
                                        b.id AS booking_id,
                                        b.service_type AS booked_services,
                                        b.detergent,
                                        b.machine_count,
                                        b.time_slot,
                                        b.booking_date,
                                        gr.payment_method,
                                        'payment_request' AS record_type
                                    FROM gcash_requests gr
                                    JOIN bookings b ON gr.booking_id = b.id
                                    JOIN users u ON gr.user_id = u.id
                                    WHERE gr.user_id = '$user_id' AND gr.status = 'approved'
                                    
                                    ORDER BY transaction_date DESC
                                ") or die(mysqli_error($conn));

                                while ($payment = mysqli_fetch_assoc($payment_query)) : 
                                    // Parse services for display
                                    $services = parseServiceNames($payment['booked_services']);
                                    $services_html = '';
                                    foreach ($services as $service) {
                                        if (!$service || $service === 'N/A') continue;
                                        
                                        $services_html .= '<span class="me-1 mb-1">' . htmlspecialchars($service) . '</span>';
                                    }
                                    
                                    // Parse detergents for display
                                    $detergents_html = '';
                                    if (!empty($payment['detergent']) && $payment['detergent'] !== 'N/A') {
                                        $detergents = explode(',', $payment['detergent']);
                                        foreach ($detergents as $detergent) {
                                            $detergent = trim($detergent);
                                            if (!$detergent || $detergent === 'Bring my own detergent') continue;
                                            
                                            $detergents_html .= '<span class="me-1 mb-1">' . htmlspecialchars($detergent) . '</span>';
                                        }
                                    }
                                    
                                    if (empty($detergents_html)) {
                                        $detergents_html = '<span class="badge bg-light text-muted">None</span>';
                                    }
                                ?>
                                <tr>
                                    <td>#<?php echo $payment['id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php echo $services_html; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php echo $detergents_html; ?>
                                        </div>
                                    </td>
                                    <td>₱<?php echo number_format($payment['total_amount'], 2); ?></td>
                                    <td>
                                        <?php 
                                            $method = $payment['payment_method'];
                                            echo ($method === 'IN_STORE') ? 'In-Store' : htmlspecialchars($method);
                                        ?>
                                    </td>
                                    <td><?php echo date("M j, Y", strtotime($payment['transaction_date'])); ?></td>
                                    <td>
                                        <a href="../admin/print_receipt.php?id=<?php echo $payment['id']; ?>&source=<?php echo $payment['record_type']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-3" title="Print Receipt">
                                            <i class="fas fa-print me-1"></i> Print
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="process_payment_user.php" class="modal-content" id="paymentForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-bill me-2"></i> Process Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Pricing:</strong> Service prices are based on our current rates from the database.
                    </div>
                    
                    <input type="hidden" name="booking_id" id="modalBookingId">
                    <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                    <input type="hidden" name="machine_count" id="machineCount" value="1">
                    <input type="hidden" name="service_request" id="serviceRequestInput" value="">

                    <!-- Customer Info -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Customer Name</label>
                            <input type="text" id="customerName" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Booking Status</label>
                            <input type="text" class="form-control" value="Pending Payment" readonly>
                        </div>
                    </div>

                    <!-- Selected Services -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Selected Service(s)</label>
                        <div id="selectedServicesDisplay" class="p-3 bg-light rounded border">
                            <div class="text-muted">Loading services...</div>
                        </div>
                    </div>

                    <!-- Laundry Weight -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Laundry Weight (kg)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="laundryWeight" name="laundry_weight" placeholder="Enter weight" min="0" step="0.1">
                                <button type="button" id="addWeightBtn" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> Add
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Payment Method</label>
                            <select class="form-select" name="payment_method" id="paymentMethod">
                                <option value="Cash">Cash</option>
                                <option value="GCASH">GCASH</option>
                            </select>
                        </div>
                    </div>

                    <!-- Payment Items Table -->
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered" id="paymentItemsTable">
                            <thead class="table-light">
                                    <th>Service(s)</th>
                                    <th>Weight</th>
                                    <th>Service Price</th>
                                    <th>Request</th>
                                    <th>Total Amount</th>
                                    <th>Points</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr id="noItemsRow">
                                    <td colspan="7" class="text-center text-muted py-3">
                                        Click "Add" button to calculate payment
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Cash Amount -->
                    <div class="row mb-4">
                        <div class="col-md-6" id="cashAmountField">
                            <label class="form-label fw-bold">Cash Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">?</span>
                                <input type="number" class="form-control" name="cash_amount" id="cashAmount" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <!-- Payment Summary -->
                    <div class="bg-light p-4 rounded mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>Total Amount:</strong>
                                    <span id="totalAmountDisplay" class="fw-bold text-success">?0.00</span>
                                    <input type="hidden" name="total_amount" id="totalAmountInput" value="0">
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>Points Earned:</strong>
                                    <span id="totalPointsDisplay" class="fw-bold text-warning">1</span>
                                    <input type="hidden" name="total_points" id="totalPointsInput" value="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div id="changeSection">
                                    <div class="d-flex justify-content-between mb-2">
                                        <strong>Change:</strong>
                                        <span id="changeAmount" class="fw-bold text-info">?0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="submitPaymentBtn">
                        <i class="fas fa-check-circle me-1"></i> Complete Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Success Modal -->
    <div class="modal fade" id="paymentSuccessModal" tabindex="-1" aria-labelledby="paymentSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="paymentSuccessModalLabel">
                        <i class="fas fa-check-circle me-2"></i> Payment Successful
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="fas fa-check me-2"></i> Payment was processed successfully!
                    </div>

                    <div class="transaction-details">
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Transaction ID:</div>
                            <div class="col-6" id="successTransactionId"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Customer:</div>
                            <div class="col-6" id="successCustomerName"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Amount Paid:</div>
                            <div class="col-6" id="successAmount"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Points Earned:</div>
                            <div class="col-6" id="successPoints"></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">New Points Balance:</div>
                            <div class="col-6" id="successNewBalance"></div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i> You will be automatically redirected in 5 seconds...
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="paymentSuccessOkBtn">
                        <i class="fas fa-check me-2"></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="paymentDetailsModalLabel">
                        <i class="fas fa-receipt me-2"></i> Transaction Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="paymentDetailsContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading transaction details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printDetailsBtn">
                        <i class="fas fa-print me-2"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- GCASH Payment Modal - Shows QR Code for Approved Payments -->
<div class="modal fade" id="gcashPaymentModal" tabindex="-1" aria-labelledby="gcashPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pay-modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gcashPaymentModalLabel">
                    <i class="fas fa-mobile-alt me-2"></i> GCASH Payment Instructions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="pay-approved-banner mb-4">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Payment request approved!</strong>
                    <span class="d-block mt-1 small fw-normal">Complete your payment using the details below.</span>
                </div>

                <div class="gcash-qr-container mb-4">
                    <img id="gcashQrImage" src="../assets/images/gcash-qr-placeholder.png"
                         alt="GCASH QR Code"
                         class="img-fluid gcash-qr-img"
                         onclick="openFullscreenQR()">
                    <p class="gcash-qr-hint"><i class="fas fa-search-plus me-1"></i>Tap to enlarge</p>
                </div>

                <div class="pay-detail-card mb-3">
                    <div class="pay-detail-card-head">GCASH Payment Details</div>
                    <div class="pay-detail-row">
                        <span class="pd-label">GCASH Number</span>
                        <span class="pd-value" id="gcashNumber">0917 123 4567</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Account Name</span>
                        <span class="pd-value" id="gcashAccountName">Jorish Express Laundry</span>
                    </div>
                    <div class="pay-detail-row pay-amount-row">
                        <span class="pd-label">Amount to Pay</span>
                        <span class="pd-value pd-amount" id="gcashAmountToPay">₱0.00</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Reference No.</span>
                        <span class="pd-value" id="gcashReferenceNo">-</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Booking ID</span>
                        <span class="pd-value" id="gcashBookingId">-</span>
                    </div>
                </div>

                <div class="pay-warning-banner mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> After payment, wait for staff confirmation. Your booking status updates automatically.
                </div>

                <div class="d-flex gap-2">
                    <button class="btn-indigo flex-fill" onclick="copyGCASHDetails()">
                        <i class="fas fa-copy me-1"></i> Copy Details
                    </button>
                    <button class="btn-outline-indigo flex-fill" data-bs-dismiss="modal">
                        <i class="fas fa-clock me-1"></i> I'll Pay Later
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cash on Delivery Payment Modal -->
<div class="modal fade" id="codPaymentModal" tabindex="-1" aria-labelledby="codPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content pay-modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="codPaymentModalLabel">
                    <i class="fas fa-truck me-2"></i> Cash on Delivery — Payment Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="pay-approved-banner mb-4">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>COD request approved!</strong>
                    <span class="d-block mt-1 small fw-normal">Review your booking details and confirm readiness for delivery.</span>
                </div>

                <div class="pay-detail-card mb-3">
                    <div class="pay-detail-card-head">Booking Summary</div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Customer Name</span>
                        <span class="pd-value" id="codCustomerName">-</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Booking ID</span>
                        <span class="pd-value" id="codBookingId">-</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Booking Date</span>
                        <span class="pd-value" id="codBookingDate">-</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Time Slot</span>
                        <span class="pd-value" id="codTimeSlot">-</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Service(s)</span>
                        <span class="pd-value" id="codServices">-</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Requested Services</span>
                        <span class="pd-value" id="codRequestedServices">-</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">No. of Machines</span>
                        <span class="pd-value" id="codMachineCount">-</span>
                    </div>
                    <div class="pay-detail-row">
                        <span class="pd-label">Machine(s)</span>
                        <span class="pd-value" id="codMachines">-</span>
                    </div>
                    <div class="pay-detail-row" id="codQueueRow" style="display: none;">
                        <span class="pd-label">Queue Code</span>
                        <span class="pd-value" id="codQueueCode">-</span>
                    </div>
                    <div class="pay-detail-row" id="codDetergentRow" style="display: none;">
                        <span class="pd-label">Detergent / Addons</span>
                        <span class="pd-value" id="codDetergent">-</span>
                    </div>
                    <div class="pay-detail-row pay-amount-row">
                        <span class="pd-label">Total Amount</span>
                        <span class="pd-value pd-amount" id="codAmount">₱0.00</span>
                    </div>
                </div>

                <div class="pay-warning-banner mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Please have the exact cash amount ready upon delivery.
                </div>

                <!-- Photo Upload Section -->
                <div class="pay-photo-banner mb-3">
                    <i class="fas fa-camera me-2"></i>
                    <strong>Confirmation Photo:</strong> Upload a photo as proof you received your laundry.
                </div>
                
                <div class="mb-3">
                    <label for="codPhotoUpload" class="form-label fw-bold">Upload Photo:</label>
                    <input type="file" class="form-control" id="codPhotoUpload" accept="image/*" onchange="handleCODPhotoUpload(event)">
                    <small class="text-muted">Supported formats: JPG, PNG, GIF</small>
                </div>
                
                <div id="codPhotoPreview" style="display: none; margin-top: 15px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="fw-bold mb-0">Photo Preview:</p>
                        <button type="button" class="btn-outline-indigo btn-sm" onclick="removeCODPhoto()">
                            <i class="fas fa-trash me-1"></i> Remove Photo
                        </button>
                    </div>
                    <div class="text-center">
                        <img id="codPhotoPreviewImg" src="" alt="Preview" style="max-width: 100%; max-height: 300px; border-radius: 8px;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-indigo" id="markCODPaidBtn" onclick="completeCODOrder()">
                    <i class="fas fa-check-circle me-1"></i> Complete Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Fullscreen QR Modal -->
<div class="modal fade" id="fullscreenQRModal" tabindex="-1" aria-labelledby="fullscreenQRModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="fullscreenQRModalLabel">
                    <i class="fas fa-qrcode me-2"></i> GCASH QR Code
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="fullscreenQRImage" src="" alt="GCASH QR Code" class="img-fluid" style="max-width: 100%;">
                <p class="text-muted mt-3">Scan this QR code with your GCASH app to pay</p>
            </div>
        </div>
    </div>
</div>
</div>
</div><!-- end profile-body -->

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
                    <li class="list-group-item text-center text-muted">Loading notifications...</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="markAllReadBtn">Mark All as Read</button>
            </div>
        </div>
    </div>
</div>

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
  <div class="footer-copy">&copy; <?php echo date("Y"); ?> Jorish Express Laundry. All Rights Reserved.</div>
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
<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
<script src="../assets/lib/js/sweetalert2.min.js"></script>
<script src="../assets/js/confirmlogout-user.js"></script>
<script>
  document.getElementById('navToggler').addEventListener('click', function() {
    document.getElementById('jlNav').classList.toggle('open');
  });
</script>
<script>
// Function to open the correct payment modal based on payment method
function openPaymentModal(event, paymentMethod) {
    event.preventDefault();
    
    const button = event.target.closest('.payBtn');
    if (!button) return;
    
    const bookingId = button.getAttribute('data-id');
    const customerName = button.getAttribute('data-name');
    const serviceType = button.getAttribute('data-service');
    const detergent = button.getAttribute('data-detergent');
    const machineCount = button.getAttribute('data-machine-count');
    const amount = button.getAttribute('data-estimated-amount');
    const requestService = button.getAttribute('data-request');
    const bookingDate = button.getAttribute('data-booking-date');
    const timeSlot = button.getAttribute('data-time-slot');
    const machines = button.getAttribute('data-machines');
    const queueCode = button.getAttribute('data-queue-code');
    
    if (paymentMethod === 'GCASH') {
        // Show GCASH modal with payment details
        populateGCASHModal(bookingId, customerName, amount, serviceType);
        const gcashModal = new bootstrap.Modal(document.getElementById('gcashPaymentModal'), {});
        gcashModal.show();
    } else if (paymentMethod === 'Cash on Delivery' || paymentMethod === 'COD') {
        // Show COD modal with booking details
        populateCODModal(bookingId, customerName, bookingDate, timeSlot, serviceType, requestService, machineCount, machines, queueCode, detergent, amount);
        const codModal = new bootstrap.Modal(document.getElementById('codPaymentModal'), {});
        codModal.show();
    } else if (paymentMethod === 'IN_STORE') {
        // In-store payments are handled by admin staff only
        alert('In-store payment will be processed by our staff when you arrive for pickup.');
    } else {
        // Fallback: treat as COD if payment method is unrecognized
        populateCODModal(bookingId, customerName, bookingDate, timeSlot, serviceType, requestService, machineCount, machines, queueCode, detergent, amount);
        const codModal = new bootstrap.Modal(document.getElementById('codPaymentModal'), {});
        codModal.show();
    }
}

// Function to populate GCASH modal with data
function populateGCASHModal(bookingId, customerName, amount, serviceType) {
    document.getElementById('gcashAmountToPay').textContent = '₱' + parseFloat(amount).toFixed(2);
    document.getElementById('gcashBookingId').textContent = bookingId;
    document.getElementById('modalBookingId').value = bookingId;
}

// Function to populate COD modal with data
function populateCODModal(bookingId, customerName, bookingDate, timeSlot, serviceType, requestService, machineCount, machines, queueCode, detergent, amount) {
    document.getElementById('codCustomerName').textContent = customerName;
    document.getElementById('codBookingId').textContent = '#' + bookingId;
    
    // Format booking date
    if (bookingDate) {
        const dateObj = new Date(bookingDate);
        const formattedDate = dateObj.toLocaleDateString('en-US', { year: '2-digit', month: 'short', day: '2-digit' });
        document.getElementById('codBookingDate').textContent = formattedDate;
    }
    
    document.getElementById('codTimeSlot').textContent = timeSlot || '-';
    document.getElementById('codServices').textContent = serviceType || '-';
    document.getElementById('codRequestedServices').textContent = requestService || '-';
    document.getElementById('codMachineCount').textContent = machineCount || '1';
    document.getElementById('codMachines').textContent = machines || '-';
    
    // Show/hide optional fields
    if (queueCode) {
        document.getElementById('codQueueRow').style.display = '';
        document.getElementById('codQueueCode').textContent = queueCode;
    }
    
    if (detergent && detergent !== 'N/A') {
        document.getElementById('codDetergentRow').style.display = '';
        document.getElementById('codDetergent').textContent = detergent;
    }
    
    document.getElementById('codAmount').textContent = '₱' + parseFloat(amount).toFixed(2);
}

// Check for approved GCASH requests on page load
document.addEventListener('DOMContentLoaded', function() {
    checkApprovedGCASHRequests();
    handleGCASHRequestFromURL(); // Add this line to call the function
});

// Function to check for approved GCASH requests
function checkApprovedGCASHRequests() {
    fetch('../assets/ajax/check_gcash_approval.php')
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP error! status: ${response.status}, body: ${text}`);
                });
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (err) {
                    throw new Error(`Invalid JSON response from check_gcash_approval.php: ${text}`);
                }
            });
        })
        .then(data => {
            if (data.success && data.approved_requests && data.approved_requests.length > 0) {
                data.approved_requests.forEach(request => {
                    showGCASHApprovalNotification(request);
                });
            }
        })
        .catch(error => {
            console.error('Error checking GCASH requests:', error);
        });
}

// Function to show payment approval notification (both GCASH and COD)
function showGCASHApprovalNotification(request) {
    // Determine payment method and set messages accordingly
    const isGCASH = request.payment_method === 'GCASH' || !request.payment_method;
    const isCOD = request.payment_method === 'COD';
    
    const titleText = isGCASH ? 'GCASH Payment Confirmed!' : 'Cash on Delivery Confirmed!';
    const methodText = isGCASH ? 'GCASH' : 'Cash on Delivery';
    
    // Show a SweetAlert with simple confirmation
    Swal.fire({
        title: titleText,
        html: `
            <div class="text-left">
                <p><strong>Booking ID:</strong> #${request.booking_id}</p>
                <p><strong>Amount:</strong> ₱${parseFloat(request.amount).toFixed(2)}</p>
                ${isGCASH ? `<p><strong>Reference:</strong> ${request.reference_number || 'N/A'}</p>` : ''}
                <hr>
                <p class="text-success"><i class="fas fa-check-circle me-1"></i> Your ${methodText} payment has been confirmed!</p>
                ${isGCASH ? '<p>Your booking is now active and ready to proceed.</p>' : '<p>Your booking has been delivered, thank you for using our service!</p>'}
            </div>
        `,
        icon: 'success',
        confirmButtonText: 'Continue',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#6f42c1',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        // Both Continue and Cancel just close the modal - payment is already confirmed
        if (result.isConfirmed || result.isDismissed) {
            // Just close the modal, no action needed
        }
    });
}

// NEW FUNCTION: Show GCASH modal directly from request ID without page reload
function showGCASHPaymentModalFromData(requestId) {
    console.log('Fetching payment request details for ID:', requestId);

    fetch(`../assets/ajax/get_gcash_request.php?id=${requestId}`)
        .then(async response => {
            const text = await response.text();
            if (!response.ok) {
                throw new Error(`HTTP ${response.status} ${response.statusText}: ${text}`);
            }
            try {
                return JSON.parse(text);
            } catch (err) {
                throw new Error(`Invalid JSON response from get_gcash_request.php: ${text}`);
            }
        })
        .then(data => {
            console.log('Payment request data:', data);
            if (data.success && data.request) {
                const request = {
                    request_id: data.request.request_id,
                    booking_id: data.request.booking_id,
                    amount: data.request.amount,
                    reference_number: data.request.reference_number,
                    status: data.request.status,
                    approved_at: data.request.approved_at,
                    payment_method: data.request.payment_method || 'GCASH'
                };
                
                // Check payment method and show appropriate modal
                if (request.payment_method === 'CASH_ON_DELIVERY' || request.payment_method === 'Cash on Delivery') {
                    showCODPaymentModal(request);
                } else {
                    showGCASHPaymentModal(request);
                }
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message || 'Unable to load payment details.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(error => {
            console.error('Error fetching payment request:', error);
            Swal.fire({
                title: 'Error',
                text: `Failed to load payment details: ${error.message}`,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
}

// Function to show GCASH payment modal with QR code
function showGCASHPaymentModal(request) {
    // Update modal with request details
    document.getElementById('gcashAmountToPay').textContent = `₱${parseFloat(request.amount).toFixed(2)}`;
    document.getElementById('gcashReferenceNo').textContent = request.reference_number || 'N/A';
    document.getElementById('gcashBookingId').textContent = `#${request.booking_id}`;
    
    // Update QR code if custom QR for this booking exists
    if (request.qr_code) {
        document.getElementById('gcashQrImage').src = request.qr_code;
    } else {
        document.getElementById('gcashQrImage').src = '../assets/images/gcash-qr-placeholder.png';
    }
    
    // Store request data for later use
    window.currentGCASHRequest = request;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('gcashPaymentModal'));
    modal.show();
}

// Function to show Cash on Delivery payment modal with booking details
function showCODPaymentModal(request) {
    // Store request data for later use
    window.currentCODRequest = request;
    
    // Fetch booking details to populate the modal
    fetch(`../assets/ajax/get_booking_details.php?id=${request.booking_id}`)
        .then(async response => {
            const text = await response.text();
            if (!response.ok) {
                throw new Error(`HTTP ${response.status} ${response.statusText}: ${text}`);
            }
            try {
                return JSON.parse(text);
            } catch (err) {
                throw new Error(`Invalid JSON response: ${text}`);
            }
        })
        .then(data => {
            if (data.success && data.booking) {
                const booking = data.booking;
                
                // Update modal with booking details
                document.getElementById('codCustomerName').textContent = booking.customer_name;
                document.getElementById('codBookingId').textContent = `#${booking.id}`;
                document.getElementById('codBookingDate').textContent = booking.booking_date;
                document.getElementById('codTimeSlot').textContent = booking.time_slot;
                document.getElementById('codServices').textContent = booking.services.length > 0 ? booking.services.join(', ') : 'N/A';
                document.getElementById('codRequestedServices').textContent = booking.requested_services.length > 0 ? booking.requested_services.join(', ') : 'N/A';
                document.getElementById('codMachineCount').textContent = booking.machine_count;
                document.getElementById('codMachines').textContent = booking.machines.length > 0 ? booking.machines.join(', ') : 'N/A';
                document.getElementById('codAmount').textContent = booking.amount_formatted;
                
                // Show queue code if available
                if (booking.queue_code) {
                    document.getElementById('codQueueCode').textContent = booking.queue_code;
                    document.getElementById('codQueueRow').style.display = 'flex';
                } else {
                    document.getElementById('codQueueRow').style.display = 'none';
                }
                
                // Show detergent if available
                if (booking.detergent.length > 0) {
                    document.getElementById('codDetergent').textContent = booking.detergent.join(', ');
                    document.getElementById('codDetergentRow').style.display = 'flex';
                } else {
                    document.getElementById('codDetergentRow').style.display = 'none';
                }
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('codPaymentModal'));
                modal.show();
            } else {
                throw new Error(data.message || 'Failed to load booking details');
            }
        })
        .catch(error => {
            console.error('Error loading booking details:', error);
            Swal.fire({
                title: 'Error',
                text: `Failed to load booking details: ${error.message}`,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
}

// NEW FUNCTION: Handle payment request from URL parameter
function handleGCASHRequestFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    const viewGcashRequest = urlParams.get('view_gcash_request');
    const viewCODRequest = urlParams.get('view_cod_request');
    
    if (viewGcashRequest) {
        showGCASHPaymentModalFromData(viewGcashRequest);
        
        // Remove the parameter from URL without refreshing
        const newUrl = window.location.pathname;
        window.history.pushState({}, '', newUrl);
    } else if (viewCODRequest) {
        showGCASHPaymentModalFromData(viewCODRequest);
        
        // Remove the parameter from URL without refreshing
        const newUrl = window.location.pathname;
        window.history.pushState({}, '', newUrl);
    }
}

// Function to copy GCASH details
function copyGCASHDetails() {
    const gcashNumber = document.getElementById('gcashNumber').textContent;
    const accountName = document.getElementById('gcashAccountName').textContent;
    const amount = document.getElementById('gcashAmountToPay').textContent;
    const reference = document.getElementById('gcashReferenceNo').textContent;
    const bookingId = document.getElementById('gcashBookingId').textContent;
    
    const details = `GCASH Payment Details:\n\nNumber: ${gcashNumber}\nAccount: ${accountName}\nAmount: ${amount}\nReference: ${reference}\nBooking ID: ${bookingId}`;
    
    navigator.clipboard.writeText(details).then(() => {
        Swal.fire({
            title: 'Copied!',
            text: 'Payment details copied to clipboard',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    });
}

// Function to open fullscreen QR code
function openFullscreenQR() {
    const qrImage = document.getElementById('gcashQrImage');
    const fullscreenImg = document.getElementById('fullscreenQRImage');
    fullscreenImg.src = qrImage.src;
    const modal = new bootstrap.Modal(document.getElementById('fullscreenQRModal'));
    modal.show();
}

// Mark payment as completed
document.getElementById('markPaymentCompletedBtn')?.addEventListener('click', function() {
    const request = window.currentGCASHRequest;
    if (!request) return;
    
    Swal.fire({
        title: 'Confirm Payment',
        html: `
            <div class="text-left">
                <p>Have you completed the GCASH payment?</p>
                <p><strong>Booking ID:</strong> #${request.booking_id}</p>
                <p><strong>Amount:</strong> ₱${parseFloat(request.amount).toFixed(2)}</p>
                <p><strong>Reference:</strong> ${request.reference_number}</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Payment Completed',
        cancelButtonText: 'Not Yet'
    }).then((result) => {
        if (result.isConfirmed) {
            confirmGCASHPayment(request);
        }
    });
});

// Mark Cash on Delivery as Paid
document.getElementById('markCODPaidBtn')?.addEventListener('click', function() {
    const request = window.currentCODRequest;
    if (!request) return;
    
    Swal.fire({
        title: 'Confirm Payment',
        html: `
            <div class="text-left">
                <p>Are you ready for the Cash on Delivery payment?</p>
                <p><strong>Booking ID:</strong> #${request.booking_id}</p>
                <p><strong>Amount:</strong> ₱${parseFloat(request.amount).toFixed(2)}</p>
                <p class="text-muted mt-2">Make sure you have the exact amount in cash ready.</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, I am Ready',
        cancelButtonText: 'Not Yet'
    }).then((result) => {
        if (result.isConfirmed) {
            confirmCODPayment(request);
        }
    });
});

// Function to confirm GCASH payment
function confirmGCASHPayment(request) {
    fetch('../assets/ajax/confirm_gcash_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            request_id: request.request_id,
            booking_id: request.booking_id,
            reference_number: request.reference_number
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Payment Submitted',
                text: 'Your payment confirmation has been sent to admin for verification. You will be notified when it is approved.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                // Close the GCASH modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('gcashPaymentModal'));
                if (modal) modal.hide();
                // Refresh the page to show updated booking status and request status
                window.location.reload();
            });
        } else {
            throw new Error(data.message || 'Failed to submit payment confirmation');
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Error',
            text: error.message,
            icon: 'error'
        });
    });
}

// Function to confirm Cash on Delivery payment
function confirmCODPayment(request) {
    fetch('../assets/ajax/confirm_cod_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            request_id: request.request_id,
            booking_id: request.booking_id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Payment Confirmed',
                text: 'Your Cash on Delivery payment confirmation has been sent to admin for verification. You will be notified when it is approved.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                // Close the COD modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('codPaymentModal'));
                if (modal) modal.hide();
                // Refresh the page to show updated booking status and request status
                window.location.reload();
            });
        } else {
            throw new Error(data.message || 'Failed to confirm COD payment');
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Error',
            text: error.message,
            icon: 'error'
        });
    });
}

// COD Photo Upload Functions
let codPhotoFile = null;

function handleCODPhotoUpload(event) {
    const file = event.target.files[0];
    
    if (!file) {
        return;
    }
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        Swal.fire({
            title: 'Invalid File',
            text: 'Please select an image file',
            icon: 'error'
        });
        event.target.value = '';
        return;
    }
    
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            title: 'File Too Large',
            text: 'Please select a file smaller than 5MB',
            icon: 'error'
        });
        event.target.value = '';
        return;
    }
    
    // Store the file
    codPhotoFile = file;
    
    // Show preview
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('codPhotoPreviewImg').src = e.target.result;
        document.getElementById('codPhotoPreview').style.display = 'block';
    };
    reader.readAsDataURL(file);
    
    // Update button text and state
    const btn = document.getElementById('markCODPaidBtn');
    btn.textContent = '';
    btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Order Complete';
    btn.disabled = false;
}

function removeCODPhoto() {
    codPhotoFile = null;
    document.getElementById('codPhotoUpload').value = '';
    document.getElementById('codPhotoPreview').style.display = 'none';
    
    // Reset button text
    const btn = document.getElementById('markCODPaidBtn');
    btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Mark as Paid';
}

function completeCODOrder() {
    // Check if photo was uploaded
    if (!codPhotoFile) {
        Swal.fire({
            title: 'Photo Required',
            text: 'Please upload a photo as proof of delivery before marking the order complete',
            icon: 'warning'
        });
        return;
    }
    
    // Show confirmation
    Swal.fire({
        title: 'Confirm Order Completion',
        text: 'Are you sure you have received your laundry and want to mark this order as complete?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7c3aed',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Complete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Upload photo and complete order
            uploadCODPhotoAndComplete();
        }
    });
}

function uploadCODPhotoAndComplete() {
    // Get booking ID from the modal (you may need to store this separately)
    const bookingId = document.getElementById('codBookingId').textContent.replace('#', '');
    
    if (!bookingId) {
        Swal.fire({
            title: 'Error',
            text: 'Booking ID not found',
            icon: 'error'
        });
        return;
    }
    
    // Create FormData to send file
    const formData = new FormData();
    formData.append('booking_id', bookingId);
    formData.append('confirmation_photo', codPhotoFile);
    formData.append('action', 'upload_cod_confirmation');
    
    // Show loading
    Swal.fire({
        title: 'Processing',
        text: 'Uploading your confirmation photo...',
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Send to server
    fetch('../assets/ajax/upload_cod_confirmation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let pointsMessage = '';
            if (data.points_awarded && data.points_awarded > 0) {
                pointsMessage = `<div class="alert alert-success mt-3">
                    <i class="fas fa-star me-2"></i>
                    <strong>Points Earned!</strong> You earned ${data.points_awarded} point(s) for this order. Your new balance is ${data.total_points} points.
                </div>`;
            }
            
            Swal.fire({
                title: 'Success!',
                html: `Your booking is now complete, thank you for using our service${pointsMessage}`,
                icon: 'success'
            }).then(() => {
                // Close modal and refresh
                bootstrap.Modal.getInstance(document.getElementById('codPaymentModal')).hide();
                location.reload();
            });
        } else {
            throw new Error(data.message || 'Failed to upload photo');
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Error',
            text: error.message,
            icon: 'error'
        });
    });
}

// Check for reward_used parameter on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const rewardUsed = urlParams.get('reward_used');
    
    if (rewardUsed) {
        Swal.fire({
            title: 'Reward Voucher Applied!',
            html: `Your reward voucher <strong>"${rewardUsed}"</strong> has been successfully applied to your booking.<br><br>The voucher has been removed from your <strong>Claimed Rewards</strong> section.`,
            icon: 'success',
            confirmButtonText: 'Got it!',
            confirmButtonColor: '#6366f1',
            background: '#fff',
            backdrop: `rgba(99,102,241,0.15)`
        }).then(() => {
            // Remove the query parameter from URL without refreshing to keep it clean
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        });
    }

    // Check for rejected rewards from PHP
    <?php if (!empty($rejected_rewards)): ?>
        Swal.fire({
            title: 'Reward Claim Rejected',
            html: `<div class="text-center">
                    <i class="fas fa-exclamation-circle text-danger mb-3" style="font-size: 3rem;"></i>
                    <p>Your pending claimed reward <strong>"<?php echo htmlspecialchars($rejected_rewards['reward_name']); ?>"</strong> has been rejected.</p>
                    <p class="mb-0 text-success"><strong><i class="fas fa-undo me-1"></i> Your points has been refunded</strong></p>
                   </div>`,
            icon: 'info',
            confirmButtonText: 'I Understand',
            confirmButtonColor: '#6366f1',
            background: '#fff'
        }).then(() => {
            // Mark as read via AJAX
            fetch('acknowledge_reward.php?id=<?php echo $rejected_rewards['id']; ?>')
                .then(() => {
                    console.log('Reward rejection acknowledged');
                    location.reload();
                });
        });
    <?php endif; ?>
});
</script>
<?php if (isset($_SESSION['user_id'])): ?>
<script src="../assets/js/user-payment.js"></script>
<script src="../assets/js/notifications.js"></script>
<script src="../assets/js/confirmlogout-user.js"></script>
<script src="../assets/js/receipt-print.js"></script>
<script src="../assets/js/modal-tabs-init.js"></script>
<script src="../assets/js/rewards-claim.js"></script>
<script src="../assets/js/booking-actions.js"></script>
<script src="../assets/js/payment-details.js"></script>
<?php endif; ?>
</body>
</html>



