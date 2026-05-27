-- Full order queue pipeline migration
-- Run after 001_add_maintenance_columns.sql

ALTER TABLE bookings
    ADD COLUMN queue_code VARCHAR(20) NULL AFTER queue_number,
    ADD COLUMN order_stage ENUM(
        'Pending / Booked',
        'Queued (Waiting for Machine)',
        'Queuing (Assigning Machines)',
        'In Process',
        'Ready for Pickup',
        'Completed / Picked Up',
        'Missed Pickup'
    ) NOT NULL DEFAULT 'Pending / Booked' AFTER pickup_status,
    ADD COLUMN estimated_start_time DATETIME NULL AFTER machine_names,
    ADD COLUMN estimated_completion_time DATETIME NULL AFTER estimated_start_time,
    ADD COLUMN process_started_at DATETIME NULL AFTER estimated_completion_time,
    ADD COLUMN process_completed_at DATETIME NULL AFTER process_started_at,
    ADD COLUMN picked_up_at DATETIME NULL AFTER process_completed_at,
    ADD COLUMN overdue_notified TINYINT(1) NOT NULL DEFAULT 0 AFTER picked_up_at;

ALTER TABLE bookings
    ADD INDEX idx_bookings_queue_date_number (booking_date, queue_number),
    ADD INDEX idx_bookings_order_stage (order_stage),
    ADD INDEX idx_bookings_pickup_status (pickup_status);

UPDATE bookings
SET queue_code = CONCAT('Q-', LPAD(queue_number, 3, '0'))
WHERE queue_number IS NOT NULL
    AND queue_number > 0
    AND (queue_code IS NULL OR queue_code = '');

UPDATE bookings
SET order_stage = 'Completed / Picked Up'
WHERE pickup_status = 'Already Picked';

UPDATE bookings
SET order_stage = 'Ready for Pickup'
WHERE status = 'Completed'
    AND pickup_status = 'Waiting for Pick Up';
