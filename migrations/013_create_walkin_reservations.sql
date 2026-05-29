-- =========================================================
-- 013_create_walkin_reservations.sql
-- Tracks walk-in customers' machine usage per date + time slot so it
-- reduces the availability shown to online bookings (prevents conflicts).
-- Kept separate from `bookings` so walk-ins stay out of the online
-- queue / points / notifications pipeline.
-- =========================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
