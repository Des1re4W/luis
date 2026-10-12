<?php
require_once __DIR__ . '/../config/db.php';

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Kunin ang items ng POS transaction
    $query = "SELECT * FROM pos_transaction_items WHERE transaction_id = '$id'";
    $result = mysqli_query($conn, $query);
    
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($items);
}
?>