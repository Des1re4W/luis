
<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $key = $_POST['key'] ?? '';
    $qty = max(1, (int)($_POST['qty'] ?? 1));

    if (isset($_SESSION['cart'][$key])) {

        $item = &$_SESSION['cart'][$key];
        $product_id = (int)$item['id'];

        if (!empty($item['variant_id'])) {

            $variant_id = (int)$item['variant_id'];

            $stmt = $pdo->prepare("
                SELECT stock FROM product_variants
                WHERE id = ? AND product_id = ?
            ");

            $stmt->execute([$variant_id, $product_id]);

        } else {

            $stmt = $pdo->prepare("
                SELECT stock FROM products
                WHERE id = ?
            ");

            $stmt->execute([$product_id]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $available_stock = $row ? (int)$row['stock'] : 0;

        if ($available_stock > 0) {
            $item['qty'] = min($qty, $available_stock);
        } else {
            unset($_SESSION['cart'][$key]);
            $_SESSION['message'] = "This item is currently out of stock.";
        }

        if ($qty > $available_stock && $available_stock > 0) {
            $_SESSION['message'] = "Quantity adjusted to available stock: {$available_stock}.";
        }
    }
}

header("Location: cart.php");
exit();
?>
