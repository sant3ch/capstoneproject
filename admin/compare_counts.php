<?php
require __DIR__ . '/../config.php';
$p = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) as c FROM payments'))['c'];
$t = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) as c FROM transactions'))['c'];
$g = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) as c FROM gcash_requests'))['c'];
echo "Payments: $p\n";
echo "Transactions: $t\n";
echo "GCASH Requests: $g\n";
?>
