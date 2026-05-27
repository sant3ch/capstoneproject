<?php
require __DIR__ . '/../config.php';
$res = mysqli_query($conn, 'SHOW TABLES');
while($row = mysqli_fetch_array($res)) {
    echo $row[0] . PHP_EOL;
}
?>
