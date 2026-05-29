<?php
session_start();
require 'config.php';
require_once 'includes/content-helpers.php';

// Admin-managed homepage content
$why_intro    = getSetting($conn, 'home_why_intro', 'At Jorish Express Laundry, we understand that you have many options when it comes to laundry services.');
$why_items    = getWhyItems($conn);
$testimonials = getActiveTestimonials($conn);
$blog_posts   = getPublishedPosts($conn, 3);

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
  <title>Jorish Express Laundry</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/lib/css/all.min.css">
  <link rel="stylesheet" href="./assets/lib/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/css/index.css">
</head>
<body>

<!-- ====== NAVBAR ====== -->
<nav class="jl-nav" id="jlNav">
  <a href="index.php" class="jl-logo">
    <img src="./assets/images/logo.png" alt="Jorish Express Laundry">
  </a>

  <ul class="jl-nav-links">
    <li><a href="index.php" class="active">Home</a></li>
    <li><a href="#why">About Us</a></li>
    <li><a href="service-and-pricing.php">Services</a></li>
    <li><a href="contact-and-map-view.php">Find Location</a></li>

    <li><a href="blog.php">Blog</a></li>
  </ul>

  <div class="jl-nav-right">
    <?php if (!isset($_SESSION['user_id'])): ?>
      <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>
      <a href="contact-and-map-view.php" class="btn-jl btn-jl-blue">Contact Us</a>
    <?php else: ?>
      <!-- Notification bell -->
      <button class="btn-bell" data-bs-toggle="modal" data-bs-target="#notificationModal">
        <i class="fas fa-bell"></i>
        <?php if (count($notifications) > 0): ?>
          <span class="bell-badge"><?php echo count($notifications); ?></span>
        <?php endif; ?>
      </button>
      <!-- User dropdown -->
      <div class="dropdown">
        <button class="btn-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="./user/user-profile.php"><i class="fas fa-user-cog me-2"></i>Manage Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger logout-link" href="javascript:void(0);"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <button class="jl-nav-toggler" id="navToggler" aria-label="Toggle navigation">
    <i class="fas fa-bars"></i>
  </button>
</nav>

<!-- Notification Modal -->
<?php if (isset($_SESSION['user_id'])): ?>
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notifLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px;border:none;">
      <div class="modal-header" style="border-bottom:2px solid var(--blue);">
        <h5 class="modal-title" id="notifLabel" style="font-weight:700;color:var(--blue);">Notifications</h5>
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

<!-- Floating Social Icons -->
<div class="floating-social-container">
  <div class="social-icons-wrapper">
    <a href="https://www.facebook.com/profile.php?id=100064010053494" class="social-icon-item" target="_blank">
      <i class="fab fa-facebook-f"></i>
      <span class="social-text">Facebook</span>
    </a>
    <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" class="social-icon-item" target="_blank">
      <i class="fab fa-facebook-messenger"></i>
      <span class="social-text">Messenger</span>
    </a>
  </div>
</div>

<!-- ====== HERO ====== -->
<section class="hero" id="home">
  <div class="hero-content">
    <?php if (isset($_SESSION['user_id'])): ?>
      <h1>Welcome Back, <span><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Guest'); ?></span>!<br>Ready for<br><span>Fresh Laundry</span>?</h1>
      <p>We're excited to serve you again. Book your laundry service now and enjoy our premium cleaning experience.</p>
    <?php else: ?>
      <h1>Simplify Your Life<br>with <span>Jorish</span> <em>Express</em><br>Laundry Service</h1>
      <p>For over 3 years, we have been dedicated to providing exceptional customer service and top-quality dry cleaning and laundry solutions.</p>
    <?php endif; ?>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="user/book-now.php" class="btn-jl btn-jl-orange">Book Now! &rarr;</a>
    <?php else: ?>
      <a href="user/book-now.php" class="btn-jl btn-jl-orange">Get Started &rarr;</a>
    <?php endif; ?>
    <div class="hero-social">
      <div class="hero-avatars">
        <div class="av" style="background:#818cf8;">JD</div>
        <div class="av" style="background:#6366f1;">SM</div>
        <div class="av" style="background:#f5a623;">KL</div>
        <div class="av" style="background:#e08f0a;">PR</div>
      </div>
      <div class="hero-rating">
        <span class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
        <span>500+ Happy Reviews</span>
      </div>
    </div>
  </div>

  <div class="hero-image">
    <div class="hero-img-container">
      <div class="hero-img-wrap">
        <img src="assets/images/laundry.png" alt="Jorish Express Laundry">
      </div>
      <div class="float-icon fi-1"><i class="fas fa-star"></i> 5.0 Rating</div>
      <div class="float-icon fi-2"><i class="fas fa-truck"></i> Free Pickup</div>
      <div class="float-icon fi-3"><i class="fas fa-shield-alt"></i> Trusted</div>
      <div class="float-icon fi-4"><i class="fas fa-clock"></i> Free Delivery</div>
    </div>
  </div>
</section>

<!-- ====== SERVICES ====== -->
<section class="jl-section services-section" id="services">
  <div class="jl-title">
    <h2>We are here to make our customers<br>smile with <span>our services</span></h2>
  </div>
  <div class="services-grid">

    <div class="service-card">
      <div class="service-img" style="background:linear-gradient(135deg,#e0e7ff,#a5b4fc);">
        <img src="./assets/images/washanddry.png" alt="Wash and Dry"
          onerror="this.style.display='none'">
      </div>
      <div class="service-info">
        <div class="service-icon-wrap"><i class="fas fa-soap"></i></div>
        <div class="service-text"><h3>Wash and Dry</h3><p>Professional cleaning and drying service</p></div>
      </div>
    </div>

    <div class="service-card">
      <div class="service-img" style="background:linear-gradient(135deg,#fde8d0,#f5c49a);">
        <img src="./assets/images/washdryfold.png" alt="Wash Dry with Fold"
          onerror="this.style.display='none'">
      </div>
      <div class="service-info">
        <div class="service-icon-wrap"><i class="fas fa-wind"></i></div>
        <div class="service-text"><h3>Wash Dry with Fold</h3><p>Complete laundry service with folding</p></div>
      </div>
    </div>

    <div class="service-card">
      <div class="service-img" style="background:linear-gradient(135deg,#d4f7d4,#8edfa0);">
        <img src="./assets/images/selfdry.jpg" alt="Self Service Wash & Dry"
          onerror="this.style.display='none'">
      </div>
      <div class="service-info">
        <div class="service-icon-wrap"><i class="fas fa-fire"></i></div>
        <div class="service-text"><h3>Self Service Wash & Dry</h3><p>Do-it-yourself washing & drying stations</p></div>
      </div>
    </div>

  </div>
</section>

<!-- ====== WHY CHOOSE US ====== -->
<section class="jl-section why-section" id="why">
  <div class="jl-title">
    <h2>Why <span>Choose Us</span></h2>
    <p><?php echo htmlspecialchars($why_intro); ?></p>
  </div>
  <div class="why-inner">
    <div class="why-center">
      <div class="why-center-inner">
        <i class="fas fa-tshirt"></i>
      </div>
    </div>
    <?php foreach ($why_items as $i => $w): $pos = ($i % 7) + 1; ?>
    <div class="why-bubble wb-<?php echo $pos; ?><?php echo $w['accent'] === 'orange' ? ' orange' : ''; ?>"><i class="<?php echo htmlspecialchars($w['icon_class']); ?>"></i><?php echo htmlspecialchars($w['label']); ?></div>
    <?php endforeach; ?>
  </div>
  <div class="why-cta">
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="user/book-now.php" class="btn-jl btn-jl-orange">Book Now &rarr;</a>
    <?php else: ?>
      <a href="user/book-now.php" class="btn-jl btn-jl-orange">Get Started &rarr;</a>
    <?php endif; ?>
  </div>
</section>

<!-- ====== TESTIMONIALS ====== -->
<section class="testi-section" id="testimonials">
  <div class="jl-title">
    <h2>Our Customers <span>Feedback</span></h2>
    <p>What our happy customers say about us</p>
  </div>
  <?php if (count($testimonials) > 0): ?>
  <div class="testi-grid">
    <?php $avColors = ['#818cf8', '#6366f1', '#f5a623', '#e08f0a']; foreach ($testimonials as $i => $t):
      $initials = $t['initials'] !== '' ? $t['initials'] : strtoupper(substr($t['author_name'], 0, 2)); ?>
    <div class="testi-card">
      <div class="quote">&ldquo;</div>
      <p><?php echo nl2br(htmlspecialchars($t['quote'])); ?></p>
      <div class="testi-author">
        <div class="testi-av" style="background:<?php echo $avColors[$i % count($avColors)]; ?>;"><?php echo htmlspecialchars($initials); ?></div>
        <div class="testi-name">
          <h4><?php echo htmlspecialchars($t['author_name']); ?></h4>
          <div class="testi-stars"><?php echo str_repeat('&#9733;', max(1, (int)$t['rating'])); ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="testi-dots">
    <?php for ($i = 0; $i < count($testimonials); $i++): ?>
    <span<?php echo $i === 0 ? ' class="active"' : ''; ?>></span>
    <?php endfor; ?>
  </div>
  <?php else: ?>
  <p class="text-center text-muted">No reviews yet.</p>
  <?php endif; ?>
</section>

<!-- ====== WORKING PROCESS ====== -->
<section class="jl-section process-section" id="process">
  <div class="jl-title">
    <h2>Our Working <span>Process</span></h2>
    <p>Simple steps to fresh, clean laundry — right from your doorstep</p>
  </div>
  <div class="process-steps">
    <div class="process-step">
      <div class="step-icon"><i class="fas fa-user-plus"></i></div>
      <h3>Register</h3>
      <p>Create an account to become a member</p>
    </div>
    <div class="process-step">
      <div class="step-icon orange"><i class="fas fa-calendar-check"></i></div>
      <h3>Reservation</h3>
      <p>Schedule preferred date and time to reserve a slot</p>
    </div>
    <div class="process-step">
      <div class="step-icon"><i class="fas fa-bell"></i></div>
      <h3>Notification</h3>
      <p>Check notifications for updates on your booking status</p>
    </div>
    <div class="process-step">
      <div class="step-icon orange"><i class="fas fa-gift"></i></div>
      <h3>Earn Points</h3>
      <p>End booking transaction to claim exclusive rewards</p>
    </div>
  </div>
</section>

<!-- ====== CONTACT ====== -->
<section class="jl-section contact-section" id="contact">
  <div class="jl-title">
    <h2>Lets <span>Talk With Us</span></h2>
    <p>Have questions? Reach out and we'll get back to you promptly.</p>
  </div>
  <div class="contact-inner">
    <div class="contact-info">
      <h3>Contact Information</h3>
      <p>Fill up the form and our team will get back to you within 24 hours.</p>
      <ul class="ci-list">
        <li><i class="fas fa-map-marker-alt"></i> Jorish Express Laundry, Philippines</li>
        <li><i class="fab fa-facebook"></i>
          <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank" style="color:#fff;">
            facebook.com/jorish.laundry
          </a>
        </li>
        <li><i class="fab fa-facebook-messenger"></i>
          <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank" style="color:#fff;">
            Message us on Messenger
          </a>
        </li>
      </ul>
      <div class="contact-social">
        <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank"><i class="fab fa-facebook-f"></i></a>
        <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank"><i class="fab fa-facebook-messenger"></i></a>
      </div>
    </div>

    <div class="contact-form">
      <form action="contact-and-map-view.php" method="get">
        <div class="form-row">
          <div class="form-group">
            <label>Name</label>
            <input type="text" placeholder="Your full name">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" placeholder="your@email.com">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" placeholder="+63 912 345 6789">
          </div>
          <div class="form-group">
            <label>Address</label>
            <input type="text" placeholder="Your address">
          </div>
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea placeholder="Write your message here..."></textarea>
        </div>
        <button type="submit" class="btn-submit">Send Message</button>
      </form>
    </div>
  </div>
</section>

<!-- ====== LATEST NEWS ====== -->
<section class="jl-section news-section" id="news">
  <div class="news-header">
    <h2>Read Our <span>Latest News</span></h2>
    <a href="blog.php" class="btn-jl btn-jl-outline">Visit Our Blog</a>
  </div>
  <div class="news-grid">
    <?php
    $newsGradients = [
      'linear-gradient(135deg,#e0e7ff,#a5b4fc)',
      'linear-gradient(135deg,#fde8d0,#f5c49a)',
      'linear-gradient(135deg,#d4f7d4,#8edfa0)',
    ];
    if (count($blog_posts) > 0):
      foreach ($blog_posts as $i => $post): ?>
    <a class="news-card" href="blog-post.php?id=<?php echo (int)$post['id']; ?>" style="text-decoration:none;color:inherit;">
      <div class="news-img" style="background:<?php echo $newsGradients[$i % count($newsGradients)]; ?>;">
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
    <?php endforeach; else: ?>
    <p class="text-center text-muted">No news yet. Check back soon!</p>
    <?php endif; ?>
  </div>
</section>

<!-- ====== NEWSLETTER ====== -->
<section class="newsletter-section">
  <div class="newsletter-inner">
    <div class="newsletter-img-wrap">
      <img src="assets/images/laundry.png" alt="Jorish Express Laundry"
        onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-envelope-open-text\'></i>'">
    </div>
    <div class="newsletter-content">
      <h2>Join Our Newsletter</h2>
      <p>Subscribe to receive laundry tips, exclusive offers, and service updates straight to your inbox.</p>
      <div class="newsletter-form">
        <input type="email" placeholder="Enter your email address...">
        <button class="btn-nl">Subscribe</button>
      </div>
    </div>
  </div>
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
        <li><a href="#why">About Us</a></li>
        <li><a href="service-and-pricing.php">Services</a></li>
        <li><a href="contact-and-map-view.php">Find Location</a></li>

        <li><a href="blog.php">Blog</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Further Links</h4>
      <ul>
        <?php if (isset($_SESSION['user_id'])): ?>
          <li><a href="user/book-now.php">Book Now</a></li>
          <li><a href="user/user-profile.php">My Profile</a></li>
        <?php else: ?>
          <li><a href="login.php">Login</a></li>
          <li><a href="register.php">Register</a></li>
        <?php endif; ?>
        <li><a href="#loyalty">Loyalty Rewards</a></li>
        <li><a href="#contact">Contact</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Contact Info</h4>
      <ul class="footer-contact-list">
        <li><i class="fas fa-map-marker-alt"></i> Jorish Express Laundry, Philippines</li>
        <li>
          <i class="fab fa-facebook"></i>
          <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank" style="color:rgba(255,255,255,.6);">
            facebook.com/jorish.laundry
          </a>
        </li>
        <li>
          <i class="fab fa-facebook-messenger"></i>
          <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank" style="color:rgba(255,255,255,.6);">
            Message Us
          </a>
        </li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <p>
      Follow Us:
      <a href="https://www.facebook.com/profile.php?id=100064010053494" target="_blank" class="ms-2" style="color:rgba(255,255,255,.7);">
        <i class="fab fa-facebook-f me-2"></i>
      </a>
      <a href="https://www.facebook.com/messages/e2ee/t/9266651076733090" target="_blank" style="color:rgba(255,255,255,.7);">
        <i class="fab fa-facebook-messenger"></i>
      </a>
    </p>
    <p>&copy; <?php echo date("Y"); ?> Jorish Express Laundry. All Rights Reserved.</p>
  </div>
</footer>

<link rel="stylesheet" href="./assets/lib/css/sweetalert2.min.css">
<script src="./assets/lib/js/bootstrap.bundle.min.js"></script>
<script src="./assets/lib/js/sweetalert2.min.js"></script>

<?php if (isset($_SESSION['user_id'])): ?>
<script src="assets/js/notifications.js"></script>
<script src="assets/js/confirmlogout-user.js"></script>
<?php endif; ?>

<script>
  // Mobile nav toggle
  document.getElementById('navToggler').addEventListener('click', function() {
    document.getElementById('jlNav').classList.toggle('open');
  });

  // Smooth scroll
  document.querySelectorAll('a[href^="#"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
      var target = document.querySelector(a.getAttribute('href'));
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
    });
  });

  // Testimonial dots
  document.querySelectorAll('.testi-dots span').forEach(function(dot, i, dots) {
    dot.addEventListener('click', function() {
      dots.forEach(function(d) { d.classList.remove('active'); });
      dot.classList.add('active');
    });
  });
</script>
</body>
</html>
