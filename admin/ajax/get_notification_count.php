<?php
session_start();
require '../../config.php';
require_once '../../includes/auth-check-admin.php';

header('Content-Type: application/json');

try {
    // Get unread notification count
    $unread_query = "SELECT COUNT(*) as count FROM admin_notifications WHERE status = 'unread'";
    $result = mysqli_query($conn, $unread_query);
    
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($conn));
    }
    
    $data = mysqli_fetch_assoc($result);
    
    echo json_encode([
        'status' => 'success',
        'count' => intval($data['count'] ?? 0)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'count' => 0
    ]);
}
?>
