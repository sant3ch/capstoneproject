<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to claim rewards']);
    exit();
}

// Get POST data
$reward_type = isset($_POST['reward_type']) ? trim($_POST['reward_type']) : '';

// Check if reward_type is provided
if (empty($reward_type)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reward type']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Validate reward type
$valid_rewards = [
    'free_wash' => 'Free Wash Load',
    'free_dry' => 'Free Dry Load',
    'free_wash_dry' => 'Free Wash & Dry Load'
];

if (!array_key_exists($reward_type, $valid_rewards)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reward type']);
    exit();
}

$reward_name = $valid_rewards[$reward_type];

// Check if user has enough points
$points_required = [
    'free_wash' => 6,
    'free_dry' => 12,
    'free_wash_dry' => 18
];

$user_query = $conn->prepare("SELECT user_points FROM users WHERE id = ?");
$user_query->bind_param('i', $user_id);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();
$user_query->close();

if (!$user_result) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$user_points = (int)$user_result['user_points'];

if ($user_points < $points_required[$reward_type]) {
    echo json_encode(['success' => false, 'message' => "You don't have enough points for this reward. You need " . $points_required[$reward_type] . " points but only have " . $user_points]);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Check for existing claims
    $check_sql = "SELECT id FROM claimed_rewards 
                 WHERE user_id = ? AND reward_name = ? AND status IN ('Pending', 'Claimed')";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $user_id, $reward_name);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $check_stmt->close();
        throw new Exception("You already have a pending or claimed reward of this type");
    }
    $check_stmt->close();
    
    // Insert new claim
    $insert_sql = "INSERT INTO claimed_rewards (user_id, reward_name, status, claimed_at) 
                  VALUES (?, ?, 'Pending', NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("is", $user_id, $reward_name);
    $insert_stmt->execute();
    $insert_stmt->close();
    
    // Deduct points
    $update_stmt = $conn->prepare("UPDATE users SET user_points = user_points - ? WHERE id = ?");
    $update_stmt->bind_param("ii", $points_required[$reward_type], $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reward claimed successfully! It will be processed soon.',
        'remaining_points' => $user_points - $points_required[$reward_type]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Reward Claim Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error claiming reward: ' . $e->getMessage()]);
}

exit();
?>