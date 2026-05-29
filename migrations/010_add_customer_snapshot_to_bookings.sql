-- =========================================================
-- 010_add_customer_snapshot_to_bookings.sql
-- Adds the customer-snapshot + location columns that the booking
-- wizard (booking_process.php INSERT) and queue_management.php SELECT
-- already expect. Only `delivery_address` had been migrated (006).
-- =========================================================

ALTER TABLE `bookings`
  ADD COLUMN `customer_first_name` VARCHAR(255) NULL AFTER `delivery_address`,
  ADD COLUMN `customer_last_name`  VARCHAR(255) NULL AFTER `customer_first_name`,
  ADD COLUMN `customer_mobile`     VARCHAR(50)  NULL AFTER `customer_last_name`,
  ADD COLUMN `customer_email`      VARCHAR(255) NULL AFTER `customer_mobile`,
  ADD COLUMN `location_details`    TEXT         NULL AFTER `customer_email`;
