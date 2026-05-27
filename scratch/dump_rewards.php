<?php
require 'config.php';
$res = $conn->query("SELECT id, reward_name, status FROM claimed_rewards");
echo "<pre>";
while($row = $res->fetch_assoc()) {
    var_dump($row);
}
echo "</pre>";
?>
