<?php
require 'config.php';
$res = $conn->query('DESCRIBE claimed_rewards');
echo "<pre>";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
?>
