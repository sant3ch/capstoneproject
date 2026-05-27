<?php
session_start();
require "../../config.php";
require_once "../../includes/auth-check.php";

/** @var mysqli $conn */

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

if (!isset($_POST["booking_id"], $_POST["payment_method"], $_POST["amount"])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

function sanitize_input(string $data): string {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, "UTF-8");
}

$booking_id = intval($_POST["booking_id"]);
$payment_method = sanitize_input($_POST["payment_method"]);
$amount = floatval($_POST["amount"]);
$user_id = $_SESSION["user_id"] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "User not authenticated"]);
    exit;
}

if ($payment_method !== "IN_STORE") {
    echo json_encode(["success" => false, "message" => "Invalid payment method for this handler"]);
    exit;
}

if ($amount <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid amount provided"]);
    exit;
}

try {
    // Check if booking exists and belongs to the user
    $booking_query = "SELECT id, status, service_type FROM bookings WHERE id = ? AND user_id = ?";
    $booking_stmt = $conn->prepare($booking_query);
    if (!$booking_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $booking_stmt->bind_param("ii", $booking_id, $user_id);
    $booking_stmt->execute();
    $booking = $booking_stmt->get_result()->fetch_assoc();
    $booking_stmt->close();
    
    if (!$booking) {
        echo json_encode(["success" => false, "message" => "Booking not found"]);
        exit;
    }

    // Get reward information if provided
    $reward_id = isset($_POST["reward_id"]) ? intval($_POST["reward_id"]) : null;
    $reward_name = isset($_POST["reward_name"]) ? sanitize_input($_POST["reward_name"]) : null;
    $discount_amount = isset($_POST["discount_amount"]) ? floatval($_POST["discount_amount"]) : 0;
    $original_amount = isset($_POST["original_amount"]) ? floatval($_POST["original_amount"]) : $amount;
    
    // Get user information for notification
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

    // Check if there's already an in-store payment record
    $check_query = "SELECT id FROM gcash_requests WHERE booking_id = ? AND user_id = ? AND payment_method = 'IN_STORE'";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt) {
        $check_stmt->bind_param("ii", $booking_id, $user_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            echo json_encode(["success" => false, "message" => "An in-store payment record for this booking already exists"]);
            exit;
        }
    }

    // Create reference number
    $reference_number = "IN-STORE-" . date("Ymd") . "-" . str_pad($booking_id, 6, "0", STR_PAD_LEFT);

    // Update the booking with in-store payment method and final amount
    $update_booking_query = "UPDATE bookings SET final_amount = ? WHERE id = ? AND user_id = ?";
    $update_booking_stmt = $conn->prepare($update_booking_query);
    if (!$update_booking_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $update_booking_stmt->bind_param("dii", $amount, $booking_id, $user_id);
    
    if ($update_booking_stmt->execute()) {
        $update_booking_stmt->close();

        // Insert in-store payment record into gcash_requests table
        $insert_query = "INSERT INTO gcash_requests (booking_id, user_id, customer_name, payment_method, amount, reward_id, reward_name, discount_amount, original_amount, reference_number, proof_image, status, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        if (!$insert_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // For in-store payments, we don't have a proof image
        $proof_image = null;
        $insert_stmt->bind_param("iissddsdsss", $booking_id, $user_id, $customer_name, $payment_method, $amount, $reward_id, $reward_name, $discount_amount, $original_amount, $reference_number, $proof_image);
        
        if ($insert_stmt->execute()) {
            $insert_stmt->close();

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

            // Create customer notification
            $cust_notif_title = "In-Store Booking Confirmed";
            $cust_notif_message = "Your in-store booking #" . $booking_id . " (Queue: " . $queue_code . ") has been confirmed. Amount to pay: ₱" . number_format($amount, 2) . ". Please bring your laundry at the scheduled time.";
            
            if ($reward_name && $discount_amount > 0) {
                $cust_notif_message .= " Reward applied: " . $reward_name . " (-₱" . number_format($discount_amount, 2) . ").";
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
            
            $cust_notif_query = "INSERT INTO notifications (user_id, title, message, booking_id, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
            $cust_notif_stmt = $conn->prepare($cust_notif_query);
            if ($cust_notif_stmt) {
                $cust_notif_stmt->bind_param("issi", $user_id, $cust_notif_title, $cust_notif_message, $booking_id);
                $cust_notif_stmt->execute();
                $cust_notif_stmt->close();
            }

            // Create admin notification
            $admin_notif_type = "in_store_booking";
            $admin_notif_title = "In-Store Booking Confirmed - Booking #" . $booking_id;
            $admin_notif_message = "New in-store booking from " . $customer_name . " for ₱" . number_format($amount, 2);
            
            if ($reward_name && $discount_amount > 0) {
                $admin_notif_message .= " (with " . $reward_name . ": -₱" . number_format($discount_amount, 2) . ")";
            }
            
            $admin_notif_message .= " (Queue: " . $queue_code . ", Stage: " . $order_stage . ")";
            
            $admin_notif_query = "INSERT INTO admin_notifications (title, message, type, related_id, status, created_at) VALUES (?, ?, ?, ?, \"unread\", NOW())";
            $admin_notif_stmt = $conn->prepare($admin_notif_query);
            if ($admin_notif_stmt) {
                $admin_notif_stmt->bind_param("sssi", $admin_notif_title, $admin_notif_message, $admin_notif_type, $booking_id);
                $admin_notif_stmt->execute();
                $admin_notif_stmt->close();
            }

            http_response_code(200);
            echo json_encode(["success" => true, "message" => "In-store booking confirmed successfully"]);
        } else {
            throw new Exception("Insert failed: " . $insert_stmt->error);
        }
    } else {
        throw new Exception("Update failed: " . $update_booking_stmt->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
