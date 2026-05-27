-- Add 'Queuing (Assigning Machines)' to order_stage ENUM
-- This stage was missing from the original migration

ALTER TABLE bookings
MODIFY COLUMN order_stage ENUM(
    'Pending / Booked',
    'Queued (Waiting for Machine)',
    'Queuing (Assigning Machines)',
    'In Process',
    'Ready for Pickup',
    'Completed / Picked Up',
    'Missed Pickup'
) NOT NULL DEFAULT 'Pending / Booked';

-- Verify the change
SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'bookings' AND COLUMN_NAME = 'order_stage';
