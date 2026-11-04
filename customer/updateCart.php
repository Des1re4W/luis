<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$id = $_GET['id'] ?? '';
$action = $_GET['action'] ?? '';

if (!isset($_SESSION['cart'][$id])) {
    header("Location: cart.php");
    exit();
}

$item = &$_SESSION['cart'][$id];

// Get available stock using PDO
if (!empty($item['variant_id'])) {

    $variant_id = (int)$item['variant_id'];
    $product_id = (int)$item['id'];

    $stmt = $pdo->prepare("
        SELECT stock 
        FROM product_variants 
        WHERE id = ? AND product_id = ?
    ");

    $stmt->execute([$variant_id, $product_id]);

} else {

    $product_id = (int)$item['id'];

    $stmt = $pdo->prepare("
        SELECT stock 
        FROM products 
        WHERE id = ?
    ");

    $stmt->execute([$product_id]);
}

$row = $stmt->fetch(PDO::FETCH_ASSOC);
$available_stock = $row ? (int)$row['stock'] : 0;

// Plus action
if ($action === 'plus') {

    if ($item['qty'] < $available_stock) {

        if (($item['qty'] + 1) <= $available_stock) {
            $item['qty'] += 1;
        } else {
            $item['qty'] = $available_stock;
        }

    } else {
        $_SESSION['message'] = "Cannot add more items. Only {$available_stock} item(s) available in stock.";
    }
}

// Minus action
if ($action === 'minus') {

    if ($item['qty'] > 1) {
        $item['qty']--;
    } else {
        unset($_SESSION['cart'][$id]);
    }
}

header("Location: cart.php");
exit();
?>
