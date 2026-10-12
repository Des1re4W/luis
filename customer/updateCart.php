<?php
session_start();
include "../config/db.php";

$id = $_GET['id'] ?? '';
$action = $_GET['action'] ?? '';

if (!isset($_SESSION['cart'][$id])) {
    header("Location: cart.php");
    exit();
}

$item = &$_SESSION['cart'][$id];

// Alamin ang tamang stock mula sa database
if (!empty($item['variant_id'])) {
    $variant_id = mysqli_real_escape_string($conn, $item['variant_id']);
    $product_id = (int)$item['id'];
    $query = mysqli_query($conn, "SELECT stock FROM product_variants WHERE id = '$variant_id' AND product_id = $product_id");
} else {
    $product_id = (int)$item['id'];
    $query = mysqli_query($conn, "SELECT stock FROM products WHERE id = $product_id");
}

$row = mysqli_fetch_assoc($query);
$available_stock = $row ? (int)$row['stock'] : 0;

// FEATURE 2: Kapag plus button, double (+2) ang dagdag
if ($action === 'plus') {
    if ($item['qty'] < $available_stock) {
        // Tinitiyak na hindi lalampas sa available stock kung magdadagdag ng 2
        if (($item['qty'] + 2) <= $available_stock) {
            $item['qty'] += 1;
        } else {
            // Kung may space pa para sa isa na lang, ibigay ang max stock
            $item['qty'] = $available_stock;
            $_SESSION['message'] = "Limit reached. Maximum stock applied.";
        }
    } else {
        $_SESSION['message'] = "Cannot add more items. Only {$available_stock} item(s) available in stock.";
    }
}

// Minus action (Bawas 1)
if ($action === 'minus') {
    if ($item['qty'] > 1) {
        $item['qty']--;
    } else {
        // Kung 1 na lang at nagbawas pa, automatic remove sa cart
        unset($_SESSION['cart'][$id]);
    }
}

header("Location: cart.php");
exit();
?>