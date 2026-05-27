<?php
session_start();
require "../../config.php";
require_once "../../includes/auth-check.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

if (!isset($_POST["booking_id"], $_POST["payment_method"], $_FILES["proof_file"])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, "UTF-8");
}

$booking_id = intval($_POST["booking_id"]);
$payment_method = sanitize_input($_POST["payment_method"]);
$user_id = $_SESSION["user_id"] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "User not authenticated"]);
    exit;
}

$file = $_FILES["proof_file"];
$allowed_types = ["image/jpeg", "image/png", "application/pdf"];
$max_size = 25 * 1024 * 1024;

if (!in_array($file["type"], $allowed_types)) {
    echo json_encode(["success" => false, "message" => "Invalid file type. Only JPG, PNG, and PDF allowed."]);
    exit;
}

if ($file["size"] > $max_size) {
    echo json_encode(["success" => false, "message" => "File size exceeds 25MB limit"]);
    exit;
}

if ($file["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "File upload failed"]);
    exit;
}

$upload_dir = "../../uploads/payment_proofs/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
$unique_filename = strtolower(str_replace(" ", "_", $payment_method)) . "_" . $booking_id . "_" . time() . "." . $file_ext;
$file_path = $upload_dir . $unique_filename;

if (!move_uploaded_file($file["tmp_name"], $file_path)) {
    echo json_encode(["success" => false, "message" => "Failed to save uploaded file"]);
    exit;
}

try {
    $check_query = "SELECT id FROM gcash_requests WHERE booking_id = ? AND user_id = ? AND status = \"pending\"";
    $check_stmt = $conn->prepare($check_query);
    if (!$check_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        unlink($file_path);
        echo json_encode(["success" => false, "message" => "A payment request for this booking is already pending"]);
        exit;
    }

    $booking_query = "SELECT id, service_type, user_id FROM bookings WHERE id = ? AND user_id = ?";
    $booking_stmt = $conn->prepare($booking_query);
    if (!$booking_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $booking_stmt->bind_param("ii", $booking_id, $user_id);
    $booking_stmt->execute();
    $booking = $booking_stmt->get_result()->fetch_assoc();
    $booking_stmt->close();
    
    if (!$booking) {
        unlink($file_path);
        echo json_encode(["success" => false, "message" => "Booking not found"]);
        exit;
    }

    // Get the amount from the POST request (sent from JavaScript)
    $amount = isset($_POST["amount"]) ? floatval($_POST["amount"]) : 0;
    $reward_id = isset($_POST["reward_id"]) ? intval($_POST["reward_id"]) : null;
    $reward_name = isset($_POST["reward_name"]) ? sanitize_input($_POST["reward_name"]) : null;
    $discount_amount = isset($_POST["discount_amount"]) ? floatval($_POST["discount_amount"]) : 0;
    $original_amount = isset($_POST["original_amount"]) ? floatval($_POST["original_amount"]) : $amount;
    
    if ($amount <= 0) {
        unlink($file_path);
        echo json_encode(["success" => false, "message" => "Invalid amount provided"]);
        exit;
    }
    
    // Get user's customer name for notification
    $user_query = "SELECT first_name, last_name FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_query);
    if (!$user_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    
    $customer_name = ($user_result) ? $user_result["first_name"] . " " . $user_result["last_name"] : "Customer";
    $reference_number = strtoupper(str_replace(" ", "-", $payment_method)) . "-" . date("Ymd") . "-" . str_pad($booking_id, 6, "0", STR_PAD_LEFT);

    $insert_query = "INSERT INTO gcash_requests (booking_id, user_id, customer_name, payment_method, amount, reward_id, reward_name, discount_amount, original_amount, reference_number, proof_image, status, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \"pending\", NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    if (!$insert_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $insert_stmt->bind_param("iissisddsss", $booking_id, $user_id, $customer_name, $payment_method, $amount, $reward_id, $reward_name, $discount_amount, $original_amount, $reference_number, $unique_filename);
    
    if ($insert_stmt->execute()) {
        $request_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        // Update the final_amount in the bookings table
        $update_booking_stmt = $conn->prepare("UPDATE bookings SET final_amount = ? WHERE id = ?");
        $update_booking_stmt->bind_param("di", $amount, $booking_id);
        $update_booking_stmt->execute();
        $update_booking_stmt->close();

        // Get booking queue info for notification
        $queue_query = "SELECT queue_code, order_stage FROM bookings WHERE id = ?";
        $queue_stmt = $conn->prepare($queue_query);
        if ($queue_stmt) {
            $queue_stmt->bind_param("i", $booking_id);
            $queue_stmt->execute();
            $queue_result = $queue_stmt->get_result()->fetch_assoc();
            $queue_code = $queue_result ? $queue_result["queue_code"] : "N/A";
            $order_stage = $queue_result ? $queue_result["order_stage"] : "Unknown";
            $queue_stmt->close();
        }

        // Mark reward as Used if provided
        if ($reward_id) {
            $update_reward_stmt = $conn->prepare("UPDATE claimed_rewards SET status = 'Used' WHERE id = ? AND user_id = ?");
            if ($update_reward_stmt) {
                $update_reward_stmt->bind_param("ii", $reward_id, $user_id);
                $update_reward_stmt->execute();
                $update_reward_stmt->close();
            }
        }

        // Create customer notification
        $cust_notif_title = $payment_method . " Payment Submitted";
        $cust_notif_message = "Your " . $payment_method . " payment for booking #" . $booking_id . " (Queue: " . $queue_code . ") has been submitted. Amount: ₱" . number_format($amount, 2) . ". Stage: " . $order_stage . ". Waiting for admin verification.";
        
        $cust_notif_query = "INSERT INTO notifications (user_id, title, message, booking_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
        $cust_notif_stmt = $conn->prepare($cust_notif_query);
        if ($cust_notif_stmt) {
            $cust_notif_stmt->bind_param("issi", $user_id, $cust_notif_title, $cust_notif_message, $booking_id);
            $cust_notif_stmt->execute();
            $cust_notif_stmt->close();
        }

        // Create admin notification
        $admin_notif_type = ($payment_method === "GCASH") ? "gcash_request" : "cod_request";
        $admin_notif_title = $payment_method . " Payment Request - Booking #" . $booking_id;
        $admin_notif_message = "New " . $payment_method . " payment request from " . $customer_name . " for ₱" . number_format($amount, 2);
        
        if ($reward_name && $discount_amount > 0) {
            $admin_notif_message .= " (with " . $reward_name . ": -₱" . number_format($discount_amount, 2) . ")";
        }
        
        $admin_notif_message .= " (Queue: " . $queue_code . ", Stage: " . $order_stage . ")";
        
        $admin_notif_query = "INSERT INTO admin_notifications (title, message, type, related_id, status, created_at) VALUES (?, ?, ?, ?, \"unread\", NOW())";
        $admin_notif_stmt = $conn->prepare($admin_notif_query);
        if ($admin_notif_stmt) {
            $admin_notif_stmt->bind_param("sssi", $admin_notif_title, $admin_notif_message, $admin_notif_type, $request_id);
            $admin_notif_stmt->execute();
            $admin_notif_stmt->close();
        }

        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Payment request submitted successfully", "request_id" => $request_id]);
    } else {
        unlink($file_path);
        throw new Exception("Insert failed: " . $insert_stmt->error);
    }

} catch (Exception $e) {
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
