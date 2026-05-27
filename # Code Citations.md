# Code Citations

## License: unknown
https://github.com/AceFire6/ordered-arrowverse/blob/756d6a3f877046eb0d99a05e8a81707bcae3f3f8/ordering/templates/index.html

```
I need to enable editing tools to proceed. Let me provide the complete implementation plan and code blocks for you to implement:

## Implementation Steps

### **Step 1: Database Migration**

Run this SQL to add the necessary columns to `gcash_requests`:

```sql
ALTER TABLE gcash_requests 
ADD COLUMN proof_image VARCHAR(255) NULL AFTER reference_number,
ADD COLUMN admin_notes TEXT NULL AFTER status,
ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER admin_notes;

-- Add indexes
ALTER TABLE gcash_requests 
ADD INDEX idx_proof_image (proof_image),
ADD INDEX idx_status_date (status, requested_at DESC);
```

### **Step 2: Update GCASH Modal in booking_confirmation.php**

Replace the payment proof section with:

```html
<!-- Payment Proof Section -->
<div class="mt-4 pt-3 border-top">
    <h6 class="fw-bold mb-3">Payment Proof <span class="badge bg-danger">Required</span></h6>
    <p class="text-muted small mb-3">Upload a screenshot or file proving your GCASH payment:</p>

    <!-- File Upload Input -->
    <div class="mb-3">
        <label class="form-label"><i class="fas fa-file-upload me-2"></i> Upload Proof of Payment</label>
        <input type="file" class="form-control" id="gcashProofFile" accept=".jpg,.jpeg,.png,.pdf" required>
        <small class="text-muted d-block mt-1">Accepted: JPG, PNG, PDF | Max size: 25MB</small>
    </div>

    <div class="alert alert-warning small">
        <i class="fas fa-exclamation-triangle me-2"></i>
        A screenshot or proof file is required to submit your payment request.
    </div>
</div>
```

### **Step 3: Update Cash Modal in booking_confirmation.php**

Add this file upload section before the submit button:

```html
<!-- Payment Proof Section -->
<div class="mt-4 pt-3 border-top">
    <h6 class="fw-bold mb-3">Payment Proof <span class="badge bg-danger">Required</span></h6>
    <p class="text-muted small mb-3">Upload a screenshot or file proving your cash payment:</p>

    <!-- File Upload Input -->
    <div class="mb-3">
        <label class="form-label"><i class="fas fa-file-upload me-2"></i> Upload Proof of Payment</label>
        <input type="file" class="form-control" id="cashProofFile" accept=".jpg,.jpeg,.png,.pdf" required>
        <small class="text-muted d-block mt-1">Accepted: JPG, PNG, PDF | Max size: 25MB</small>
    </div>

    <div class="alert alert-warning small">
        <i class="fas fa-exclamation-triangle me-2"></i>
        A screenshot or proof file is required to submit your payment request.
    </div>
</div>
```

### **Step 4: Create Payment Upload Handler**

Create file: `assets/ajax/upload_payment_proof.php`

```php
<?php
session_start();
require '../../config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate required fields
if (!isset($_POST['booking_id'], $_POST['payment_method'], $_FILES['proof_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$booking_id = intval($_POST['booking_id']);
$payment_method = sanitize_input($_POST['payment_method']);
$user_id = $_SESSION['user_id'];

// Validate file
$file = $_FILES['proof_file'];
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size = 25 * 1024 * 1024; // 25MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF allowed.']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 25MB limit']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/payment_proofs/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$unique_filename = $payment_method . '_' . $booking_id . '_' . time() . '.' . $file_ext;
$file_path = $upload_dir . $unique_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

try {
    // Check if request already exists
    $check_query = "SELECT id FROM gcash_requests WHERE booking_id = ? AND user_id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'A payment request for this booking is already pending']);
        exit;
    }

    // Insert payment request
    $insert_query = "INSERT INTO gcash_requests (booking_id, user_id, amount, payment_method, proof_image, status, requested_at) 
                     VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    
    // Get booking amount
    $amount_query = "SELECT SUM(price) as total FROM (
                        SELECT s.price FROM bookings b
                        JOIN services s ON FIND_IN_SET(LOWER(s.service_name), LOWER(b.service_type))
                        WHERE b.id = ?
                    ) as prices";
    $amount_stmt = $conn->prepare($amount_query);
    $amount_stmt->bind_param("i", $booking_id);
    $amount_stmt->execute();
    $amount_result = $amount_stmt->get_result()->fetch_assoc();
    $amount = $amount_result['total'] ?? 0;
    $amount_stmt->close();

    $insert_stmt->bind_param("iidss", $booking_id, $user_id, $amount, $payment_method, $unique_filename);
    
    if ($insert_stmt->execute()) {
        $request_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        // Create admin notification
        $notif_title = ($payment_method === 'GCASH' ? 'GCASH' : 'Cash on Delivery') . ' Payment Request';
        $notif_message = "New payment request from booking #$booking_id. Amount: ₱" . number_format($amount, 2);
        $notif_query = "INSERT INTO admin_notifications (title, message, type, related_id, status, created_at) 
                        VALUES (?, ?, ?, ?, 'unread', NOW())";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("sssi", $notif_title, $notif_message, $payment_method, $request_id);
        $notif_stmt->execute();
        $notif_stmt->close();

        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Payment request submitted successfully',
            'request_id' => $request_id
        ]);
    } else {
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'Failed to create payment request']);
    }

} catch (Exception $e) {
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>
```

### **Step 5: Update gcash-payment.js**

Replace the entire file with:

```javascript
/**
 * GCASH Payment Handler with Proof Upload
 */

document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitGcashPaymentBtn');
    const proofFileInput = document.getElementById('gcashProofFile');
    const gcashModal = document.getElementById('gcashModal');
    const gcashSuccessModal = document.getElementById('gcashSuccessModal');
    
    if (!submitBtn) return;

    // Submit GCASH payment with proof
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // Check if file is selected
        if (!proofFileInput.files || !proofFileInput.files[0]) {
            Swal.fire({
                title: 'Proof Required',
                text: 'Please upload a screenshot or proof of your GCASH payment',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const file = proofFileInput.files[0];
        const max_size = 25 * 1024 * 1024; // 25MB
        const allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

        // Validate file
        if (!allowed_types.includes(file.type)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, and PDF files are allowed',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (file.size > max_size) {
            Swal.fire({
                title: 'File Too Large',
                text: 'File size must not exceed 25MB',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';

        // Get booking ID
        const idNumberElement = document.querySelector('.id-number');
        const bookingId = idNumberElement ? idNumberElement.textContent.replace('#', '').trim() : '';

        if (!bookingId) {
            Swal.fire({
                title: 'Error',
                text: 'Could not find booking ID. Please refresh the page.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        // Get amount
        let totalAmount = 0;
        const breakdownItems = document.querySelectorAll('.breakdown-item');
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector('span:last-child').textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ''));
        }

        // Create FormData with file
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('payment_method', 'GCASH');
        formData.append('proof_file', file);

        // Submit to upload handler
        fetch('../assets/ajax/upload_payment_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // Close GCASH modal
                const bsModal = bootstrap.Modal.getInstance(gcashModal);
                if (bsModal) bsModal.hide();

                // Show success modal
                const successModal = new bootstrap.Modal(gcashSuccessModal);
                successModal.show();

                // Update success modal details
                document.getElementById('successGcashBookingId').textContent = '#' + bookingId;
                document.getElementById('successGcashAmount').textContent = '₱' + totalAmount.toFixed(2);

                // Handle success modal OK button
                document.getElementById('gcashSuccessOkBtn').addEventListener('click', function() {
                    window.location.href = 'user-profile.php';
                });

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = 'user-profile.php';
                }, 3000);
            } else {
                Swal.fire({
                    title: 'Submission Failed',
                    text: data.message || 'An error occurred while submitting your payment request',
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            console.error('Payment submission error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to submit payment request. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    });
});
```

### **Step 6: Update cash-payment.js**

Replace the entire file with:

```javascript
/**
 * Cash on Delivery Payment Handler with Proof Upload
 */

document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitCashPaymentBtn');
    const editAddressBtn = document.getElementById('editAddressBtn');
    const saveAddressBtn = document.getElementById('saveAddressBtn');
    const cancelAddressBtn = document.getElementById('cancelAddressBtn');
    const addressDisplay = document.getElementById('addressDisplay');
    const addressEditForm = document.getElementById('addressEditForm');
    const addressText = document.getElementById('addressText');
    const addressInput = document.getElementById('addressInput');
    const proofFileInput = document.getElementById('cashProofFile');
    const cashModal = document.getElementById('cashModal');
    const cashSuccessModal = document.getElementById('cashSuccessModal');
    
    if (!submitBtn) return;

    // Show/hide address editing
    if (editAddressBtn) {
        editAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addressDisplay.style.display = 'none';
            addressEditForm.style.display = 'block';
            addressInput.focus();
        });
    }

    if (cancelAddressBtn) {
        cancelAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addressEditForm.style.display = 'none';
            addressDisplay.style.display = 'block';
            addressInput.value = addressText.textContent;
        });
    }

    if (saveAddressBtn) {
        saveAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const newAddress = addressInput.value.trim();
            
            if (!newAddress) {
                Swal.fire({
                    title: 'Address Required',
                    text: 'Please enter a delivery address',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            addressText.textContent = newAddress;
            addressEditForm.style.display = 'none';
            addressDisplay.style.display = 'block';
            
            Swal.fire({
                title: 'Address Updated',
                text: 'Delivery address has been updated',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        });
    }

    // Submit COD payment with proof
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // Validate address
        const currentAddress = addressText.textContent.trim();
        if (!currentAddress || currentAddress === 'No address provided') {
            Swal.fire({
                title: 'Address Required',
                text: 'Please provide a delivery address',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Check if file is selected
        if (!proofFileInput || !proofFileInput.files || !proofFileInput.files[0]) {
            Swal.fire({
                title: 'Proof Required',
                text: 'Please upload a screenshot or proof of your payment',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const file = proofFileInput.files[0];
        const max_size = 25 * 1024 * 1024; // 25MB
        const allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

        // Validate file
        if (!allowed_types.includes(file.type)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, and PDF files are allowed',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (file.size > max_size) {
            Swal.fire({
                title: 'File Too Large',
                text: 'File size must not exceed 25MB',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';

        // Get booking ID
        const idNumberElement = document.querySelector('.id-number');
        const bookingId = idNumberElement ? idNumberElement.textContent.replace('#', '').trim() : '';

        if (!bookingId) {
            Swal.fire({
                title: 'Error',
                text: 'Could not find booking ID. Please refresh the page.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        // Get amount
        let totalAmount = 0;
        const breakdownItems = document.querySelectorAll('.breakdown-item');
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector('span:last-child').textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ''));
        }

        // Create FormData with file
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('payment_method', 'Cash on Delivery');
        formData.append('proof_file', file);

        // Submit to upload handler
        fetch('../assets/ajax/upload_payment_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // Close payment modal
                const bsModal = bootstrap.Modal.getInstance(cashModal);
                if (bsModal) bsModal.hide();

                // Show success modal
                const successModal = new bootstrap.Modal(cashSuccessModal);
                successModal.show();

                // Update success modal details
                document.getElementById('successCashBookingId').textContent = '#' + bookingId;
                document.getElementById('successCashAmount').textContent = '₱' + totalAmount.toFixed(2);
                document.getElementById('paymentAmountDisplay').textContent = '₱' + totalAmount.toFixed(2);

                // Handle success modal OK button
                document.getElementById('cashSuccessOkBtn').addEventListener('click', function() {
                    window.location.href = 'user-profile.php';
                });

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = 'user-profile.php';
                }, 3000);
            } else {
                Swal.fire({
                    title: 'Submission Failed',
                    text: data.message || 'An error occurred while submitting your payment request',
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            console.error('Payment submission error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to submit payment request. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    });
});
```

### **Step 7: Update payment_requests-management.php**

Add this code to display proof images and verification buttons. I'll provide the specific sections to add to the payment requests table display.

**Add this CSS** in the `<head>` section:

```html
<style>
.proof-thumbnail {
    max-width: 60px;
    max-height: 60px;
    cursor: pointer;
    border-radius: 4px;
    border: 1px solid #ddd;
    transition: transform 0.2s;
}

.proof-thumbnail:hover {
    transform: scale(1.05);
    border-color: #007bff;
}

.modal-proof-image {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 8px;
}
</style>
```

**Add this modal** before the closing `</body>`:

```html
<!-- Payment Proof Image Modal -->
<div class="modal fade" id="proofImageModal" tabindex="-1" aria-labelledby="proofImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proofImageModalLabel">Payment Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="proofImageDisplay" src="" alt="Payment Proof" class="modal-proof-image">
                <p class="text-muted mt-3" id="proofFileInfo"></p>
            </div>
            <div class="modal-footer">
                <a id
```


## License: unknown
https://github.com/hiten36/Sigma_photon/blob/483566f331226b78cefccc325cbb8927effa160b/views/gallery.handlebars

```
I need to enable editing tools to proceed. Let me provide the complete implementation plan and code blocks for you to implement:

## Implementation Steps

### **Step 1: Database Migration**

Run this SQL to add the necessary columns to `gcash_requests`:

```sql
ALTER TABLE gcash_requests 
ADD COLUMN proof_image VARCHAR(255) NULL AFTER reference_number,
ADD COLUMN admin_notes TEXT NULL AFTER status,
ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER admin_notes;

-- Add indexes
ALTER TABLE gcash_requests 
ADD INDEX idx_proof_image (proof_image),
ADD INDEX idx_status_date (status, requested_at DESC);
```

### **Step 2: Update GCASH Modal in booking_confirmation.php**

Replace the payment proof section with:

```html
<!-- Payment Proof Section -->
<div class="mt-4 pt-3 border-top">
    <h6 class="fw-bold mb-3">Payment Proof <span class="badge bg-danger">Required</span></h6>
    <p class="text-muted small mb-3">Upload a screenshot or file proving your GCASH payment:</p>

    <!-- File Upload Input -->
    <div class="mb-3">
        <label class="form-label"><i class="fas fa-file-upload me-2"></i> Upload Proof of Payment</label>
        <input type="file" class="form-control" id="gcashProofFile" accept=".jpg,.jpeg,.png,.pdf" required>
        <small class="text-muted d-block mt-1">Accepted: JPG, PNG, PDF | Max size: 25MB</small>
    </div>

    <div class="alert alert-warning small">
        <i class="fas fa-exclamation-triangle me-2"></i>
        A screenshot or proof file is required to submit your payment request.
    </div>
</div>
```

### **Step 3: Update Cash Modal in booking_confirmation.php**

Add this file upload section before the submit button:

```html
<!-- Payment Proof Section -->
<div class="mt-4 pt-3 border-top">
    <h6 class="fw-bold mb-3">Payment Proof <span class="badge bg-danger">Required</span></h6>
    <p class="text-muted small mb-3">Upload a screenshot or file proving your cash payment:</p>

    <!-- File Upload Input -->
    <div class="mb-3">
        <label class="form-label"><i class="fas fa-file-upload me-2"></i> Upload Proof of Payment</label>
        <input type="file" class="form-control" id="cashProofFile" accept=".jpg,.jpeg,.png,.pdf" required>
        <small class="text-muted d-block mt-1">Accepted: JPG, PNG, PDF | Max size: 25MB</small>
    </div>

    <div class="alert alert-warning small">
        <i class="fas fa-exclamation-triangle me-2"></i>
        A screenshot or proof file is required to submit your payment request.
    </div>
</div>
```

### **Step 4: Create Payment Upload Handler**

Create file: `assets/ajax/upload_payment_proof.php`

```php
<?php
session_start();
require '../../config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate required fields
if (!isset($_POST['booking_id'], $_POST['payment_method'], $_FILES['proof_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$booking_id = intval($_POST['booking_id']);
$payment_method = sanitize_input($_POST['payment_method']);
$user_id = $_SESSION['user_id'];

// Validate file
$file = $_FILES['proof_file'];
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size = 25 * 1024 * 1024; // 25MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF allowed.']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 25MB limit']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/payment_proofs/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$unique_filename = $payment_method . '_' . $booking_id . '_' . time() . '.' . $file_ext;
$file_path = $upload_dir . $unique_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

try {
    // Check if request already exists
    $check_query = "SELECT id FROM gcash_requests WHERE booking_id = ? AND user_id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'A payment request for this booking is already pending']);
        exit;
    }

    // Insert payment request
    $insert_query = "INSERT INTO gcash_requests (booking_id, user_id, amount, payment_method, proof_image, status, requested_at) 
                     VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    
    // Get booking amount
    $amount_query = "SELECT SUM(price) as total FROM (
                        SELECT s.price FROM bookings b
                        JOIN services s ON FIND_IN_SET(LOWER(s.service_name), LOWER(b.service_type))
                        WHERE b.id = ?
                    ) as prices";
    $amount_stmt = $conn->prepare($amount_query);
    $amount_stmt->bind_param("i", $booking_id);
    $amount_stmt->execute();
    $amount_result = $amount_stmt->get_result()->fetch_assoc();
    $amount = $amount_result['total'] ?? 0;
    $amount_stmt->close();

    $insert_stmt->bind_param("iidss", $booking_id, $user_id, $amount, $payment_method, $unique_filename);
    
    if ($insert_stmt->execute()) {
        $request_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        // Create admin notification
        $notif_title = ($payment_method === 'GCASH' ? 'GCASH' : 'Cash on Delivery') . ' Payment Request';
        $notif_message = "New payment request from booking #$booking_id. Amount: ₱" . number_format($amount, 2);
        $notif_query = "INSERT INTO admin_notifications (title, message, type, related_id, status, created_at) 
                        VALUES (?, ?, ?, ?, 'unread', NOW())";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("sssi", $notif_title, $notif_message, $payment_method, $request_id);
        $notif_stmt->execute();
        $notif_stmt->close();

        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Payment request submitted successfully',
            'request_id' => $request_id
        ]);
    } else {
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'Failed to create payment request']);
    }

} catch (Exception $e) {
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>
```

### **Step 5: Update gcash-payment.js**

Replace the entire file with:

```javascript
/**
 * GCASH Payment Handler with Proof Upload
 */

document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitGcashPaymentBtn');
    const proofFileInput = document.getElementById('gcashProofFile');
    const gcashModal = document.getElementById('gcashModal');
    const gcashSuccessModal = document.getElementById('gcashSuccessModal');
    
    if (!submitBtn) return;

    // Submit GCASH payment with proof
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // Check if file is selected
        if (!proofFileInput.files || !proofFileInput.files[0]) {
            Swal.fire({
                title: 'Proof Required',
                text: 'Please upload a screenshot or proof of your GCASH payment',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const file = proofFileInput.files[0];
        const max_size = 25 * 1024 * 1024; // 25MB
        const allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

        // Validate file
        if (!allowed_types.includes(file.type)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, and PDF files are allowed',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (file.size > max_size) {
            Swal.fire({
                title: 'File Too Large',
                text: 'File size must not exceed 25MB',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';

        // Get booking ID
        const idNumberElement = document.querySelector('.id-number');
        const bookingId = idNumberElement ? idNumberElement.textContent.replace('#', '').trim() : '';

        if (!bookingId) {
            Swal.fire({
                title: 'Error',
                text: 'Could not find booking ID. Please refresh the page.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        // Get amount
        let totalAmount = 0;
        const breakdownItems = document.querySelectorAll('.breakdown-item');
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector('span:last-child').textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ''));
        }

        // Create FormData with file
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('payment_method', 'GCASH');
        formData.append('proof_file', file);

        // Submit to upload handler
        fetch('../assets/ajax/upload_payment_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // Close GCASH modal
                const bsModal = bootstrap.Modal.getInstance(gcashModal);
                if (bsModal) bsModal.hide();

                // Show success modal
                const successModal = new bootstrap.Modal(gcashSuccessModal);
                successModal.show();

                // Update success modal details
                document.getElementById('successGcashBookingId').textContent = '#' + bookingId;
                document.getElementById('successGcashAmount').textContent = '₱' + totalAmount.toFixed(2);

                // Handle success modal OK button
                document.getElementById('gcashSuccessOkBtn').addEventListener('click', function() {
                    window.location.href = 'user-profile.php';
                });

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = 'user-profile.php';
                }, 3000);
            } else {
                Swal.fire({
                    title: 'Submission Failed',
                    text: data.message || 'An error occurred while submitting your payment request',
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            console.error('Payment submission error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to submit payment request. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    });
});
```

### **Step 6: Update cash-payment.js**

Replace the entire file with:

```javascript
/**
 * Cash on Delivery Payment Handler with Proof Upload
 */

document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitCashPaymentBtn');
    const editAddressBtn = document.getElementById('editAddressBtn');
    const saveAddressBtn = document.getElementById('saveAddressBtn');
    const cancelAddressBtn = document.getElementById('cancelAddressBtn');
    const addressDisplay = document.getElementById('addressDisplay');
    const addressEditForm = document.getElementById('addressEditForm');
    const addressText = document.getElementById('addressText');
    const addressInput = document.getElementById('addressInput');
    const proofFileInput = document.getElementById('cashProofFile');
    const cashModal = document.getElementById('cashModal');
    const cashSuccessModal = document.getElementById('cashSuccessModal');
    
    if (!submitBtn) return;

    // Show/hide address editing
    if (editAddressBtn) {
        editAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addressDisplay.style.display = 'none';
            addressEditForm.style.display = 'block';
            addressInput.focus();
        });
    }

    if (cancelAddressBtn) {
        cancelAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addressEditForm.style.display = 'none';
            addressDisplay.style.display = 'block';
            addressInput.value = addressText.textContent;
        });
    }

    if (saveAddressBtn) {
        saveAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const newAddress = addressInput.value.trim();
            
            if (!newAddress) {
                Swal.fire({
                    title: 'Address Required',
                    text: 'Please enter a delivery address',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            addressText.textContent = newAddress;
            addressEditForm.style.display = 'none';
            addressDisplay.style.display = 'block';
            
            Swal.fire({
                title: 'Address Updated',
                text: 'Delivery address has been updated',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        });
    }

    // Submit COD payment with proof
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // Validate address
        const currentAddress = addressText.textContent.trim();
        if (!currentAddress || currentAddress === 'No address provided') {
            Swal.fire({
                title: 'Address Required',
                text: 'Please provide a delivery address',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Check if file is selected
        if (!proofFileInput || !proofFileInput.files || !proofFileInput.files[0]) {
            Swal.fire({
                title: 'Proof Required',
                text: 'Please upload a screenshot or proof of your payment',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const file = proofFileInput.files[0];
        const max_size = 25 * 1024 * 1024; // 25MB
        const allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

        // Validate file
        if (!allowed_types.includes(file.type)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, and PDF files are allowed',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (file.size > max_size) {
            Swal.fire({
                title: 'File Too Large',
                text: 'File size must not exceed 25MB',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';

        // Get booking ID
        const idNumberElement = document.querySelector('.id-number');
        const bookingId = idNumberElement ? idNumberElement.textContent.replace('#', '').trim() : '';

        if (!bookingId) {
            Swal.fire({
                title: 'Error',
                text: 'Could not find booking ID. Please refresh the page.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        // Get amount
        let totalAmount = 0;
        const breakdownItems = document.querySelectorAll('.breakdown-item');
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector('span:last-child').textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ''));
        }

        // Create FormData with file
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('payment_method', 'Cash on Delivery');
        formData.append('proof_file', file);

        // Submit to upload handler
        fetch('../assets/ajax/upload_payment_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // Close payment modal
                const bsModal = bootstrap.Modal.getInstance(cashModal);
                if (bsModal) bsModal.hide();

                // Show success modal
                const successModal = new bootstrap.Modal(cashSuccessModal);
                successModal.show();

                // Update success modal details
                document.getElementById('successCashBookingId').textContent = '#' + bookingId;
                document.getElementById('successCashAmount').textContent = '₱' + totalAmount.toFixed(2);
                document.getElementById('paymentAmountDisplay').textContent = '₱' + totalAmount.toFixed(2);

                // Handle success modal OK button
                document.getElementById('cashSuccessOkBtn').addEventListener('click', function() {
                    window.location.href = 'user-profile.php';
                });

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = 'user-profile.php';
                }, 3000);
            } else {
                Swal.fire({
                    title: 'Submission Failed',
                    text: data.message || 'An error occurred while submitting your payment request',
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            console.error('Payment submission error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to submit payment request. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    });
});
```

### **Step 7: Update payment_requests-management.php**

Add this code to display proof images and verification buttons. I'll provide the specific sections to add to the payment requests table display.

**Add this CSS** in the `<head>` section:

```html
<style>
.proof-thumbnail {
    max-width: 60px;
    max-height: 60px;
    cursor: pointer;
    border-radius: 4px;
    border: 1px solid #ddd;
    transition: transform 0.2s;
}

.proof-thumbnail:hover {
    transform: scale(1.05);
    border-color: #007bff;
}

.modal-proof-image {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 8px;
}
</style>
```

**Add this modal** before the closing `</body>`:

```html
<!-- Payment Proof Image Modal -->
<div class="modal fade" id="proofImageModal" tabindex="-1" aria-labelledby="proofImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proofImageModalLabel">Payment Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="proofImageDisplay" src="" alt="Payment Proof" class="modal-proof-image">
                <p class="text-muted mt-3" id="proofFileInfo"></p>
            </div>
            <div class="modal-footer">
                <a id
```


## License: unknown
https://github.com/AceFire6/ordered-arrowverse/blob/756d6a3f877046eb0d99a05e8a81707bcae3f3f8/ordering/templates/index.html

```
I need to enable editing tools to proceed. Let me provide the complete implementation plan and code blocks for you to implement:

## Implementation Steps

### **Step 1: Database Migration**

Run this SQL to add the necessary columns to `gcash_requests`:

```sql
ALTER TABLE gcash_requests 
ADD COLUMN proof_image VARCHAR(255) NULL AFTER reference_number,
ADD COLUMN admin_notes TEXT NULL AFTER status,
ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER admin_notes;

-- Add indexes
ALTER TABLE gcash_requests 
ADD INDEX idx_proof_image (proof_image),
ADD INDEX idx_status_date (status, requested_at DESC);
```

### **Step 2: Update GCASH Modal in booking_confirmation.php**

Replace the payment proof section with:

```html
<!-- Payment Proof Section -->
<div class="mt-4 pt-3 border-top">
    <h6 class="fw-bold mb-3">Payment Proof <span class="badge bg-danger">Required</span></h6>
    <p class="text-muted small mb-3">Upload a screenshot or file proving your GCASH payment:</p>

    <!-- File Upload Input -->
    <div class="mb-3">
        <label class="form-label"><i class="fas fa-file-upload me-2"></i> Upload Proof of Payment</label>
        <input type="file" class="form-control" id="gcashProofFile" accept=".jpg,.jpeg,.png,.pdf" required>
        <small class="text-muted d-block mt-1">Accepted: JPG, PNG, PDF | Max size: 25MB</small>
    </div>

    <div class="alert alert-warning small">
        <i class="fas fa-exclamation-triangle me-2"></i>
        A screenshot or proof file is required to submit your payment request.
    </div>
</div>
```

### **Step 3: Update Cash Modal in booking_confirmation.php**

Add this file upload section before the submit button:

```html
<!-- Payment Proof Section -->
<div class="mt-4 pt-3 border-top">
    <h6 class="fw-bold mb-3">Payment Proof <span class="badge bg-danger">Required</span></h6>
    <p class="text-muted small mb-3">Upload a screenshot or file proving your cash payment:</p>

    <!-- File Upload Input -->
    <div class="mb-3">
        <label class="form-label"><i class="fas fa-file-upload me-2"></i> Upload Proof of Payment</label>
        <input type="file" class="form-control" id="cashProofFile" accept=".jpg,.jpeg,.png,.pdf" required>
        <small class="text-muted d-block mt-1">Accepted: JPG, PNG, PDF | Max size: 25MB</small>
    </div>

    <div class="alert alert-warning small">
        <i class="fas fa-exclamation-triangle me-2"></i>
        A screenshot or proof file is required to submit your payment request.
    </div>
</div>
```

### **Step 4: Create Payment Upload Handler**

Create file: `assets/ajax/upload_payment_proof.php`

```php
<?php
session_start();
require '../../config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate required fields
if (!isset($_POST['booking_id'], $_POST['payment_method'], $_FILES['proof_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$booking_id = intval($_POST['booking_id']);
$payment_method = sanitize_input($_POST['payment_method']);
$user_id = $_SESSION['user_id'];

// Validate file
$file = $_FILES['proof_file'];
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size = 25 * 1024 * 1024; // 25MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF allowed.']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 25MB limit']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/payment_proofs/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$unique_filename = $payment_method . '_' . $booking_id . '_' . time() . '.' . $file_ext;
$file_path = $upload_dir . $unique_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

try {
    // Check if request already exists
    $check_query = "SELECT id FROM gcash_requests WHERE booking_id = ? AND user_id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'A payment request for this booking is already pending']);
        exit;
    }

    // Insert payment request
    $insert_query = "INSERT INTO gcash_requests (booking_id, user_id, amount, payment_method, proof_image, status, requested_at) 
                     VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    
    // Get booking amount
    $amount_query = "SELECT SUM(price) as total FROM (
                        SELECT s.price FROM bookings b
                        JOIN services s ON FIND_IN_SET(LOWER(s.service_name), LOWER(b.service_type))
                        WHERE b.id = ?
                    ) as prices";
    $amount_stmt = $conn->prepare($amount_query);
    $amount_stmt->bind_param("i", $booking_id);
    $amount_stmt->execute();
    $amount_result = $amount_stmt->get_result()->fetch_assoc();
    $amount = $amount_result['total'] ?? 0;
    $amount_stmt->close();

    $insert_stmt->bind_param("iidss", $booking_id, $user_id, $amount, $payment_method, $unique_filename);
    
    if ($insert_stmt->execute()) {
        $request_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        // Create admin notification
        $notif_title = ($payment_method === 'GCASH' ? 'GCASH' : 'Cash on Delivery') . ' Payment Request';
        $notif_message = "New payment request from booking #$booking_id. Amount: ₱" . number_format($amount, 2);
        $notif_query = "INSERT INTO admin_notifications (title, message, type, related_id, status, created_at) 
                        VALUES (?, ?, ?, ?, 'unread', NOW())";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("sssi", $notif_title, $notif_message, $payment_method, $request_id);
        $notif_stmt->execute();
        $notif_stmt->close();

        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Payment request submitted successfully',
            'request_id' => $request_id
        ]);
    } else {
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'Failed to create payment request']);
    }

} catch (Exception $e) {
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>
```

### **Step 5: Update gcash-payment.js**

Replace the entire file with:

```javascript
/**
 * GCASH Payment Handler with Proof Upload
 */

document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitGcashPaymentBtn');
    const proofFileInput = document.getElementById('gcashProofFile');
    const gcashModal = document.getElementById('gcashModal');
    const gcashSuccessModal = document.getElementById('gcashSuccessModal');
    
    if (!submitBtn) return;

    // Submit GCASH payment with proof
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // Check if file is selected
        if (!proofFileInput.files || !proofFileInput.files[0]) {
            Swal.fire({
                title: 'Proof Required',
                text: 'Please upload a screenshot or proof of your GCASH payment',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const file = proofFileInput.files[0];
        const max_size = 25 * 1024 * 1024; // 25MB
        const allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

        // Validate file
        if (!allowed_types.includes(file.type)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, and PDF files are allowed',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (file.size > max_size) {
            Swal.fire({
                title: 'File Too Large',
                text: 'File size must not exceed 25MB',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';

        // Get booking ID
        const idNumberElement = document.querySelector('.id-number');
        const bookingId = idNumberElement ? idNumberElement.textContent.replace('#', '').trim() : '';

        if (!bookingId) {
            Swal.fire({
                title: 'Error',
                text: 'Could not find booking ID. Please refresh the page.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        // Get amount
        let totalAmount = 0;
        const breakdownItems = document.querySelectorAll('.breakdown-item');
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector('span:last-child').textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ''));
        }

        // Create FormData with file
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('payment_method', 'GCASH');
        formData.append('proof_file', file);

        // Submit to upload handler
        fetch('../assets/ajax/upload_payment_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // Close GCASH modal
                const bsModal = bootstrap.Modal.getInstance(gcashModal);
                if (bsModal) bsModal.hide();

                // Show success modal
                const successModal = new bootstrap.Modal(gcashSuccessModal);
                successModal.show();

                // Update success modal details
                document.getElementById('successGcashBookingId').textContent = '#' + bookingId;
                document.getElementById('successGcashAmount').textContent = '₱' + totalAmount.toFixed(2);

                // Handle success modal OK button
                document.getElementById('gcashSuccessOkBtn').addEventListener('click', function() {
                    window.location.href = 'user-profile.php';
                });

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = 'user-profile.php';
                }, 3000);
            } else {
                Swal.fire({
                    title: 'Submission Failed',
                    text: data.message || 'An error occurred while submitting your payment request',
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            console.error('Payment submission error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to submit payment request. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    });
});
```

### **Step 6: Update cash-payment.js**

Replace the entire file with:

```javascript
/**
 * Cash on Delivery Payment Handler with Proof Upload
 */

document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitCashPaymentBtn');
    const editAddressBtn = document.getElementById('editAddressBtn');
    const saveAddressBtn = document.getElementById('saveAddressBtn');
    const cancelAddressBtn = document.getElementById('cancelAddressBtn');
    const addressDisplay = document.getElementById('addressDisplay');
    const addressEditForm = document.getElementById('addressEditForm');
    const addressText = document.getElementById('addressText');
    const addressInput = document.getElementById('addressInput');
    const proofFileInput = document.getElementById('cashProofFile');
    const cashModal = document.getElementById('cashModal');
    const cashSuccessModal = document.getElementById('cashSuccessModal');
    
    if (!submitBtn) return;

    // Show/hide address editing
    if (editAddressBtn) {
        editAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addressDisplay.style.display = 'none';
            addressEditForm.style.display = 'block';
            addressInput.focus();
        });
    }

    if (cancelAddressBtn) {
        cancelAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addressEditForm.style.display = 'none';
            addressDisplay.style.display = 'block';
            addressInput.value = addressText.textContent;
        });
    }

    if (saveAddressBtn) {
        saveAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const newAddress = addressInput.value.trim();
            
            if (!newAddress) {
                Swal.fire({
                    title: 'Address Required',
                    text: 'Please enter a delivery address',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            addressText.textContent = newAddress;
            addressEditForm.style.display = 'none';
            addressDisplay.style.display = 'block';
            
            Swal.fire({
                title: 'Address Updated',
                text: 'Delivery address has been updated',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        });
    }

    // Submit COD payment with proof
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // Validate address
        const currentAddress = addressText.textContent.trim();
        if (!currentAddress || currentAddress === 'No address provided') {
            Swal.fire({
                title: 'Address Required',
                text: 'Please provide a delivery address',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Check if file is selected
        if (!proofFileInput || !proofFileInput.files || !proofFileInput.files[0]) {
            Swal.fire({
                title: 'Proof Required',
                text: 'Please upload a screenshot or proof of your payment',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const file = proofFileInput.files[0];
        const max_size = 25 * 1024 * 1024; // 25MB
        const allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

        // Validate file
        if (!allowed_types.includes(file.type)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, and PDF files are allowed',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (file.size > max_size) {
            Swal.fire({
                title: 'File Too Large',
                text: 'File size must not exceed 25MB',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';

        // Get booking ID
        const idNumberElement = document.querySelector('.id-number');
        const bookingId = idNumberElement ? idNumberElement.textContent.replace('#', '').trim() : '';

        if (!bookingId) {
            Swal.fire({
                title: 'Error',
                text: 'Could not find booking ID. Please refresh the page.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        // Get amount
        let totalAmount = 0;
        const breakdownItems = document.querySelectorAll('.breakdown-item');
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector('span:last-child').textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ''));
        }

        // Create FormData with file
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('payment_method', 'Cash on Delivery');
        formData.append('proof_file', file);

        // Submit to upload handler
        fetch('../assets/ajax/upload_payment_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // Close payment modal
                const bsModal = bootstrap.Modal.getInstance(cashModal);
                if (bsModal) bsModal.hide();

                // Show success modal
                const successModal = new bootstrap.Modal(cashSuccessModal);
                successModal.show();

                // Update success modal details
                document.getElementById('successCashBookingId').textContent = '#' + bookingId;
                document.getElementById('successCashAmount').textContent = '₱' + totalAmount.toFixed(2);
                document.getElementById('paymentAmountDisplay').textContent = '₱' + totalAmount.toFixed(2);

                // Handle success modal OK button
                document.getElementById('cashSuccessOkBtn').addEventListener('click', function() {
                    window.location.href = 'user-profile.php';
                });

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = 'user-profile.php';
                }, 3000);
            } else {
                Swal.fire({
                    title: 'Submission Failed',
                    text: data.message || 'An error occurred while submitting your payment request',
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            console.error('Payment submission error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to submit payment request. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    });
});
```

### **Step 7: Update payment_requests-management.php**

Add this code to display proof images and verification buttons. I'll provide the specific sections to add to the payment requests table display.

**Add this CSS** in the `<head>` section:

```html
<style>
.proof-thumbnail {
    max-width: 60px;
    max-height: 60px;
    cursor: pointer;
    border-radius: 4px;
    border: 1px solid #ddd;
    transition: transform 0.2s;
}

.proof-thumbnail:hover {
    transform: scale(1.05);
    border-color: #007bff;
}

.modal-proof-image {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 8px;
}
</style>
```

**Add this modal** before the closing `</body>`:

```html
<!-- Payment Proof Image Modal -->
<div class="modal fade" id="proofImageModal" tabindex="-1" aria-labelledby="proofImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proofImageModalLabel">Payment Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="proofImageDisplay" src="" alt="Payment Proof" class="modal-proof-image">
                <p class="text-muted mt-3" id="proofFileInfo"></p>
            </div>
            <div class="modal-footer">
                <a id
```


## License: unknown
https://github.com/hiten36/Sigma_photon/blob/483566f331226b78cefccc325cbb8927effa160b/views/gallery.handlebars

```
I need to enable editing tools to proceed. Let me provide the complete implementation plan and code blocks for you to implement:

## Implementation Steps

### **Step 1: Database Migration**

Run this SQL to add the necessary columns to `gcash_requests`:

```sql
ALTER TABLE gcash_requests 
ADD COLUMN proof_image VARCHAR(255) NULL AFTER reference_number,
ADD COLUMN admin_notes TEXT NULL AFTER status,
ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER admin_notes;

-- Add indexes
ALTER TABLE gcash_requests 
ADD INDEX idx_proof_image (proof_image),
ADD INDEX idx_status_date (status, requested_at DESC);
```

### **Step 2: Update GCASH Modal in booking_confirmation.php**

Replace the payment proof section with:

```html
<!-- Payment Proof Section -->
<div class="mt-4 pt-3 border-top">
    <h6 class="fw-bold mb-3">Payment Proof <span class="badge bg-danger">Required</span></h6>
    <p class="text-muted small mb-3">Upload a screenshot or file proving your GCASH payment:</p>

    <!-- File Upload Input -->
    <div class="mb-3">
        <label class="form-label"><i class="fas fa-file-upload me-2"></i> Upload Proof of Payment</label>
        <input type="file" class="form-control" id="gcashProofFile" accept=".jpg,.jpeg,.png,.pdf" required>
        <small class="text-muted d-block mt-1">Accepted: JPG, PNG, PDF | Max size: 25MB</small>
    </div>

    <div class="alert alert-warning small">
        <i class="fas fa-exclamation-triangle me-2"></i>
        A screenshot or proof file is required to submit your payment request.
    </div>
</div>
```

### **Step 3: Update Cash Modal in booking_confirmation.php**

Add this file upload section before the submit button:

```html
<!-- Payment Proof Section -->
<div class="mt-4 pt-3 border-top">
    <h6 class="fw-bold mb-3">Payment Proof <span class="badge bg-danger">Required</span></h6>
    <p class="text-muted small mb-3">Upload a screenshot or file proving your cash payment:</p>

    <!-- File Upload Input -->
    <div class="mb-3">
        <label class="form-label"><i class="fas fa-file-upload me-2"></i> Upload Proof of Payment</label>
        <input type="file" class="form-control" id="cashProofFile" accept=".jpg,.jpeg,.png,.pdf" required>
        <small class="text-muted d-block mt-1">Accepted: JPG, PNG, PDF | Max size: 25MB</small>
    </div>

    <div class="alert alert-warning small">
        <i class="fas fa-exclamation-triangle me-2"></i>
        A screenshot or proof file is required to submit your payment request.
    </div>
</div>
```

### **Step 4: Create Payment Upload Handler**

Create file: `assets/ajax/upload_payment_proof.php`

```php
<?php
session_start();
require '../../config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate required fields
if (!isset($_POST['booking_id'], $_POST['payment_method'], $_FILES['proof_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$booking_id = intval($_POST['booking_id']);
$payment_method = sanitize_input($_POST['payment_method']);
$user_id = $_SESSION['user_id'];

// Validate file
$file = $_FILES['proof_file'];
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size = 25 * 1024 * 1024; // 25MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF allowed.']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 25MB limit']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/payment_proofs/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$unique_filename = $payment_method . '_' . $booking_id . '_' . time() . '.' . $file_ext;
$file_path = $upload_dir . $unique_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

try {
    // Check if request already exists
    $check_query = "SELECT id FROM gcash_requests WHERE booking_id = ? AND user_id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'A payment request for this booking is already pending']);
        exit;
    }

    // Insert payment request
    $insert_query = "INSERT INTO gcash_requests (booking_id, user_id, amount, payment_method, proof_image, status, requested_at) 
                     VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    
    // Get booking amount
    $amount_query = "SELECT SUM(price) as total FROM (
                        SELECT s.price FROM bookings b
                        JOIN services s ON FIND_IN_SET(LOWER(s.service_name), LOWER(b.service_type))
                        WHERE b.id = ?
                    ) as prices";
    $amount_stmt = $conn->prepare($amount_query);
    $amount_stmt->bind_param("i", $booking_id);
    $amount_stmt->execute();
    $amount_result = $amount_stmt->get_result()->fetch_assoc();
    $amount = $amount_result['total'] ?? 0;
    $amount_stmt->close();

    $insert_stmt->bind_param("iidss", $booking_id, $user_id, $amount, $payment_method, $unique_filename);
    
    if ($insert_stmt->execute()) {
        $request_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        // Create admin notification
        $notif_title = ($payment_method === 'GCASH' ? 'GCASH' : 'Cash on Delivery') . ' Payment Request';
        $notif_message = "New payment request from booking #$booking_id. Amount: ₱" . number_format($amount, 2);
        $notif_query = "INSERT INTO admin_notifications (title, message, type, related_id, status, created_at) 
                        VALUES (?, ?, ?, ?, 'unread', NOW())";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("sssi", $notif_title, $notif_message, $payment_method, $request_id);
        $notif_stmt->execute();
        $notif_stmt->close();

        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Payment request submitted successfully',
            'request_id' => $request_id
        ]);
    } else {
        unlink($file_path);
        echo json_encode(['success' => false, 'message' => 'Failed to create payment request']);
    }

} catch (Exception $e) {
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>
```

### **Step 5: Update gcash-payment.js**

Replace the entire file with:

```javascript
/**
 * GCASH Payment Handler with Proof Upload
 */

document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitGcashPaymentBtn');
    const proofFileInput = document.getElementById('gcashProofFile');
    const gcashModal = document.getElementById('gcashModal');
    const gcashSuccessModal = document.getElementById('gcashSuccessModal');
    
    if (!submitBtn) return;

    // Submit GCASH payment with proof
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // Check if file is selected
        if (!proofFileInput.files || !proofFileInput.files[0]) {
            Swal.fire({
                title: 'Proof Required',
                text: 'Please upload a screenshot or proof of your GCASH payment',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const file = proofFileInput.files[0];
        const max_size = 25 * 1024 * 1024; // 25MB
        const allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

        // Validate file
        if (!allowed_types.includes(file.type)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, and PDF files are allowed',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (file.size > max_size) {
            Swal.fire({
                title: 'File Too Large',
                text: 'File size must not exceed 25MB',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';

        // Get booking ID
        const idNumberElement = document.querySelector('.id-number');
        const bookingId = idNumberElement ? idNumberElement.textContent.replace('#', '').trim() : '';

        if (!bookingId) {
            Swal.fire({
                title: 'Error',
                text: 'Could not find booking ID. Please refresh the page.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        // Get amount
        let totalAmount = 0;
        const breakdownItems = document.querySelectorAll('.breakdown-item');
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector('span:last-child').textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ''));
        }

        // Create FormData with file
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('payment_method', 'GCASH');
        formData.append('proof_file', file);

        // Submit to upload handler
        fetch('../assets/ajax/upload_payment_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // Close GCASH modal
                const bsModal = bootstrap.Modal.getInstance(gcashModal);
                if (bsModal) bsModal.hide();

                // Show success modal
                const successModal = new bootstrap.Modal(gcashSuccessModal);
                successModal.show();

                // Update success modal details
                document.getElementById('successGcashBookingId').textContent = '#' + bookingId;
                document.getElementById('successGcashAmount').textContent = '₱' + totalAmount.toFixed(2);

                // Handle success modal OK button
                document.getElementById('gcashSuccessOkBtn').addEventListener('click', function() {
                    window.location.href = 'user-profile.php';
                });

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = 'user-profile.php';
                }, 3000);
            } else {
                Swal.fire({
                    title: 'Submission Failed',
                    text: data.message || 'An error occurred while submitting your payment request',
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            console.error('Payment submission error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to submit payment request. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    });
});
```

### **Step 6: Update cash-payment.js**

Replace the entire file with:

```javascript
/**
 * Cash on Delivery Payment Handler with Proof Upload
 */

document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('submitCashPaymentBtn');
    const editAddressBtn = document.getElementById('editAddressBtn');
    const saveAddressBtn = document.getElementById('saveAddressBtn');
    const cancelAddressBtn = document.getElementById('cancelAddressBtn');
    const addressDisplay = document.getElementById('addressDisplay');
    const addressEditForm = document.getElementById('addressEditForm');
    const addressText = document.getElementById('addressText');
    const addressInput = document.getElementById('addressInput');
    const proofFileInput = document.getElementById('cashProofFile');
    const cashModal = document.getElementById('cashModal');
    const cashSuccessModal = document.getElementById('cashSuccessModal');
    
    if (!submitBtn) return;

    // Show/hide address editing
    if (editAddressBtn) {
        editAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addressDisplay.style.display = 'none';
            addressEditForm.style.display = 'block';
            addressInput.focus();
        });
    }

    if (cancelAddressBtn) {
        cancelAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addressEditForm.style.display = 'none';
            addressDisplay.style.display = 'block';
            addressInput.value = addressText.textContent;
        });
    }

    if (saveAddressBtn) {
        saveAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const newAddress = addressInput.value.trim();
            
            if (!newAddress) {
                Swal.fire({
                    title: 'Address Required',
                    text: 'Please enter a delivery address',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            addressText.textContent = newAddress;
            addressEditForm.style.display = 'none';
            addressDisplay.style.display = 'block';
            
            Swal.fire({
                title: 'Address Updated',
                text: 'Delivery address has been updated',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        });
    }

    // Submit COD payment with proof
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // Validate address
        const currentAddress = addressText.textContent.trim();
        if (!currentAddress || currentAddress === 'No address provided') {
            Swal.fire({
                title: 'Address Required',
                text: 'Please provide a delivery address',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Check if file is selected
        if (!proofFileInput || !proofFileInput.files || !proofFileInput.files[0]) {
            Swal.fire({
                title: 'Proof Required',
                text: 'Please upload a screenshot or proof of your payment',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const file = proofFileInput.files[0];
        const max_size = 25 * 1024 * 1024; // 25MB
        const allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

        // Validate file
        if (!allowed_types.includes(file.type)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Only JPG, PNG, and PDF files are allowed',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (file.size > max_size) {
            Swal.fire({
                title: 'File Too Large',
                text: 'File size must not exceed 25MB',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';

        // Get booking ID
        const idNumberElement = document.querySelector('.id-number');
        const bookingId = idNumberElement ? idNumberElement.textContent.replace('#', '').trim() : '';

        if (!bookingId) {
            Swal.fire({
                title: 'Error',
                text: 'Could not find booking ID. Please refresh the page.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        // Get amount
        let totalAmount = 0;
        const breakdownItems = document.querySelectorAll('.breakdown-item');
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector('span:last-child').textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ''));
        }

        // Create FormData with file
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('payment_method', 'Cash on Delivery');
        formData.append('proof_file', file);

        // Submit to upload handler
        fetch('../assets/ajax/upload_payment_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // Close payment modal
                const bsModal = bootstrap.Modal.getInstance(cashModal);
                if (bsModal) bsModal.hide();

                // Show success modal
                const successModal = new bootstrap.Modal(cashSuccessModal);
                successModal.show();

                // Update success modal details
                document.getElementById('successCashBookingId').textContent = '#' + bookingId;
                document.getElementById('successCashAmount').textContent = '₱' + totalAmount.toFixed(2);
                document.getElementById('paymentAmountDisplay').textContent = '₱' + totalAmount.toFixed(2);

                // Handle success modal OK button
                document.getElementById('cashSuccessOkBtn').addEventListener('click', function() {
                    window.location.href = 'user-profile.php';
                });

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = 'user-profile.php';
                }, 3000);
            } else {
                Swal.fire({
                    title: 'Submission Failed',
                    text: data.message || 'An error occurred while submitting your payment request',
                    icon: 'error',
                    confirmButtonText: 'Try Again'
                });
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            console.error('Payment submission error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to submit payment request. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    });
});
```

### **Step 7: Update payment_requests-management.php**

Add this code to display proof images and verification buttons. I'll provide the specific sections to add to the payment requests table display.

**Add this CSS** in the `<head>` section:

```html
<style>
.proof-thumbnail {
    max-width: 60px;
    max-height: 60px;
    cursor: pointer;
    border-radius: 4px;
    border: 1px solid #ddd;
    transition: transform 0.2s;
}

.proof-thumbnail:hover {
    transform: scale(1.05);
    border-color: #007bff;
}

.modal-proof-image {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 8px;
}
</style>
```

**Add this modal** before the closing `</body>`:

```html
<!-- Payment Proof Image Modal -->
<div class="modal fade" id="proofImageModal" tabindex="-1" aria-labelledby="proofImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proofImageModalLabel">Payment Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="proofImageDisplay" src="" alt="Payment Proof" class="modal-proof-image">
                <p class="text-muted mt-3" id="proofFileInfo"></p>
            </div>
            <div class="modal-footer">
                <a id
```

