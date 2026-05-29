<?php
session_start();

require 'config.php';
require_once 'includes/content-helpers.php';

// Admin-managed About page content
$about_title   = getSetting($conn, 'about_title', 'ABOUT US');
$about_tagline = getSetting($conn, 'about_tagline', 'Your Trusted Partner in Laundry Care');
$about_desc    = getSetting($conn, 'about_description', 'We are professionals and are committed to providing quality laundry and dry cleaning services.');
$about_image   = getSetting($conn, 'about_image', 'assets/images/aboutus.png');
$core_values   = getCoreValues($conn);

// Fetch notifications if logged in
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
    <title>About Us - Jorish Express Laundry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/lib/css/all.min.css">
    <link rel="stylesheet" href="./assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="./assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="./assets/css/index.css">
    <link rel="stylesheet" href="./assets/css/aboutus.css">
</head>
<body>

<!-- ====== NAVBAR ====== -->
<nav class="jl-nav" id="jlNav">
  <a href="index.php" class="jl-logo">
    <img src="./assets/images/logo.png" alt="Jorish Express Laundry">
  </a>
  <ul class="jl-nav-links">
    <li><a href="index.php">Home</a></li>
    <li><a href="service-and-pricing.php">Services</a></li>
    <li><a href="contact-and-map-view.php">Find Location</a></li>
    <li><a href="aboutus.php" class="active">About Us</a></li>
    <li><a href="blog.php">Blog</a></li>
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
<main>
    <!-- First Container: Image and About Text -->
    <div class="about-first-container">
        <div class="about-content-row">
            <!-- Left Side: Image -->
            <div class="about-image-section">
                <img src="./<?php echo htmlspecialchars($about_image); ?>" alt="About Jorish Express Laundry" class="about-main-image" onerror="this.src='./assets/images/aboutus.png'">
                <p class="image-caption">Professional Laundry Services</p>
            </div>

            <!-- Right Side: About Text -->
            <div class="about-text-section">
                <h1 class="about-title"><?php echo htmlspecialchars($about_title); ?></h1>
                <h2 class="about-tagline"><?php echo htmlspecialchars($about_tagline); ?></h2>
                <p class="about-description">
                    <?php echo nl2br(htmlspecialchars($about_desc)); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Second Container: Our Core Values -->
    <div class="about-second-container">
        <h2 class="features-title">Our Core Values</h2>
        <div class="features-grid">
            <?php if (count($core_values) > 0): foreach ($core_values as $v): ?>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="<?php echo htmlspecialchars($v['icon_class']); ?>"></i>
                </div>
                <h3><?php echo htmlspecialchars($v['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($v['description'])); ?></p>
            </div>
            <?php endforeach; else: ?>
            <p class="text-center text-muted">No core values to display yet.</p>
            <?php endif; ?>
        </div>
    
        <!-- Book Now Button -->
        <div class="book-now-section">
            <a href="user/book-now.php" class="btn btn-primary book-now-btn">
                    Book Now! <i class="fas fa-calendar-check"></i>
                </a>
            <p class="book-now-tagline">Experience a whole new level of convenience!</p>
        </div>
    </div>
</main>

<!-- ====== FOOTER ====== -->
<footer class="jl-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <img src="./assets/images/logo.png" alt="Jorish Express Laundry">
      <span>Jorish Express Laundry</span>
    </div>
    <ul class="footer-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="service-and-pricing.php">Services</a></li>
      <li><a href="contact-and-map-view.php">Contact</a></li>
      <li><a href="aboutus.php">About Us</a></li>
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


