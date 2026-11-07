<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$pos_id = intval($_GET['id'] ?? 0);

try {
    // Kunin ang mismong transaction row
    $stmt = $pdo->prepare("SELECT * FROM pos_transaction WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $pos_id]);
    $pos_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Kunin din mula sa pos_transaction_items o pos_items kung mayroon
    $stmt_items = $pdo->prepare("SELECT * FROM pos_transaction_items WHERE pos_transaction_id = :id OR transaction_id = :id");
    $stmt_items->execute([':id' => $pos_id]);
    $pos_items_rows = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'debug_pos_transaction_row' => $pos_data,
        'debug_pos_transaction_items' => $pos_items_rows
    ], JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>