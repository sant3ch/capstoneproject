-- Migration: Add maintenance tracking columns to machines table
-- Date: 2026-03-30
-- Purpose: Implement usage-based maintenance scheduling

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

-- Create maintenance_log table for audit trail
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

-- Create maintenance_schedule table for tracking scheduled maintenance
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
