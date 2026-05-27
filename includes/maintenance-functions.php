<?php
/**
 * Maintenance Functions
 * Handles usage-based and time-based maintenance scheduling
 * 
 * Maintenance Requirements:
 * - Washers: 10 uses
 * - Dryers: 10 uses
 * - All machines: Every 6 months
 */

// Define maintenance thresholds - 10 uses for both washer and dryer
define('WASHER_MAINTENANCE_USES', 10);
define('DRYER_MAINTENANCE_USES', 10);
define('MAINTENANCE_MONTHS', 6);

/**
 * Check if a machine needs maintenance
 * Returns 'usage-based', 'time-based', or null
 */
function checkMaintenanceNeeded($machine_id, $conn) {
    $stmt = $conn->prepare("SELECT machine_type, usage_count, last_maintenance_date FROM machines WHERE id = ?");
    $stmt->bind_param("i", $machine_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $machine = $result->fetch_assoc();
    $stmt->close();
    
    // Check usage-based maintenance
    $usage_threshold = ($machine['machine_type'] === 'washer') ? WASHER_MAINTENANCE_USES : DRYER_MAINTENANCE_USES;
    
    if ($machine['usage_count'] >= $usage_threshold) {
        return 'usage-based';
    }
    
    // Check time-based maintenance (6 months)
    // Only check if machine has been maintained before
    if ($machine['last_maintenance_date']) {
        $last_maintenance = strtotime($machine['last_maintenance_date']);
        $six_months_ago = strtotime('-6 months');
        
        if ($last_maintenance <= $six_months_ago) {
            return 'time-based';
        }
    }
    
    return null;
}

/**
 * Get full maintenance status for a machine
 */
function getMaintenanceStatus($machine_id, $conn) {
    $stmt = $conn->prepare("SELECT id, machine_name, machine_type, status, usage_count, last_maintenance_date FROM machines WHERE id = ?");
    $stmt->bind_param("i", $machine_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }
    
    $machine = $result->fetch_assoc();
    $stmt->close();
    
    $usage_threshold = ($machine['machine_type'] === 'washer') ? WASHER_MAINTENANCE_USES : DRYER_MAINTENANCE_USES;
    $usage_percentage = ($machine['usage_count'] / $usage_threshold) * 100;
    
    $maintenance_type = checkMaintenanceNeeded($machine_id, $conn);
    
    return array(
        'id' => $machine['id'],
        'name' => $machine['machine_name'],
        'type' => $machine['machine_type'],
        'status' => $machine['status'],
        'usage_count' => $machine['usage_count'],
        'usage_threshold' => $usage_threshold,
        'usage_percentage' => $usage_percentage,
        'last_maintenance_date' => $machine['last_maintenance_date'],
        'needs_maintenance' => $maintenance_type !== null,
        'maintenance_type' => $maintenance_type,
        'is_under_maintenance' => in_array($machine['status'], ['Under Maintenance', 'Maintenance']),
        'is_in_use' => $machine['status'] === 'In Use'
    );
}

/**
 * Increment machine usage count
 */
function incrementMachineUsage($machine_name, $conn) {
    $stmt = $conn->prepare("UPDATE machines SET usage_count = usage_count + 1 WHERE machine_name = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("s", $machine_name);
    $result = $stmt->execute();
    $stmt->close();
    
    // Check if maintenance is needed after incrementing
    $machine_stmt = $conn->prepare("SELECT id FROM machines WHERE machine_name = ?");
    $machine_stmt->bind_param("s", $machine_name);
    $machine_stmt->execute();
    $machine_result = $machine_stmt->get_result();
    $machine = $machine_result->fetch_assoc();
    $machine_stmt->close();
    
    if ($machine) {
        $maintenance_type = checkMaintenanceNeeded($machine['id'], $conn);
        if ($maintenance_type === 'usage-based') {
            // Auto-update status to Needs Maintenance
            $update_stmt = $conn->prepare("UPDATE machines SET status = 'Needs Maintenance' WHERE id = ?");
            $update_stmt->bind_param("i", $machine['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Create maintenance alert
            createMaintenanceAlert($machine['id'], $machine_name, $maintenance_type, $conn);
        }
    }
    
    return $result;
}

/**
 * Reset machine usage count after maintenance
 */
function resetMachineUsage($machine_id, $admin_id, $admin_name, $conn) {
    $current_time = date("Y-m-d H:i:s");
    
    $stmt = $conn->prepare("UPDATE machines SET usage_count = 0, last_maintenance_date = ?, status = 'Available' WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("si", $current_time, $machine_id);
    $result = $stmt->execute();
    $stmt->close();
    
    // Log maintenance activity
    logMaintenanceActivity($machine_id, 'Maintenance Completed', $admin_id, $admin_name, $conn);
    
    return $result;
}

/**
 * Create or update maintenance alert
 */
function createMaintenanceAlert($machine_id, $machine_name, $maintenance_type, $conn) {
    $stmt = $conn->prepare(
        "INSERT INTO maintenance_schedule (machine_id, machine_name, machine_type, trigger_type, status) 
         SELECT id, machine_name, machine_type, ?, 'Pending' 
         FROM machines WHERE id = ?"
    );
    
    if ($stmt) {
        $stmt->bind_param("si", $maintenance_type, $machine_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Get all machines needing maintenance
 */
function getMachinesNeedingMaintenance($conn) {
    $query = "SELECT id, machine_name, machine_type, status, usage_count, 
                     (CASE WHEN machine_type = 'washer' THEN " . WASHER_MAINTENANCE_USES . " ELSE " . DRYER_MAINTENANCE_USES . " END) as threshold,
                     DATEDIFF(CURDATE(), DATE(last_maintenance_date)) as days_since_maintenance,
                     CASE 
                        WHEN usage_count >= (CASE WHEN machine_type = 'washer' THEN " . WASHER_MAINTENANCE_USES . " ELSE " . DRYER_MAINTENANCE_USES . " END) THEN 'usage-based'
                        WHEN last_maintenance_date IS NOT NULL AND DATEDIFF(CURDATE(), DATE(last_maintenance_date)) >= " . (MAINTENANCE_MONTHS * 30) . " THEN 'time-based'
                        ELSE NULL
                     END as maintenance_type
              FROM machines
              WHERE status NOT IN ('Under Maintenance', 'Maintenance', 'Unavailable')
              AND (
                    usage_count >= (CASE WHEN machine_type = 'washer' THEN " . WASHER_MAINTENANCE_USES . " ELSE " . DRYER_MAINTENANCE_USES . " END)
                    OR (last_maintenance_date IS NOT NULL AND DATEDIFF(CURDATE(), DATE(last_maintenance_date)) >= " . (MAINTENANCE_MONTHS * 30) . ")
              )
              ORDER BY usage_count DESC, last_maintenance_date ASC";
    
    $result = $conn->query($query);
    return $result;
}

/**
 * Get machines with maintenance warnings
 * Returns machines that are close to needing maintenance
 */
function getMachinesWithMaintenanceWarnings($conn) {
    $query = "SELECT id, machine_name, machine_type, status, usage_count, last_maintenance_date,
                     (CASE WHEN machine_type = 'washer' THEN " . WASHER_MAINTENANCE_USES . " ELSE " . DRYER_MAINTENANCE_USES . " END) as threshold,
                     ROUND((usage_count / (CASE WHEN machine_type = 'washer' THEN " . WASHER_MAINTENANCE_USES . " ELSE " . DRYER_MAINTENANCE_USES . " END)) * 100, 1) as usage_percentage,
                     DATEDIFF(CURDATE(), DATE(last_maintenance_date)) as days_since_maintenance
              FROM machines
              WHERE status NOT IN ('Under Maintenance', 'Maintenance')
              AND (
                    (usage_count >= (CASE WHEN machine_type = 'washer' THEN " . WASHER_MAINTENANCE_USES . " ELSE " . DRYER_MAINTENANCE_USES . " END) * 0.75)
                    OR (DATEDIFF(CURDATE(), DATE(last_maintenance_date)) >= " . (MAINTENANCE_MONTHS * 30 * 0.8) . " AND last_maintenance_date IS NOT NULL)
              )
              ORDER BY usage_count DESC";
    
    $result = $conn->query($query);
    return $result;
}

/**
 * Get maintenance statistics for dashboard
 */
function getMaintenanceStats($conn) {
    $query = "SELECT 
                COUNT(CASE WHEN (usage_count >= (CASE WHEN machine_type = 'washer' THEN " . WASHER_MAINTENANCE_USES . " ELSE " . DRYER_MAINTENANCE_USES . " END) 
                           OR (last_maintenance_date IS NOT NULL AND DATEDIFF(CURDATE(), DATE(last_maintenance_date)) >= " . (MAINTENANCE_MONTHS * 30) . ")) 
                           AND status NOT IN ('Under Maintenance', 'Maintenance', 'Unavailable') THEN 1 END) as total_needs_maintenance,
                COUNT(CASE WHEN status IN ('Under Maintenance', 'Maintenance') THEN 1 END) as currently_under_maintenance,
                COUNT(CASE WHEN status = 'Available' THEN 1 END) as total_available,
                COUNT(*) as total_machines
              FROM machines";
    
    $result = $conn->query($query);
    return $result->fetch_assoc();
}

/**
 * Log maintenance activity
 */
function logMaintenanceActivity($machine_id, $action, $user_id, $user_name, $conn) {
    $stmt = $conn->prepare(
        "INSERT INTO maintenance_log (machine_id, machine_name, maintenance_type, performed_by, performed_by_name, reason) 
         SELECT id, machine_name, 'Manual', ?, ?, ? FROM machines WHERE id = ?"
    );
    
    if ($stmt) {
        $stmt->bind_param("issi", $user_id, $user_name, $action, $machine_id);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    
    return false;
}

/**
 * Get maintenance history for a machine
 */
function getMachineMaintenanceHistory($machine_id, $conn, $limit = 10) {
    $stmt = $conn->prepare(
        "SELECT * FROM maintenance_log WHERE machine_id = ? ORDER BY performed_at DESC LIMIT ?"
    );
    
    $stmt->bind_param("ii", $machine_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result;
}

/**
 * Log maintenance event to maintenance_logs table
 */
function logMaintenanceEvent($machine_id, $machine_name, $maintenance_type, $usage_count, $notes, $status, $conn) {
    // Create table if not exists
    $createTableQuery = "CREATE TABLE IF NOT EXISTS maintenance_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_id INT NOT NULL,
        machine_name VARCHAR(255) NOT NULL,
        machine_type VARCHAR(50) DEFAULT NULL,
        maintenance_type VARCHAR(50) NOT NULL,
        usage_count_at_maintenance INT DEFAULT 0,
        maintenance_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        maintenance_status VARCHAR(50) DEFAULT 'scheduled',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(machine_id),
        INDEX(maintenance_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    mysqli_query($conn, $createTableQuery);
    
    // Get machine type
    $machine_query = "SELECT machine_type FROM machines WHERE id = ?";
    $machine_stmt = $conn->prepare($machine_query);
    $machine_stmt->bind_param('i', $machine_id);
    $machine_stmt->execute();
    $machine_result = $machine_stmt->get_result();
    $machine_data = $machine_result->fetch_assoc();
    $machine_type = $machine_data['machine_type'] ?? 'unknown';
    $machine_stmt->close();
    
    // Insert maintenance log
    $stmt = $conn->prepare("INSERT INTO maintenance_logs (machine_id, machine_name, machine_type, maintenance_type, usage_count_at_maintenance, maintenance_status, notes, maintenance_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param('isssiss', $machine_id, $machine_name, $machine_type, $maintenance_type, $usage_count, $status, $notes);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

/**
 * Get all maintenance logs
 */
function getMaintenanceLogs($conn, $limit = 100) {
    // Ensure table exists first
    $createTableQuery = "CREATE TABLE IF NOT EXISTS maintenance_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_id INT NOT NULL,
        machine_name VARCHAR(255) NOT NULL,
        machine_type VARCHAR(50) DEFAULT NULL,
        maintenance_type VARCHAR(50) NOT NULL,
        usage_count_at_maintenance INT DEFAULT 0,
        maintenance_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        maintenance_status VARCHAR(50) DEFAULT 'scheduled',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(machine_id),
        INDEX(maintenance_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    mysqli_query($conn, $createTableQuery);
    
    $query = "SELECT * FROM maintenance_logs ORDER BY maintenance_date DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result();
    }
    return null;
}

?>
