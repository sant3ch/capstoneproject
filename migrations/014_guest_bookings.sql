-- =========================================================
-- 014_guest_bookings.sql
-- Enables unregistered ("guest") online bookings + payments.
-- - bookings.guest_token: random access token for guests (no account)
-- - gcash_requests.user_id: made nullable so guest payment requests can be stored
-- =========================================================

ALTER TABLE `bookings`
  ADD COLUMN `guest_token` VARCHAR(64) NULL AFTER `user_id`;

ALTER TABLE `gcash_requests`
  MODIFY `user_id` INT(11) NULL;
