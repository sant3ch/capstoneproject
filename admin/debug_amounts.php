<?php
session_start();
require '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo "Unauthorized access";
    exit();
}

echo "<h2>Amount Mismatch Debug Report</h2>";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";

// Query to compare amounts across tables
$debug_query = "
    SELECT
        b.id as booking_id,
        CONCAT(u.first_name, ' ', u.last_name) as customer_name,
        b.booking_date,
        b.service_type,
        b.detergent,
        t.total_amount as 'transactions_amount',
        gr.amount as 'gcash_requests_amount',
        gr.status as 'gcash_status',
        COALESCE(t.total_amount, gr.amount, 0) as 'current_displayed_amount',
        gr.id as 'latest_gcash_id'
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN transactions t ON b.id = t.booking_id
    LEFT JOIN gcash_requests gr ON b.id = gr.booking_id 
        AND gr.status IN ('approved', 'completed', 'pending')
        AND gr.id = (
            SELECT MAX(id) FROM gcash_requests 
            WHERE booking_id = b.id AND status IN ('approved', 'completed', 'pending')
        )
    WHERE b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY b.booking_date DESC
    LIMIT 20
";

$result = mysqli_query($conn, $debug_query);

if (!$result) {
    echo "<p style='color: red;'>Error: " . mysqli_error($conn) . "</p>";
    exit();
}

echo "<table border='1' cellpadding='10' style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #6f42c1; color: white;'>
    <th>Booking ID</th>
    <th>Customer</th>
    <th>Date</th>
    <th>Services</th>
    <th>Detergent</th>
    <th>Transactions Amount</th>
    <th>GCASH Amount</th>
    <th>GCASH Status</th>
    <th>Displayed Amount</th>
    <th>Match?</th>
</tr>";

while ($row = mysqli_fetch_assoc($result)) {
    $trans_amt = $row['transactions_amount'] ?? 'NULL';
    $gcash_amt = $row['gcash_requests_amount'] ?? 'NULL';
    $displayed_amt = $row['current_displayed_amount'];
    
    // Check if amounts match what should be calculated
    $mismatch = '';
    if ($trans_amt !== 'NULL' && $gcash_amt !== 'NULL') {
        if ($trans_amt != $gcash_amt) {
            $mismatch = "⚠️ MISMATCH";
        }
    }
    
    echo "<tr>";
    echo "<td>#" . $row['booking_id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
    echo "<td>" . $row['booking_date'] . "</td>";
    echo "<td>" . htmlspecialchars($row['service_type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['detergent']) . "</td>";
    echo "<td>" . ($trans_amt === 'NULL' ? '<span style="color: red;">NULL</span>' : '₱' . number_format($trans_amt, 2)) . "</td>";
    echo "<td>" . ($gcash_amt === 'NULL' ? '<span style="color: red;">NULL</span>' : '₱' . number_format($gcash_amt, 2)) . "</td>";
    echo "<td>" . htmlspecialchars($row['gcash_status'] ?? 'N/A') . "</td>";
    echo "<td><strong>₱" . number_format($displayed_amt, 2) . "</strong></td>";
    echo "<td>" . $mismatch . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Raw GCASH_REQUESTS Table (Last 10 entries)</h3>";
$gcash_check = "SELECT id, booking_id, user_id, amount, status, requested_at FROM gcash_requests ORDER BY id DESC LIMIT 10";
$gcash_result = mysqli_query($conn, $gcash_check);

echo "<table border='1' cellpadding='10' style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #6f42c1; color: white;'>
    <th>GCASH ID</th>
    <th>Booking ID</th>
    <th>User ID</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Requested At</th>
</tr>";

while ($row = mysqli_fetch_assoc($gcash_result)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['booking_id'] . "</td>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>₱" . number_format($row['amount'], 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . $row['requested_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Raw TRANSACTIONS Table (Last 10 entries)</h3>";
$trans_check = "SELECT id, booking_id, user_id, total_amount, payment_method, transaction_date FROM transactions ORDER BY id DESC LIMIT 10";
$trans_result = mysqli_query($conn, $trans_check);

echo "<table border='1' cellpadding='10' style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #6f42c1; color: white;'>
    <th>Transaction ID</th>
    <th>Booking ID</th>
    <th>User ID</th>
    <th>Total Amount</th>
    <th>Payment Method</th>
    <th>Date</th>
</tr>";

while ($row = mysqli_fetch_assoc($trans_result)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['booking_id'] . "</td>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>₱" . number_format($row['total_amount'], 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['payment_method']) . "</td>";
    echo "<td>" . $row['transaction_date'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Summary</h3>";
echo "<p>This report shows the last 20 bookings from the past 7 days.</p>";
echo "<p><strong>Issue:</strong> If there's a voucher discount applied on the frontend in booking_confirmation.php, it's NOT being saved to either the transactions or gcash_requests tables.</p>";
echo "<p><strong>Solution:</strong> The final amount (with voucher) must be saved to the transactions table when the user confirms payment.</p>";

?>
