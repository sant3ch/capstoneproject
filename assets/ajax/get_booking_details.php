<?php
// ajax/get_booking_details.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

$query = "SELECT 
            b.id,
            b.booking_date,
            b.time_slot,
            b.service_type,
            b.detergent,
            b.machine_count,
            b.machine_names,
            b.request_service,
            b.queue_number,
            b.queue_code,
            u.first_name,
            u.last_name
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Calculate amount
    require_once __DIR__ . '/../../includes/booking-functions.php';
    $amount = calculateBookingAmountFromDB($row);
    
    // Format services
    $services = array_filter(array_map('trim', explode(',', $row['service_type'] ?? '')));
    $requestedServices = array_filter(array_map('trim', explode(',', $row['request_service'] ?? '')));
    
    // Format detergent/addons
    $detergents = array_filter(array_map('trim', explode(',', $row['detergent'] ?? '')));
    
    // Format machines
    $machines = array_filter(array_map('trim', explode(',', $row['machine_names'] ?? '')));
    
    // Generate queue code if needed
    $queueCode = $row['queue_code'] ?? null;
    if (empty($queueCode) && !empty($row['queue_number'])) {
        $queueCode = 'Q-' . str_pad((string)$row['queue_number'], 3, '0', STR_PAD_LEFT);
    }
    
    echo json_encode([
        'success' => true,
        'booking' => [
            'id' => $row['id'],
            'customer_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'booking_date' => date('M d, Y', strtotime($row['booking_date'])),
            'time_slot' => $row['time_slot'],
            'services' => $services,
            'requested_services' => $requestedServices,
            'detergent' => $detergents,
            'machine_count' => $row['machine_count'],
            'machines' => $machines,
            'queue_number' => $row['queue_number'],
            'queue_code' => $queueCode,
            'amount' => $amount,
            'amount_formatted' => '₱' . number_format($amount, 2)
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
}

$stmt->close();
$conn->close();
?>

