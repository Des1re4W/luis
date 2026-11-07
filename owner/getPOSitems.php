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
    // 1. Kuhanin ang buong POS Transaction record
    $stmt_pos = $pdo->prepare("SELECT * FROM pos_transaction WHERE id = :id LIMIT 1");
    $stmt_pos->execute([':id' => $pos_id]);
    $pos_data = $stmt_pos->fetch(PDO::FETCH_ASSOC);

    if (!$pos_data) {
        echo json_encode(['success' => false, 'items' => [], 'transaction' => null]);
        exit;
    }

    $transaction_date = $pos_data['transaction_date'] ?? ($pos_data['created_at'] ?? 'N/A');
    $items = [];

    // Helper function para mag-parse kahit double-encoded ang JSON string
    function parseJsonItems($raw) {
        if (empty($raw)) return [];
        if (is_array($raw)) return $raw;
        
        $decoded = json_decode($raw, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        return is_array($decoded) ? $decoded : [];
    }

    // 2. I-check muna kung naka-store sa JSON column (items / cart / order_items)
    $cart_obj = [];
    if (!empty($pos_data['items'])) {
        $cart_obj = parseJsonItems($pos_data['items']);
    } elseif (!empty($pos_data['cart'])) {
        $cart_obj = parseJsonItems($pos_data['cart']);
    } elseif (!empty($pos_data['order_items'])) {
        $cart_obj = parseJsonItems($pos_data['order_items']);
    } elseif (!empty($pos_data['details'])) {
        $cart_obj = parseJsonItems($pos_data['details']);
    }

    if (!empty($cart_obj) && is_array($cart_obj)) {
        foreach ($cart_obj as $item) {
            $p_name = $item['product_name'] ?? ($item['name'] ?? ($item['title'] ?? 'Item'));
            $p_price = floatval($item['price'] ?? 0);
            $p_qty = intval($item['qty'] ?? ($item['quantity'] ?? 1));
            $items[] = [
                'product_name'     => $p_name,
                'price'            => $p_price,
                'qty'              => $p_qty,
                'subtotal'         => $p_price * $p_qty,
                'transaction_date' => $transaction_date
            ];
        }
    }

    // 3. Fallback Query kung may relational tables (Inayos ang Column Names para sa PostgreSQL)
    if (empty($items)) {
        // Subukan muna sa pos_items gamit ang transaction_id o pos_id
        try {
            $stmt_items = $pdo->prepare("SELECT * FROM pos_items WHERE transaction_id = :id OR pos_id = :id");
            $stmt_items->execute([':id' => $pos_id]);
            $rows = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $rows = [];
        }

        // Kung wala sa pos_items, subukan sa pos_transaction_items gamit ang transaction_id
        if (empty($rows)) {
            try {
                $stmt_items2 = $pdo->prepare("SELECT * FROM pos_transaction_items WHERE transaction_id = :id");
                $stmt_items2->execute([':id' => $pos_id]);
                $rows = $stmt_items2->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $rows = [];
            }
        }

        foreach ($rows as $row) {
            $p_name = $row['product_name'] ?? ($row['name'] ?? 'Item');
            $p_price = floatval($row['price'] ?? 0);
            $p_qty = intval($row['qty'] ?? ($row['quantity'] ?? 1));
            $items[] = [
                'product_name'     => $p_name,
                'price'            => $p_price,
                'qty'              => $p_qty,
                'subtotal'         => $p_price * $p_qty,
                'transaction_date' => $transaction_date
            ];
        }
    }

    // Ibabalik ang nalikom na items at transaction data
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