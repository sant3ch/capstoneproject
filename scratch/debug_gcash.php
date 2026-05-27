<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../config.php';

echo "<h2>Test queries AFTER fix</h2><pre>";

$q = mysqli_query($conn, "SELECT IFNULL(SUM(amount), 0) AS total FROM gcash_requests WHERE DATE(COALESCE(approved_at, payment_date)) = CURDATE() AND status IN ('approved', 'completed')");
$r = mysqli_fetch_assoc($q);
echo "Today's sales (COALESCE): " . $r['total'] . "\n";

$q = mysqli_query($conn, "SELECT IFNULL(SUM(amount), 0) AS total FROM gcash_requests WHERE MONTH(COALESCE(approved_at, payment_date)) = MONTH(NOW()) AND YEAR(COALESCE(approved_at, payment_date)) = YEAR(NOW()) AND status IN ('approved', 'completed')");
$r = mysqli_fetch_assoc($q);
echo "Monthly sales (COALESCE): " . $r['total'] . "\n";

$q = mysqli_query($conn, "SELECT IFNULL(SUM(amount), 0) AS total FROM gcash_requests WHERE status IN ('approved', 'completed')");
$r = mysqli_fetch_assoc($q);
echo "Total sales (no date filter): " . $r['total'] . "\n";

$q = mysqli_query($conn, "SELECT DATE(COALESCE(approved_at, payment_date)) AS sale_date, SUM(amount) AS total FROM gcash_requests WHERE DATE(COALESCE(approved_at, payment_date)) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE() AND status IN ('approved', 'completed') GROUP BY DATE(COALESCE(approved_at, payment_date))");
echo "Weekly data rows:\n";
while ($r = mysqli_fetch_assoc($q)) { echo "  " . json_encode($r) . "\n"; }

$q = mysqli_query($conn, "SELECT DATE_FORMAT(COALESCE(approved_at, payment_date), '%b %d') AS date, SUM(amount) AS total FROM gcash_requests WHERE MONTH(COALESCE(approved_at, payment_date)) = MONTH(NOW()) AND YEAR(COALESCE(approved_at, payment_date)) = YEAR(NOW()) AND status IN ('approved', 'completed') GROUP BY DATE(COALESCE(approved_at, payment_date)), DATE_FORMAT(COALESCE(approved_at, payment_date), '%b %d')");
echo "Monthly chart data rows:\n";
while ($r = mysqli_fetch_assoc($q)) { echo "  " . json_encode($r) . "\n"; }
echo "</pre>";
echo "<p><strong>All values above should be non-zero if the fix works.</strong></p>";
