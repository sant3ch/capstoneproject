<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/error.log');

date_default_timezone_set('Asia/Manila');

$host = "localhost";
$user = "root";
$password = "";
$database = "jorishlaundry_db";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die(" Connection failed: " . mysqli_connect_error());
}

$conn->query("SET time_zone = '+08:00'");

if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY_HERE');
}
?>
