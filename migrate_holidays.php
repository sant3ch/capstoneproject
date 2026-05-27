<?php
include 'config.php';

$queries = [
    "ALTER TABLE gcash_requests ADD COLUMN is_holiday TINYINT(1) DEFAULT 0 AFTER rejection_reason",
    "ALTER TABLE gcash_requests ADD COLUMN holiday_name VARCHAR(100) DEFAULT NULL AFTER is_holiday",
    "ALTER TABLE transactions ADD COLUMN is_holiday TINYINT(1) DEFAULT 0 AFTER transaction_date",
    "ALTER TABLE transactions ADD COLUMN holiday_name VARCHAR(100) DEFAULT NULL AFTER is_holiday"
];

foreach ($queries as $query) {
    echo "Executing: $query\n";
    if (mysqli_query($conn, $query)) {
        echo "Success\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
}
?>
