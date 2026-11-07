<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'items' => [], 'transaction' => null]);
    exit;
}

$pos_id = intval($_GET['id']);

try {
    // 1. Kuhanin muna ang POS Transaction record
    $stmt_pos = $pdo->prepare("SELECT * FROM pos_transaction WHERE id = :id LIMIT 1");
    $stmt_pos->execute([':id' => $pos_id]);
    $pos_data = $stmt_pos->fetch(PDO::FETCH_ASSOC);

    if (!$pos_data) {
        echo json_encode(['success' => false, 'items' => [], 'transaction' => null]);
        exit;
    }

    $transaction_date = $pos_data['transaction_date'] ?? ($pos_data['created_at'] ?? 'N/A');
    $items = [];

    // Option A: Kung naka-store bilang JSON sa column na 'items' o 'cart'
    $raw_json = $pos_data['items'] ?? ($pos_data['cart'] ?? '');
    
    if (!empty($raw_json)) {
        $cart_obj = is_string($raw_json) ? json_decode($raw_json, true) : $raw_json;
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
    }

    // Option B: Kung walang laman sa JSON column, i-check ang mga posibleng relational tables
    if (empty($items)) {
        // I-check sa pos_transaction_items
        $stmt_items = $pdo->prepare("SELECT * FROM pos_transaction_items WHERE pos_transaction_id = :id OR transaction_id = :id");
        $stmt_items->execute([':id' => $pos_id]);
        $rows = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        // Kung wala rin, i-check sa pos_items
        if (empty($rows)) {
            $stmt_items2 = $pdo->prepare("SELECT * FROM pos_items WHERE transaction_id = :id OR pos_id = :id");
            $stmt_items2->execute([':id' => $pos_id]);
            $rows = $stmt_items2->fetchAll(PDO::FETCH_ASSOC);
        }

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

    // Ibabalik ang parehong listahan ng items at transaction details
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