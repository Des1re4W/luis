<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No ID provided', 'items' => [], 'transaction' => null]);
    exit;
}

$pos_id = intval($_GET['id']);

try {
    // 1. Kuhanin muna ang POS Transaction details
    $stmt_pos = $pdo->prepare("SELECT * FROM pos_transaction WHERE id = :id LIMIT 1");
    $stmt_pos->execute([':id' => $pos_id]);
    $pos_data = $stmt_pos->fetch(PDO::FETCH_ASSOC);

    if (!$pos_data) {
        echo json_encode(['success' => false, 'items' => [], 'transaction' => null]);
        exit;
    }

    $transaction_date = $pos_data['transaction_date'] ?? ($pos_data['created_at'] ?? 'N/A');
    $items = [];

    // 2. Kuhanin ang items mula sa items/cart JSON column kung mayroon
    if (!empty($pos_data['items']) || !empty($pos_data['cart'])) {
        $cart_raw = !empty($pos_data['items']) ? $pos_data['items'] : $pos_data['cart'];
        $cart_obj = is_string($cart_raw) ? json_decode($cart_raw, true) : $cart_raw;
        
        if (is_array($cart_obj)) {
            foreach ($cart_obj as $item) {
                $p_name = $item['product_name'] ?? ($item['name'] ?? 'Item');
                $p_price = floatval($item['price'] ?? 0);
                $p_qty = intval($item['qty'] ?? 1);
                $items[] = [
                    'product_name'     => $p_name,
                    'price'            => $p_price,
                    'qty'              => $p_qty,
                    'subtotal'         => $p_price * $p_qty,
                    'transaction_date' => $transaction_date
                ];
            }
        }
    } else {
        // Kung nakahiwalay ang items table (halimbawa: pos_items / pos_transaction_items)
        $stmt_items = $pdo->prepare("SELECT * FROM pos_items WHERE transaction_id = :id");
        $stmt_items->execute([':id' => $pos_id]);
        $rows = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $p_name = $row['product_name'] ?? ($row['name'] ?? 'Item');
            $p_price = floatval($row['price'] ?? 0);
            $p_qty = intval($row['qty'] ?? 1);
            $items[] = [
                'product_name'     => $p_name,
                'price'            => $p_price,
                'qty'              => $p_qty,
                'subtotal'         => $p_price * $p_qty,
                'transaction_date' => $transaction_date
            ];
        }
    }

    // Ibabalik natin ang parehong items at ang buong transaction data
    echo json_encode([
        'success'     => true,
        'items'       => $items,
        'transaction' => $pos_data
    ]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'items' => [], 'transaction' => null, 'error' => $e->getMessage()]);
    exit;
}
?>