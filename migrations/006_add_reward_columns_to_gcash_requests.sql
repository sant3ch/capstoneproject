-- Migration: Add reward tracking columns to gcash_requests table
-- Purpose: Store applied reward/voucher information with payment requests

USE `jorishlaundry_db`;

ALTER TABLE `gcash_requests` 
ADD COLUMN `reward_id` INT(11) NULL DEFAULT NULL AFTER `amount`,
ADD COLUMN `reward_name` VARCHAR(255) NULL DEFAULT NULL AFTER `reward_id`,
ADD COLUMN `discount_amount` DECIMAL(10,2) NULL DEFAULT 0 AFTER `reward_name`,
ADD COLUMN `original_amount` DECIMAL(10,2) NULL DEFAULT NULL AFTER `discount_amount`;

-- Create index for faster reward lookups
CREATE INDEX `idx_reward_id` ON `gcash_requests`(`reward_id`);

-- Update existing records to store original amount if discount info missing
UPDATE `gcash_requests` 
SET `original_amount` = `amount`, `discount_amount` = 0 
WHERE `original_amount` IS NULL;
