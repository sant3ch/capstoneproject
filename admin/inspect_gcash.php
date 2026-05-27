<?php
require __DIR__ . '/../config.php';
$res = mysqli_query($conn, 'DESCRIBE gcash_requests');
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
