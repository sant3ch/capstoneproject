<?php
/**
 * Migration: add missing customer-snapshot + location columns to `bookings`.
 * Fixes the "Unknown column 'b.customer_first_name'" 500 in queue_management.php
 * and the matching INSERT in user/booking_process.php.
 *
 * Run once: http://localhost/capstoneproject/migrate_booking_customer_columns.php
 * Idempotent + works on MariaDB and MySQL 8 (checks INFORMATION_SCHEMA first).
 */
include 'config.php';
header('Content-Type: text/plain');

$columns = [
    'customer_first_name' => "ADD COLUMN `customer_first_name` VARCHAR(255) NULL AFTER `delivery_address`",
    'customer_last_name'  => "ADD COLUMN `customer_last_name`  VARCHAR(255) NULL AFTER `customer_first_name`",
    'customer_mobile'     => "ADD COLUMN `customer_mobile`     VARCHAR(50)  NULL AFTER `customer_last_name`",
    'customer_email'      => "ADD COLUMN `customer_email`      VARCHAR(255) NULL AFTER `customer_mobile`",
    'location_details'    => "ADD COLUMN `location_details`    TEXT         NULL AFTER `customer_email`",
];

foreach ($columns as $name => $addClause) {
    // Does the column already exist?
    $check = $conn->prepare(
        "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = ?"
    );
    $check->bind_param('s', $name);
    $check->execute();
    $exists = (int) $check->get_result()->fetch_assoc()['c'] > 0;
    $check->close();

    if ($exists) {
        echo "Skipped (already exists): $name\n";
        continue;
    }

    echo "Adding: $name\n";
    if (mysqli_query($conn, "ALTER TABLE `bookings` $addClause")) {
        echo "Success\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
}

echo "Done.\n";
?>
