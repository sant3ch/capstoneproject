function confirmLogout(event) {
    // Prevent default link behavior
    if (event) {
        event.preventDefault();
    }
    
    // Detect the correct logout path based on current location
    let logoutPath = 'logout.php';
    
    // If we're in a subfolder (like /user/), go up one level
    if (window.location.pathname.includes('/user/') || 
        window.location.pathname.includes('/staff/') || 
        window.location.pathname.includes('/admin/')) {
        logoutPath = '../logout.php';
    }
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Are you sure?',
            text: 'You will be logged out of your account.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, logout!',
            cancelButtonText: 'Cancel',
            background: '#ffffff',
            color: '#212529',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = logoutPath;
            }
        });
    } else {
        if (window.confirm('Are you sure you want to logout?')) {
            window.location.href = logoutPath;
        }
    }
}

// Alternative approach if you want to attach event listeners programmatically
document.addEventListener('DOMContentLoaded', function() {
    // You can also attach event listeners to logout links with a specific class
    const logoutLinks = document.querySelectorAll('.logout-link');
    
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            confirmLogout(e);
        });
    });
    
    // Also handle the original logout link if it has an ID or different class
    const originalLogoutLink = document.querySelector('a[href*="logout.php"]');
    if (originalLogoutLink) {
        originalLogoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            confirmLogout(e);
        });
    }
});