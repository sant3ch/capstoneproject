<?php
session_start();
require 'config.php';
$notifications = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT title, message, created_at FROM notifications
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Services & Pricing — Jorish Express Laundry</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/lib/css/all.min.css">
  <link rel="stylesheet" href="./assets/lib/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/lib/css/sweetalert2.min.css">
  <link rel="stylesheet" href="./assets/css/service-and-pricing.css">
</head>
<body>

<!-- ====== NAVBAR ====== -->
<nav class="jl-nav" id="jlNav">
  <a href="index.php" class="jl-logo">
    <img src="./assets/images/logo.png" alt="Jorish Express Laundry">
  </a>
  <ul class="jl-nav-links">
    <li><a href="index.php">Home</a></li>
    <li><a href="index.php#why">About Us</a></li>
    <li><a href="service-and-pricing.php" class="active">Services</a></li>
    <li><a href="contact-and-map-view.php">Find Location</a></li>

    <li><a href="index.php#news">Blog</a></li>
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
          <li><a class="dropdown-item" href="user/user-profile.php"><i class="fas fa-user-cog me-2"></i>Manage Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="#" onclick="confirmLogout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    <?php else: ?>
      <a href="login.php"    class="btn-nav-outline">Login</a>
      <a href="register.php" class="btn-nav-solid">Register</a>
    <?php endif; ?>
  </div>
  <button class="jl-nav-toggler" id="navToggler"><i class="fas fa-bars"></i></button>
</nav>

<!-- Notification Modal -->
<?php if (isset($_SESSION['user_id'])): ?>
<div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Notifications</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="markAllNotificationsRead()"></button>
      </div>
      <div class="modal-body">
        <ul class="list-group list-group-flush">
          <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $n): ?>
              <li class="list-group-item">
                <strong><?php echo htmlspecialchars($n['title'] ?? ''); ?></strong><br>
                <?php echo htmlspecialchars($n['message']); ?>
                <small class="text-muted d-block"><?php echo date("F j, Y, g:i A", strtotime($n['created_at'])); ?></small>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li class="list-group-item text-center text-muted">No new notifications.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ====== HERO BANNER ====== -->
<section class="service-hero">
  <div class="service-hero-content">
    <span class="hero-pill"><i class="fas fa-soap"></i> What We Offer</span>
    <h1>Services & Pricing</h1>
    <p>Professional laundry services designed to meet your needs and budget — affordable, fast, and reliable.</p>
  </div>
</section>

<!-- ====== SERVICES SECTION ====== -->
<section class="services-section">
  <div class="services-header">
    <span class="section-pill">Our Plans</span>
    <h2>Choose Your Service</h2>
    <p>Pick the plan that works best for you. All services use 8kg per load capacity.</p>
  </div>

  <div class="services-grid">

    <!-- Self-Service Card -->
    <div class="service-card">
      <div class="sc-icon-wrap sc-blue">
        <i class="fas fa-tshirt"></i>
      </div>
      <div class="sc-badge">Popular</div>
      <h3>Self-Service Laundry</h3>
      <div class="sc-price">
        <span class="price-from">from</span>
        <span class="price-amount">₱65</span>
        <span class="price-unit">/ load</span>
      </div>
      <p class="sc-desc">Enjoy our modern, efficient self-service washers and dryers. Ideal for quick and budget-friendly laundry.</p>
      <ul class="sc-features">
        <li><i class="fas fa-check"></i> 8kg per load capacity</li>
        <li><i class="fas fa-check"></i> Washing machines — ₱65/load</li>
        <li><i class="fas fa-check"></i> Drying machines — ₱80/load</li>
        <li><i class="fas fa-check"></i> Available detergents & softeners</li>
        <li><i class="fas fa-check"></i> Folding service — ₱30 extra</li>
      </ul>
      <div class="sc-actions">
        <button class="btn-sc-detail" data-bs-toggle="modal" data-bs-target="#selfServiceModal">
          View Details
        </button>
        <a href="user/book-now.php" class="btn-sc-book">Book Now</a>
      </div>
    </div>

    <!-- Full-Service Card -->
    <div class="service-card sc-featured">
      <div class="sc-icon-wrap sc-orange">
        <i class="fas fa-concierge-bell"></i>
      </div>
      <div class="sc-badge sc-badge-orange">Premium</div>
      <h3>Full-Service Laundry</h3>
      <div class="sc-price">
        <span class="price-from">from</span>
        <span class="price-amount">₱145</span>
        <span class="price-unit">/ load</span>
      </div>
      <p class="sc-desc">Our professional staff handles everything — washing, drying, and folding — so you don't have to.</p>
      <ul class="sc-features">
        <li><i class="fas fa-check"></i> 8kg per load capacity</li>
        <li><i class="fas fa-check"></i> Wash, dry & fold — ₱175</li>
        <li><i class="fas fa-check"></i> Wash & dry only — ₱145</li>
        <li><i class="fas fa-check"></i> Professional handling</li>
        <li><i class="fas fa-check"></i> Express service available</li>
      </ul>
      <div class="sc-actions">
        <button class="btn-sc-detail" data-bs-toggle="modal" data-bs-target="#fullServiceModal">
          View Details
        </button>
        <a href="user/book-now.php" class="btn-sc-book btn-sc-book-orange">Book Now</a>
      </div>
    </div>

  </div>
</section>

<!-- ====== WHY CHOOSE US ====== -->
<section class="why-section">
  <div class="why-header">
    <span class="section-pill section-pill-white">Why Jorish?</span>
    <h2>The Jorish Advantage</h2>
    <p>We go beyond just clean clothes — we deliver a premium experience every time.</p>
  </div>
  <div class="why-grid">
    <div class="why-item">
      <div class="why-icon"><i class="fas fa-bolt"></i></div>
      <h4>Fast Turnaround</h4>
      <p>Same-day service available. We respect your time as much as your clothes.</p>
    </div>
    <div class="why-item">
      <div class="why-icon"><i class="fas fa-shield-alt"></i></div>
      <h4>Safe & Gentle</h4>
      <p>Premium detergents and careful handling keep your fabrics fresh and damage-free.</p>
    </div>
    <div class="why-item">
      <div class="why-icon"><i class="fas fa-tags"></i></div>
      <h4>Affordable Rates</h4>
      <p>Transparent pricing with no hidden fees. Quality service at a price you'll love.</p>
    </div>
    <div class="why-item">
      <div class="why-icon"><i class="fas fa-gift"></i></div>
      <h4>Loyalty Points</h4>
      <p>Earn points every transaction and redeem them for discounts on future services.</p>
    </div>
  </div>
</section>

<!-- ====== SELF-SERVICE MODAL ====== -->
<div class="modal fade" id="selfServiceModal" tabindex="-1" aria-labelledby="selfServiceLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content jl-modal">
      <div class="modal-header jl-modal-header">
        <div class="modal-title-wrap">
          <div class="modal-icon-wrap"><i class="fas fa-tshirt"></i></div>
          <div>
            <h5 class="modal-title" id="selfServiceLabel">Self-Service Laundry</h5>
            <p class="modal-sub">Budget-friendly, flexible, efficient</p>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body jl-modal-body">
        <div class="modal-cols">
          <div class="modal-col">
            <div class="modal-price-tag">₱65 – ₱80 <span>per load</span></div>
            <p>Enjoy our modern and efficient self-service washers and dryers. Perfect for quick, budget-friendly laundry at your own pace.</p>
            <h6>What's Included:</h6>
            <ul class="modal-list">
              <li><i class="fas fa-check-circle"></i> Washing machines — <strong>₱65/load</strong></li>
              <li><i class="fas fa-check-circle"></i> Drying machines — <strong>₱80/load</strong></li>
              <li><i class="fas fa-check-circle"></i> Available detergents & fabric softeners</li>
              <li><i class="fas fa-check-circle"></i> Folding service — <strong>₱30</strong> additional</li>
            </ul>
          </div>
          <div class="modal-col modal-col-right">
            <h6>Best For:</h6>
            <ul class="modal-best-list">
              <li><i class="fas fa-user-graduate"></i> Students & Budget-conscious</li>
              <li><i class="fas fa-clock"></i> Quick laundry needs</li>
              <li><i class="fas fa-hand-paper"></i> Those who prefer DIY</li>
              <li><i class="fas fa-calendar-alt"></i> Flexible timing</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="modal-footer jl-modal-footer">
        <button type="button" class="btn-modal-close" data-bs-dismiss="modal">Close</button>
        <a href="user/book-now.php" class="btn-modal-book">Book Self-Service</a>
      </div>
    </div>
  </div>
</div>

<!-- ====== FULL-SERVICE MODAL ====== -->
<div class="modal fade" id="fullServiceModal" tabindex="-1" aria-labelledby="fullServiceLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content jl-modal">
      <div class="modal-header jl-modal-header jl-modal-header-orange">
        <div class="modal-title-wrap">
          <div class="modal-icon-wrap modal-icon-orange"><i class="fas fa-concierge-bell"></i></div>
          <div>
            <h5 class="modal-title" id="fullServiceLabel">Full-Service Laundry</h5>
            <p class="modal-sub">Professional, convenient, premium</p>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body jl-modal-body">
        <div class="modal-cols">
          <div class="modal-col">
            <div class="modal-price-tag modal-price-orange">₱145 – ₱175 <span>per load</span></div>
            <p>Our professional staff handles everything from washing to folding, so you can focus on what matters.</p>
            <h6>What's Included:</h6>
            <ul class="modal-list">
              <li><i class="fas fa-check-circle"></i> Wash, dry & fold — <strong>₱175</strong></li>
              <li><i class="fas fa-check-circle"></i> Wash & dry only — <strong>₱145</strong></li>
              <li><i class="fas fa-check-circle"></i> Available detergents & fabric softeners</li>
              <li><i class="fas fa-check-circle"></i> Express service options available</li>
            </ul>
          </div>
          <div class="modal-col modal-col-right">
            <h6>Best For:</h6>
            <ul class="modal-best-list">
              <li><i class="fas fa-briefcase"></i> Busy professionals</li>
              <li><i class="fas fa-users"></i> Families with kids</li>
              <li><i class="fas fa-bolt"></i> Time-saving solution</li>
              <li><i class="fas fa-star"></i> Premium service seekers</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="modal-footer jl-modal-footer">
        <button type="button" class="btn-modal-close" data-bs-dismiss="modal">Close</button>
        <a href="user/book-now.php" class="btn-modal-book btn-modal-book-orange">Book Full-Service</a>
      </div>
    </div>
  </div>
</div>

<!-- ====== FOOTER ====== -->
<footer class="jl-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <img src="./assets/images/logo.png" alt="Jorish Express Laundry">
      <span>Jorish Express Laundry</span>
    </div>
    <ul class="footer-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="index.php#why">About Us</a></li>
      <li><a href="service-and-pricing.php">Services</a></li>
      <li><a href="contact-and-map-view.php">Find Location</a></li>

      <li><a href="index.php#news">Blog</a></li>
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

<script src="./assets/lib/js/bootstrap.bundle.min.js"></script>
<?php if (isset($_SESSION['user_id'])): ?>
<script src="assets/lib/js/sweetalert2.min.js"></script>
<script src="assets/js/notifications.js"></script>
<script src="assets/js/confirmlogout-user.js"></script>
<?php endif; ?>
<script>
  document.getElementById('navToggler').addEventListener('click', function() {
    document.getElementById('jlNav').classList.toggle('open');
  });
</script>
</body>
</html>
