-- Migration: Add Delivery Address Field
-- Adds delivery_address column to store customer's delivery address
-- Date: 2026-04-18

-- Add delivery_address column to bookings table
ALTER TABLE bookings
ADD COLUMN delivery_address TEXT DEFAULT NULL AFTER machine_names;

-- Verify the change
SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'bookings' AND COLUMN_NAME = 'delivery_address';
