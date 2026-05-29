<?php
/**
 * Migration: walk-in customer tracking.
 * Run once: http://localhost/capstoneproject/migrate_walkin_reservations.php
 * Safe to re-run (CREATE TABLE IF NOT EXISTS).
 */
include 'config.php';
header('Content-Type: text/plain');

$sql = "
CREATE TABLE IF NOT EXISTS `walkin_reservations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(150) DEFAULT NULL,
  `contact` VARCHAR(50) DEFAULT NULL,
  `booking_date` DATE NOT NULL,
  `time_slot` VARCHAR(50) NOT NULL,
  `washers_used` INT(11) NOT NULL DEFAULT 0,
  `dryers_used` INT(11) NOT NULL DEFAULT 0,
  `estimated_weight` DECIMAL(5,2) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Active','Completed','Cancelled') NOT NULL DEFAULT 'Active',
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date_status` (`booking_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

echo "-- create walkin_reservations\n";
echo mysqli_query($conn, $sql) ? "Success\n" : ("Error: " . mysqli_error($conn) . "\n");
echo "Done.\n";
?>
