# Maintenance Functions API Reference

This document provides detailed information about all functions available in `includes/maintenance-functions.php`.

## Constants

```php
WASHER_MAINTENANCE_USES  // 200 - Maximum uses before washer needs maintenance
DRYER_MAINTENANCE_USES   // 150 - Maximum uses before dryer needs maintenance
MAINTENANCE_MONTHS       // 6   - Months before time-based maintenance required
```

## Functions

### checkMaintenanceNeeded()

```php
$result = checkMaintenanceNeeded($machine_id, $conn);
```

**Purpose**: Determine if a machine requires maintenance

**Parameters**:
- `$machine_id` (int): ID of the machine to check
- `$conn` (mysqli): Database connection

**Returns**: 
- `'usage-based'` - Machine has exceeded usage threshold
- `'time-based'` - 6 months have passed since last maintenance
- `null` - No maintenance needed

**Example**:
```php
if (checkMaintenanceNeeded(1, $conn) === 'usage-based') {
    echo "Machine needs maintenance due to usage";
}
```

---

### getMaintenanceStatus()

```php
$status = getMaintenanceStatus($machine_id, $conn);
```

**Purpose**: Get comprehensive maintenance information for a machine

**Parameters**:
- `$machine_id` (int): ID of the machine
- `$conn` (mysqli): Database connection

**Returns**: Array with keys:
- `id` - Machine ID
- `name` - Machine name
- `type` - 'washer' or 'dryer'
- `status` - Current status
- `usage_count` - Current usage count
- `usage_threshold` - When machine needs maintenance
- `usage_percentage` - Percentage of threshold used
- `last_maintenance_date` - Last maintenance date/time or null
- `needs_maintenance` - Boolean
- `maintenance_type` - 'usage-based', 'time-based', or null
- `is_under_maintenance` - Boolean
- `is_in_use` - Boolean

**Example**:
```php
$info = getMaintenanceStatus(1, $conn);
echo "Usage: {$info['usage_count']} / {$info['usage_threshold']}";
echo "Percentage: {$info['usage_percentage']}%";
if ($info['needs_maintenance']) {
    echo "Needs {$info['maintenance_type']} maintenance";
}
```

---

### incrementMachineUsage()

```php
$success = incrementMachineUsage($machine_name, $conn);
```

**Purpose**: Increment machine usage count (called when booking completes)

**Parameters**:
- `$machine_name` (string): Name of the machine (e.g., "Washer1")
- `$conn` (mysqli): Database connection

**Returns**: Boolean (true on success, false on failure)

**Side Effects**:
- Increments `usage_count` by 1
- If usage exceeds threshold, auto-updates status to 'Needs Maintenance'
- Creates maintenance alert record

**Example**:
```php
if (incrementMachineUsage("Washer1", $conn)) {
    echo "Usage updated successfully";
    // Check if alert was created
    if (checkMaintenanceNeeded($machineId, $conn)) {
        sendAlert("Machine needs maintenance");
    }
}
```

---

### resetMachineUsage()

```php
$success = resetMachineUsage($machine_id, $admin_id, $admin_name, $conn);
```

**Purpose**: Reset usage counter after maintenance completion

**Parameters**:
- `$machine_id` (int): ID of the machine
- `$admin_id` (int): ID of admin/staff performing maintenance
- `$admin_name` (string): Name of admin/staff
- `$conn` (mysqli): Database connection

**Returns**: Boolean (true on success, false on failure)

**Side Effects**:
- Sets `usage_count` to 0
- Sets `last_maintenance_date` to current date/time
- Sets `status` to 'Available'
- Logs activity to `maintenance_log` table

**Example**:
```php
if (resetMachineUsage(1, $_SESSION['user_id'], $_SESSION['user_name'], $conn)) {
    echo "Maintenance completed and machine reset";
}
```

---

### createMaintenanceAlert()

```php
createMaintenanceAlert($machine_id, $machine_name, $maintenance_type, $conn);
```

**Purpose**: Create a maintenance alert record

**Parameters**:
- `$machine_id` (int): ID of the machine
- `$machine_name` (string): Name of the machine
- `$maintenance_type` (string): 'usage-based' or 'time-based'
- `$conn` (mysqli): Database connection

**Returns**: None

**Note**: Called automatically by `incrementMachineUsage()` and `checkMaintenanceNeeded()`

---

### getMachinesNeedingMaintenance()

```php
$result = getMachinesNeedingMaintenance($conn);
```

**Purpose**: Get all machines that need maintenance

**Parameters**:
- `$conn` (mysqli): Database connection

**Returns**: mysqli_result with columns:
- `id` - Machine ID
- `machine_name` - Machine name
- `machine_type` - 'washer' or 'dryer'
- `status` - Current status
- `usage_count` - Current usage
- `threshold` - Maintenance threshold for type
- `days_since_maintenance` - Days since last maintenance
- `maintenance_type` - 'usage-based' or 'time-based'

**Example**:
```php
$result = getMachinesNeedingMaintenance($conn);
while ($machine = $result->fetch_assoc()) {
    echo "Machine: {$machine['machine_name']} - {$machine['maintenance_type']}";
}
```

---

### getMachinesWithMaintenanceWarnings()

```php
$result = getMachinesWithMaintenanceWarnings($conn);
```

**Purpose**: Get machines approaching maintenance threshold (75% or more)

**Parameters**:
- `$conn` (mysqli): Database connection

**Returns**: mysqli_result with columns:
- `id` - Machine ID
- `machine_name` - Machine name
- `machine_type` - 'washer' or 'dryer'
- `status` - Current status
- `usage_count` - Current usage
- `last_maintenance_date` - When last maintained
- `threshold` - Maintenance threshold
- `usage_percentage` - Percentage of threshold
- `days_since_maintenance` - Days since maintenance

**Example**:
```php
$warnings = getMachinesWithMaintenanceWarnings($conn);
while ($m = $warnings->fetch_assoc()) {
    if ($m['usage_percentage'] >= 100) {
        echo "URGENT: {$m['machine_name']} at {$m['usage_percentage']}%";
    } elseif ($m['usage_percentage'] >= 75) {
        echo "WARNING: {$m['machine_name']} at {$m['usage_percentage']}%";
    }
}
```

---

### getMaintenanceStats()

```php
$stats = getMaintenanceStats($conn);
```

**Purpose**: Get overall maintenance statistics

**Parameters**:
- `$conn` (mysqli): Database connection

**Returns**: Associative array with keys:
- `total_needs_maintenance` - Count of machines needing maintenance
- `currently_under_maintenance` - Count under maintenance
- `total_available` - Count of available machines
- `total_machines` - Total machines

**Example**:
```php
$stats = getMaintenanceStats($conn);
echo "Total machines: {$stats['total_machines']}";
echo "Available: {$stats['total_available']}";
echo "Needing maintenance: {$stats['total_needs_maintenance']}";
echo "Under maintenance: {$stats['currently_under_maintenance']}";
```

---

### logMaintenanceActivity()

```php
$success = logMaintenanceActivity($machine_id, $action, $user_id, $user_name, $conn);
```

**Purpose**: Log a maintenance activity to the maintenance_log table

**Parameters**:
- `$machine_id` (int): ID of the machine
- `$action` (string): Description of action taken
- `$user_id` (int): ID of the user performing action
- `$user_name` (string): Name of the user
- `$conn` (mysqli): Database connection

**Returns**: Boolean (true on success, false on failure)

**Example**:
```php
logMaintenanceActivity(
    1, 
    "Oil changed and filters replaced", 
    $_SESSION['user_id'], 
    $_SESSION['user_name'], 
    $conn
);
```

---

### getMachineMaintenanceHistory()

```php
$history = getMachineMaintenanceHistory($machine_id, $conn, $limit = 10);
```

**Purpose**: Get maintenance history for a machine

**Parameters**:
- `$machine_id` (int): ID of the machine
- `$conn` (mysqli): Database connection
- `$limit` (int, optional): Maximum number of records to retrieve (default: 10)

**Returns**: mysqli_result with maintenance_log records ordered by date (newest first)

**Example**:
```php
$history = getMachineMaintenanceHistory(1, $conn, 5);
while ($log = $history->fetch_assoc()) {
    echo "Date: {$log['performed_at']}";
    echo "By: {$log['performed_by_name']}";
    echo "Reason: {$log['reason']}";
}
```

---

## Usage Examples

### Example 1: Check Machine Status on Dashboard

```php
<?php
require '../includes/maintenance-functions.php';

// Get all machines
$machines = mysqli_query($conn, "SELECT id, machine_name FROM machines");

while ($m = $machines->fetch_assoc()) {
    $status = getMaintenanceStatus($m['id'], $conn);
    
    if ($status['needs_maintenance']) {
        echo "<div class='alert alert-warning'>";
        echo "{$status['name']} needs {$status['maintenance_type']} maintenance";
        echo "Usage: {$status['usage_count']} / {$status['usage_threshold']}";
        echo "</div>";
    }
}
?>
```

### Example 2: Complete Booking and Track Usage

```php
<?php
require '../includes/maintenance-functions.php';

// Mark booking as completed
$bookingId = 123;
$machineNames = ['Washer1', 'Dryer1'];

mysqli_query($conn, "UPDATE bookings SET status = 'Completed' WHERE id = $bookingId");

// Increment usage for each machine
foreach ($machineNames as $machineName) {
    if (incrementMachineUsage($machineName, $conn)) {
        $machineId = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT id FROM machines WHERE machine_name = '$machineName'")
        )['id'];
        
        // Check if maintenance needed
        if (checkMaintenanceNeeded($machineId, $conn) == 'usage-based') {
            sendAlert("Machine $machineName needs maintenance!");
        }
    }
}
?>
```

### Example 3: Perform Maintenance

```php
<?php
require '../includes/maintenance-functions.php';

$machineId = 1;
$adminId = $_SESSION['user_id'];
$adminName = $_SESSION['user_name'];

// Mark as under maintenance
mysqli_query($conn, "UPDATE machines SET status = 'Under Maintenance' WHERE id = $machineId");

// ... perform actual maintenance ...

// Mark as complete and reset
if (resetMachineUsage($machineId, $adminId, $adminName, $conn)) {
    echo "Maintenance completed";
}
?>
```

### Example 4: Generate Maintenance Report

```php
<?php
require '../includes/maintenance-functions.php';

$stats = getMaintenanceStats($conn);

echo "MAINTENANCE REPORT\n";
echo "==================\n";
echo "Total Machines: {$stats['total_machines']}\n";
echo "Available: {$stats['total_available']}\n";
echo "Needing Maintenance: {$stats['total_needs_maintenance']}\n";
echo "Under Maintenance: {$stats['currently_under_maintenance']}\n\n";

echo "MACHINES NEEDING MAINTENANCE:\n";
$machines = getMachinesNeedingMaintenance($conn);
while ($m = $machines->fetch_assoc()) {
    echo "- {$m['machine_name']} ({$m['machine_type']}): ";
    echo "{$m['usage_count']}/{$m['threshold']} uses\n";
}
?>
```

## Error Handling

Most functions return boolean or null. Always check return values:

```php
if (resetMachineUsage($id, $userId, $userName, $conn)) {
    echo "Success";
} else {
    echo "Error: Could not reset usage";
}

if ($result = getMachinesNeedingMaintenance($conn)) {
    if ($result->num_rows > 0) {
        // Process results
    } else {
        echo "No machines need maintenance";
    }
} else {
    echo "Database error";
}
```

## Performance Considerations

- `getMachinesNeedingMaintenance()` performs a complex query - cache results if called frequently
- `getMaintenanceStatus()` queries for single machine - efficient
- `getMachineMaintenanceHistory()` with high $limit may be slow - use pagination for large datasets
- Consider adding indexes on machine_type and status columns

## Security Notes

- All functions use prepared statements (no SQL injection risk)
- User IDs and names are logged for audit trail
- Maintenance logs are immutable records
- Always verify user permissions before calling maintenance functions
