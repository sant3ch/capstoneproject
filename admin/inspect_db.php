<?php
require __DIR__ . '/../config.php';

echo "<h2>Database Tables</h2>";
$res = mysqli_query($conn, 'SHOW TABLES');
while($row = mysqli_fetch_array($res)) {
    echo $row[0] . "<br>";
}

echo "<h2>Payments Sample</h2>";
$res = mysqli_query($conn, "SELECT * FROM payments LIMIT 5");
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        print_r($row);
    }
}

echo "<h2>Transactions Sample</h2>";
$res = mysqli_query($conn, "SELECT * FROM transactions LIMIT 5");
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        print_r($row);
    }
}

echo "<h2>GCASH Requests Sample</h2>";
$res = mysqli_query($conn, "SELECT * FROM gcash_requests LIMIT 5");
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        print_r($row);
    }
}
?>
