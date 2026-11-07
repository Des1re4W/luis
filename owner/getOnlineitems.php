<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'items' => [], 'order' => null]);
    exit;
}

$order_id = intval($_GET['id']);

try {
    // 1. Kunin ang Online Order record
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'items' => [], 'order' => null]);
        exit;
    }

    $order_date = $order['order_date'] ?? ($order['created_at'] ?? 'N/A');
    $items = [];

    // Helper function para sa safe JSON decoding
    function parseOrderJsonItems($raw) {
        if (empty($raw)) return [];
        if (is_array($raw)) return $raw;
        
        $decoded = json_decode($raw, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        return is_array($decoded) ? $decoded : [];
    }

    // 2. I-check ang JSON columns (cart / items / order_items)
    $cart_obj = [];
    if (!empty($order['cart'])) {
        $cart_obj = parseOrderJsonItems($order['cart']);
    } elseif (!empty($order['items'])) {
        $cart_obj = parseOrderJsonItems($order['items']);
    } elseif (!empty($order['order_items'])) {
        $cart_obj = parseOrderJsonItems($order['order_items']);
    }

    if (!empty($cart_obj) && is_array($cart_obj)) {
        foreach ($cart_obj as $item) {
            $p_name = $item['product_name'] ?? ($item['name'] ?? 'Item');
            $p_price = floatval($item['price'] ?? 0);
            $p_qty = intval($item['qty'] ?? ($item['quantity'] ?? 1));
            $items[] = [
                'product_name' => $p_name,
                'price'        => $p_price,
                'qty'          => $p_qty,
                'subtotal'     => $p_price * $p_qty,
                'order_date'   => $order_date
            ];
        }
    }

    // 3. Fallback: Kung wala sa JSON, mag-query sa order_items table
    if (empty($items)) {
        try {
            $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :id");
            $stmt_items->execute([':id' => $order_id]);
            $rows = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $p_name = $row['product_name'] ?? ($row['name'] ?? 'Item');
                $p_price = floatval($row['price'] ?? 0);
                $p_qty = intval($row['qty'] ?? ($row['quantity'] ?? 1));
                $items[] = [
                    'product_name' => $p_name,
                    'price'        => $p_price,
                    'qty'          => $p_qty,
                    'subtotal'     => $p_price * $p_qty,
                    'order_date'   => $order_date
                ];
            }
        } catch (Exception $e) {
            // Huwag mag-throw ng error kapag walang order_items table
        }
    }

    echo json_encode([
        'success' => true,
        'items'   => $items,
        'order'   => $order
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'items' => [], 'order' => null, 'error' => $e->getMessage()]);
    exit;
}
?>