<?php
// includes/booking-functions.php
// Shared functions for booking calculations

if (!function_exists('getInventoryItemPrice')) {
    /**
     * Get inventory item price from database
     */
    function getInventoryItemPrice($item_name) {
        global $conn;
        
        $price = 16.00; // Default fallback for detergent
        
        $query = "SELECT price, item_type FROM inventory WHERE item_name = ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $item_name);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $price = $row['price'];
                error_log("Retrieved price for '{$item_name}': ₱{$price} (Type: {$row['item_type']})");
            } else {
                // Item not found in inventory - detect by name
                $item_lower = strtolower($item_name);
                if (strpos($item_lower, 'fabric') !== false || 
                    strpos($item_lower, 'conditioner') !== false ||
                    strpos($item_lower, 'softener') !== false ||
                    strpos($item_lower, 'downy') !== false) {
                    $price = 11.00;
                    error_log("Item '{$item_name}' not found, detected as fabric conditioner, using ₱{$price}");
                } else {
                    error_log("Item '{$item_name}' not found in inventory, using default detergent price ₱{$price}");
                }
            }
            $stmt->close();
        }
        
        return $price;
    }
}

if (!function_exists('calculateBookingAmountFromDB')) {
    /**
     * Calculate booking amount using database prices
     */
    function calculateBookingAmountFromDB($booking, $all_services = null) {
        global $conn;
        
        $amount = 0;
        
        // Fetch services from database if not provided
        if (empty($all_services)) {
            $services_from_db = [];
            $service_query = "SELECT id, service_name, price FROM services";
            $service_result = $conn->query($service_query);
            if ($service_result) {
                while ($row = $service_result->fetch_assoc()) {
                    $services_from_db[$row['id']] = $row;
                    $services_from_db[strtolower(trim($row['service_name']))] = $row;
                    $services_from_db[$row['service_name']] = $row;
                }
            }
            $all_services = $services_from_db;
            
            // Debug log
            error_log("Services loaded from DB: " . print_r(array_keys($services_from_db), true));
        }
        
        // Get service types
        $service_items = [];
        if (!empty($booking['service_type'])) {
            $raw_services = explode(',', $booking['service_type']);
            foreach ($raw_services as $service_item) {
                $service_item = trim($service_item);
                
                // Check if service is stored as "ID:Name"
                if (strpos($service_item, ':') !== false) {
                    list($service_id, $service_name) = explode(':', $service_item, 2);
                    $service_items[] = ['id' => trim($service_id), 'name' => trim($service_name)];
                } else {
                    $service_items[] = ['id' => null, 'name' => $service_item];
                }
            }
        }
        
        error_log("Service items to calculate: " . print_r($service_items, true));
        
        // Calculate service total using database prices
        foreach ($service_items as $service) {
            $service_price = 0;
            $service_name_clean = trim($service['name']);
            $service_name_lower = strtolower($service_name_clean);
            
            // Try to find by ID first
            if ($service['id'] && isset($all_services[$service['id']])) {
                $service_price = $all_services[$service['id']]['price'];
                error_log("Found by ID {$service['id']}: ₱{$service_price}");
            }
            // Try to find by exact name match
            elseif (isset($all_services[$service_name_clean])) {
                $service_price = $all_services[$service_name_clean]['price'];
                error_log("Found by exact name '{$service_name_clean}': ₱{$service_price}");
            }
            // Try to find by lowercase name match
            elseif (isset($all_services[$service_name_lower])) {
                $service_price = $all_services[$service_name_lower]['price'];
                error_log("Found by lowercase name '{$service_name_lower}': ₱{$service_price}");
            }
            // Try partial matching
            else {
                foreach ($all_services as $key => $svc) {
                    if (is_array($svc) && isset($svc['service_name'])) {
                        $db_service_name = $svc['service_name'];
                        $db_service_lower = strtolower($db_service_name);
                        
                        // Check for exact match after trimming
                        if ($db_service_lower === $service_name_lower) {
                            $service_price = $svc['price'];
                            error_log("Found by trimmed match '{$db_service_name}': ₱{$service_price}");
                            break;
                        }
                        // Check if service name contains the keyword
                        elseif (strpos($db_service_lower, $service_name_lower) !== false) {
                            $service_price = $svc['price'];
                            error_log("Found by partial match '{$db_service_name}' contains '{$service_name_lower}': ₱{$service_price}");
                            break;
                        }
                        // Check for specific service mappings
                        elseif (strpos($service_name_lower, 'wash') !== false && strpos($db_service_lower, 'wash') !== false) {
                            $service_price = $svc['price'];
                            error_log("Found by wash keyword in '{$db_service_name}': ₱{$service_price}");
                            break;
                        }
                        elseif (strpos($service_name_lower, 'dry') !== false && strpos($db_service_lower, 'dry') !== false) {
                            $service_price = $svc['price'];
                            error_log("Found by dry keyword in '{$db_service_name}': ₱{$service_price}");
                            break;
                        }
                        elseif (strpos($service_name_lower, 'fold') !== false && strpos($db_service_lower, 'fold') !== false) {
                            $service_price = $svc['price'];
                            error_log("Found by fold keyword in '{$db_service_name}': ₱{$service_price}");
                            break;
                        }
                    }
                }
            }
            
            // If still no price found, use default based on service name keywords
            if ($service_price == 0) {
                error_log("No price found for '{$service_name_clean}', using fallback");
                if (strpos($service_name_lower, 'wash') !== false && strpos($service_name_lower, 'dry') !== false) {
                    $service_price = 145.00; // Full Service - Wash & Dry
                } elseif (strpos($service_name_lower, 'wash') !== false) {
                    $service_price = 65.00; // Self-Service - Washer
                } elseif (strpos($service_name_lower, 'dry') !== false) {
                    $service_price = 80.00; // Self-Service - Dryer
                } elseif (strpos($service_name_lower, 'fold') !== false) {
                    $service_price = 30.00; // Fold
                } else {
                    $service_price = 65.00; // Default
                }
            }
            
            $amount += $service_price;
            error_log("Added ₱{$service_price} for '{$service_name_clean}', total now: ₱{$amount}");
        }
        
        // Add detergent cost using inventory prices
        if (!empty($booking['detergent']) && $booking['detergent'] !== 'N/A') {
            $detergents = explode(',', $booking['detergent'] ?? '');
            foreach ($detergents as $detergent_entry) {
                $detergent_entry = trim($detergent_entry);
                
                if (empty($detergent_entry) || $detergent_entry === 'Bring my own detergent' || $detergent_entry === 'Bring my own') {
                    continue;
                }
                
                // Parse "Qty x ItemName" format (new format) or plain ItemName (old format)
                $qty = 1;
                $item_name = $detergent_entry;
                
                // Check if it matches the format "NUMBER x NAME"
                if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $detergent_entry, $matches)) {
                    $qty = intval($matches[1]);
                    $item_name = trim($matches[2]);
                }
                
                $detergent_price = getInventoryItemPrice($item_name);
                $item_total = $detergent_price * $qty;
                $amount += $item_total;
                error_log("Added {$qty}x {$item_name} @ ₱{$detergent_price} = ₱{$item_total}, total now: ₱{$amount}");
            }
        }
        
        error_log("Final calculated amount for booking #{$booking['id']}: ₱{$amount}");
        
        return $amount;
    }
}

if (!function_exists('parseServiceNames')) {
    /**
     * Parse service names from stored format
     */
    function parseServiceNames($service_type) {
        $service_names = [];
        if (!empty($service_type)) {
            $services_raw = array_map('trim', explode(',', $service_type));
            foreach ($services_raw as $service_item) {
                if (strpos($service_item, ':') !== false) {
                    list($service_id, $service_name) = explode(':', $service_item, 2);
                    $service_names[] = trim($service_name);
                } else {
                    $service_names[] = $service_item;
                }
            }
        }
        return $service_names;
    }
}

if (!function_exists('getServicePrice')) {
    /**
     * Get service price from database
     */
    function getServicePrice($service_name, $service_id, $all_services) {
        // Try by ID first
        if ($service_id && isset($all_services[$service_id])) {
            return $all_services[$service_id]['price'];
        }
        
        // Try by exact name
        if (isset($all_services[$service_name])) {
            return $all_services[$service_name]['price'];
        }
        
        // Try by lowercase name
        $service_lower = strtolower($service_name);
        if (isset($all_services[$service_lower])) {
            return $all_services[$service_lower]['price'];
        }
        
        // Try partial matching
        foreach ($all_services as $svc) {
            if (is_array($svc) && isset($svc['service_name'])) {
                $db_lower = strtolower($svc['service_name']);
                if (strpos($db_lower, $service_lower) !== false || strpos($service_lower, $db_lower) !== false) {
                    return $svc['price'];
                }
            }
        }
        
        // Default fallback based on keywords
        if (strpos($service_lower, 'wash') !== false && strpos($service_lower, 'dry') !== false) {
            return 145.00; // Full Service - Wash & Dry
        } elseif (strpos($service_lower, 'wash') !== false) {
            return 65.00; // Self-Service - Washer
        } elseif (strpos($service_lower, 'dry') !== false) {
            return 80.00; // Self-Service - Dryer
        } elseif (strpos($service_lower, 'fold') !== false) {
            return 30.00; // Fold
        }
        
        return 0;
    }
}

if (!function_exists('autoMoveToDeliveringStage')) {
    /**
     * Automatically transition bookings from "In Process" to "Delivering" stage
     * when estimated_completion_time has passed
     * 
     * This applies to all payment methods (GCASH, COD, Cash, etc.)
     * 
     * @return int Number of bookings transitioned to Delivering stage
     */
    function autoMoveToDeliveringStage() {
        global $conn;
        
        error_log("\n\n════════════════════════════════════════════════════════");
        error_log("autoMoveToDeliveringStage() CALLED - " . date('Y-m-d H:i:s'));
        error_log("════════════════════════════════════════════════════════");
        
        $transitioned_count = 0;
        
        try {
            // Get current time from both PHP and database
            $phpTime = date('Y-m-d H:i:s');
            error_log("PHP time: $phpTime");
            
            // Check database time
            $dbTimeStmt = $conn->query("SELECT NOW() as db_time");
            if (!$dbTimeStmt) {
                error_log("ERROR: Failed to query database time: " . $conn->error);
                return 0;
            }
            $dbTimeRow = $dbTimeStmt->fetch_assoc();
            $dbTime = $dbTimeRow['db_time'] ?? 'NULL';
            error_log("Database time: $dbTime");
            
            // Count ALL bookings by stage
            $allStagesQuery = "SELECT order_stage, COUNT(*) as cnt FROM bookings WHERE status <> 'Cancelled' GROUP BY order_stage";
            $allStagesResult = $conn->query($allStagesQuery);
            error_log("ALL BOOKING STAGES IN DATABASE:");
            if ($allStagesResult && $allStagesResult->num_rows > 0) {
                while ($stageRow = $allStagesResult->fetch_assoc()) {
                    error_log("  - {$stageRow['order_stage']}: {$stageRow['cnt']}");
                }
            }
            
            // Count bookings in "Ready for Pickup" stage
            $countStmt = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE order_stage = 'Ready for Pickup'");
            $countRow = $countStmt->fetch_assoc();
            $readyCount = $countRow['cnt'] ?? 0;
            error_log("Ready for Pickup count: $readyCount");
            
            // Show details of EACH booking ready for delivery
            if ($readyCount > 0) {
                error_log("DETAILED READY FOR DELIVERY BOOKINGS:");
                $detailStmt = $conn->query(
                    "SELECT id, queue_code, order_stage, process_completed_at, status,
                            TIMESTAMPDIFF(MINUTE, process_completed_at, NOW()) as minutes_ready
                     FROM bookings 
                     WHERE order_stage = 'Ready for Pickup'
                     ORDER BY process_completed_at ASC"
                );
                
                if ($detailStmt && $detailStmt->num_rows > 0) {
                    while ($detail = $detailStmt->fetch_assoc()) {
                        error_log("  ID={$detail['id']}, Queue={$detail['queue_code']}, MinutesReady={$detail['minutes_ready']}, Status={$detail['status']}");
                    }
                } else {
                    error_log("  No details found for Ready for Pickup bookings!");
                }
            }
            
            // CRITICAL: Find bookings that are READY and awaiting delivery (optional auto-transition to Delivering)
            // Currently disabled - admin manually transitions from Ready to Delivering via the stage dropdown
            error_log("READY FOR DELIVERY BOOKINGS - Awaiting manual admin transition to Delivering");
            
            // If you want to auto-transition Ready → Delivering, uncomment below:
            // $query = "SELECT id, queue_code FROM bookings 
            //           WHERE order_stage = 'Ready for Pickup' 
            //           AND status = 'Completed'
            //           ORDER BY process_completed_at ASC";
            
            // For now, just count Ready bookings
            $query = "SELECT COUNT(*) as cnt FROM bookings WHERE order_stage = 'Ready for Pickup' AND status = 'Completed'";
            
            error_log("Query: $query");
            
            $result = $conn->query($query);
            
            if (!$result) {
                error_log("ERROR: Query failed: " . $conn->error);
                return 0;
            }
            
            $readyCount = $result->fetch_assoc()['cnt'] ?? 0;
            error_log("FOUND $readyCount READY FOR DELIVERY BOOKINGS (awaiting manual admin transition)");
            
            // No automatic transition - admin will manually set Ready → Delivering from the filter/dropdown
            $transitioned_count = 0;
            error_log("═══ autoMoveToDeliveringStage() COMPLETED: $transitioned_count transitioned (manual transition enabled) ═══\n");
            
        } catch (Exception $e) {
            error_log("FATAL ERROR: " . $e->getMessage());
        }
        
        return $transitioned_count;
    }
}
?>