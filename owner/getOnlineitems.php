<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode([]);
    exit;
}

$order_id = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode([]);
        exit;
    }

    $items = [];
    $cart_raw = $order['cart'] ?? '';
    $cart_obj = is_string($cart_raw) ? json_decode($cart_raw, true) : $cart_raw;

    if (is_array($cart_obj)) {
        foreach ($cart_obj as $item) {
            $p_name = $item['name'] ?? ($item['product_name'] ?? 'Item');
            $p_price = floatval($item['price'] ?? 0);
            $p_qty = intval($item['qty'] ?? 1);
            $items[] = [
                'product_name' => $p_name,
                'price' => $p_price,
                'qty' => $p_qty,
                'subtotal' => $p_price * $p_qty,
                'order_date' => $order['order_date'] ?? 'N/A'
            ];
        }
    }

    echo json_encode($items);
    exit;
} catch (Exception $e) {
    echo json_encode([]);
    exit;
}