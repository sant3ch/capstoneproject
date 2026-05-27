<?php
// inventory_operations.php - SIMPLER VERSION

// Fetch all inventory items
$result_all = mysqli_query($conn, "SELECT * FROM inventory ORDER BY item_type, item_name");

// Fetch detergents
$result_detergents = mysqli_query($conn, "SELECT * FROM inventory WHERE item_type = 'detergent' ORDER BY item_name");

// Fetch fabric conditioners  
$result_fabcon = mysqli_query($conn, "SELECT * FROM inventory WHERE item_type = 'fabric_conditioner' ORDER BY item_name");

if (!$result_all) {
    die("Query Failed: " . mysqli_error($conn));
}

// Get counts
$total_items = mysqli_num_rows($result_all);
$detergent_items = mysqli_num_rows($result_detergents);
$fabcon_items = mysqli_num_rows($result_fabcon);

// Get stock status counts
$good_stock_items = mysqli_num_rows(mysqli_query($conn, 
    "SELECT * FROM inventory WHERE stock_quantity > 5"
));
$low_stock_items = mysqli_num_rows(mysqli_query($conn, 
    "SELECT * FROM inventory WHERE stock_quantity > 0 AND stock_quantity <= 5"
));
$out_of_stock_items = mysqli_num_rows(mysqli_query($conn, 
    "SELECT * FROM inventory WHERE stock_quantity = 0"
));
?>