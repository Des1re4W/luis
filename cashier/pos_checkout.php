<?php
session_start();
include "../config/db.php";
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
$cashier = mysqli_real_escape_string($conn, $_SESSION['username']);

// Simulan ang pag-save ng transaction sa POS table
$insert = mysqli_query($conn, "INSERT INTO pos_transaction (cashier_name, total_amount, amount_paid, change_amount) VALUES ('$cashier', '$total', '$paid', '$change')");

if (!$insert) {
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    exit();
}

$transaction_id = mysqli_insert_id($conn);

// I-loop ang cart para sa bawas stock at pag-save ng items
foreach ($cart as $item) {
    $raw_id = $item['id'];
    $type = substr($raw_id, 0, 2);
    $id = (int)substr($raw_id, 2);
    $qty = (int)$item['qty'];
    $price = (float)$item['price'];
    $subtotal = $qty * $price;
    $name = mysqli_real_escape_string($conn, $item['name']);

    if ($type == 'v_') {
        // Bawas stock sa variants at i-save ang item
        mysqli_query($conn, "UPDATE product_variants SET stock = stock - $qty WHERE id = $id");
        mysqli_query($conn, "INSERT INTO pos_transaction_items (transaction_id, variant_id, product_name, price, qty, subtotal) VALUES ($transaction_id, $id, '$name', $price, $qty, $subtotal)");
    } else {
        // Bawas stock sa main products at i-save ang item
        mysqli_query($conn, "UPDATE products SET stock = stock - $qty WHERE id = $id");
        mysqli_query($conn, "INSERT INTO pos_transaction_items (transaction_id, product_id, product_name, price, qty, subtotal) VALUES ($transaction_id, $id, '$name', $price, $qty, $subtotal)");
    }
}
//audit trail
$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$action = "POS Checkout";
$ip_address = $_SERVER['REMOTE_ADDR'];

$details = "Processed walk-in transaction #" . $transaction_id . ". Total Amount: ₱" . number_format($total, 2) . ", Paid: ₱" . number_format($paid, 2) . ", Change: ₱" . number_format($change, 2);
$audit_sql = "INSERT INTO audit_log (user_id, username, role, action, details, ip_address) 
              VALUES ($user_id, '$cashier', '$role', '$action', '$details', '$ip_address')";
mysqli_query($conn, $audit_sql);
echo json_encode(["status" => "success", "transaction_id" => $transaction_id]);
?>