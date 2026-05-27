<?php
// includes/user-profile-data.php
// Fetch user profile data and related information

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include shared booking functions
require_once __DIR__ . '/booking-functions.php';

$user_id = $_SESSION['user_id'];

// Fetch user information
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($user_query);
$user_points = $user['user_points'] ?? 0;

// Fetch all services from database for price calculation
$services_from_db = [];
$service_query = "SELECT id, service_name, price FROM services";
$service_result = $conn->query($service_query);
if ($service_result) {
    while ($row = $service_result->fetch_assoc()) {
        $services_from_db[$row['id']] = $row;
        $services_from_db[strtolower($row['service_name'])] = $row;
    }
}

// Fetch booking schedule
$booking_query = mysqli_query($conn, "
    SELECT 
        b.id,
        b.booking_date,
        b.time_slot,
        b.machine_count,
        b.request_service,
        b.status,
        b.service_type,
        b.detergent,
        b.points_claimed,
        b.queue_number,
        b.queue_code,
        b.pickup_status,
        b.machine_names,
        b.order_stage,
        b.estimated_start_time,
        b.estimated_completion_time,
        b.final_amount,
        b.cod_confirmation_photo,
        COALESCE(gr.payment_method, 'GCASH') as payment_method
    FROM bookings b
    LEFT JOIN gcash_requests gr ON b.id = gr.booking_id AND gr.user_id = $user_id
    WHERE b.user_id = $user_id
    ORDER BY
        CASE
            WHEN b.booking_date IS NULL THEN 1
            WHEN YEAR(b.booking_date) = 0 THEN 1
            ELSE 0
        END,
        b.booking_date DESC,
        b.id DESC
");

// Store booking data with calculated amounts for later use
$bookings_with_amounts = [];
if ($booking_query && mysqli_num_rows($booking_query) > 0) {
    while ($booking = mysqli_fetch_assoc($booking_query)) {
        $booking['calculated_amount'] = calculateBookingAmountFromDB($booking, $services_from_db);
        $bookings_with_amounts[] = $booking;
    }
    // Reset pointer for the original query if needed elsewhere
    mysqli_data_seek($booking_query, 0);
}

// Fetch payment history
$payment_query = mysqli_query($conn, "
    SELECT 
        t.id,
        t.customer_name,
        t.user_id,
        t.booking_id,
        t.service_type,
        t.total_amount,
        t.payment_method,
        t.transaction_date,
        b.booking_date,
        b.service_type AS booked_services,
        b.time_slot,
        b.detergent
    FROM transactions t
    LEFT JOIN bookings b ON t.booking_id = b.id
    WHERE t.user_id = '$user_id'
    ORDER BY t.transaction_date DESC
");

// Fetch claimed rewards
$claimed_rewards_query = mysqli_query($conn, "
    SELECT 
        id,
        reward_name,
        status,
        DATE_FORMAT(claimed_at, '%M %e, %Y %h:%i %p') as claimed_date,
        DATE_FORMAT(approved_at, '%M %e, %Y %h:%i %p') as approved_date
    FROM claimed_rewards
    WHERE user_id = $user_id AND (status != 'Rejected' OR is_read_by_user = 0)
    ORDER BY claimed_at DESC
");

// Fetch notifications
$notifications = [];
$notification_stmt = $conn->prepare("SELECT title, message, created_at FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC LIMIT 10");

if ($notification_stmt) {
    $notification_stmt->bind_param("i", $user_id);
    if ($notification_stmt->execute()) {
        $notification_result = $notification_stmt->get_result();
        if ($notification_result && $notification_result->num_rows > 0) {
            $notifications = $notification_result->fetch_all(MYSQLI_ASSOC);
        }
    }
    $notification_stmt->close();
}

// Store services array for use in user-profile.php
$GLOBALS['services_from_db'] = $services_from_db;
$GLOBALS['bookings_with_amounts'] = $bookings_with_amounts;
?>