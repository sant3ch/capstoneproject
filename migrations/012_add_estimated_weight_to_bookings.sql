-- =========================================================
-- 012_add_estimated_weight_to_bookings.sql
-- Stores the customer's estimated laundry weight (kg) entered in the
-- booking wizard. Drives the auto-calculated machine count (8kg / load)
-- and is shown to staff/admin. Final actual weight is still captured
-- later in transactions.laundry_weight.
-- =========================================================

ALTER TABLE `bookings`
  ADD COLUMN `estimated_weight` DECIMAL(5,2) NULL AFTER `machine_count`;
