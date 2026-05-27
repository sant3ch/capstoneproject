-- Migration: Add COD and Delivering Stage Support
-- Adds "Confirmed - Scheduled for Pickup" status and "Delivering" order_stage
-- Date: 2026-04-17

-- Add "Confirmed - Scheduled for Pickup" to status ENUM in bookings table
ALTER TABLE bookings
MODIFY COLUMN status ENUM(
    'Pending',
    'Completed',
    'Cancelled',
    'Pending Payment',
    'Rescheduled',
    'Confirmed - Scheduled for Pickup'
) NOT NULL DEFAULT 'Pending';

-- Add "Delivering" to order_stage ENUM in bookings table
-- This stage transitions from "In Process" and comes before "Ready for Pickup"
ALTER TABLE bookings
MODIFY COLUMN order_stage ENUM(
    'Pending / Booked',
    'Queued (Waiting for Machine)',
    'Queuing (Assigning Machines)',
    'In Process',
    'Delivering',
    'Ready for Pickup',
    'Completed / Picked Up',
    'Missed Pickup'
) NOT NULL DEFAULT 'Pending / Booked';

-- Verify the changes
SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'bookings' AND (COLUMN_NAME = 'status' OR COLUMN_NAME = 'order_stage');
