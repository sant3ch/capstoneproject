<?php
session_start();
require '../config.php';

$payments_count = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM payments"));
$transactions_count = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM transactions"));
$completed_requests_count = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM gcash_requests WHERE status = 'completed'"));
$total = $payments_count + $transactions_count + $completed_requests_count;

echo "<h2>Transaction Count Breakdown</h2>";
echo "<p><strong>Payments table:</strong> " . $payments_count . " rows</p>";
echo "<p><strong>Transactions table:</strong> " . $transactions_count . " rows</p>";
echo "<p><strong>GCASH Requests (completed status):</strong> " . $completed_requests_count . " rows</p>";
echo "<hr>";
echo "<p><strong>Total: " . $total . " transactions</strong></p>";
?>
