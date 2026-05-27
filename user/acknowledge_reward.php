<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$reward_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

if ($reward_id > 0) {
    $stmt = $conn->prepare("UPDATE claimed_rewards SET is_read_by_user = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reward_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true]);
?>
