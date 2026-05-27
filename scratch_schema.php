<?php
include 'config.php';
$tables = ['bookings'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    $res = mysqli_query($conn, "DESCRIBE $table");
    if ($res) {
        while($row = mysqli_fetch_assoc($res)) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
}
?>
