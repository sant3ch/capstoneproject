<?php
require 'config.php';
$res = $conn->query("SELECT * FROM gcash_requests LIMIT 5");
while($row = $res->fetch_assoc()) print_r($row);
