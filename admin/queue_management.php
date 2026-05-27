<?php
session_start();
require '../config.php';
require_once 'send_queue_notification.php';
require_once '../includes/booking-functions.php';
require_once '../includes/maintenance-functions.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Set timezone for consistency
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// Auto-transition bookings from "In Process" to "Ready for Pickup" when completion time is reached
autoUpdateReadyBookings($conn);

// Auto-transition bookings from "Ready for Pickup" to "Delivering" (optional - can be manual)
autoMoveToDeliveringStage();

// Auto-transition bookings to "Missed Pickup" if they were not picked up on their scheduled date
autoMarkOverduePickups($conn);

function getQueueCode(array $booking): string {
    if (!empty($booking['queue_code'])) {
        return $booking['queue_code'];
    }

    $queueNumber = (int)($booking['queue_number'] ?? 0);
    if ($queueNumber <= 0) {
        return 'N/A';
    }

    return 'Q-' . str_pad((string)$queueNumber, 3, '0', STR_PAD_LEFT);
}

function parseTimeSlot(string $timeSlot): array {
    // Parse time slot like "8:30 AM - 10:00 AM" into start and end times
    $parts = explode('-', $timeSlot);
    if (count($parts) !== 2) {
        return ['start' => '09:00', 'end' => '10:30'];
    }

    $startStr = trim($parts[0]);
    $endStr = trim($parts[1]);

    // Convert 12-hour format to 24-hour format
    $startTime = date('H:i', strtotime($startStr));
    $endTime = date('H:i', strtotime($endStr));

    return [
        'start' => $startTime,
        'end' => $endTime
    ];
}

function calculateTimesFromTimeSlot(string $bookingDate, string $timeSlot): array {
    $parsed = parseTimeSlot($timeSlot);
    $startDateTime = $bookingDate . ' ' . $parsed['start'] . ':00';
    $endDateTime = $bookingDate . ' ' . $parsed['end'] . ':00';

    return [
        'start' => $startDateTime,
        'end' => $endDateTime
    ];
}

function releaseMachines(mysqli $conn, string $machineNames): void {
    $machines = array_filter(array_map('trim', explode(',', $machineNames)));
    foreach ($machines as $machineName) {
        $stmt = $conn->prepare("UPDATE machines SET status = 'Available' WHERE machine_name = ? AND status = 'Unavailable'");
        if ($stmt) {
            $stmt->bind_param('s', $machineName);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                // Increment usage count ONLY if we actually updated the status (i.e., it was Unavailable)
                incrementMachineUsage($machineName, $conn);
            }
            $stmt->close();
        }
    }
}

/**
 * Release machines of a specific type (washer/dryer) and increment usage.
 * Only releases if the machine's current status is 'Unavailable'.
 */
function releaseMachinesByType(mysqli $conn, string $machineNames, string $type): void {
    $machines = array_filter(array_map('trim', explode(',', $machineNames)));
    foreach ($machines as $machineName) {
        // Check machine type and current status
        $checkStmt = $conn->prepare("SELECT machine_type, status FROM machines WHERE machine_name = ?");
        $checkStmt->bind_param('s', $machineName);
        $checkStmt->execute();
        $res = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        
        if ($res && $res['machine_type'] === $type && $res['status'] === 'Unavailable') {
            $updateStmt = $conn->prepare("UPDATE machines SET status = 'Available' WHERE machine_name = ?");
            $updateStmt->bind_param('s', $machineName);
            $updateStmt->execute();
            if ($updateStmt->affected_rows > 0) {
                incrementMachineUsage($machineName, $conn);
                error_log("Machine '$machineName' ($type) released and usage incremented.");
            }
            $updateStmt->close();
        }
    }
}


function assignAvailableMachine(mysqli $conn, int $bookingId): array {
    error_log("assignAvailableMachine called with bookingId: $bookingId");
    
    $bookingStmt = $conn->prepare('SELECT id, machine_names, machine_count, order_stage, booking_date, time_slot FROM bookings WHERE id = ?');
    $bookingStmt->bind_param('i', $bookingId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();

    if (!$booking) {
        error_log("Booking not found for ID: $bookingId");
        return ['ok' => false, 'message' => 'Booking not found.'];
    }

    error_log("Booking found. machine_names: " . ($booking['machine_names'] ?? 'NULL') . ", order_stage: " . ($booking['order_stage'] ?? 'NULL'));

    // STRICT MODE: Only use user-selected machines, NO fallback
    if (empty($booking['machine_names'])) {
        error_log("No machine_names for booking $bookingId");
        return ['ok' => false, 'message' => 'User has not selected machines for this booking.'];
    }

    $selectedMachines = array_filter(array_map('trim', explode(',', $booking['machine_names'])));
    $machinesToAssign = [];
    $unavailableMachines = [];

    // Check if ALL selected machines are available
    foreach ($selectedMachines as $machineName) {
        $checkStmt = $conn->prepare("SELECT status FROM machines WHERE machine_name = ?");
        $checkStmt->bind_param('s', $machineName);
        $checkStmt->execute();
        $result = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        
        if ($result && $result['status'] === 'Available') {
            $machinesToAssign[] = $machineName;
        } else {
            $unavailableMachines[] = $machineName;
        }
    }

    // STRICT: If ANY selected machine is unavailable, fail completely
    if (!empty($unavailableMachines)) {
        return ['ok' => false, 'message' => 'Selected machine(s) not available: ' . implode(', ', $unavailableMachines)];
    }

    if (empty($machinesToAssign)) {
        return ['ok' => false, 'message' => 'No machines could be assigned.'];
    }

    // Calculate start and end times based on user's selected time slot
    $timesCalc = calculateTimesFromTimeSlot($booking['booking_date'], $booking['time_slot']);
    $startAt = $timesCalc['start'];
    $endAt = $timesCalc['end'];

    $conn->begin_transaction();
    try {
        // Update machines to Unavailable
        foreach ($machinesToAssign as $machineName) {
            $updateMachine = $conn->prepare("UPDATE machines SET status = 'Unavailable' WHERE machine_name = ?");
            if (!$updateMachine) {
                throw new Exception('Prepare failed for machine update: ' . $conn->error);
            }
            if (!$updateMachine->bind_param('s', $machineName)) {
                throw new Exception('Bind param failed: ' . $updateMachine->error);
            }
            if (!$updateMachine->execute()) {
                throw new Exception('Execute failed for machine update: ' . $updateMachine->error);
            }
            $updateMachine->close();
            error_log("Machine '$machineName' assigned to booking $bookingId.");
        }

        // Move booking to Queuing state first (10-second queue animation)
        $machineNameStr = implode(', ', $machinesToAssign);
        
        $updateBooking = $conn->prepare(
            "UPDATE bookings
             SET order_stage = 'Queuing (Assigning Machines)',
                 overdue_notified = 0
             WHERE id = ?"
        );
        if (!$updateBooking) {
            throw new Exception('Prepare failed for booking update: ' . $conn->error);
        }
        if (!$updateBooking->bind_param('i', $bookingId)) {
            throw new Exception('Bind param failed: ' . $updateBooking->error);
        }
        if (!$updateBooking->execute()) {
            throw new Exception('Execute failed for booking update: ' . $updateBooking->error);
        }
        
        // Check if the update actually affected any rows
        if ($updateBooking->affected_rows === 0) {
            $updateBooking->close();
            throw new Exception('Booking update did not affect any rows. Booking ID ' . $bookingId . ' may not exist or already updated.');
        }
        
        $updateBooking->close();

        $conn->commit();
        
        error_log("assignAvailableMachine SUCCESS for booking $bookingId. Machines: $machineNameStr");
        
        // Return success with special flag to trigger 10-second queue animation
        return [
            'ok' => true,
            'message' => "Queuing machine(s) {$machineNameStr}...",
            'booking_id' => $bookingId,
            'machines' => $machineNameStr,
            'start_time' => $startAt,
            'end_time' => $endAt,
            'is_queueing' => true
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("assignAvailableMachine FAILED for booking $bookingId: " . $e->getMessage());
        return ['ok' => false, 'message' => 'Machine assignment failed: ' . $e->getMessage()];
    }
}

function autoMarkOverduePickups(mysqli $conn): void {
    $stmt = $conn->prepare(
        "SELECT id
         FROM bookings
         WHERE order_stage = 'Ready for Pickup'
           AND pickup_status = 'Waiting for Pick Up'
           AND booking_date < CURDATE()
           AND overdue_notified = 0"
    );

    if (!$stmt) {
        return;
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $bookingId = (int)$row['id'];

        $update = $conn->prepare(
            "UPDATE bookings
             SET order_stage = 'Missed Pickup', overdue_notified = 1
             WHERE id = ?"
        );
        $update->bind_param('i', $bookingId);
        $update->execute();
        $update->close();

        sendQueueStageNotification($bookingId, $conn, 'Missed Pickup');
    }
}

function autoUpdateReadyBookings(mysqli $conn): void {
    $currentTime = date('Y-m-d H:i:s');
    $nowTs = time();
    
    // Get all bookings that are In Process
    $stmt = $conn->prepare(
        "SELECT id, machine_names, service_type, estimated_start_time, estimated_completion_time, request_service
         FROM bookings
         WHERE order_stage = 'In Process'
           AND status <> 'Cancelled'"
    );

    if (!$stmt) return;
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($bookings as $row) {
        $bookingId = (int)$row['id'];
        $machineNames = $row['machine_names'] ?? '';
        $serviceType = $row['service_type'] ?? '';
        $etaStartRaw = $row['estimated_start_time'];
        $etaEndRaw = $row['estimated_completion_time'];
        
        if (empty($etaStartRaw) || empty($etaEndRaw) || empty($machineNames)) continue;

        // Determine services
        $hasWasher = (stripos($serviceType, 'Washer') !== false || stripos($serviceType, 'Wash & Dry') !== false);
        $hasDryer = (stripos($serviceType, 'Dryer') !== false || stripos($serviceType, 'Wash & Dry') !== false);
        
        $startTimeObj = new DateTime($etaStartRaw);
        $endTimeObj = new DateTime($etaEndRaw);
        $totalDuration = $endTimeObj->getTimestamp() - $startTimeObj->getTimestamp();
        
        $washEndTime = null;
        $dryEndTime = $etaEndRaw;

        if ($hasWasher && $hasDryer) {
            $halfDuration = $totalDuration / 2;
            $washEndTimeObj = clone $startTimeObj;
            $washEndTimeObj->modify('+' . intval($halfDuration) . ' seconds');
            $washEndTime = $washEndTimeObj->format('Y-m-d H:i:s');
        } elseif ($hasWasher) {
            $washEndTime = $etaEndRaw;
            $dryEndTime = null;
        } elseif ($hasDryer) {
            $washEndTime = null;
            $dryEndTime = $etaEndRaw;
        }

        // 1. Release Washer if washing phase is done
        if ($hasWasher && !empty($washEndTime) && $nowTs >= strtotime($washEndTime)) {
            releaseMachinesByType($conn, $machineNames, 'washer');
        }

        // 2. Release Dryer and Complete Booking if drying phase (or whole booking) is done
        $finalEndTime = !empty($dryEndTime) ? strtotime($dryEndTime) : (!empty($washEndTime) ? strtotime($washEndTime) : 0);
        
        if ($finalEndTime > 0 && $nowTs >= $finalEndTime) {
            // Release whatever is left (Dryers)
            releaseMachinesByType($conn, $machineNames, 'dryer');
            
            // Final move to Ready for Pickup
            $update = $conn->prepare(
                "UPDATE bookings
                 SET order_stage = 'Ready for Pickup',
                     status = 'Completed',
                     pickup_status = 'Waiting for Pick Up',
                     process_completed_at = ?
                 WHERE id = ?"
            );
            $update->bind_param('ss', $currentTime, $bookingId);
            $update->execute();
            $update->close();

            // Get request_service for notification
            $request_service = $row['request_service'] ?? '';

            sendQueueStageNotification($bookingId, $conn, 'Ready for Pickup', $request_service);
        }
    }
}

function calculateProgressData(?string $estimatedStartTime = null, ?string $estimatedEndTime = null): array {
    if (empty($estimatedStartTime) || empty($estimatedEndTime)) {
        return [
            'percentage' => 0,
            'timeRemaining' => 'N/A',
            'startTime' => 'N/A',
            'endTime' => 'N/A',
            'isReady' => false,
            'progressColor' => 'success',
            'status' => 'pending'
        ];
    }

    $currentTime = new DateTime();
    $startTime = new DateTime($estimatedStartTime);
    $endTime = new DateTime($estimatedEndTime);

    if ($currentTime < $startTime) {
        $totalSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
        $percentage = 0;
        $timeRemaining = $currentTime->diff($endTime)->format('%H:%I:%S');
        $status = 'pending';
    } elseif ($currentTime >= $endTime) {
        $percentage = 100;
        $timeRemaining = '0 mins';
        $status = 'ready';
        $isReady = true;
    } else {
        $totalSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
        $elapsedSeconds = $currentTime->getTimestamp() - $startTime->getTimestamp();
        $percentage = (int)(($elapsedSeconds / $totalSeconds) * 100);
        $remainingSeconds = $endTime->getTimestamp() - $currentTime->getTimestamp();
        $remainingMins = ceil($remainingSeconds / 60);
        $timeRemaining = $remainingMins . ' mins';
        $status = 'processing';
    }

    $percentage = min(100, max(0, $percentage));

    if ($percentage >= 90) {
        $progressColor = 'danger';
    } elseif ($percentage >= 70) {
        $progressColor = 'warning';
    } else {
        $progressColor = 'success';
    }

    return [
        'percentage' => $percentage,
        'timeRemaining' => $timeRemaining,
        'startTime' => $startTime->format('g:i A'),
        'endTime' => $endTime->format('g:i A'),
        'isReady' => $percentage >= 100,
        'progressColor' => $progressColor,
        'status' => $status
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    error_log("POST received. booking_id: $bookingId, assign_machine: " . (isset($_POST['assign_machine']) ? 'yes' : 'no') . ", auto_assign_next: " . (isset($_POST['auto_assign_next']) ? 'yes' : 'no') . ", update_stage: " . (isset($_POST['update_stage']) ? 'yes' : 'no'));

    if (isset($_POST['assign_machine']) && $bookingId > 0) {
        $result = assignAvailableMachine($conn, $bookingId);
        if ($result['ok']) {
            // Verify booking was actually updated
            $verifyStmt = $conn->prepare('SELECT id, order_stage, status, machine_names FROM bookings WHERE id = ?');
            if (!$verifyStmt) {
                $_SESSION['error'] = 'Database error during verification: ' . $conn->error;
                header('Location: queue_management.php');
                exit();
            }
            $verifyStmt->bind_param('i', $bookingId);
            $verifyStmt->execute();
            $verify = $verifyStmt->get_result()->fetch_assoc();
            $verifyStmt->close();
            
            if (!$verify) {
                $_SESSION['error'] = 'CRITICAL: Booking #' . $bookingId . ' disappeared from database after assignment!';
                header('Location: queue_management.php');
                exit();
            }
            
            if ($verify['status'] === 'Cancelled') {
                $_SESSION['error'] = 'ERROR: Booking was cancelled during assignment!';
                header('Location: queue_management.php');
                exit();
            }
            
            if ($verify['order_stage'] !== 'Queuing (Assigning Machines)') {
                $_SESSION['error'] = 'ERROR: Booking stage is "' . htmlspecialchars($verify['order_stage'] ?? '[NULL]') . '" but should be "Queuing (Assigning Machines)". Machines: ' . htmlspecialchars($verify['machine_names'] ?? '[NULL]');
                error_log("Booking $bookingId assignment verification failed. Stage: " . ($verify['order_stage'] ?? 'NULL') . ", Machines: " . ($verify['machine_names'] ?? 'NULL'));
                header('Location: queue_management.php');
                exit();
            }
            
            // Send notification to user - machine assigned, now queueing
            sendQueueStageNotification($bookingId, $conn, 'Queuing (Assigning Machines)');
            
            $_SESSION['success'] = $result['message'];
            // After successful assignment, redirect to queueing view to see the 10-second progress
            header('Location: queue_management.php?filter=queueing');
        } else {
            $_SESSION['error'] = $result['message'];
            header('Location: queue_management.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
        }
        exit();
    }

    if (isset($_POST['auto_assign_next'])) {
        $nextStmt = $conn->prepare(
            "SELECT id
             FROM bookings
             WHERE order_stage IN ('Pending / Booked', 'Queued (Waiting for Machine)')
               AND status <> 'Cancelled'
             ORDER BY booking_date ASC, queue_number ASC, id ASC
             LIMIT 1"
        );
        $nextStmt->execute();
        $next = $nextStmt->get_result()->fetch_assoc();
        $nextStmt->close();

        if ($next) {
            $result = assignAvailableMachine($conn, (int)$next['id']);
            if ($result['ok']) {
                // Verify booking was actually updated
                $verifyStmt = $conn->prepare('SELECT id, order_stage, status FROM bookings WHERE id = ?');
                $verifyStmt->bind_param('i', $next['id']);
                $verifyStmt->execute();
                $verify = $verifyStmt->get_result()->fetch_assoc();
                $verifyStmt->close();
                
                if ($verify && $verify['order_stage'] === 'Queuing (Assigning Machines)') {
                    // Send notification to user - machine assigned, now queueing
                    sendQueueStageNotification((int)$next['id'], $conn, 'Queuing (Assigning Machines)');
                    $_SESSION['success'] = 'Auto-assign complete. ' . $result['message'];
                } else {
                    $_SESSION['error'] = 'Assignment succeeded but booking stage verification failed!';
                }
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } else {
            $_SESSION['error'] = 'No booking is waiting for machine assignment.';
        }

        header('Location: queue_management.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
        exit();
    }

    if (isset($_POST['update_stage']) && $bookingId > 0) {
        $newStage = trim($_POST['order_stage'] ?? 'Pending / Booked');
        $allowedStages = [
            'Pending / Booked',
            'Queued (Waiting for Machine)',
            'Queuing (Assigning Machines)',
            'In Process',
            'Delivering',
            'Ready for Pickup',
            'Completed / Picked Up',
            'Missed Pickup'
        ];

        if (!in_array($newStage, $allowedStages, true)) {
            $_SESSION['error'] = 'Invalid stage selected.';
            header('Location: queue_management.php');
            exit();
        }

        $bookingStmt = $conn->prepare('SELECT id, machine_names, order_stage, request_service FROM bookings WHERE id = ?');
        $bookingStmt->bind_param('i', $bookingId);
        $bookingStmt->execute();
        $booking = $bookingStmt->get_result()->fetch_assoc();
        $bookingStmt->close();

        if (!$booking) {
            $_SESSION['error'] = 'Booking not found.';
            header('Location: queue_management.php');
            exit();
        }

        if ($newStage === 'In Process') {
            // This transition is handled by auto-completion of queuing
            // Don't allow manual update to In Process - must go through Queuing state
            $_SESSION['error'] = 'Use "Assign Machine" button to start machine assignment.';
            header('Location: queue_management.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
            exit();
        }

        if ($newStage === 'Queuing (Assigning Machines)') {
            $_SESSION['error'] = 'Use "Assign Machine" button to queue machines. Do not select this stage manually.';
            header('Location: queue_management.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
            exit();
        }

        $status = null;
        $pickupStatus = null;
        $processCompletedAt = null;
        $pickedUpAt = null;

        if ($newStage === 'Delivering') {
            $status = 'pending';  // Keep status as pending while in Delivering stage
            $processCompletedAt = date('Y-m-d H:i:s');

            if (!empty($booking['machine_names'])) {
                releaseMachines($conn, $booking['machine_names']);
            }
        }

        if ($newStage === 'Ready for Pickup') {
            $status = 'Completed';
            $pickupStatus = 'Waiting for Pick Up';
            $processCompletedAt = date('Y-m-d H:i:s');

            if (!empty($booking['machine_names'])) {
                releaseMachines($conn, $booking['machine_names']);
            }
        }

        if ($newStage === 'Completed / Picked Up') {
            $status = 'Completed';
            $pickupStatus = 'Already Picked';
            $pickedUpAt = date('Y-m-d H:i:s');

            if (!empty($booking['machine_names'])) {
                releaseMachines($conn, $booking['machine_names']);
            }
        }

        if ($newStage === 'Missed Pickup') {
            $pickupStatus = 'Waiting for Pick Up';
            if (!empty($booking['machine_names'])) {
                releaseMachines($conn, $booking['machine_names']);
            }
        }

        $updateSql =
            'UPDATE bookings SET order_stage = ?, ' .
            'status = COALESCE(?, status), ' .
            'pickup_status = COALESCE(?, pickup_status), ' .
            'process_completed_at = COALESCE(?, process_completed_at), ' .
            'picked_up_at = COALESCE(?, picked_up_at), ' .
            'overdue_notified = CASE WHEN ? = \'Missed Pickup\' THEN 1 ELSE overdue_notified END ' .
            'WHERE id = ?';

        error_log("DEBUG update_stage: Preparing to update booking #$bookingId to stage '$newStage'");
        
        $updateStmt = $conn->prepare($updateSql);
        if (!$updateStmt) {
            error_log("ERROR: Failed to prepare update statement: " . $conn->error);
            $_SESSION['error'] = 'Database error: Failed to prepare update statement.';
            header('Location: queue_management.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
            exit();
        }
        
        $updateStmt->bind_param('ssssssi', $newStage, $status, $pickupStatus, $processCompletedAt, $pickedUpAt, $newStage, $bookingId);
        
        if (!$updateStmt->execute()) {
            error_log("ERROR: Failed to execute update: " . $updateStmt->error);
            $_SESSION['error'] = 'Database error: Failed to update booking.';
            $updateStmt->close();
            header('Location: queue_management.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
            exit();
        }
        
        $affectedRows = $updateStmt->affected_rows;
        error_log("DEBUG update_stage: Update executed. Affected rows: $affectedRows");
        
        if ($affectedRows <= 0) {
            error_log("WARNING: No rows were affected by the update for booking #$bookingId");
            $_SESSION['error'] = 'No changes were made. The booking may not exist or is already in this stage.';
            $updateStmt->close();
            header('Location: queue_management.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
            exit();
        }
        
        $updateStmt->close();

        // Verify the update was successful
        $verifyStmt = $conn->prepare('SELECT id, order_stage FROM bookings WHERE id = ?');
        $verifyStmt->bind_param('i', $bookingId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result()->fetch_assoc();
        $verifyStmt->close();
        
        if ($verifyResult) {
            error_log("✓ Booking #$bookingId successfully updated to stage: " . $verifyResult['order_stage']);
        } else {
            error_log("✗ WARNING: Verification failed. Booking #$bookingId not found after update.");
        }

        sendQueueStageNotification($bookingId, $conn, $newStage, $booking['request_service'] ?? '');

        $_SESSION['success'] = 'Order stage updated to "' . $newStage . '" successfully.';
        header('Location: queue_management.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
        exit();
    }
}

// AJAX: Complete queueing and move to "In Process" with user's time slot
if (isset($_POST['complete_queueing'])) {
    header('Content-Type: application/json');
    
    error_log("DEBUG: complete_queueing AJAX called");
    
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $startTime = $_POST['start_time'] ?? null;
    $endTime = $_POST['end_time'] ?? null;
    
    error_log("DEBUG: bookingId=$bookingId, startTime=$startTime, endTime=$endTime");
    
    if (!$bookingId || !$startTime || !$endTime) {
        error_log("DEBUG: Missing required parameters");
        echo json_encode(['ok' => false, 'message' => 'Invalid parameters']);
        exit();
    }
    
    // Verify booking is in Queuing state
    $verifyStmt = $conn->prepare('SELECT id, machine_names FROM bookings WHERE id = ? AND order_stage = ?');
    $queueingStage = 'Queuing (Assigning Machines)';
    $verifyStmt->bind_param('is', $bookingId, $queueingStage);
    $verifyStmt->execute();
    $booking = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();
    
    if (!$booking) {
        // Check if the booking is already in 'In Process' stage (e.g., due to a duplicate request)
        $alreadyStmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND order_stage = 'In Process'");
        $alreadyStmt->bind_param('i', $bookingId);
        $alreadyStmt->execute();
        $isAlreadyDone = $alreadyStmt->get_result()->fetch_assoc();
        $alreadyStmt->close();

        if ($isAlreadyDone) {
            error_log("DEBUG: Booking #$bookingId already in 'In Process' state, returning success.");
            echo json_encode(['ok' => true, 'message' => 'Booking already in process']);
            exit();
        }

        error_log("DEBUG: Booking not in queuing state");
        echo json_encode(['ok' => false, 'message' => 'Booking not in queuing state']);
        exit();
    }
    
    // Calculate duration from the booked time-slot (startTime and endTime from booking_date + time_slot)
    $slotStartTs = strtotime($startTime);
    $slotEndTs = strtotime($endTime);
    $durationSeconds = max(1, $slotEndTs - $slotStartTs);
    $durationMinutes = ceil($durationSeconds / 60);
    
    error_log("DEBUG: Time-slot duration = $durationMinutes minutes ($durationSeconds seconds)");
    
    // Move to "In Process" - START TIMING RIGHT NOW for immediate progress bar display
    // For demo purposes: 60 seconds total (30 sec washing + 30 sec drying)
    $now = date('Y-m-d H:i:s');
    $processStartTime = $now;  // Start from NOW
    $processEndTime = date('Y-m-d H:i:s', strtotime("+60 seconds"));  // Demo: 60 seconds total (30 wash + 30 dry)
    
    error_log("DEBUG: Setting processStartTime=$processStartTime, processEndTime=$processEndTime");
    
    $updateStmt = $conn->prepare(
        "UPDATE bookings
         SET order_stage = 'In Process',
             status = 'pending',
             process_started_at = ?,
             estimated_start_time = ?,
             estimated_completion_time = ?,
             overdue_notified = 0
         WHERE id = ?"
    );
    $updateStmt->bind_param('sssi', $now, $processStartTime, $processEndTime, $bookingId);
    $updateStmt->execute();
    
    $updateSuccess = $updateStmt->affected_rows > 0;
    
    if (!$updateSuccess) {
        error_log("DEBUG: Update query affected 0 rows - checking booking state");
        // Verify the booking exists and check its current state
        $checkStmt = $conn->prepare("SELECT id, order_stage, status FROM bookings WHERE id = ?");
        $checkStmt->bind_param('i', $bookingId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        
        if ($checkResult) {
            error_log("DEBUG: Booking exists, current order_stage: " . $checkResult['order_stage'] . ", status: " . $checkResult['status']);
        } else {
            error_log("DEBUG: Booking not found");
        }
    } else {
        error_log("DEBUG: Update query affected " . $updateStmt->affected_rows . " rows");
        // Verify the update worked
        $verifyStmt = $conn->prepare("SELECT id, order_stage, status, estimated_start_time, estimated_completion_time FROM bookings WHERE id = ?");
        $verifyStmt->bind_param('i', $bookingId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result()->fetch_assoc();
        $verifyStmt->close();
        
        if ($verifyResult) {
            error_log("DEBUG: AFTER UPDATE - id=" . $verifyResult['id'] . 
                     ", order_stage=" . $verifyResult['order_stage'] . 
                     ", status=" . $verifyResult['status'] . 
                     ", estimated_start_time=" . $verifyResult['estimated_start_time'] . 
                     ", estimated_completion_time=" . $verifyResult['estimated_completion_time']);
            error_log("DEBUG: Current server time: " . date('Y-m-d H:i:s'));
        }
    }
    
    $updateStmt->close();
    
    // Machines are now in use (status updated in assignAvailableMachine)
    // We don't increment usage here anymore; it's done when machines are released.
    
    // Send notification
    sendQueueStageNotification($bookingId, $conn, 'In Process');
    
    error_log("DEBUG: complete_queueing successful");
    echo json_encode(['ok' => true, 'message' => 'Machines queued and processing started']);
    exit();
}

// Handle delivery address fetch
if (isset($_POST['get_delivery_address'])) {
    $bookingId = (int)$_POST['booking_id'];
    
    // Fetch delivery_address and location_details directly from bookings table
    $bookingStmt = $conn->prepare(
        "SELECT delivery_address, location_details FROM bookings WHERE id = ?"
    );
    $bookingStmt->bind_param('i', $bookingId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();
    
    $address = $bookingResult['delivery_address'] ?? 'No delivery address provided';
    $details = $bookingResult['location_details'] ?? 'None';
    
    echo json_encode(['ok' => true, 'address' => $address, 'location_details' => $details]);
    exit();
}

// Handle delivery confirmation
if (isset($_POST['confirm_delivery'])) {
    // Clear any output buffer to ensure clean JSON response
    ob_clean();
    header('Content-Type: application/json', true);
    
    error_log("DEBUG: confirm_delivery POST received");
    
    $bookingId = (int)$_POST['booking_id'];
    
    if (!$bookingId) {
        error_log("DEBUG: Invalid booking ID");
        echo json_encode(['ok' => false, 'message' => 'Invalid booking ID']);
        exit();
    }
    
    error_log("DEBUG: Processing delivery confirmation for booking #$bookingId");
    
    // Update booking to mark as completed/picked up
    $updateStmt = $conn->prepare(
        "UPDATE bookings SET order_stage = 'Completed / Picked Up', status = 'Completed', pickup_status = 'Already Picked' WHERE id = ?"
    );
    
    if (!$updateStmt) {
        error_log("DEBUG: Failed to prepare statement: " . $conn->error);
        echo json_encode(['ok' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $updateStmt->bind_param('i', $bookingId);
    
    if (!$updateStmt->execute()) {
        error_log("DEBUG: Failed to execute update: " . $updateStmt->error);
        echo json_encode(['ok' => false, 'message' => 'Failed to update booking: ' . $updateStmt->error]);
        $updateStmt->close();
        exit();
    }
    
    error_log("DEBUG: Update successful, affected rows: " . $updateStmt->affected_rows);
    $updateStmt->close();
    
    // Points for GCASH payments are handled in payment_requests-management.php when marked as paid.
    // COD points are handled in other sections (e.g., photo upload or manual marking).
    error_log("DEBUG: skipping duplicate points award for booking #$bookingId");
    
    // Get booking details for notification
    $bookingStmt = $conn->prepare(
        "SELECT b.user_id, b.queue_code, b.id, u.first_name, u.last_name, u.email, u.phone FROM bookings b 
         JOIN users u ON b.user_id = u.id WHERE b.id = ?"
    );
    
    if (!$bookingStmt) {
        error_log("DEBUG: Failed to prepare booking select: " . $conn->error);
        echo json_encode(['ok' => true, 'message' => 'Delivery confirmed (notification sending skipped)']);
        exit();
    }
    
    $bookingStmt->bind_param('i', $bookingId);
    $bookingStmt->execute();
    $bookingData = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();
    
    // Send delivery confirmation notification to user
    if ($bookingData) {
        $userId = (int)$bookingData['user_id'];
        $queueCode = $bookingData['queue_code'];
        
        error_log("DEBUG: Sending notification for booking #$bookingId");
        
        // Send notification using the standard notification function
        sendQueueStageNotification($bookingId, $conn, 'Completed / Picked Up');
        
        error_log("DEBUG: Delivery notification sent for booking #$bookingId to user #$userId");
    }
    
    error_log("DEBUG: Returning success response");
    echo json_encode(['ok' => true, 'message' => 'Delivery confirmed']);
    exit();
}

// Auto-transition bookings from In Process to Delivering when completion time passes
$transitioned = autoMoveToDeliveringStage();
if ($transitioned > 0) {
    error_log("Queue Management: $transitioned booking(s) transitioned to Delivering stage");
}

$filter = $_GET['filter'] ?? 'active';

$filterMap = [
    'active' => "b.order_stage IN ('Pending / Booked', 'Queued (Waiting for Machine)', 'Queuing (Assigning Machines)', 'In Process', 'Delivering', 'Ready for Pickup')",
    'pending' => "b.order_stage = 'Pending / Booked'",
    'queued' => "b.order_stage = 'Queued (Waiting for Machine)'",
    'queueing' => "b.order_stage = 'Queuing (Assigning Machines)'",
    'in_process' => "b.order_stage = 'In Process'",
    'delivering' => "b.order_stage = 'Delivering'",
    'ready' => "b.order_stage = 'Ready for Pickup'",
    'completed' => "b.order_stage = 'Completed / Picked Up'",
    'missed' => "b.order_stage = 'Missed Pickup'",
    'all' => '1=1'
];

$pageTitleMap = [
    'active' => 'Active Order Pipeline',
    'pending' => 'Pending / Booked',
    'queued' => 'Queued (Waiting for Machine)',
    'queueing' => 'Queuing (Assigning Machines)',
    'in_process' => 'In Process',
    'delivering' => 'Delivering',
    'ready' => 'Ready for Pickup',
    'completed' => 'Completed / Picked Up',
    'missed' => 'Missed Pickup',
    'all' => 'All Orders'
];

$whereClause = $filterMap[$filter] ?? $filterMap['active'];
$pageTitle = $pageTitleMap[$filter] ?? $pageTitleMap['active'];

$query = "
    SELECT
        b.id,
        b.booking_date,
        b.time_slot,
        b.status,
        b.pickup_status,
        b.queue_number,
        b.queue_code,
        b.order_stage,
        b.machine_names,
        b.estimated_start_time,
        b.estimated_completion_time,
        b.service_type,
        b.request_service,
        COALESCE(b.customer_first_name, u.first_name) as first_name,
        COALESCE(b.customer_last_name, u.last_name) as last_name,
        COALESCE(b.customer_email, u.email) as email,
        COALESCE(b.customer_mobile, u.phone) as phone
    FROM bookings b
     JOIN users u ON b.user_id = u.id
    WHERE {$whereClause}
      AND b.status <> 'Cancelled'
    ORDER BY b.booking_date ASC, b.queue_number ASC, b.id ASC
";

$result = mysqli_query($conn, $query);
if (!$result) {
    $_SESSION['error'] = 'Database query error: ' . mysqli_error($conn);
    header('Location: queue_management.php?filter=active');
    exit();
}

$counts = [];
$countStages = [
    'pending' => 'Pending / Booked',
    'queued' => 'Queued (Waiting for Machine)',
    'queueing' => 'Queuing (Assigning Machines)',
    'in_process' => 'In Process',
    'delivering' => 'Delivering',
    'ready' => 'Ready for Pickup',
    'completed' => 'Completed / Picked Up',
    'missed' => 'Missed Pickup'
];

foreach ($countStages as $key => $stageValue) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE order_stage = ? AND status <> 'Cancelled'");
    $stmt->bind_param('s', $stageValue);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $counts[$key] = (int)($row['total'] ?? 0);
    $stmt->close();
}

$activeCount = $counts['pending'] + $counts['queued'] + $counts['queueing'] + $counts['in_process'] + $counts['delivering'] + $counts['ready'];

$machineCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM machines WHERE status = 'Available'");
$machineCountRow = mysqli_fetch_assoc($machineCountResult);
$availableMachines = (int)($machineCountRow['total'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Management - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/lib/css/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/colors.css">
    <link rel="stylesheet" href="../assets/css/admin_home.css">
    <link rel="stylesheet" href="../assets/css/manage-queue.css">
</head>
<body>
    <div class="sidebar d-flex flex-column justify-content-between">
        <div>
            <div class="sidebar-header">
                <h4><i class="fas fa-cogs me-2"></i> Admin Panel</h4>
                <small class="sidebar-subtitle">Jorish Express Laundry</small>
            </div>

            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link" href="admin_home.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link dropdown-toggle active" data-bs-toggle="collapse" href="#managementMenu" role="button">
                        <i class="fas fa-cogs"></i> Management
                    </a>
                    <ul class="collapse show list-unstyled ps-4" id="managementMenu">
                        <li><a class="nav-link py-1" href="manage_users.php"><i class="fas fa-user me-2"></i> Registered Users</a></li>
                        <li><a class="nav-link py-1" href="manage_machines.php"><i class="fas fa-tools me-2"></i> Machine Management</a></li>
                        <li><a class="nav-link py-1" href="manage_inventory.php"><i class="fas fa-box me-2"></i> Inventory Management</a></li>
                        <li><a class="nav-link py-1" href="booking_schedules.php"><i class="fas fa-calendar-alt me-2"></i> Booked Schedules</a></li>
                        <li><a class="nav-link py-1 active" href="queue_management.php"><i class="fas fa-people-arrows me-2"></i> Queue Management</a></li>
                        <li><a class="nav-link py-1" href="payment_requests-management.php"><i class="fas fa-money-bill-wave me-2"></i> Payment Requests</a></li>
                        <li><a class="nav-link py-1" href="claimed_rewards.php"><i class="fas fa-gift me-2"></i> Claimed Rewards</a></li>
                        <li><a class="nav-link py-1" href="admin_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="collapse" href="#reportsMenu" role="button">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <ul class="collapse list-unstyled ps-4" id="reportsMenu">
                        <li><a class="nav-link py-1" href="reports.php"><i class="fas fa-file-invoice-dollar me-2"></i> Sales Report</a></li>
                        <li><a class="nav-link py-1" href="transaction_report.php"><i class="fas fa-exchange-alt me-2"></i> Transaction Report</a></li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer">
            <a class="nav-link text-danger d-flex align-items-center" href="#" onclick="confirmLogout(event)">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="page-header-combined">
            <div class="header-main">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="header-title-section">
                        <div class="d-flex align-items-center mb-2">
                            <div class="header-icon">
                                <i class="fas fa-people-arrows"></i>
                            </div>
                            <div>
                                <h1>Order Queue Pipeline</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="admin_home.php"><i class="fas fa-home"></i> Home</a></li>
                                        <li class="breadcrumb-item"><a href="#">Management</a></li>
                                        <li class="breadcrumb-item active">Queue Management</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <p class="header-subtitle">Track and manage bookings from Pending to Pickup completion</p>
                    </div>
                    <div class="header-action-section">
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-calendar-day me-1"></i>
                            <?php echo date('F j, Y'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="header-stats">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary text-white"><i class="fas fa-stream"></i></div>
                            <div class="stat-content">
                                <h3><?php echo $activeCount; ?></h3>
                                <p>Active Pipeline</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary text-white"><i class="fas fa-cogs"></i></div>
                            <div class="stat-content">
                                <h3><?php echo $counts['in_process']; ?></h3>
                                <p>In Process</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary text-white"><i class="fas fa-shopping-basket"></i></div>
                            <div class="stat-content">
                                <h3><?php echo $counts['ready']; ?></h3>
                                <p>Ready for Pickup</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary text-white"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-content">
                                <h3><?php echo $availableMachines; ?></h3>
                                <p>Available Machines</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card queue-table-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5><i class="fas fa-list me-2"></i> <?php echo htmlspecialchars($pageTitle); ?></h5>
                    <small class="text-muted">Queue number, stage progress, machine assignment, and ETA</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="queue_management.php?filter=active" class="btn btn-sm" style="<?php echo $filter === 'active' ? 'background-color: #6b7280; border-color: #6b7280; color: white;' : 'border: 1px solid #6b7280; color: #6b7280;'; ?>">Active</a>
                    <a href="queue_management.php?filter=pending" class="btn btn-sm" style="<?php echo $filter === 'pending' ? 'background-color: #6b7280; border-color: #6b7280; color: white;' : 'border: 1px solid #6b7280; color: #6b7280;'; ?>">Pending</a>
                    <a href="queue_management.php?filter=queueing" class="btn btn-sm" style="<?php echo $filter === 'queueing' ? 'background-color: #6b7280; border-color: #6b7280; color: white;' : 'border: 1px solid #6b7280; color: #6b7280;'; ?>">Queueing</a>
                    <a href="queue_management.php?filter=in_process" class="btn btn-sm" style="<?php echo $filter === 'in_process' ? 'background-color: #6b7280; border-color: #6b7280; color: white;' : 'border: 1px solid #6b7280; color: #6b7280;'; ?>">In Process</a>
                    <a href="queue_management.php?filter=ready" class="btn btn-sm" style="<?php echo $filter === 'ready' ? 'background-color: #6b7280; border-color: #6b7280; color: white;' : 'border: 1px solid #6b7280; color: #6b7280;'; ?>">Ready</a>
                    <a href="queue_management.php?filter=delivering" class="btn btn-sm" style="<?php echo $filter === 'delivering' ? 'background-color: #6b7280; border-color: #6b7280; color: white;' : 'border: 1px solid #6b7280; color: #6b7280;'; ?>">Delivering</a>
                    <a href="queue_management.php?filter=completed" class="btn btn-sm" style="<?php echo $filter === 'completed' ? 'background-color: #6b7280; border-color: #6b7280; color: white;' : 'border: 1px solid #6b7280; color: #6b7280;'; ?>">Picked Up</a>
                    <a href="queue_management.php?filter=missed" class="btn btn-sm" style="<?php echo $filter === 'missed' ? 'background-color: #6b7280; border-color: #6b7280; color: white;' : 'border: 1px solid #6b7280; color: #6b7280;'; ?>">Missed</a>
                    <form method="post" class="d-inline">
                        <button type="submit" name="auto_assign_next" class="btn btn-sm btn-primary">
                            <i class="fas fa-magic me-1"></i> Auto Assign Next
                        </button>
                    </form>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Queue #</th>
                                <th>Customer</th>
                                <th>Schedule</th>
                                <th>Machine</th>
                                <th>Stage</th>
                                <th>Progress</th>
                                <th>Contact</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php
                                        $queueCode = getQueueCode($row);
                                        $stage = $row['order_stage'] ?? 'Pending / Booked';
                                        $machineNames = trim((string)($row['machine_names'] ?? ''));

                                        $queuePos = null;
                                        if (in_array($stage, ['Pending / Booked', 'Queued (Waiting for Machine)'], true) && !empty($row['queue_number'])) {
                                            $posStmt = $conn->prepare(
                                                "SELECT COUNT(*) + 1 AS queue_pos
                                                 FROM bookings
                                                 WHERE booking_date = ?
                                                   AND order_stage IN ('Pending / Booked', 'Queued (Waiting for Machine)')
                                                   AND status <> 'Cancelled'
                                                   AND queue_number < ?"
                                            );
                                            $bookingDate = $row['booking_date'];
                                            $queueNumber = (int)$row['queue_number'];
                                            $posStmt->bind_param('si', $bookingDate, $queueNumber);
                                            $posStmt->execute();
                                            $posResult = $posStmt->get_result()->fetch_assoc();
                                            $queuePos = (int)($posResult['queue_pos'] ?? 1);
                                            $posStmt->close();
                                        }

                                        $etaStartRaw = $row['estimated_start_time'] ?? null;
                                        $etaEndRaw = $row['estimated_completion_time'] ?? null;

                                        // Calculate estimated times if missing
                                        if (empty($etaStartRaw) || empty($etaEndRaw)) {
                                            // Calculate duration from the booked time-slot
                                            $slotTimes = parseTimeSlot($row['time_slot']);
                                            $slotStartStr = $row['booking_date'] . ' ' . $slotTimes['start'];
                                            $slotEndStr = $row['booking_date'] . ' ' . $slotTimes['end'];
                                            $slotStartTs = strtotime($slotStartStr);
                                            $slotEndTs = strtotime($slotEndStr);
                                            $durationSeconds = max(1, $slotEndTs - $slotStartTs);
                                            $durationMinutes = ceil($durationSeconds / 60);
                                            
                                            // For In Process, Queuing, and other stages, use actual time-slot duration
                                            if ($queuePos !== null) {
                                                // For pending/queued bookings, calculate when they'll start based on queue position
                                                $parallelCapacity = max(1, (int)$availableMachines);
                                                $queueBatchIndex = (int)floor(($queuePos - 1) / $parallelCapacity);
                                                $estimatedStartTs = strtotime('+' . ($queueBatchIndex * $durationMinutes) . ' minutes');
                                                $estimatedEndTs = strtotime("+$durationMinutes minutes", $estimatedStartTs);
                                            } else {
                                                // For In Process and other stages, start from now
                                                $estimatedStartTs = time();
                                                $estimatedEndTs = strtotime("+$durationMinutes minutes");
                                            }
                                            $etaStartRaw = date('Y-m-d H:i:s', $estimatedStartTs);
                                            $etaEndRaw = date('Y-m-d H:i:s', $estimatedEndTs);
                                            
                                            // If In Process stage and timestamps were just calculated, save them to database
                                            if ($stage === 'In Process' && !empty($etaStartRaw) && !empty($etaEndRaw)) {
                                                $updateTimestampsStmt = $conn->prepare(
                                                    "UPDATE bookings 
                                                     SET estimated_start_time = ?, estimated_completion_time = ? 
                                                     WHERE id = ? AND (estimated_start_time IS NULL OR estimated_completion_time IS NULL)"
                                                );
                                                $updateTimestampsStmt->bind_param('ssi', $etaStartRaw, $etaEndRaw, $row['id']);
                                                $updateTimestampsStmt->execute();
                                                $updateTimestampsStmt->close();
                                            }
                                        }

                                        $etaStart = !empty($etaStartRaw) ? date('M d, h:i A', strtotime($etaStartRaw)) : 'N/A';
                                        $etaEnd = !empty($etaEndRaw) ? date('M d, h:i A', strtotime($etaEndRaw)) : 'N/A';

                                        // Determine which services are included in this booking
                                        $serviceType = $row['service_type'] ?? '';
                                        $hasWasher = (stripos($serviceType, 'Washer') !== false || stripos($serviceType, 'Wash & Dry') !== false);
                                        $hasDryer = (stripos($serviceType, 'Dryer') !== false || stripos($serviceType, 'Wash & Dry') !== false);

                                        
                                        // Calculate washing and drying times based on selected services
                                        $washEndTime = null;
                                        $dryEndTime = null;
                                        $currentPhase = null;
                                        
                                        if (!empty($etaStartRaw) && !empty($etaEndRaw)) {
                                            $startTimeObj = new DateTime($etaStartRaw);
                                            $endTimeObj = new DateTime($etaEndRaw);
                                            $totalDuration = $endTimeObj->getTimestamp() - $startTimeObj->getTimestamp();
                                            
                                            // Only split duration if BOTH washer and dryer are selected
                                            if ($hasWasher && $hasDryer) {
                                                // Full service: split duration in half
                                                $halfDuration = $totalDuration / 2;
                                                $washEndTimeObj = clone $startTimeObj;
                                                $washEndTimeObj->modify('+' . intval($halfDuration) . ' seconds');
                                                $washEndTime = $washEndTimeObj->format('Y-m-d H:i:s');
                                                $dryEndTime = $etaEndRaw;
                                            } elseif ($hasWasher) {
                                                // Only washer: full duration for washing, no drying
                                                $washEndTime = $etaEndRaw;
                                                $dryEndTime = null;
                                                $endTimeObj = new DateTime($etaEndRaw);
                                            } elseif ($hasDryer) {
                                                // Only dryer: no washing, full duration for drying
                                                $washEndTime = null;
                                                $dryEndTime = $etaEndRaw;
                                            }
                                            
                                            // Determine current phase based on selected services
                                            $nowTimestamp = time();
                                            if ($hasWasher && !empty($washEndTime)) {
                                                $washEndTs = strtotime($washEndTime);
                                                if ($nowTimestamp < $washEndTs) {
                                                    $currentPhase = 'Washing';
                                                } elseif ($hasDryer && !empty($dryEndTime)) {
                                                    $currentPhase = 'Drying';
                                                }
                                            } elseif ($hasDryer && !empty($dryEndTime)) {
                                                $currentPhase = 'Drying';
                                            }
                                        }

                                        $stageClass = 'secondary';
                                        $displayStage = $stage;
                                        if ($stage === 'Pending / Booked') {
                                            $stageClass = 'secondary';
                                        } elseif ($stage === 'Queued (Waiting for Machine)') {
                                            $stageClass = 'secondary';
                                        } elseif ($stage === 'Queuing (Assigning Machines)') {
                                            $stageClass = 'primary';
                                        } elseif ($stage === 'In Process') {
                                            $stageClass = 'secondary';
                                            // Show current washing or drying phase instead of just "In Process"
                                            if ($currentPhase) {
                                                $displayStage = $currentPhase;
                                            }
                                        } elseif ($stage === 'Delivering') {
                                            $stageClass = 'info';
                                        } elseif ($stage === 'Ready for Pickup') {
                                            $stageClass = 'success';
                                        } elseif ($stage === 'Completed / Picked Up') {
                                            $stageClass = 'dark';
                                        } elseif ($stage === 'Missed Pickup') {
                                            $stageClass = 'danger';
                                        }
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($queueCode); ?></td>
                                        <td>
                                            <strong class="d-block"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                            <small class="text-muted">Booking #<?php echo (int)$row['id']; ?></small>
                                        </td>
                                        <td>
                                            <small class="d-block"><i class="fas fa-calendar me-1 text-muted"></i><?php echo date('M d, Y', strtotime($row['booking_date'])); ?></small>
                                            <small class="d-block"><i class="fas fa-clock me-1 text-muted"></i><?php echo htmlspecialchars($row['time_slot']); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($machineNames)): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($machineNames); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: #6c757d; color: white;"><?php echo htmlspecialchars($displayStage); ?></span>
                                            <small class="d-block mt-1 text-muted">Pickup: <?php echo htmlspecialchars($row['pickup_status'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($stage === 'Pending / Booked' || $stage === 'Queued (Waiting for Machine)'): ?>
                                                <div style="padding: 8px; background-color: #f5f5f5; border-radius: 4px; text-align: center; font-size: 12px;">
                                                    <i class="fas fa-clock-o" style="margin-right: 4px;"></i>
                                                    <span>Waiting for machine assignment</span>
                                                </div>
                                            <?php elseif ($stage === 'Queuing (Assigning Machines)'): ?>
                                                <div class="queueing-progress" data-booking-id="<?php echo (int)$row['id']; ?>" data-start-time="<?php echo htmlspecialchars($row['booking_date'] . ' ' . parseTimeSlot($row['time_slot'])['start']); ?>" data-end-time="<?php echo htmlspecialchars($row['booking_date'] . ' ' . parseTimeSlot($row['time_slot'])['end']); ?>" style="font-size: 12px;">
                                                    <div style="padding: 8px; background-color: #f8f9fa; border-radius: 4px;">
                                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                                            <div class="progress" style="flex: 1; height: 12px; background-color: #e9ecef; border-radius: 6px;">
                                                                <div class="queueing-bar" role="progressbar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #0d6efd, #0a58ca); border-radius: 6px; transition: width 0.1s linear;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                            <span style="font-weight: 600; min-width: 50px; text-align: right;" class="queueing-percentage">0%</span>
                                                        </div>
                                                        <div style="text-align: center; color: #0d6efd; font-weight: 500; margin-bottom: 6px;">
                                                            <i class="fas fa-spinner fa-spin"></i> Assigning machines...
                                                        </div>
                                                        <div style="text-align: center; color: #0a58ca; font-weight: 600; font-size: 14px;" class="queueing-timer">00:00:00</div>
                                                    </div>
                                                </div>
                                            <?php elseif ($stage === 'In Process'): ?>
                                                <?php 
                                                    // Calculate current progress percentages for initial display
                                                    $washInitPercent = 0;
                                                    $dryInitPercent = 0;
                                                    $washTimeLeft = '--:--:--';
                                                    $dryTimeLeft = '--:--:--';
                                                    $showWashing = false;
                                                    $showDrying = false;
                                                    $isComplete = false;
                                                    
                                                    // Only show phases for selected services
                                                    $showWashing = $hasWasher;
                                                    $showDrying = $hasDryer;
                                                    
                                                    if (!empty($etaStartRaw)) {
                                                        $now = time();
                                                        $startTs = strtotime($etaStartRaw);
                                                        
                                                        // Calculate washing progress if washer is selected
                                                        if ($hasWasher && !empty($washEndTime)) {
                                                            $washEndTs = strtotime($washEndTime);
                                                            if ($now < $washEndTs) {
                                                                $washTotal = $washEndTs - $startTs;
                                                                $washElapsed = max(0, $now - $startTs);
                                                                $washInitPercent = min(100, round(($washElapsed / $washTotal) * 100));
                                                                $washRemaining = max(0, $washEndTs - $now);
                                                                $washHours = floor($washRemaining / 3600);
                                                                $washMins = floor(($washRemaining % 3600) / 60);
                                                                $washSecs = $washRemaining % 60;
                                                                $washTimeLeft = str_pad($washHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($washMins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($washSecs, 2, '0', STR_PAD_LEFT);
                                                            } else {
                                                                $washInitPercent = 100;
                                                                $washTimeLeft = '00:00:00';
                                                            }
                                                        }
                                                        
                                                        // Calculate drying progress if dryer is selected
                                                        if ($hasDryer && !empty($dryEndTime)) {
                                                            $dryEndTs = strtotime($dryEndTime);
                                                            // For drying, start counting from when washing ends (or from start if no washer)
                                                            $dryStartTs = !empty($washEndTime) ? strtotime($washEndTime) : $startTs;
                                                            
                                                            if ($now < $dryEndTs) {
                                                                $dryTotal = $dryEndTs - $dryStartTs;
                                                                $dryElapsed = max(0, $now - $dryStartTs);
                                                                if ($dryTotal > 0) {
                                                                    $dryInitPercent = min(100, round(($dryElapsed / $dryTotal) * 100));
                                                                }
                                                                $dryRemaining = max(0, $dryEndTs - $now);
                                                                $dryHours = floor($dryRemaining / 3600);
                                                                $dryMins = floor(($dryRemaining % 3600) / 60);
                                                                $drySecs = $dryRemaining % 60;
                                                                $dryTimeLeft = str_pad($dryHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($dryMins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($drySecs, 2, '0', STR_PAD_LEFT);
                                                            } else {
                                                                $dryInitPercent = 100;
                                                                $dryTimeLeft = '00:00:00';
                                                                $isComplete = true;
                                                            }
                                                        }
                                                    }
                                                ?>
                                                <div class="wash-dry-progress" data-start-time="<?php echo htmlspecialchars($etaStartRaw ?? ''); ?>" data-wash-end-time="<?php echo htmlspecialchars($washEndTime ?? ''); ?>" data-dry-end-time="<?php echo htmlspecialchars($dryEndTime ?? ''); ?>">
                                                    <?php if ($showWashing): ?>
                                                    <div class="washing-phase" style="padding: 10px; background-color: #f8f9fa; border-radius: 6px; border-left: 4px solid #0d6efd;">
                                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                                            <div style="flex: 1; height: 24px; background-color: #e9ecef; border-radius: 4px; border: 1px solid #0d6efd; overflow: hidden;">
                                                                <div class="wash-progress-bar" style="width: <?php echo $washInitPercent; ?>%; height: 100%; background: linear-gradient(90deg, #0d6efd, #0a58ca); border-radius: 4px; transition: width 0.1s linear;"></div>
                                                            </div>
                                                            <span style="font-weight: 700; color: #0a58ca; min-width: 45px; text-align: right; font-size: 14px;" class="wash-percentage"><?php echo $washInitPercent; ?>%</span>
                                                        </div>
                                                        <div style="text-align: center; color: #0d6efd; font-weight: 600; margin-bottom: 6px; font-size: 12px;">
                                                            <i class="fas fa-water"></i> Washing
                                                        </div>
                                                        <div style="text-align: center; color: #0a58ca; font-weight: 700; font-size: 16px; font-family: monospace; letter-spacing: 2px;" class="wash-timer"><?php echo $washTimeLeft; ?></div>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($showDrying): ?>
                                                    <div class="drying-phase" style="padding: 10px; background-color: #f8f9fa; border-radius: 6px; border-left: 4px solid #0d6efd;">
                                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                                            <div style="flex: 1; height: 24px; background-color: #e9ecef; border-radius: 4px; border: 1px solid #0d6efd; overflow: hidden;">
                                                                <div class="dry-progress-bar" style="width: <?php echo $dryInitPercent; ?>%; height: 100%; background: linear-gradient(90deg, #0d6efd, #0a58ca); border-radius: 4px; transition: width 0.1s linear;"></div>
                                                            </div>
                                                            <span style="font-weight: 700; color: #0a58ca; min-width: 45px; text-align: right; font-size: 14px;" class="dry-percentage"><?php echo $dryInitPercent; ?>%</span>
                                                        </div>
                                                        <div style="text-align: center; color: #0d6efd; font-weight: 600; margin-bottom: 6px; font-size: 12px;">
                                                            <i class="fas fa-fan"></i> <?php echo $isComplete ? 'Complete' : 'Drying'; ?>
                                                        </div>
                                                        <div style="text-align: center; color: #0a58ca; font-weight: 700; font-size: 16px; font-family: monospace; letter-spacing: 2px;" class="dry-timer"><?php echo $dryTimeLeft; ?></div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="padding: 8px; background-color: #f5f5f5; border-radius: 4px; text-align: center; font-size: 12px;">
                                                    <small><?php echo htmlspecialchars($stage); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="d-block"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($row['phone']); ?></small>
                                            <small class="d-block"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($row['email']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                                // Stage progression order — used to prevent going backwards
                                                $stageOrder = [
                                                    'Pending / Booked'             => 0,
                                                    'Queued (Waiting for Machine)' => 1,
                                                    'Queuing (Assigning Machines)' => 2,
                                                    'In Process'                   => 3,
                                                    'Delivering'                   => 4,
                                                    'Ready for Pickup'             => 4,
                                                    'Completed / Picked Up'        => 5,
                                                    'Missed Pickup'                => 5,
                                                ];
                                                $currentOrder = $stageOrder[$stage] ?? 0;

                                                // Once a booking has been through Queueing or beyond,
                                                // it cannot be moved back to Pending or Queued.
                                                $lockPending = ($currentOrder >= $stageOrder['Queuing (Assigning Machines)']);

                                                // Once a booking is at Ready for Pickup or beyond,
                                                // it additionally cannot be moved back to In Process.
                                                $lockInProcess = ($currentOrder >= $stageOrder['Ready for Pickup']);
                                            ?>
                                            <form method="post" class="mb-2">
                                                <input type="hidden" name="booking_id" value="<?php echo (int)$row['id']; ?>">
                                                <div class="d-flex gap-2 justify-content-center">
                                                    <select name="order_stage" class="form-select form-select-sm" style="min-width: 210px;" required>
                                                        <option value="Pending / Booked"
                                                            <?php echo $stage === 'Pending / Booked' ? 'selected' : ''; ?>
                                                            <?php if ($lockPending): ?>disabled style="color: #adb5bd; background-color: #f8f9fa;"<?php endif; ?>>
                                                            Pending / Booked<?php echo $lockPending ? ' (locked)' : ''; ?>
                                                        </option>
                                                        <option value="Queued (Waiting for Machine)"
                                                            <?php echo $stage === 'Queued (Waiting for Machine)' ? 'selected' : ''; ?>
                                                            <?php if ($lockPending): ?>disabled style="color: #adb5bd; background-color: #f8f9fa;"<?php endif; ?>>
                                                            Queued (Waiting for Machine)<?php echo $lockPending ? ' (locked)' : ''; ?>
                                                        </option>
                                                        <option value="In Process"
                                                            <?php echo $stage === 'In Process' ? 'selected' : ''; ?>
                                                            <?php if ($lockInProcess): ?>disabled style="color: #adb5bd; background-color: #f8f9fa;"<?php endif; ?>>
                                                            In Process<?php echo $lockInProcess ? ' (locked)' : ''; ?>
                                                        </option>
                                                        <option value="Ready for Pickup" <?php echo $stage === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                                        <?php
                                                            $serviceType = $row['service_type'] ?? '';
                                                            $isSelfService = (stripos($serviceType, 'Self-Service') !== false);
                                                            $isDeliveringDisabled = $isSelfService;
                                                        ?>
                                                        <option value="Delivering"
                                                            <?php echo $stage === 'Delivering' ? 'selected' : ''; ?>
                                                            <?php echo $isDeliveringDisabled ? 'disabled style="color: #ccc; background-color: #f8f9fa;"' : ''; ?>>
                                                            Delivering <?php echo $isDeliveringDisabled ? '(Self-Service Booking)' : ''; ?>
                                                        </option>
                                                        <option value="Completed / Picked Up" <?php echo $stage === 'Completed / Picked Up' ? 'selected' : ''; ?>>Completed / Picked Up</option>
                                                        <option value="Missed Pickup" <?php echo $stage === 'Missed Pickup' ? 'selected' : ''; ?>>Missed Pickup</option>
                                                    </select>
                                                    <button type="submit" name="update_stage" class="btn btn-sm btn-primary">Update</button>
                                                </div>
                                            </form>

                                            <?php if ($currentOrder < 2): ?>
                                            <form method="post" class="mb-2">
                                                <input type="hidden" name="booking_id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" name="assign_machine" class="btn btn-sm" style="background-color: #0d6efd; border-color: #0d6efd; color: white;">
                                                    <i class="fas fa-tools me-1"></i> Assign Machine
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($stage === 'Delivering'): ?>
                                            <button type="button" class="btn btn-sm" style="background-color: #0d6efd; border-color: #0d6efd; color: white;" data-bs-toggle="modal" data-bs-target="#deliverModal" onclick="showDeliveryModal(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['phone'])); ?>', '<?php echo htmlspecialchars(addslashes($row['email'])); ?>')">
                                                <i class="fas fa-truck me-1"></i> Deliver
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <div>No bookings in this stage.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Modal -->
    <div class="modal fade" id="deliverModal" tabindex="-1" aria-labelledby="deliverModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #0d6efd; color: white;">
                    <h5 class="modal-title" id="deliverModalLabel">
                        <i class="fas fa-truck me-2"></i>Delivery Confirmation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h6 class="mb-3" style="color: #6c757d; font-weight: 600;">Customer Information</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size: 1.1rem;">Name:</label>
                                <input type="text" class="form-control" id="deliveryName" placeholder="Customer name" style="font-size: 1rem;">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size: 1.1rem;">Phone:</label>
                                <input type="text" class="form-control" id="deliveryPhone" placeholder="Phone number" style="font-size: 1rem;">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size: 1.1rem;">Email:</label>
                                <input type="email" class="form-control" id="deliveryEmail" placeholder="Email address" style="font-size: 1rem;">
                            </div>
                        </div>
                    </div>
                    <hr style="border-color: #e9ecef;">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h6 class="mb-3" style="color: #6c757d; font-weight: 600;">Delivery Details</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size: 1.1rem;">Delivery Address:</label>
                                <textarea class="form-control" id="deliveryAddress" rows="3" placeholder="Delivery address" style="white-space: pre-wrap; font-size: 1rem;"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size: 1.1rem;">Location Details:</label>
                                <textarea class="form-control" id="deliveryDetails" rows="2" placeholder="Specific instructions, landmarks, etc." style="white-space: pre-wrap; font-size: 1rem;"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="alert" style="background-color: #f8f9fa; border: 1px solid #e9ecef; color: #495057;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Please verify all details are correct before confirming delivery.</strong>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn" style="background-color: #0d6efd; border-color: #0d6efd; color: white;" onclick="confirmDelivery()">
                        <i class="fas fa-check me-1"></i>Confirm Delivery
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/lib/js/sweetalert2.min.js"></script>
    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/confirmlogout.js"></script>
    <script>
        const REFRESH_INTERVAL = 5000; // 5 seconds
        let currentDeliveryBookingId = null;

        function calculateProgressBar(startTime, endTime) {
            const currentTime = new Date();
            const start = new Date(startTime);
            const end = new Date(endTime);

            if (currentTime < start) {
                return {
                    percentage: 0,
                    timeRemaining: 'Not started',
                    endTimeFormatted: end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                    isReady: false,
                    colorEmoji: '??',
                    colorLabel: 'Normal'
                };
            } else if (currentTime >= end) {
                return {
                    percentage: 100,
                    timeRemaining: '0 mins',
                    endTimeFormatted: end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                    isReady: true,
                    colorEmoji: '??',
                    colorLabel: 'Ready Now'
                };
            } else {
                const totalMs = end.getTime() - start.getTime();
                const elapsedMs = currentTime.getTime() - start.getTime();
                const percentage = Math.min(100, Math.round((elapsedMs / totalMs) * 100));
                const remainingMs = end.getTime() - currentTime.getTime();
                const remainingMins = Math.ceil(remainingMs / 60000);

                let colorEmoji = '??';
                let colorLabel = 'Normal';

                if (percentage >= 90) {
                    colorEmoji = '??';
                    colorLabel = 'Ready Soon';
                } else if (percentage >= 70) {
                    colorEmoji = '??';
                    colorLabel = 'Almost Done';
                }

                return {
                    percentage: percentage,
                    timeRemaining: remainingMins + ' mins',
                    endTimeFormatted: end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                    isReady: percentage >= 100,
                    colorEmoji: colorEmoji,
                    colorLabel: colorLabel
                };
            }
        }

        function buildProgressBar(percentage) {
            const filledBars = Math.round(percentage / 5);
            const emptyBars = 20 - filledBars;
            return '?'.repeat(filledBars) + '?'.repeat(emptyBars);
        }

        function getProgressColor(percentage) {
            if (percentage >= 90) return 'danger';
            if (percentage >= 70) return 'warning';
            return 'success';
        }

        function getColorEmoji(percentage) {
            if (percentage >= 90) return { emoji: '??', label: 'Ready Soon' };
            if (percentage >= 70) return { emoji: '??', label: 'Almost Done' };
            return { emoji: '??', label: 'On Track' };
        }

        function refreshWashDryProgress() {
            const containers = document.querySelectorAll('.wash-dry-progress');
            
            let anyCompleted = false;
            
            // Silently return if no containers found (e.g., when viewing completed/ready items)
            if (containers.length === 0) {
                return;
            }
            
            containers.forEach((container, index) => {
                const startTimeStr = container.getAttribute('data-start-time');
                const washEndTimeStr = container.getAttribute('data-wash-end-time');
                const dryEndTimeStr = container.getAttribute('data-dry-end-time');
                
                // Must have at least start time and at least one end time
                if (!startTimeStr || (!washEndTimeStr && !dryEndTimeStr)) {
                    return;
                }
                
                const currentTime = new Date();
                const startTime = new Date(startTimeStr);
                
                const washingPhase = container.querySelector('.washing-phase');
                const dryingPhase = container.querySelector('.drying-phase');
                
                const hasWashing = !!washEndTimeStr;
                const hasDrying = !!dryEndTimeStr;
                
                // If we have a washing phase
                if (hasWashing) {
                    const washEndTime = new Date(washEndTimeStr);
                    
                    if (currentTime < washEndTime) {
                        // Still in washing phase
                        if (washingPhase) washingPhase.style.display = 'block';
                        
                        const washTotalMs = washEndTime.getTime() - startTime.getTime();
                        const washElapsedMs = Math.max(0, currentTime.getTime() - startTime.getTime());
                        const washPercentage = Math.min(100, Math.round((washElapsedMs / washTotalMs) * 100));
                        const washRemainingMs = Math.max(0, washEndTime.getTime() - currentTime.getTime());
                        const washRemainingSeconds = Math.ceil(washRemainingMs / 1000);
                        
                        // Update washing progress
                        const washBar = container.querySelector('.wash-progress-bar');
                        const washPercent = container.querySelector('.wash-percentage');
                        const washTimer = container.querySelector('.wash-timer');
                        
                        if (washBar) washBar.style.width = washPercentage + '%';
                        if (washPercent) washPercent.textContent = washPercentage + '%';
                        if (washTimer) {
                            const hours = Math.floor(washRemainingSeconds / 3600);
                            const minutes = Math.floor((washRemainingSeconds % 3600) / 60);
                            const seconds = washRemainingSeconds % 60;
                            washTimer.textContent = String(hours).padStart(2, '0') + ':' + 
                                                   String(minutes).padStart(2, '0') + ':' + 
                                                   String(seconds).padStart(2, '0');
                        }
                    } else {
                        // Washing phase complete
                        if (washingPhase) washingPhase.style.display = 'none';
                    }
                } else {
                    // No washing phase - hide it
                    if (washingPhase) washingPhase.style.display = 'none';
                }
                
                // If we have a drying phase
                if (hasDrying) {
                    const dryEndTime = new Date(dryEndTimeStr);
                    const dryStartTime = hasWashing ? new Date(washEndTimeStr) : startTime;
                    
                    if (currentTime < dryEndTime) {
                        // In or approaching drying phase
                        if (currentTime >= dryStartTime) {
                            // Actually in drying phase
                            if (dryingPhase) dryingPhase.style.display = 'block';
                            
                            const dryTotalMs = dryEndTime.getTime() - dryStartTime.getTime();
                            const dryElapsedMs = Math.max(0, currentTime.getTime() - dryStartTime.getTime());
                            const dryPercentage = dryTotalMs > 0 ? Math.min(100, Math.round((dryElapsedMs / dryTotalMs) * 100)) : 0;
                            const dryRemainingMs = Math.max(0, dryEndTime.getTime() - currentTime.getTime());
                            const dryRemainingSeconds = Math.ceil(dryRemainingMs / 1000);
                            
                            // Update drying progress
                            const dryBar = container.querySelector('.dry-progress-bar');
                            const dryPercent = container.querySelector('.dry-percentage');
                            const dryTimer = container.querySelector('.dry-timer');
                            
                            if (dryBar) dryBar.style.width = dryPercentage + '%';
                            if (dryPercent) dryPercent.textContent = dryPercentage + '%';
                            if (dryTimer) {
                                const hours = Math.floor(dryRemainingSeconds / 3600);
                                const minutes = Math.floor((dryRemainingSeconds % 3600) / 60);
                                const seconds = dryRemainingSeconds % 60;
                                dryTimer.textContent = String(hours).padStart(2, '0') + ':' + 
                                                      String(minutes).padStart(2, '0') + ':' + 
                                                      String(seconds).padStart(2, '0');
                            }
                        } else {
                            // Waiting for drying phase to start
                            if (dryingPhase) dryingPhase.style.display = 'none';
                        }
                    } else {
                        // Drying phase complete
                        if (dryingPhase) {
                            dryingPhase.style.display = 'block';
                            const dryBar = container.querySelector('.dry-progress-bar');
                            const dryPercent = container.querySelector('.dry-percentage');
                            const dryTimer = container.querySelector('.dry-timer');
                            
                            if (dryBar) dryBar.style.width = '100%';
                            if (dryPercent) dryPercent.textContent = '100%';
                            if (dryTimer) dryTimer.textContent = '00:00:00';
                        }
                        
                        // Mark completion if this was the last phase
                        if (!hasWashing || currentTime >= new Date(washEndTimeStr)) {
                            anyCompleted = true;
                        }
                    }
                } else {
                    // No drying phase - hide it
                    if (dryingPhase) dryingPhase.style.display = 'none';
                }
            });
            
            // If any booking completed, refresh the page to trigger auto-transition to "Delivering"
            if (anyCompleted) {
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        }

        function refreshProgressBars() {
            const containers = document.querySelectorAll('.progress-timer-compact');
            
            containers.forEach((container) => {
                const startTimeStr = container.getAttribute('data-start-time');
                const endTimeStr = container.getAttribute('data-end-time');

                if (!startTimeStr || !endTimeStr) return;

                const currentTime = new Date();
                const startTime = new Date(startTimeStr);
                const endTime = new Date(endTimeStr);

                let percentage = 0;
                let timeRemaining = 'N/A';
                let isReady = false;

                if (currentTime < startTime) {
                    percentage = 0;
                    const diffMs = endTime.getTime() - currentTime.getTime();
                    const totalMins = Math.ceil(diffMs / 60000);
                    timeRemaining = totalMins + ' mins';
                } else if (currentTime >= endTime) {
                    percentage = 100;
                    timeRemaining = '0 mins';
                    isReady = true;
                } else {
                    const totalMs = endTime.getTime() - startTime.getTime();
                    const elapsedMs = currentTime.getTime() - startTime.getTime();
                    percentage = Math.min(100, Math.round((elapsedMs / totalMs) * 100));
                    const remainingMs = endTime.getTime() - currentTime.getTime();
                    const remainingMins = Math.ceil(remainingMs / 60000);
                    timeRemaining = remainingMins + ' mins';
                }

                // Update percentage text
                const percentSpan = container.querySelector('span[style*="font-weight"]');
                if (percentSpan) {
                    percentSpan.textContent = percentage + '%';
                }

                // Update progress bar fill
                const progressBar = container.querySelector('.progress-bar');
                const progressColor = percentage >= 90 ? 'danger' : percentage >= 70 ? 'warning' : 'success';
                if (progressBar) {
                    progressBar.style.width = percentage + '%';
                    progressBar.className = 'progress-bar bg-' + progressColor;
                }

                // Update time remaining
                const divs = container.querySelectorAll('div');
                divs.forEach(div => {
                    if (div.textContent.includes('remaining')) {
                        const span = div.querySelector('span');
                        if (span && span.textContent.includes('remaining')) {
                            span.textContent = timeRemaining + ' remaining';
                        }
                    }
                    if (div.textContent.includes('Ends:')) {
                        const span = div.querySelector('span');
                        if (span && span.textContent.includes('Ends:')) {
                            span.textContent = 'Ends: ' + endTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                        }
                    }
                });

                // Auto-refresh page if progress reaches 100%
                if (isReady) {
                    console.log('Progress 100% detected. Refreshing page to update status...');
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                }
            });
        }

        // Handle actual time slot duration queueing progress bar with countdown timer
        function handleQueueingProgress() {
            const queueingContainers = document.querySelectorAll('.queueing-progress');
            
            queueingContainers.forEach((container) => {
                // Prevent double-initialization of intervals
                if (container.getAttribute('data-queued-init') === 'true') return;
                container.setAttribute('data-queued-init', 'true');
                const bookingId = container.getAttribute('data-booking-id');
                const startTimeStr = container.getAttribute('data-start-time');
                const endTimeStr = container.getAttribute('data-end-time');
                
                // Fixed 20-second queueing duration for loading laundry onto machines
                const queueDuration = 20 * 1000; // 20 seconds in milliseconds
                const queueStartedAt = Date.now();
                
                const progressInterval = setInterval(() => {
                    const elapsed = Date.now() - queueStartedAt;
                    const remaining = Math.max(0, queueDuration - elapsed);
                    const percentage = Math.min(100, (elapsed / queueDuration) * 100);
                    
                    // Update progress bar
                    const bar = container.querySelector('.queueing-bar');
                    const percentSpan = container.querySelector('.queueing-percentage');
                    const timerSpan = container.querySelector('.queueing-timer');
                    
                    if (bar) bar.style.width = percentage + '%';
                    if (percentSpan) percentSpan.textContent = Math.round(percentage) + '%';
                    
                    // Calculate and display remaining time in HH:MM:SS format
                    if (timerSpan) {
                        const totalSeconds = Math.ceil(remaining / 1000);
                        const hours = Math.floor(totalSeconds / 3600);
                        const minutes = Math.floor((totalSeconds % 3600) / 60);
                        const seconds = totalSeconds % 60;
                        
                        const timeDisplay = String(hours).padStart(2, '0') + ':' + 
                                          String(minutes).padStart(2, '0') + ':' + 
                                          String(seconds).padStart(2, '0');
                        timerSpan.textContent = timeDisplay;
                    }
                    
                    // Complete queueing when 20-second timer reaches zero
                    if (elapsed >= queueDuration) {
                        clearInterval(progressInterval);
                        
                        // AJAX call to complete queueing and move to In Process
                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'complete_queueing=1&booking_id=' + bookingId + 
                                  '&start_time=' + encodeURIComponent(startTimeStr) + 
                                  '&end_time=' + encodeURIComponent(endTimeStr)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.ok) {
                                console.log('✅ Queueing completed successfully');
                                console.log('Response:', data);
                                // Show success message and then redirect
                                Swal.fire({
                                    title: 'Queueing Complete!',
                                    text: 'Booking moved to In Process stage. Redirecting...',
                                    icon: 'success',
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    didOpen: (modal) => {
                                        console.log('✅ Success modal displayed');
                                        setTimeout(() => {
                                            console.log('⏳ 2 seconds passed - waiting 3 more seconds for database commit...');
                                            setTimeout(() => {
                                                console.log('⏳ 5 seconds total passed - waiting 1 more second...');
                                                setTimeout(() => {
                                                    console.log('⏳ 6 seconds total - NOW CLOSING MODAL');
                                                    Swal.close();
                                                    const newUrl = window.location.pathname + '?filter=in_process';
                                                    console.log('🔄 REDIRECTING to:', newUrl);
                                                    window.location.href = newUrl;
                                                }, 1000);
                                            }, 3000);
                                        }, 2000);
                                    }
                                });
                            } else {
                                console.error('❌ Queueing completion failed:', data.message);
                                Swal.fire({
                                    title: 'Error',
                                    text: data.message,
                                    icon: 'error'
                                });
                                setTimeout(() => location.reload(), 3000);
                            }
                        })
                        .catch(error => {
                            console.error('❌ Queueing AJAX error:', error);
                            Swal.fire({
                                title: 'Network Error',
                                text: 'Failed to complete queueing: ' + error.message,
                                icon: 'error'
                            });
                            setTimeout(() => location.reload(), 3000);
                        });
                    }
                }, 100);
            });
        }

        // Handle delivery modal and confirmation
        function showDeliveryModal(bookingId, customerName, phone, email) {
            currentDeliveryBookingId = bookingId;
            
            // Set customer info in editable fields
            document.getElementById('deliveryName').value = customerName;
            document.getElementById('deliveryPhone').value = phone;
            document.getElementById('deliveryEmail').value = email;
            
            // Fetch delivery address and details
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'get_delivery_address=1&booking_id=' + bookingId
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    document.getElementById('deliveryAddress').value = data.address || 'No address provided';
                    document.getElementById('deliveryDetails').value = data.location_details || 'None';
                } else {
                    document.getElementById('deliveryAddress').value = 'Unable to load address';
                    document.getElementById('deliveryDetails').value = 'N/A';
                }
            })
            .catch(error => {
                console.error('Error fetching delivery address:', error);
                document.getElementById('deliveryAddress').value = 'Error loading address';
            });
        }

        function confirmDelivery() {
            if (!currentDeliveryBookingId) {
                Swal.fire('Error', 'Booking ID not found', 'error');
                return;
            }

            Swal.fire({
                title: 'Confirm Delivery',
                text: 'Deliver this order to the customer?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delivered',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send delivery confirmation to backend
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'confirm_delivery=1&booking_id=' + currentDeliveryBookingId
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP error, status = ' + response.status);
                        }
                        return response.text().then(text => {
                            console.log('Response text:', text);
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('Failed to parse JSON. Response was:', text);
                                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                            }
                        });
                    })
                    .then(data => {
                        if (data.ok) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Order delivered successfully!',
                                icon: 'success'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to confirm delivery', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error', 'Network error occurred: ' + error.message, 'error');
                    });
                }
            });
        }

        // Initialize tooltips and start refresh interval
        // Consolidated initialization to prevent double-execution
        let appInitialized = false;
        function initializeQueueManagement() {
            if (appInitialized) return;
            appInitialized = true;
            
            console.log('✅ Initializing Queue Management Pipeline...');
            
            // Initialize Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Start queueing progress handlers
            handleQueueingProgress();

            // Start auto-refresh intervals
            setInterval(refreshProgressBars, REFRESH_INTERVAL);
            setInterval(refreshWashDryProgress, 100); 
            
            // Initial run
            refreshProgressBars();
            refreshWashDryProgress();
            
            console.log('✨ Queue Management page fully initialized!');
        }

        document.addEventListener('DOMContentLoaded', initializeQueueManagement);
        // Fallback for late initialization
        setTimeout(initializeQueueManagement, 500);

        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                title: 'Success',
                text: '<?php echo addslashes($_SESSION['success']); ?>',
                icon: 'success'
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                title: 'Error',
                text: '<?php echo addslashes($_SESSION['error']); ?>',
                icon: 'error'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>













