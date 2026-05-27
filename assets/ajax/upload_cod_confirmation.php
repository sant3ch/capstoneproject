<?php
session_start();
require '../../config.php';
require_once '../../includes/admin-notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

header('Content-Type: application/json');

// Get booking ID and validate
$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$userId = (int)$_SESSION['user_id'];

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

// Verify booking belongs to user
$verifyStmt = $conn->prepare("SELECT id, order_stage FROM bookings WHERE id = ? AND user_id = ?");
$verifyStmt->bind_param('ii', $bookingId, $userId);
$verifyStmt->execute();
$booking = $verifyStmt->get_result()->fetch_assoc();
$verifyStmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or does not belong to you']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['confirmation_photo']) || $_FILES['confirmation_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['confirmation_photo'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file extension
if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed']);
    exit();
}

// Validate file size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit();
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../../uploads/cod_confirmations/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$timestamp = time();
$randomString = bin2hex(random_bytes(4));
$filename = "cod_confirmation_{$bookingId}_{$timestamp}_{$randomString}.{$fileExtension}";
$filePath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit();
}

// Fetch user's current points
$userPoints = 0;
$pointsToAward = 1; // Award 1 point per booking completion
$pointsAlreadyClaimed = 0;

$userStmt = $conn->prepare("SELECT user_points FROM users WHERE id = ?");
if ($userStmt) {
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result()->fetch_assoc();
    $userPoints = $userResult ? (int)$userResult['user_points'] : 0;
    $userStmt->close();
}

// Check if points were already claimed for this booking
$checkPointsStmt = $conn->prepare("SELECT points_claimed FROM bookings WHERE id = ? AND user_id = ?");
if ($checkPointsStmt) {
    $checkPointsStmt->bind_param('ii', $bookingId, $userId);
    $checkPointsStmt->execute();
    $pointsResult = $checkPointsStmt->get_result()->fetch_assoc();
    $pointsAlreadyClaimed = $pointsResult ? (int)$pointsResult['points_claimed'] : 0;
    $checkPointsStmt->close();
}

// Calculate new points total
$newUserPoints = $userPoints + $pointsToAward;

// Update booking with confirmation photo filename
$updateStmt = $conn->prepare(
    "UPDATE bookings 
     SET cod_confirmation_photo = ?, order_stage = 'Completed / Picked Up', status = 'Completed', pickup_status = 'Already Picked', points_claimed = 1
     WHERE id = ? AND user_id = ?"
);

if (!$updateStmt) {
    // Delete uploaded file if database update fails
    unlink($filePath);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$updateStmt->bind_param('sii', $filename, $bookingId, $userId);

if ($updateStmt->execute()) {
    $updateStmt->close();
    
    // Award points to user if not already claimed
    if (!$pointsAlreadyClaimed && $pointsToAward > 0) {
        $pointsUpdateStmt = $conn->prepare("UPDATE users SET user_points = ? WHERE id = ?");
        if ($pointsUpdateStmt) {
            $pointsUpdateStmt->bind_param('ii', $newUserPoints, $userId);
            $pointsUpdateStmt->execute();
            $pointsUpdateStmt->close();
        }
    }
    
    // Insert notification for user
    $notifStmt = $conn->prepare(
        "INSERT INTO notifications (user_id, booking_id, title, message, is_read, created_at) 
         VALUES (?, ?, ?, ?, 0, NOW())"
    );
    
    $notifTitle = "Order Complete - COD Confirmed";
    $notifMessage = "Your laundry order #$bookingId has been marked as complete with photo confirmation.";
    
    if ($notifStmt) {
        $notifStmt->bind_param('iiss', $userId, $bookingId, $notifTitle, $notifMessage);
        $notifStmt->execute();
        $notifStmt->close();
    }
    
    // Send notification to admin
    $adminTitle = "COD Payment Confirmation Received - Order #$bookingId";
    $adminMessage = "User has uploaded photo proof for Cash on Delivery order #$bookingId. Please verify and confirm payment completion.";
    sendAdminNotification($adminTitle, $adminMessage, 'cod_confirmation', $bookingId);
    
    // Update gcash_requests status to approved if exists (for COD payments tracked in gcash_requests)
    $updateRequestStmt = $conn->prepare(
        "UPDATE gcash_requests SET status = 'approved', approved_at = NOW() WHERE booking_id = ? AND payment_method = 'Cash on Delivery'"
    );
    if ($updateRequestStmt) {
        $updateRequestStmt->bind_param('i', $bookingId);
        $updateRequestStmt->execute();
        $updateRequestStmt->close();
    }
    
    error_log("COD confirmation uploaded for booking #$bookingId by user #$userId. File: $filename. Points awarded: $pointsToAward (Total: $newUserPoints)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Order completed successfully with photo confirmation',
        'filename' => $filename,
        'points_awarded' => !$pointsAlreadyClaimed ? $pointsToAward : 0,
        'total_points' => $newUserPoints
    ]);
    exit();
} else {
    // Delete uploaded file if database update fails
    unlink($filePath);
    $updateStmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to update booking: ' . $conn->error]);
    exit();
}
?>
