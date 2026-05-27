# Quick Start Guide - Maintenance System Setup

## Prerequisites
- XAMPP running with MySQL
- phpMyAdmin or MySQL command line access
- Current database: `jorishlaundry_db`

## Installation (5 minutes)

### Step 1: Apply Database Migration
Run the SQL commands below in phpMyAdmin:

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select the `jorishlaundry_db` database
3. Click "SQL" tab
4. Copy and paste the following commands:

```sql
-- Modify machines table to add maintenance tracking
ALTER TABLE `machines` 
MODIFY COLUMN `status` enum('Available','In Use','Needs Maintenance','Under Maintenance','Unavailable','Maintenance') DEFAULT 'Available',
ADD COLUMN `machine_type` enum('washer','dryer') AFTER `machine_model`,
ADD COLUMN `usage_count` int(11) DEFAULT 0 AFTER `machine_type`,
ADD COLUMN `last_maintenance_date` datetime DEFAULT NULL AFTER `usage_count`,
ADD KEY `idx_machine_type_status` (`machine_type`, `status`);

-- Populate machine_type based on machine_name
UPDATE `machines` SET `machine_type` = 'washer' WHERE `machine_name` LIKE '%Washer%';
UPDATE `machines` SET `machine_type` = 'dryer' WHERE `machine_name` LIKE '%Dryer%';

-- Create maintenance_log table
CREATE TABLE IF NOT EXISTS `maintenance_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `machine_id` int(11) NOT NULL,
  `machine_name` varchar(50) NOT NULL,
  `maintenance_type` enum('Usage-Based','Time-Based','Manual') DEFAULT 'Manual',
  `usage_count_before` int(11) DEFAULT NULL,
  `reason` text,
  `performed_by` int(11) DEFAULT NULL,
  `performed_by_name` varchar(255) DEFAULT NULL,
  `status_before` varchar(50),
  `status_after` varchar(50),
  `performed_at` datetime DEFAULT current_timestamp(),
  INDEX `idx_machine_id` (`machine_id`),
  INDEX `idx_performed_at` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create maintenance_schedule table
CREATE TABLE IF NOT EXISTS `maintenance_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `machine_id` int(11) NOT NULL,
  `machine_name` varchar(50) NOT NULL,
  `machine_type` enum('washer','dryer') NOT NULL,
  `trigger_type` enum('Usage-Based','Time-Based') NOT NULL,
  `trigger_value` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `status` enum('Pending','Acknowledged','Completed','Ignored') DEFAULT 'Pending',
  INDEX `idx_machine_id` (`machine_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

5. Click "Go" to execute

### Step 2: Verify Installation
1. Go to admin panel: Navigate to "Manage Machines"
2. Check that each machine now displays:
   - ✅ Machine type (🧺 Washer or 🔥 Dryer)
   - ✅ Usage count progressbar
   - ✅ Last maintenance date column
3. Go to admin home dashboard
4. In the "Machine Status" card, verify:
   - ✅ "Need Maintenance" and "Under Maintenance" badges appear (if applicable)

## Verification Checklist

### Database
- [ ] `machines` table has `machine_type` column
- [ ] `machines` table has `usage_count` column
- [ ] `machines` table has `last_maintenance_date` column
- [ ] `maintenance_log` table exists
- [ ] `maintenance_schedule` table exists

### UI - Admin
- [ ] manage_machines.php shows machine type
- [ ] manage_machines.php shows usage count with progress bar
- [ ] manage_machines.php shows last maintenance date
- [ ] Edit modal shows maintenance information card
- [ ] Status dropdown includes all 6 statuses
- [ ] admin_home.php shows maintenance alerts on dashboard

### Functionality
- [ ] Can change machine status to all 6 options
- [ ] Changing to "Available" from "Under Maintenance" resets usage count
- [ ] Complete a booking - machine usage count increases
- [ ] When usage exceeds threshold, status auto-updates to "Needs Maintenance"

## First-Time Usage

1. **Admin**: Go to Manage Machines and edit a machine
   - Change status to "Under Maintenance" and click Save
   - Change status to "Available" and click Save
   - Verify usage count reset to 0
   - Verify "Last Maintenance" date is updated

2. **Complete a Booking**: 
   - Mark a booking as Completed
   - Go to Manage Machines
   - Check the machine's usage increased by 1

3. **Trigger Maintenance Alert**:
   - Edit a machine with very high usage count
   - Set usage close to threshold (e.g., 195 for washer, 145 for dryer)
   - Complete bookings until threshold is reached
   - Machine status should auto-update to "Needs Maintenance"
   - Maintenance alert should appear on admin_home.php

## Configuration

### Change Maintenance Thresholds
Edit: `includes/maintenance-functions.php`

```php
// Line ~8-10
define('WASHER_MAINTENANCE_USES', 200);  // Change this value
define('DRYER_MAINTENANCE_USES', 150);   // Change this value
define('MAINTENANCE_MONTHS', 6);         // Change this value
```

## Important Notes

⚠️ **Do Not Skip Database Migration** - A few machines must have machine_type set
   - If you have machines without a type, set them manually in phpMyAdmin

✅ **Usage Increment** - Only happens when bookings marked as "Completed"
   - Cancelled or Pending bookings don't increment usage

✅ **Automatic Reset** - Only happens when status changed to "Available" from maintenance status
   - Manual updates of usage count require database edits

## Support / FAQ

**Q: Usage count didn't increase after completing booking?**
A: 
1. Check that booking was marked "Completed" (not Cancelled)
2. Check that machine_names field is populated in booking
3. Verify maintenance-functions.php include is in booking_schedules.php

**Q: Status didn't auto-update to "Needs Maintenance"?**
A:
1. Check usage_count in database against threshold
2. Check machine_type is set (washer=200, dryer=150)
3. Clear browser cache and refresh

**Q: Dashboard alerts not showing?**
A:
1. Verify admin_home.php includes maintenance-functions.php
2. Check database query for machines needing maintenance
3. Try clearing cache/restart server

## Rollback Instructions

If you need to revert the changes:

```sql
-- Remove new columns (WARNING: This will lose data!)
ALTER TABLE `machines` 
DROP COLUMN `machine_type`,
DROP COLUMN `usage_count`,
DROP COLUMN `last_maintenance_date`,
DROP KEY `idx_machine_type_status`;

-- Drop new tables
DROP TABLE IF EXISTS `maintenance_log`;
DROP TABLE IF EXISTS `maintenance_schedule`;

-- Revert status enum
ALTER TABLE `machines` 
MODIFY COLUMN `status` enum('Available','Unavailable','Maintenance') DEFAULT 'Available';
```

Then restore backup files of:
- `admin/manage_machines.php`
- `admin/admin_home.php`
- `admin/booking_schedules.php`
- `staff/manage-machines-staff.php`

## Success!

Your maintenance system is now ready to use. Start by:
1. Reviewing a machine in Manage Machines
2. Checking the admin dashboard for alerts
3. Completing a booking to test usage tracking
