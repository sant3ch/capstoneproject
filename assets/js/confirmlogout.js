function confirmLogout(event) {
    if (event) {
        event.preventDefault();
    }

    var logoutUrl = '/jorishlaundry/logout.php';

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Are you sure?',
            text: 'You will be logged out of your admin session.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, logout!',
            cancelButtonText: 'Cancel',
            background: '#ffffff',
            color: '#212529'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = logoutUrl;
            }
        });
    } else {
        if (window.confirm('Are you sure you want to logout?')) {
            window.location.href = logoutUrl;
        }
    }

    return false;
}