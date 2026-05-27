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
  <title>Contact & Map — Jorish Express Laundry</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/lib/css/all.min.css">
  <link rel="stylesheet" href="./assets/lib/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/lib/css/sweetalert2.min.css">
  <link rel="stylesheet" href="./assets/css/contact-and-map-view.css">
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
    <li><a href="service-and-pricing.php">Services</a></li>
    <li><a href="contact-and-map-view.php" class="active">Find Location</a></li>

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
<section class="contact-hero">
  <div class="contact-hero-content">
    <span class="hero-pill"><i class="fas fa-map-marker-alt"></i> Find Us</span>
    <h1>Contact & Location</h1>
    <p>We'd love to hear from you. Reach out, visit our shop, or drop us a message anytime.</p>
  </div>
</section>

<!-- ====== CONTACT SECTION ====== -->
<section class="contact-section">
  <div class="contact-inner">

    <!-- LEFT — Info Panel -->
    <div class="contact-info-panel">
      <h2>Get in Touch</h2>
      <p class="info-lead">Have questions? We're happy to help. Find us at our shop or send a message online.</p>

      <div class="info-block">
        <div class="info-icon"><i class="fas fa-phone-alt"></i></div>
        <div>
          <div class="info-label">Phone</div>
          <div class="info-value">0928 520 3517</div>
          <div class="info-value">046 537 3125</div>
        </div>
      </div>

      <div class="info-block">
        <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
        <div>
          <div class="info-label">Address</div>
          <div class="info-value">Queen's Row East, Bacoor, Cavite</div>
        </div>
      </div>

      <div class="info-block">
        <div class="info-icon"><i class="fas fa-clock"></i></div>
        <div>
          <div class="info-label">Business Hours</div>
          <div class="info-value">Mon – Sat: 7:00 AM – 8:00 PM</div>
          <div class="info-value">Sunday: 8:00 AM – 6:00 PM</div>
        </div>
      </div>

      <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank" class="fb-btn">
        <i class="fab fa-facebook-f"></i> Follow us on Facebook
      </a>
    </div>

    <!-- RIGHT — Contact Form -->
    <div class="contact-form-panel">
      <h2>Send a Message</h2>
      <p class="info-lead">Fill in the form below and we'll get back to you as soon as possible.</p>

      <form class="contact-form" id="contactForm">
        <div class="cf-group">
          <label for="cf_email">Email Address</label>
          <div class="cf-input-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" id="cf_email" name="email" placeholder="you@example.com" required>
          </div>
        </div>
        <div class="cf-group">
          <label for="cf_mobile">Mobile Number</label>
          <div class="cf-input-wrap">
            <i class="fas fa-phone"></i>
            <input type="text" id="cf_mobile" name="mobile" placeholder="+63 912 345 6789">
          </div>
        </div>
        <div class="cf-group">
          <label for="cf_message">Your Message</label>
          <div class="cf-input-wrap">
            <i class="fas fa-comment-dots" style="top:18px;transform:none;"></i>
            <textarea id="cf_message" name="message" rows="5" placeholder="Write your message here..." required></textarea>
          </div>
        </div>
        <button type="submit" class="btn-contact-submit">
          Send Message &nbsp;<i class="fas fa-paper-plane"></i>
        </button>
      </form>
    </div>

  </div>
</section>

<!-- ====== MAP SECTION ====== -->
<section class="map-section">
  <div class="map-header">
    <span class="section-pill">Our Location</span>
    <h2>Visit Our Shop</h2>
    <p>Located at Queen's Row East, Bacoor, Cavite — easy to find, easy to visit.</p>
  </div>
  <div class="map-frame-wrap">
    <iframe
      src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d7722.829850373023!2d120.9880949!3d14.3982934!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397d17ec8117e81%3A0xf02365efec53ef62!2sJORISH%20LAUNDRY!5e0!3m2!1sen!2sph!4v1708420000000!5m2!1sen!2sph"
      allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
    </iframe>
  </div>
</section>

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
