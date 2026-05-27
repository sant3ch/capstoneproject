// booking-actions.js - Complete solution with modal flow
function confirmCancel(bookingId) {
    Swal.fire({
        title: 'Confirm Cancellation',
        html: `
            <div class="text-center">
                <p>Are you sure you want to cancel this booking?</p>
                <p class="text-danger mt-2">
                    <small>
                        <i class="bi bi-exclamation-triangle"></i> 
                        This action cannot be undone.
                    </small>
                </p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#6f42c1',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-x-circle"></i> Yes, Cancel',
        cancelButtonText: '<i class="bi bi-arrow-left"></i> No, Go Back',
        reverseButtons: true,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return cancelBooking(bookingId);
        },
        allowOutsideClick: () => !Swal.isLoading(),
        customClass: {
            htmlContainer: 'text-center'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Show success message
            Swal.fire({
                title: 'Success!',
                html: `
                    <div class="text-center">
                        <i class="bi bi-check-circle text-success display-4 mb-3"></i>
                        <p class="mb-2">Booking #${bookingId} has been cancelled.</p>
                        <p class="text-muted small">Page will refresh in 3 seconds...</p>
                    </div>
                `,
                icon: 'success',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                willClose: () => {
                    // Refresh the page
                    window.location.reload();
                },
                customClass: {
                    htmlContainer: 'text-center'
                }
            });
        }
    });
}

function cancelBooking(bookingId) {
    return fetch(`cancel_booking.php?booking_id=${bookingId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            return data;
        } else {
            throw new Error(data.message || 'Failed to cancel booking');
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Error!',
            html: `
                <div class="text-start">
                    <p>Failed to cancel booking:</p>
                    <p class="text-danger"><small>${error.message}</small></p>
                </div>
            `,
            icon: 'error',
            confirmButtonText: 'OK'
        });
        throw error;
    });
}

// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});