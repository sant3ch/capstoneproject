// admin-gcash.js - Handle admin GCASH request management
document.addEventListener('DOMContentLoaded', function() {
    // View request buttons
    document.querySelectorAll('.view-request-btn').forEach(button => {
        button.addEventListener('click', function() {
            const requestId = this.getAttribute('data-id');
            loadRequestDetails(requestId);
        });
    });
    
    // Approve request button
    const approveRequestBtn = document.getElementById('approveRequestBtn');
    if (approveRequestBtn) {
        approveRequestBtn.addEventListener('click', function() {
            const requestId = document.getElementById('approveRequestId')?.value;
            if (requestId) {
                showApproveModal(requestId);
            }
        });
    }
    
    // Approve form submission
    const approveRequestForm = document.getElementById('approveRequestForm');
    if (approveRequestForm) {
        approveRequestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitApproval();
        });
    }
});

// Load request details
async function loadRequestDetails(requestId) {
    try {
        const response = await fetch(`get_gcash_request.php?id=${requestId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            displayRequestDetails(data.request);
            
            // Store request ID for approval
            document.getElementById('approveRequestId').value = requestId;
        } else {
            throw new Error(data.message || 'Failed to load request details');
        }
    } catch (error) {
        console.error('Error loading request details:', error);
        Swal.fire('Error', error.message, 'error');
    }
}

// Display request details in modal
function displayRequestDetails(request) {
    const detailsDiv = document.getElementById('requestDetails');
    
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Request ID:</strong><br>
                    <span class="badge bg-primary">#${request.id}</span>
                </div>
                <div class="mb-3">
                    <strong>Booking ID:</strong><br>
                    <span class="badge bg-info">#${request.booking_id}</span>
                </div>
                <div class="mb-3">
                    <strong>Customer:</strong><br>
                    ${request.customer_name}
                </div>
                <div class="mb-3">
                    <strong>Contact:</strong><br>
                    <i class="fas fa-envelope text-primary"></i> ${request.email}<br>
                    <i class="fas fa-phone text-success"></i> ${request.phone}
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Booking Date:</strong><br>
                    ${new Date(request.booking_date).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    })}
                </div>
                <div class="mb-3">
                    <strong>Time Slot:</strong><br>
                    ${request.time_slot}
                </div>
                <div class="mb-3">
                    <strong>Services:</strong><br>
                    ${request.service_type}
                </div>
                <div class="mb-3">
                    <strong>Requested:</strong><br>
                    ${new Date(request.requested_at).toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Status:</strong> 
                    <span class="badge bg-warning text-dark">${request.status}</span>
                </div>
            </div>
        </div>
    `;
    
    detailsDiv.innerHTML = detailsHtml;
}

// Show approve modal
function showApproveModal(requestId) {
    // Hide view modal
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewRequestModal'));
    if (viewModal) {
        viewModal.hide();
    }
    
    // Show approve modal
    const approveModal = new bootstrap.Modal(document.getElementById('approveRequestModal'));
    approveModal.show();
}

// Submit approval
async function submitApproval() {
    const requestId = document.getElementById('approveRequestId').value;
    const amount = document.getElementById('gcashAmount').value;
    const referenceNumber = document.getElementById('referenceNumber').value;
    const qrCodeUrl = document.getElementById('qrCodeUrl').value;
    const adminNotes = document.getElementById('adminNotes').value;
    
    if (!amount || !referenceNumber) {
        Swal.fire('Error', 'Amount and reference number are required', 'error');
        return;
    }
    
    try {
        const response = await fetch('approve_gcash_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `request_id=${requestId}&amount=${amount}&reference_number=${referenceNumber}&qr_code_url=${qrCodeUrl}&admin_notes=${adminNotes}`
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // Hide approve modal
            const approveModal = bootstrap.Modal.getInstance(document.getElementById('approveRequestModal'));
            if (approveModal) {
                approveModal.hide();
            }
            
            // Show success modal
            showSuccessModal(data);
        } else {
            throw new Error(data.message || 'Failed to approve request');
        }
    } catch (error) {
        console.error('Error approving request:', error);
        Swal.fire('Error', error.message, 'error');
    }
}

// Show success modal
function showSuccessModal(data) {
    const successDetails = document.getElementById('successDetails');
    successDetails.innerHTML = `
        <div class="alert alert-success">
            <strong>Request approved successfully!</strong><br>
            <small>Details have been sent to the customer.</small>
        </div>
        <div class="mt-3">
            <p><strong>Request ID:</strong> #${data.request_id}</p>
            <p><strong>Amount:</strong> ₱${parseFloat(data.amount).toFixed(2)}</p>
            <p><strong>Reference Number:</strong> ${data.reference_number}</p>
            ${data.qr_code_url ? `<p><strong>QR Code URL:</strong> <a href="${data.qr_code_url}" target="_blank">View QR Code</a></p>` : ''}
        </div>
    `;
    
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
}