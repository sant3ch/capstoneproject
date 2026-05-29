<?php
session_start();
require 'config.php';
require_once 'includes/content-helpers.php';

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = $id > 0 ? getPostById($conn, $id) : null;

$notifications = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT title, message, created_at FROM notifications
        WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
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
  <title><?php echo $post ? htmlspecialchars($post['title']) : 'Post not found'; ?> — Jorish Express Laundry</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/lib/css/all.min.css">
  <link rel="stylesheet" href="./assets/lib/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/css/index.css">
</head>
<body>

<!-- ====== NAVBAR ====== -->
<nav class="jl-nav" id="jlNav">
  <a href="index.php" class="jl-logo"><img src="./assets/images/logo.png" alt="Jorish Express Laundry"></a>
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

<section class="jl-section" style="padding-top:140px;max-width:820px;margin:0 auto;">
  <a href="blog.php" class="btn-jl btn-jl-outline" style="margin-bottom:1.5rem;display:inline-block;"><i class="fas fa-arrow-left me-1"></i> Back to Blog</a>

  <?php if ($post): ?>
    <div class="news-meta" style="margin-bottom:.5rem;">
      <?php if (!empty($post['category'])): ?><strong><?php echo htmlspecialchars($post['category']); ?></strong> &bull; <?php endif; ?>
      <?php echo date("F j, Y", strtotime($post['created_at'])); ?> &bull; <span><?php echo (int)$post['read_minutes']; ?> Min Read</span>
    </div>
    <h1 style="font-weight:800;margin-bottom:1.25rem;"><?php echo htmlspecialchars($post['title']); ?></h1>
    <?php if (!empty($post['image_path'])): ?>
      <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" style="width:100%;border-radius:16px;margin-bottom:1.5rem;object-fit:cover;max-height:420px;" onerror="this.style.display='none'">
    <?php endif; ?>
    <div style="font-size:1.05rem;line-height:1.8;color:#374151;">
      <?php echo nl2br(htmlspecialchars($post['content'])); ?>
    </div>
  <?php else: ?>
    <div class="text-center" style="padding:3rem 0;">
      <i class="fas fa-newspaper" style="font-size:3rem;color:#cbd5e1;"></i>
      <h2 style="margin-top:1rem;">Post not found</h2>
      <p class="text-muted">This article may have been removed or is no longer available.</p>
    </div>
  <?php endif; ?>
</section>

<!-- ====== FOOTER ====== -->
<footer class="jl-footer">
  <div class="footer-bottom">
    <p>&copy; <?php echo date("Y"); ?> Jorish Express Laundry. All Rights Reserved.</p>
  </div>
</footer>

<script src="./assets/lib/js/bootstrap.bundle.min.js"></script>
<?php if (isset($_SESSION['user_id'])): ?>
<script src="assets/js/confirmlogout-user.js"></script>
<?php endif; ?>
<script>
  document.getElementById('navToggler').addEventListener('click', function() {
    document.getElementById('jlNav').classList.toggle('open');
  });
</script>
</body>
</html>
