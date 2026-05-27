-- Migration: Add COD Confirmation Photo Column
-- Date: 2026-04-19
-- Purpose: Store the filename of the user's COD delivery confirmation photo

-- Add cod_confirmation_photo column to bookings table
ALTER TABLE bookings 
ADD COLUMN cod_confirmation_photo VARCHAR(255) NULL AFTER picked_up_at;

-- Add index for faster queries on this column
ALTER TABLE bookings 
ADD INDEX idx_cod_photo (cod_confirmation_photo);

-- Verify the column was added
SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'bookings' AND COLUMN_NAME = 'cod_confirmation_photo';
