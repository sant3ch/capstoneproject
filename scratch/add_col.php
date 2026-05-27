<?php
require 'config.php';
$conn->query("ALTER TABLE claimed_rewards ADD COLUMN is_read_by_user TINYINT(1) DEFAULT 0");
echo "Column added successfully or already exists.";
?>
