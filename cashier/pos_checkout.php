<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json");

if (!isset($_SESSION['role']) || $_SESSION['role'] != "cashier") {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['cart'])) {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit();
}

$cart = $data['cart'];
$total = (float)$data['total'];
$paid = (float)$data['paid'];
$change = (float)$data['change'];
$cashier = $_SESSION['username'] ?? 'Cashier';
$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$role = $_SESSION['role'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

try {
    // Simulan ang Database Transaction para ligtas ang inventory at sales records
    $pdo->beginTransaction();

    // 1. I-save ang transaction sa pos_transaction table gamit ang PDO prepared statement
    $stmt = $pdo->prepare("INSERT INTO pos_transaction (cashier_name, total_amount, amount_paid, change_amount) VALUES (?, ?, ?, ?)");
    $stmt->execute([$cashier, $total, $paid, $change]);
    $transaction_id = $pdo->lastInsertId();

    // 2. I-loop ang cart para sa bawas stock at pag-save ng items
    foreach ($cart as $item) {
        $raw_id = $item['id'];
        $type = substr($raw_id, 0, 2);
        $id = (int)substr($raw_id, 2);
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];
        $subtotal = $qty * $price;
        $name = $item['name'];

        if ($type == 'v_') {
            // Bawas stock sa product_variants
            $updateStock = $pdo->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");
            $updateStock->execute([$qty, $id]);

            // Insert POS transaction items para sa variant
            $insertItem = $pdo->prepare("INSERT INTO pos_transaction_items (transaction_id, variant_id, product_name, price, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $insertItem->execute([$transaction_id, $id, $name, $price, $qty, $subtotal]);
        } else {
            // Bawas stock sa main products
            $updateStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $updateStock->execute([$qty, $id]);

            // Insert POS transaction items para sa main product
            $insertItem = $pdo->prepare("INSERT INTO pos_transaction_items (transaction_id, product_id, product_name, price, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $insertItem->execute([$transaction_id, $id, $name, $price, $qty, $subtotal]);
        }
    }

    // 3. I-log ang audit trail (isinama ang user_id para hindi mag-error sa NOT NULL constraint)
    $action = "POS Checkout";
    $details = "Processed walk-in transaction #" . $transaction_id . ". Total Amount: ₱" . number_format($total, 2) . ", Paid: ₱" . number_format($paid, 2) . ", Change: ₱" . number_format($change, 2);
    
    $audit_stmt = $pdo->prepare("INSERT INTO audit_log (user_id, username, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $audit_stmt->execute([$user_id > 0 ? $user_id : null, $cashier, $role, $action, $details, $ip_address]);

    // Kung walang naganap na error, i-commit ang lahat ng pagbabago sa database
    $pdo->commit();

    echo json_encode(["status" => "success", "transaction_id" => $transaction_id]);

} catch (Exception $e) {
    // Kung sakaling magka-error, ibalik sa dati ang database state
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>