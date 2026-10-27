<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid or missing product ID.'
        ]);
        exit;
    }

    $product_id = (int) $_GET['id'];

    $product_query = "
        SELECT *
        FROM public.products
        WHERE id = :id
    ";

    $stmt = $pdo->prepare($product_query);
    $stmt->execute(['id' => $product_id]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Product not found.'
        ]);
        exit;
    }

    $variants_query = "
        SELECT *
        FROM public.product_variants
        WHERE product_id = :product_id
        ORDER BY id ASC
    ";

    $stmt = $pdo->prepare($variants_query);
    $stmt->execute(['product_id' => $product_id]);

    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'product' => $product,
        'variants' => $variants
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
    error_log('getProductDetails.php error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'error' => 'Unable to load product details. Please check the server logs.'
    ]);
}

exit;
