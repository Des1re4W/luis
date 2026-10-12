<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = $_POST['key'] ?? '';
    $qty = (int)($_POST['qty'] ?? 1);
    
    if (isset($_SESSION['cart'][$key])) {
        // Siguraduhing hindi bababa sa 1 ang quantity
        $_SESSION['cart'][$key]['qty'] = ($qty < 1) ? 1 : $qty;
    }
}
header("Location: cart.php");
exit();