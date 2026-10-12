<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json');

$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$items = [];

// Gumamit ng JOIN para makuha ang transaction_date mula sa pos_transaction table
$query = "SELECT pi.product_name, pi.qty, pi.subtotal, pt.transaction_date 
          FROM pos_transaction_items pi 
          INNER JOIN pos_transaction pt ON pi.transaction_id = pt.id 
          WHERE pi.transaction_id = $transaction_id";

$result = mysqli_query($conn, $query);

if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
}

echo json_encode($items);
?>