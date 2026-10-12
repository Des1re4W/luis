<?php
include "../config/db.php";

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Product ID is missing']);
    exit();
}

$product_id = intval($_GET['id']);
$product_query = "SELECT * FROM products WHERE id = '$product_id'";
$product_result = mysqli_query($conn, $product_query);
$product = mysqli_fetch_assoc($product_result);

if (!$product) {
    echo json_encode(['error' => 'Product not found']);
    exit();
}

$variants_query = "SELECT * FROM product_variants WHERE product_id = '$product_id' ORDER BY id ASC";
$variants_result = mysqli_query($conn, $variants_query);

$variants = [];
while ($v = mysqli_fetch_assoc($variants_result)) {
    $variants[] = $v;
}

echo json_encode([
    'product' => $product,
    'variants' => $variants
]);
exit();
?>