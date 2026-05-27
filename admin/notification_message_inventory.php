<?php
//Author Bryce
require '../config.php';

$query = "SELECT item_name, stock_quantity FROM inventory WHERE stock_quantity < 5";
$result = mysqli_query($conn, $query);

$lowStockItems = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $lowStockItems[] = $row;
    }
}
?>

<div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body">
        <h5 class="card-title fw-bold text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Inventory Notification</h5>
        <hr>
        <?php if (!empty($lowStockItems)): ?>
            <ul class="mb-0 ps-3">
                <?php foreach ($lowStockItems as $item): ?>
                    <li>
                        <strong><?= htmlspecialchars($item['item_name']) ?></strong> is low (only <?= intval($item['stock_quantity']) ?> left)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="text-success d-flex align-items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <span>All inventory levels are healthy. No low-stock items! ✅</span>
            </div>
        <?php endif; ?>
    </div>
</div>
