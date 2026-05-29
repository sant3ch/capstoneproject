<?php
/**
 * Migration: guest (unregistered) booking support.
 * Run once: http://localhost/capstoneproject/migrate_guest_bookings.php
 * Idempotent.
 */
include 'config.php';
header('Content-Type: text/plain');

// 1) bookings.guest_token (add if missing)
$res = mysqli_query($conn, "SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'guest_token'");
$has = (int) mysqli_fetch_assoc($res)['c'] > 0;
echo "-- bookings.guest_token\n";
if ($has) {
    echo "Skipped (exists)\n";
} else {
    echo mysqli_query($conn, "ALTER TABLE `bookings` ADD COLUMN `guest_token` VARCHAR(64) NULL AFTER `user_id`")
        ? "Added\n" : ("Error: " . mysqli_error($conn) . "\n");
}

// 2) gcash_requests.user_id -> nullable
$res = mysqli_query($conn, "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gcash_requests' AND COLUMN_NAME = 'user_id'");
$row = mysqli_fetch_assoc($res);
echo "-- gcash_requests.user_id nullable\n";
if ($row && $row['IS_NULLABLE'] === 'YES') {
    echo "Skipped (already nullable)\n";
} else {
    echo mysqli_query($conn, "ALTER TABLE `gcash_requests` MODIFY `user_id` INT(11) NULL")
        ? "Modified\n" : ("Error: " . mysqli_error($conn) . "\n");
}

echo "Done.\n";
?>
