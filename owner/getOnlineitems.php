<?php
include "../config/db.php";
header('Content-Type: application/json');

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$items = [];

// Gumamit ng JOIN para makuha ang order_date mula sa orders table
$query = "SELECT oi.product_name, oi.qty, oi.subtotal, o.order_date 
          FROM order_items oi 
          INNER JOIN orders o ON oi.order_id = o.id 
          WHERE oi.order_id = $order_id";

$result = mysqli_query($conn, $query);

if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
}

echo json_encode($items);
?>