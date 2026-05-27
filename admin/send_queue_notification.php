<?php
//Author Bryce
require '../config.php';

function insertQueueNotification($conn, $userId, $title, $message, $bookingId) {
    $notifQuery = "
        INSERT INTO notifications (user_id, title, message, is_read, created_at, booking_id)
        VALUES (?, ?, ?, 0, NOW(), ?)
    ";
    $notifStmt = mysqli_prepare($conn, $notifQuery);
    mysqli_stmt_bind_param($notifStmt, "issi", $userId, $title, $message, $bookingId);
    mysqli_stmt_execute($notifStmt);
    mysqli_stmt_close($notifStmt);
}

function sendQueueNotification($bookingId, $conn) {
    // Get booking info
    $infoQuery = "
        SELECT b.user_id, b.booking_date, b.time_slot, b.queue_number, b.queue_code
        FROM bookings b
        WHERE b.id = ?
    ";
    $infoStmt = mysqli_prepare($conn, $infoQuery);
    mysqli_stmt_bind_param($infoStmt, "i", $bookingId);
    mysqli_stmt_execute($infoStmt);
    mysqli_stmt_bind_result($infoStmt, $userId, $bookingDate, $timeSlot, $queueNumberDb, $queueCodeDb);
    mysqli_stmt_fetch($infoStmt);
    mysqli_stmt_close($infoStmt);

    $queueNumber = (int)($queueNumberDb ?? 0);
    if ($queueNumber <= 0) {
        $queueQuery = "
            SELECT COUNT(*) + 1 AS queue_number
            FROM bookings
            WHERE booking_date = ? AND time_slot = ? AND id < ?
        ";
        $queueStmt = mysqli_prepare($conn, $queueQuery);
        mysqli_stmt_bind_param($queueStmt, "ssi", $bookingDate, $timeSlot, $bookingId);
        mysqli_stmt_execute($queueStmt);
        mysqli_stmt_bind_result($queueStmt, $queueNumber);
        mysqli_stmt_fetch($queueStmt);
        mysqli_stmt_close($queueStmt);
    }

    $queueCode = !empty($queueCodeDb)
        ? $queueCodeDb
        : ('Q-' . str_pad((string)$queueNumber, 3, '0', STR_PAD_LEFT));

    // Notification message
    $message = "Your queue number is $queueCode. Please pick up your laundry on " .
               date("F j, Y", strtotime($bookingDate)) . " at $timeSlot. Thank you for using Jorish Laundry!";

    insertQueueNotification($conn, $userId, 'Queue Update', $message, $bookingId);
}

function sendQueueStageNotification($bookingId, $conn, $stage, $requestService = null) {
    $infoQuery = "
        SELECT b.user_id, b.queue_number, b.queue_code, b.request_service, b.service_type
        FROM bookings b
        WHERE b.id = ?
    ";
    $infoStmt = mysqli_prepare($conn, $infoQuery);
    mysqli_stmt_bind_param($infoStmt, "i", $bookingId);
    mysqli_stmt_execute($infoStmt);
    $infoResult = mysqli_stmt_get_result($infoStmt);
    $booking = mysqli_fetch_assoc($infoResult);
    mysqli_stmt_close($infoStmt);

    if (!$booking) {
        return;
    }

    $queueCode = !empty($booking['queue_code'])
        ? $booking['queue_code']
        : ('Q-' . str_pad((string)($booking['queue_number'] ?? 0), 3, '0', STR_PAD_LEFT));

    $title = 'Queue Update';
    $message = null;
    
    // Use provided requestService or get from DB
    $currentRequestService = ($requestService !== null) ? $requestService : ($booking['request_service'] ?? '');
    $isDelivery = (strpos($currentRequestService, 'Delivery') !== false);
    
    // Determine services from service_type
    $serviceType = $booking['service_type'] ?? '';
    $hasWash = (stripos($serviceType, 'Wash') !== false);
    $hasDry = (stripos($serviceType, 'Dry') !== false);

    if ($stage === 'Queuing (Assigning Machines)') {
        $title = 'Machine Assigned - Queueing';
        if ($hasWash && $hasDry) {
            $message = "Your laundry ($queueCode) has been assigned to a machine and is preparing to start washing.";
        } elseif ($hasWash) {
            $message = "Your laundry ($queueCode) has been assigned to a machine and is preparing to start washing.";
        } elseif ($hasDry) {
            $message = "Your laundry ($queueCode) has been assigned to a machine and is preparing to start drying.";
        } else {
            $message = "Your laundry ($queueCode) has been assigned to a machine and is preparing to start.";
        }
    } elseif ($stage === 'In Process') {
        $title = 'Laundry In Process';
        if ($hasWash && $hasDry) {
            $message = "Your laundry ($queueCode) is now in process - washing and drying.";
        } elseif ($hasWash) {
            $message = "Your laundry ($queueCode) is now in process - washing.";
        } elseif ($hasDry) {
            $message = "Your laundry ($queueCode) is now in process - drying.";
        } else {
            $message = "Your laundry ($queueCode) is now in process.";
        }
    } elseif ($stage === 'Ready for Pickup') {
        if ($isDelivery) {
            $title = 'Laundry Complete - Ready for Delivery';
            if ($hasWash && $hasDry) {
                $message = "Your laundry ($queueCode) has been washed and dried, and is now ready for delivery.";
            } elseif ($hasWash) {
                $message = "Your laundry ($queueCode) has been washed, and is now ready for delivery.";
            } elseif ($hasDry) {
                $message = "Your laundry ($queueCode) has been dried, and is now ready for delivery.";
            } else {
                $message = "Your laundry ($queueCode) is now ready for delivery.";
            }
        } else {
            $title = 'Laundry Ready for Pickup';
            if ($hasWash && $hasDry) {
                $message = "Your laundry ($queueCode) has been washed and dried, and is now ready for pickup.";
            } elseif ($hasWash) {
                $message = "Your laundry ($queueCode) has been washed, and is now ready for pickup.";
            } elseif ($hasDry) {
                $message = "Your laundry ($queueCode) has been dried, and is now ready for pickup.";
            } else {
                $message = "Your laundry ($queueCode) is now ready for pickup.";
            }
        }
    } elseif ($stage === 'Delivering') {
        $title = 'Your Laundry is on the Way';
        $message = "Your laundry ($queueCode) is now out for delivery. Your rider will be at your location soon!";
    } elseif ($stage === 'Completed / Picked Up') {
        $title = 'Order Completed';
        $message = $isDelivery 
            ? "Your laundry ($queueCode) has been successfully delivered! Thank you for using our service."
            : "Your laundry ($queueCode) has been successfully picked up! Thank you for using our service.";
    } elseif ($stage === 'Missed Pickup') {
        $title = 'Pickup Overdue';
        $message = "Your laundry ($queueCode) pickup is overdue. Please claim it as soon as possible.";
    }

    if ($message !== null) {
        insertQueueNotification($conn, (int)$booking['user_id'], $title, $message, $bookingId);
    }
}