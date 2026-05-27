<?php
session_start();
require 'config.php';

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
    $user  = mysqli_fetch_assoc($query);

    if ($user && password_verify($password, $user['password'])) {
        if ($user['user_status'] != 1) {
            $error_message = "Your account is inactive. Please contact the administrator.";
        } else {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['first_name']  = $user['first_name'];
            $_SESSION['last_name']   = $user['last_name'];
            $_SESSION['email']       = $user['email'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['user_points'] = $user['user_points'];

            if (strtolower($user['role']) === 'admin') {
                header("Location: admin/admin_home.php"); exit();
            } elseif (strtolower($user['role']) === 'staff') {
                header("Location: staff/staff-home.php"); exit();
            } else {
                header("Location: index.php"); exit();
            }
        }
    } else {
        $error_message = "Invalid email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Jorish Express Laundry</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/lib/css/all.min.css">
  <link rel="stylesheet" href="./assets/lib/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/css/login.css">
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

    <li><a href="index.php#news">Blog</a></li>
  </ul>
  <div class="jl-nav-right">
    <a href="register.php" class="btn-nav-outline">Register</a>
    <a href="login.php" class="btn-nav-solid">Login</a>
  </div>
  <button class="jl-nav-toggler" id="navToggler"><i class="fas fa-bars"></i></button>
</nav>

<!-- ====== LOGIN PAGE ====== -->
<div class="login-page">

  <!-- LEFT PANEL -->
  <div class="login-left">
    <div class="login-brand">
      <img src="./assets/images/logo.png" alt="Jorish Express Laundry">
      <h2>Jorish Express Laundry</h2>
      <p>Your trusted partner for fast,<br>fresh, and quality laundry services.</p>
    </div>

    <div class="login-image-wrap">
      <img src="assets/images/laundry.png" alt="Laundry Service"
        onerror="this.style.display='none'">
    </div>

    <div class="login-features">
      <div class="login-feature-item">
        <i class="fas fa-star"></i>
        <span>5.0 Rating — 500+ Happy Customers</span>
      </div>
      <div class="login-feature-item">
        <i class="fas fa-gift"></i>
        <span>Earn loyalty points every transaction</span>
      </div>
      <div class="login-feature-item">
        <i class="fas fa-clock"></i>
        <span>Fast, reliable 24-hour service</span>
      </div>
    </div>

    <div class="login-accent"></div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="login-right">
    <div class="login-form-wrap">

      <div class="form-heading">
        <h2>Welcome Back!</h2>
        <p>Sign in to your account to continue</p>
      </div>

      <?php if (!empty($error_message)): ?>
        <div class="login-alert">
          <i class="fas fa-exclamation-circle"></i>
          <?php echo htmlspecialchars($error_message); ?>
        </div>
      <?php endif; ?>

      <form action="login.php" method="POST">
        <div class="field-group">
          <label for="email">Email Address</label>
          <div class="field-input-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" name="email"
              placeholder="you@example.com" required
              value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>
        </div>

        <div class="field-group">
          <label for="password">Password</label>
          <div class="field-input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password"
              placeholder="Enter your password" required>
            <button type="button" class="toggle-pw" id="togglePassword" aria-label="Toggle password">
              <i class="fas fa-eye" id="toggleIcon"></i>
            </button>
          </div>
        </div>

        <div class="forgot-row">
          <a href="forgot-password.php">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-login-submit">
          Sign In &nbsp;<i class="fas fa-arrow-right"></i>
        </button>
      </form>

      <div class="or-divider">or</div>

      <p class="register-prompt">
        Don't have an account?
        <a href="register.php">Register here!</a>
      </p>
    </div>
  </div>

</div>

<!-- ====== FOOTER ====== -->
<footer class="jl-footer-mini">
  <span>&copy; <?php echo date("Y"); ?> Jorish Express Laundry. All Rights Reserved.</span>
  <div>
    <a href="index.php">Home</a>
    <a href="index.php#why">About Us</a>
    <a href="service-and-pricing.php">Services</a>
    <a href="contact-and-map-view.php">Find Location</a>

    <a href="index.php#news">Blog</a>
  </div>
</footer>

<script src="./assets/lib/js/bootstrap.bundle.min.js"></script>
<script>
  // Password toggle
  document.getElementById('togglePassword').addEventListener('click', function() {
    var input = document.getElementById('password');
    var icon  = document.getElementById('toggleIcon');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
  });

  // Mobile nav toggle
  document.getElementById('navToggler').addEventListener('click', function() {
    document.getElementById('jlNav').classList.toggle('open');
  });
</script>
</body>
</html>
