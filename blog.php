<?php
session_start();
require 'config.php';
require_once 'includes/content-helpers.php';

$posts = getPublishedPosts($conn);

$notifications = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT title, message, created_at FROM notifications
        WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$gradients = [
    'linear-gradient(135deg,#e0e7ff,#a5b4fc)',
    'linear-gradient(135deg,#fde8d0,#f5c49a)',
    'linear-gradient(135deg,#d4f7d4,#8edfa0)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blog — Jorish Express Laundry</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/lib/css/all.min.css">
  <link rel="stylesheet" href="./assets/lib/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/lib/css/sweetalert2.min.css">
  <link rel="stylesheet" href="./assets/css/index.css">
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
    <li><a href="contact-and-map-view.php">Find Location</a></li>
    <li><a href="blog.php" class="active">Blog</a></li>
  </ul>
  <div class="jl-nav-right">
    <?php if (!isset($_SESSION['user_id'])): ?>
      <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>
      <a href="contact-and-map-view.php" class="btn-jl btn-jl-blue">Contact Us</a>
    <?php else: ?>
      <button class="btn-bell" data-bs-toggle="modal" data-bs-target="#notificationModal">
        <i class="fas fa-bell"></i>
        <?php if (count($notifications) > 0): ?><span class="bell-badge"><?php echo count($notifications); ?></span><?php endif; ?>
      </button>
      <div class="dropdown">
        <button class="btn-user dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="./user/user-profile.php"><i class="fas fa-user-cog me-2"></i>Manage Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger logout-link" href="javascript:void(0);"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    <?php endif; ?>
  </div>
  <button class="jl-nav-toggler" id="navToggler"><i class="fas fa-bars"></i></button>
</nav>

<?php if (isset($_SESSION['user_id'])): ?>
<div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px;border:none;">
      <div class="modal-header" style="border-bottom:2px solid var(--blue);">
        <h5 class="modal-title" style="font-weight:700;color:var(--blue);">Notifications</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="markAllNotificationsRead()"></button>
      </div>
      <div class="modal-body">
        <ul class="list-group list-group-flush">
          <?php if (count($notifications) > 0): foreach ($notifications as $n): ?>
            <li class="list-group-item"><strong><?php echo htmlspecialchars($n['title'] ?? ''); ?></strong><br><?php echo htmlspecialchars($n['message']); ?><small class="text-muted d-block"><?php echo date("F j, Y, g:i A", strtotime($n['created_at'])); ?></small></li>
          <?php endforeach; else: ?>
            <li class="list-group-item text-center text-muted">No new notifications.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ====== BLOG ====== -->
<section class="jl-section news-section" id="news" style="padding-top:140px;">
  <div class="news-header">
    <h2>Our <span>Blog &amp; News</span></h2>
  </div>

  <?php if (count($posts) > 0): ?>
  <div class="news-grid">
    <?php foreach ($posts as $i => $post): ?>
    <a class="news-card" href="blog-post.php?id=<?php echo (int)$post['id']; ?>" style="text-decoration:none;color:inherit;">
      <div class="news-img" style="background:<?php echo $gradients[$i % count($gradients)]; ?>;">
        <?php if (!empty($post['image_path'])): ?>
          <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" onerror="this.style.display='none'">
        <?php endif; ?>
        <?php if (!empty($post['category'])): ?><span class="news-tag"><?php echo htmlspecialchars($post['category']); ?></span><?php endif; ?>
      </div>
      <div class="news-body">
        <div class="news-meta"><?php echo date("F j, Y", strtotime($post['created_at'])); ?> &bull; <span><?php echo (int)$post['read_minutes']; ?> Min Read</span></div>
        <h3><?php echo htmlspecialchars($post['title']); ?></h3>
        <p><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="text-center text-muted">No blog posts yet. Check back soon!</p>
  <?php endif; ?>
</section>

<!-- ====== FOOTER ====== -->
<footer class="jl-footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <img src="./assets/images/logo.png" alt="Jorish Express Laundry">
      <p>For over 3 years, we have been dedicated to providing exceptional customer service and top-quality dry cleaning and laundry solutions.</p>
    </div>
    <div class="footer-col">
      <h4>Company</h4>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="index.php#why">About Us</a></li>
        <li><a href="service-and-pricing.php">Services</a></li>
        <li><a href="contact-and-map-view.php">Find Location</a></li>
        <li><a href="blog.php">Blog</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Contact Info</h4>
      <ul class="footer-contact-list">
        <li><i class="fas fa-map-marker-alt"></i> Jorish Express Laundry, Philippines</li>
        <li><i class="fab fa-facebook"></i> <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank" style="color:rgba(255,255,255,.6);">facebook.com/jorish.laundry</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?php echo date("Y"); ?> Jorish Express Laundry. All Rights Reserved.</p>
  </div>
</footer>

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
