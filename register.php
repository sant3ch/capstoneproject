<?php
session_start();

// Check for successful registration
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — Jorish Express Laundry</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/lib/css/all.min.css">
  <link rel="stylesheet" href="./assets/lib/css/bootstrap.min.css">
  <link rel="stylesheet" href="./assets/lib/css/sweetalert2.min.css">
  <link rel="stylesheet" href="./assets/css/register.css">
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
    <a href="login.php"    class="btn-nav-outline">Login</a>
    <a href="register.php" class="btn-nav-solid">Register</a>
  </div>
  <button class="jl-nav-toggler" id="navToggler"><i class="fas fa-bars"></i></button>
</nav>

<!-- ====== REGISTER PAGE ====== -->
<div class="register-page">

  <!-- LEFT PANEL -->
  <div class="reg-left">
    <div class="reg-brand">
      <img src="./assets/images/logo.png" alt="Jorish Express Laundry">
      <h2>Jorish Express Laundry</h2>
      <p>Join us today and enjoy premium<br>laundry services at your convenience.</p>
    </div>

    <div class="reg-image-wrap">
      <img src="assets/images/laundry.png" alt="Laundry Service"
        onerror="this.style.display='none'">
    </div>

    <div class="reg-features">
      <div class="reg-feature-item">
        <i class="fas fa-gift"></i>
        <span>Earn loyalty points every wash</span>
      </div>
      <div class="reg-feature-item">
        <i class="fas fa-calendar-check"></i>
        <span>Easy online booking anytime</span>
      </div>
      <div class="reg-feature-item">
        <i class="fas fa-bell"></i>
        <span>Real-time booking notifications</span>
      </div>
    </div>

    <div class="reg-accent"></div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="reg-right">
    <div class="reg-form-wrap">

      <div class="form-heading">
        <h2>Create Account</h2>
        <p>Fill in your details to get started — it only takes a minute!</p>
      </div>

      <form action="process_register.php" method="POST" id="registerForm">

        <div class="field-row">
          <div class="field-group">
            <label class="required" for="first_name">First Name</label>
            <div class="field-input-wrap">
              <i class="fas fa-user field-icon"></i>
              <input type="text" id="first_name" name="first_name"
                placeholder="Juan" required>
            </div>
          </div>
          <div class="field-group">
            <label class="required" for="last_name">Last Name</label>
            <div class="field-input-wrap">
              <i class="fas fa-user field-icon"></i>
              <input type="text" id="last_name" name="last_name"
                placeholder="Dela Cruz" required>
            </div>
          </div>
        </div>

        <div class="field-row">
          <div class="field-group">
            <label for="phone">Mobile Number</label>
            <div class="field-input-wrap">
              <i class="fas fa-phone field-icon"></i>
              <input type="text" id="phone" name="phone"
                placeholder="+63 912 345 6789">
            </div>
          </div>
          <div class="field-group">
            <label for="address">Address</label>
            <div class="field-input-wrap">
              <i class="fas fa-map-marker-alt field-icon"></i>
              <input type="text" id="address" name="address"
                placeholder="Your address">
            </div>
          </div>
        </div>

        <div class="field-group">
          <label class="required" for="email">Email Address</label>
          <div class="field-input-wrap">
            <i class="fas fa-envelope field-icon"></i>
            <input type="email" id="email" name="email"
              placeholder="you@example.com" required>
          </div>
        </div>

        <div class="field-row">
          <div class="field-group">
            <label class="required" for="password">Password</label>
            <div class="field-input-wrap">
              <i class="fas fa-lock field-icon"></i>
              <input type="password" id="password" name="password"
                placeholder="Create password" required>
              <button type="button" class="toggle-pw" onclick="togglePassword('password', this)">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="field-group">
            <label class="required" for="confirm_password">Confirm Password</label>
            <div class="field-input-wrap">
              <i class="fas fa-lock field-icon"></i>
              <input type="password" id="confirm_password" name="confirm_password"
                placeholder="Repeat password" required>
              <button type="button" class="toggle-pw" onclick="togglePassword('confirm_password', this)">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div class="pw-error" id="passwordError">
              <i class="fas fa-exclamation-circle"></i> Passwords do not match!
            </div>
          </div>
        </div>

        <button type="submit" class="btn-register-submit">
          Create Account &nbsp;<i class="fas fa-arrow-right"></i>
        </button>
      </form>

      <div class="or-divider">or</div>

      <p class="login-prompt">
        Already have an account?
        <a href="login.php">Login here!</a>
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
<script src="./assets/lib/js/sweetalert2.min.js"></script>
<script>
  // Password visibility toggle
  function togglePassword(fieldId, btn) {
    var input = document.getElementById(fieldId);
    var icon  = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
  }

  // Real-time password match validation
  document.getElementById('confirm_password').addEventListener('input', function() {
    var pw      = document.getElementById('password').value;
    var confirm = this.value;
    var err     = document.getElementById('passwordError');
    if (confirm !== '' && pw !== confirm) {
      err.classList.add('visible');
      this.classList.add('is-error');
    } else {
      err.classList.remove('visible');
      this.classList.remove('is-error');
    }
  });

  // Prevent submit if passwords don't match
  document.getElementById('registerForm').addEventListener('submit', function(e) {
    var pw      = document.getElementById('password').value;
    var confirm = document.getElementById('confirm_password').value;
    if (pw !== confirm) {
      e.preventDefault();
      document.getElementById('passwordError').classList.add('visible');
      document.getElementById('confirm_password').classList.add('is-error');
      document.getElementById('confirm_password').focus();
    }
  });

  // Mobile nav toggle
  document.getElementById('navToggler').addEventListener('click', function() {
    document.getElementById('jlNav').classList.toggle('open');
  });

  // Success modal display
  <?php if ($success_message !== ''): ?>
    Swal.fire({
      title: 'Account Created Successfully!',
      text: '<?php echo $success_message; ?>',
      icon: 'success',
      confirmButtonText: 'Go to Login',
      confirmButtonColor: '#10b981',
      allowOutsideClick: false,
      didOpen: function() {
        let countdown = 3;
        const timerInterval = setInterval(function() {
          if (countdown > 0) {
            Swal.update({
              confirmButtonText: 'Go to Login (' + countdown + 's)'
            });
            countdown--;
          } else {
            clearInterval(timerInterval);
            window.location.href = 'login.php';
          }
        }, 1000);
      }
    }).then(function() {
      window.location.href = 'login.php';
    });
  <?php endif; ?>
</script>
</body>
</html>
