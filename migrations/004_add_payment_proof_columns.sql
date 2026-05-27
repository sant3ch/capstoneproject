-- Migration: Add payment proof image column to gcash_requests table
-- Date: 2026-04-14
-- Purpose: Store proof of payment images with verification and rejection support

-- Add proof_image column to store proof image filename
ALTER TABLE gcash_requests 
ADD COLUMN proof_image VARCHAR(255) NULL AFTER reference_number,
ADD COLUMN admin_notes TEXT NULL AFTER status,
ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER admin_notes;

-- Add index for faster queries
ALTER TABLE gcash_requests 
ADD INDEX idx_proof_image (proof_image),
ADD INDEX idx_status_date (status, requested_at DESC);

-- Verify the columns were added
SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'gcash_requests' AND COLUMN_NAME IN ('proof_image', 'admin_notes', 'rejection_reason');
