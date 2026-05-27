# Usage-Based Maintenance Scheduling System - Implementation Guide

## Overview
This document describes the implementation of a comprehensive usage-based maintenance scheduling system for Jorish Express Laundry that tracks machine usage and automatically alerts administrators when maintenance is needed.

## Features

### 1. Usage-Based Maintenance Thresholds
- **Washers**: 200 uses per maintenance cycle
- **Dryers**: 150 uses per maintenance cycle
- **All Machines**: 6-month time-based maintenance requirement

### 2. Machine Status Indicators
- **Available** (🟢): Machine ready for booking
- **In Use** (🔵): Currently being used
- **Needs Maintenance** (🟡): Usage threshold reached, requires immediate maintenance
- **Under Maintenance** (🟠): Currently undergoing maintenance
- **Maintenance** (🟠): Legacy status for compatibility
- **Unavailable** (🔴): Not operational

### 3. Automatic Tracking
- Usage count increments automatically when bookings are marked as completed
- Last maintenance date is recorded when machine status is set to Available after maintenance
- Maintenance alerts generated when thresholds are reached
- Dashboard notifications for administrators and staff

## Database Changes

### New Columns Added to `machines` Table
```sql
ALTER TABLE `machines` 
ADD COLUMN `machine_type` enum('washer','dryer') AFTER `machine_model`,
ADD COLUMN `usage_count` int(11) DEFAULT 0 AFTER `machine_type`,
ADD COLUMN `last_maintenance_date` datetime DEFAULT NULL AFTER `usage_count`;
```

### New Status Options
The `status` enum now includes: 'Available', 'In Use', 'Needs Maintenance', 'Under Maintenance', 'Maintenance', 'Unavailable'

### New Tables Created
1. **maintenance_log**: Audit trail for maintenance activities
   - Tracks who performed maintenance, when, and the reason
   - Usage count before maintenance
   - Status changes

2. **maintenance_schedule**: Scheduled maintenance tracking
   - Pending maintenance records
   - Trigger type (usage-based or time-based)
   - Acknowledgment tracking

## File Changes

### New Files Created
1. **includes/maintenance-functions.php** - Core maintenance logic
2. **migrations/001_add_maintenance_columns.sql** - Database migration

### Files Modified

#### admin/manage_machines.php
- Added maintenance functions include
- Added machine_type field for new machines
- Updated add/edit forms to handle machine_type
- Updated table display to show:
  - Usage count with progress bar
  - Machine type indicator
  - Last maintenance date
  - Maintenance warning badges
- Added maintenance information card in edit modal
- Enhanced status dropdown with new status options
- Automatic usage count reset when setting to Available after maintenance

#### admin/admin_home.php
- Added maintenance functions include
- Added maintenance statistics fetching
- Enhanced machine status card with maintenance alerts:
  - Shows count of machines needing maintenance
  - Shows count of machines under maintenance
  - Links to manage_machines.php
- Added new section displaying maintenance requirements table
- Shows up to 5 machines needing maintenance with details
- Link to view all machines needing maintenance

#### admin/booking_schedules.php
- Added maintenance functions include
- Automatic usage increment when booking is marked as Completed
- Parses `machine_names` field from booking and increments each machine's usage count
- Automatically triggers "Needs Maintenance" status if threshold is exceeded

#### staff/manage-machines-staff.php
- Updated with same maintenance features as admin version
- Staff can view and manage machine maintenance status
- Can mark machines as complete after maintenance

## Implementation Steps

### Step 1: Database Migration
Run the migration script to add new columns and tables:

```bash
# Using phpMyAdmin: Import the migration file
# Or using MySQL command line:
mysql -u root -p jorishlaundry_db < migrations/001_add_maintenance_columns.sql
```

### Step 2: Populate Machine Types
If machines already exist, the migration script automatically populates machine_type based on machine_name:
- Names containing "Washer" → washer
- Names containing "Dryer" → dryer

### Step 3: File Updates
All PHP files have been updated. No additional changes needed.

### Step 4: Test the System
1. Navigate to admin/manage_machines.php
2. Verify machines show type, usage count, and last maintenance date
3. Check admin_home.php dashboard for maintenance alerts
4. Mark a booking as completed to test usage increment

## Usage Instructions

### For Administrators

#### Viewing Machine Status
1. Go to "Manage Machines" in admin panel
2. View the enhanced table showing:
   - Machine type (🧺 Washer or 🔥 Dryer)
   - Current usage vs. threshold
   - Usage percentage bar (green → yellow → red as it approaches threshold)
   - Last maintenance date
   - Warning badge (⚠️) if maintenance needed

#### Performing Maintenance
1. Click Edit on the machine needing maintenance
2. Review the Maintenance Information card showing:
   - Machine type
   - Usage count
   - Usage percentage
   - Last maintenance date
3. Change status to "Under Maintenance" while working
4. When complete, change status to "Available"
   - System automatically:
     - Resets usage count to 0
     - Records current date/time as last_maintenance_date
     - Shows confirmation message

#### Dashboard Alerts
- Machine Status card on admin_home.php shows:
  - Number of machines needing maintenance (🟡)
  - Number under maintenance (🟠)
  - Clickable link to manage_machines.php
- Maintenance Required section displays:
  - Table of machines needing maintenance (up to 5)
  - Machine name, type, usage, and reason
  - Quick action buttons
  - Link to view all if more than 5

### For Staff

#### Using Staff Version
- Similar interface to admin version
- Can view machine status and usage information
- Can update machine status during maintenance
- Same automatic reset when setting to Available

## API Functions

### maintenance-functions.php

#### checkMaintenanceNeeded($machine_id, $conn)
Returns: 'usage-based', 'time-based', or null
Checks if maintenance is needed based on usage or time

#### getMaintenanceStatus($machine_id, $conn)
Returns: Array with machine info and maintenance status

#### incrementMachineUsage($machine_name, $conn)
Increments usage count and triggers alerts if needed

#### resetMachineUsage($machine_id, $admin_id, $admin_name, $conn)
Resets usage counter after maintenance and records date

#### getMachinesNeedingMaintenance($conn)
Returns: Query result with all machines exceeding thresholds

#### getMaintenanceStats($conn)
Returns: Statistics array with maintenance counts

## Configuration

### Maintenance Thresholds
Edit in `includes/maintenance-functions.php`:
```php
define('WASHER_MAINTENANCE_USES', 200);
define('DRYER_MAINTENANCE_USES', 150);
define('MAINTENANCE_MONTHS', 6);
```

## Notifications

### Automatic Alerts
1. **Dashboard Alert**: Shown on admin_home.php when machines need maintenance
2. **Warning Badge**: Displayed in manage_machines.php table
3. **Modal Warning**: Shown when editing a machine that needs maintenance
4. **Status Badge**: Visual indicator in machine list (⚠️)

### Admin Notification
Maintenance completion logged to admin_notifications and notification tables

## Troubleshooting

### Usage Count Not Incrementing
- Check that booking is marked as 'Completed'
- Verify `machine_names` field in booking is populated
- Check maintenance-functions.php include is present

### Maintenance Alert Not Showing
- Verify machine_type is set correctly
- Check usage_count in database
- Ensure functions include is loaded

### Last Maintenance Date Not Updated
- Verify status change is to 'Available'
- Check that machine was in Needs Maintenance/Under Maintenance status
- Review database record for last_maintenance_date field

## Future Enhancements

1. **Maintenance Reports**: Generate reports on maintenance history
2. **Predictive Alerts**: Alert before reaching threshold (e.g., at 75%)
3. **Automated Scheduling**: Schedule maintenance based on patterns
4. **Cost Tracking**: Track maintenance costs per machine
5. **Service Integration**: Link to external maintenance vendors

## Support

For issues or questions:
1. Check the maintenance_log and maintenance_schedule tables
2. Review admin/error.log for PHP errors
3. Verify all database columns were created
4. Check that includes/maintenance-functions.php is properly formatted

## Changelog

### Version 1.0 (2026-03-30)
- Initial implementation
- Usage-based maintenance scheduling
- Automatic usage tracking
- Dashboard alerts and notifications
- Staff support
- Database tables for audit trail
