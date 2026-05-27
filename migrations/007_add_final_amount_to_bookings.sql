-- Migration: Add final_amount column to bookings table
-- Purpose: Store the final (discounted) amount for each booking

USE `jorishlaundry_db`;

ALTER TABLE `bookings`
ADD COLUMN `final_amount` DECIMAL(10,2) NULL DEFAULT NULL AFTER `order_stage`;

-- Optionally, backfill existing bookings with calculated amount if needed
-- UPDATE bookings SET final_amount = 0 WHERE final_amount IS NULL;