<?php
session_start();
require '../config.php';
require_once '../includes/booking-functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid transaction ID.");
}

$transaction_id = intval($_GET['id']);
$source = $_GET['source'] ?? 'transaction';

if ($source === 'payment_request') {
    // If from gcash_requests, we need to map the fields to match the transaction structure
    $query = "SELECT gr.id, CONCAT(u.first_name, ' ', u.last_name) as customer_name, 
                     gr.amount as total_amount, gr.payment_method,
                     COALESCE(gr.payment_date, gr.approved_at, gr.requested_at) as transaction_date,
                     b.id as booking_id, b.booking_date, b.time_slot, b.machine_count, b.service_type, b.detergent, b.request_service, b.points_claimed, b.user_id
              FROM gcash_requests gr
              JOIN bookings b ON gr.booking_id = b.id
              LEFT JOIN users u ON gr.user_id = u.id
              WHERE gr.id = $transaction_id LIMIT 1";
} else {
    // Default to transactions table
    $query = "SELECT t.*, b.id as booking_id, b.booking_date, b.time_slot, b.machine_count, b.service_type, b.detergent, b.request_service, b.points_claimed, b.user_id
              FROM transactions t
              LEFT JOIN bookings b ON t.booking_id = b.id
              WHERE t.id = $transaction_id LIMIT 1";
}

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die("Transaction not found. (Source: $source, ID: $transaction_id)");
}

$transaction = mysqli_fetch_assoc($result);

// Get user details for phone and address
$user_query = "SELECT phone, address FROM users WHERE id = " . intval($transaction['user_id']);
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result) ?? ['phone' => '', 'address' => ''];

// Get actual payment method from gcash_requests if exists
$payment_method_query = "SELECT payment_method FROM gcash_requests WHERE booking_id = " . intval($transaction['booking_id']) . " AND status = 'completed' LIMIT 1";
$payment_method_result = mysqli_query($conn, $payment_method_query);
$payment_from_gcash = mysqli_fetch_assoc($payment_method_result);
$actual_payment_method = $payment_from_gcash['payment_method'] ?? $transaction['payment_method'];

// Use the same calculation as booking_confirmation.php
// Build booking array for the function
$booking_for_calc = [
    'id' => $transaction['booking_id'],
    'service_type' => $transaction['service_type'],
    'detergent' => $transaction['detergent']
];

// Calculate using the same function
$grand_total = calculateBookingAmountFromDB($booking_for_calc);

// Format dates/times - alphabetic format
$bookingDate = date("F j, Y", strtotime($transaction['booking_date']));
$transactionTime = date("g:i A", strtotime($transaction['transaction_date']));
$transactionDate = date("F j, Y", strtotime($transaction['transaction_date']));
$fullTransactionDate = $transactionDate . " at " . $transactionTime;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Transaction #<?= $transaction_id ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.5;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 20px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            margin-right: 20px;
            font-weight: bold;
        }
        
        .logo-img {
            width: 80px;
            height: 80px;
            margin-right: 20px;
            border-radius: 50%;
            object-fit: contain;
        }
        
        .header-info h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .header-info p {
            font-size: 12px;
            color: #666;
            margin: 2px 0;
        }
        
        .receipt-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-left: 4px solid #6366f1;
        }
        
        .meta-item {
            flex: 1;
        }
        
        .meta-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #999;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .meta-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }
        
        .customer-info {
            margin-bottom: 30px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
            align-items: center;
        }
        
        .info-label {
            width: 120px;
            font-weight: bold;
            color: #555;
            font-size: 13px;
        }
        
        .info-value {
            flex: 1;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            color: #333;
            font-size: 13px;
        }
        
        .services-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            font-size: 13px;
        }
        
        .services-table thead {
            background: #f0f0f0;
            border-top: 2px solid #6366f1;
            border-bottom: 2px solid #6366f1;
        }
        
        .services-table th {
            padding: 12px 10px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-right: 1px solid #ccc;
        }
        
        .services-table th:last-child {
            border-right: none;
        }
        
        .services-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            border-right: 1px solid #eee;
        }
        
        .services-table td:last-child {
            border-right: none;
        }
        
        .services-table tbody tr:hover {
            background: #fafafa;
        }
        
        .amount-cell {
            text-align: right;
            font-weight: 500;
        }
        
        .units-cell {
            text-align: center;
        }
        
        .cost-cell {
            text-align: right;
        }
        
        .total-row {
            background: #f0f0f0;
            font-weight: bold;
            border-top: 2px solid #6366f1;
            border-bottom: 2px solid #6366f1;
        }
        
        .total-section {
            display: flex;
            justify-content: flex-end;
            margin: 20px 0 30px 0;
        }
        
        .total-box {
            width: 300px;
        }
        
        .total-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 15px;
            font-size: 13px;
            border: 1px solid #ddd;
        }
        
        .total-item-label {
            font-weight: 600;
            color: #555;
        }
        
        .total-item-value {
            text-align: right;
            color: #333;
        }
        
        .grand-total {
            background: #6366f1;
            color: white;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
        }
        
        .payment-info {
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border-left: 4px solid #6366f1;
            font-size: 13px;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .payment-label {
            font-weight: bold;
            color: #555;
        }
        
        .payment-value {
            color: #333;
        }
        
        .payment-method-badge {
            display: inline-block;
            background: #6366f1;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .payment-method-badge.cash {
            background: #6366f1;
        }
        
        .payment-method-badge.cod {
            background: #6366f1;
        }
        
        .receipt-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px dashed #ccc;
            text-align: center;
            font-size: 11px;
            color: #666;
        }
        
        .footer-contact {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            font-size: 11px;
            color: #555;
        }
        
        .footer-item {
            text-align: center;
        }
        
        .footer-item strong {
            display: block;
            margin-bottom: 3px;
            color: #333;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }
        
        .btn-custom {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-print {
            background: #6366f1;
            color: white;
        }
        
        .btn-print:hover {
            background: #4338ca;
        }
        
        .btn-close {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-close:hover {
            background: #d0d0d0;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .receipt-container {
                box-shadow: none;
                max-width: 100%;
            }
            
            .button-group {
                display: none !important;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Header with Logo -->
        <div class="receipt-header">
            <img src="../assets/images/logo.png" alt="Logo" class="logo-img">
            <div class="header-info">
                <h1>Jorish Express Laundry</h1>
                <p>Professional Laundry Services</p>
                <p>Quality Care for Your Clothes</p>
            </div>
        </div>
        
        <!-- Receipt Meta Info -->
        <div class="receipt-meta">
            <div class="meta-item">
                <div class="meta-label">Receipt No.</div>
                <div class="meta-value"><?= $transaction['id'] ?></div>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="customer-info">
            <div class="info-row">
                <div class="info-label">Name:</div>
                <div class="info-value"><?= htmlspecialchars($transaction['customer_name']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone:</div>
                <div class="info-value"><?= htmlspecialchars($user_data['phone'] ?? '_________________________') ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Address:</div>
                <div class="info-value"><?= htmlspecialchars($user_data['address'] ?? '_________________________') ?></div>
            </div>
        </div>
        
        <!-- Services Table -->
        <table class="services-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Type of Service</th>
                    <th style="width: 20%;">Cost of Service</th>
                    <th style="width: 20%;">No. of Machines used</th>
                    <th style="width: 20%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Parse and display each service
                $services_raw = array_map('trim', explode(',', $transaction['service_type']));
                $total_service_cost = 0;
                
                foreach ($services_raw as $service_item) {
                    $service_name_display = preg_replace('/^\d+:\s*/', '', $service_item);
                    // Get price from services table
                    $svc_q = "SELECT price FROM services WHERE service_name = '" . mysqli_real_escape_string($conn, $service_name_display) . "' LIMIT 1";
                    $svc_r = mysqli_query($conn, $svc_q);
                    $svc_d = mysqli_fetch_assoc($svc_r);
                    $svc_price = $svc_d['price'] ?? 0;
                    $service_subtotal = $svc_price; // Service price is already complete, no machine multiplication
                    $total_service_cost += $service_subtotal;
                ?>
                <tr>
                    <td><?= htmlspecialchars($service_name_display) ?></td>
                    <td class="cost-cell">₱<?= number_format($service_subtotal, 2) ?></td>
                    <td class="units-cell"><?= $transaction['machine_count'] ?></td>
                    <td class="amount-cell">₱<?= number_format($service_subtotal, 2) ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        
        <!-- Laundry Items Table -->
        <table class="services-table" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th style="width: 50%;">Laundry Item</th>
                    <th style="width: 25%;">Cost of Item</th>
                    <th style="width: 25%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Display detergents with individual prices
                if ($transaction['detergent'] && $transaction['detergent'] !== 'N/A') {
                    $detergents = array_map('trim', explode(',', $transaction['detergent']));
                    foreach ($detergents as $detergent_entry) {
                        if (empty($detergent_entry) || strpos(strtolower($detergent_entry), 'bring') !== false) {
                            continue;
                        }
                        
                        // Parse "Qty x ItemName" format
                        $qty = 1;
                        $item_name = $detergent_entry;
                        if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $detergent_entry, $matches)) {
                            $qty = intval($matches[1]);
                            $item_name = trim($matches[2]);
                        }
                        
                        // Get price from inventory
                        $inv_q = "SELECT price FROM inventory WHERE item_name = '" . mysqli_real_escape_string($conn, $item_name) . "' LIMIT 1";
                        $inv_r = mysqli_query($conn, $inv_q);
                        $inv_d = mysqli_fetch_assoc($inv_r);
                        $item_price = $inv_d['price'] ?? 16.00; // Default detergent price
                        $item_subtotal = $item_price * $qty;
                ?>
                <tr>
                    <td><?= $qty ?>x <?= htmlspecialchars($item_name) ?></td>
                    <td class="cost-cell">₱<?= number_format($item_price, 2) ?></td>
                    <td class="amount-cell">₱<?= number_format($item_subtotal, 2) ?></td>
                </tr>
                <?php } } ?>
            </tbody>
        </table>
        
        <!-- Total -->
        <div style="text-align: right; margin-top: 15px; padding-right: 20px; font-size: 16px; font-weight: bold;">
            TOTAL: ₱<?= number_format($grand_total, 2) ?>
        </div>
        
        <!-- Payment Information -->
        <div class="payment-info">
            <div class="payment-row">
                <span class="payment-label">Payment Method:</span>
                <span class="payment-value">
                    <?php
                    $method = $actual_payment_method;
                    $badgeClass = $method === 'Cash' ? 'cash' : ($method === 'GCASH' ? '' : 'cod');
                    ?>
                    <span class="payment-method-badge <?= $badgeClass ?>">
                        <?= htmlspecialchars($method) ?>
                    </span>
                </span>
            </div>
            <div class="payment-row">
                <span class="payment-label">Time Slot:</span>
                <span class="payment-value"><?= htmlspecialchars($transaction['time_slot'] ?? 'N/A') ?></span>
            </div>
            <div class="payment-row">
                <span class="payment-label">Transaction Date & Time:</span>
                <span class="payment-value"><?= $fullTransactionDate ?></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="receipt-footer">
            <p>Thank you for choosing Jorish Express Laundry!</p>
            <p>Please keep this receipt for your records.</p>
            
            <div class="footer-contact">
                <div class="footer-item">
                    <strong>CALL</strong>
                    <span>0928 520 3517<br/>046 5373 125</span>
                </div>
                <div class="footer-item">
                    <strong>VISIT</strong>
                    <span>Queen's Row East<br/>Bacoor, Cavite</span>
                </div>
                <div class="footer-item">
                    <strong>EMAIL</strong>
                    <span>info@jorishlaundry.com</span>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="button-group no-print">
            <button class="btn-custom btn-print" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Print Receipt
            </button>
            <button class="btn-custom btn-close" onclick="window.close()">
                <i class="fas fa-times me-2"></i> Close
            </button>
        </div>
    </div>

    <script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
    <script>
        // Remove auto-print on load - user can click Print button manually
    </script>
</body>
</html>
