<?php
session_start();
include "config/db.php";

header("Content-Type: application/json; charset=UTF-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || !isset($data['cart'])) {
    echo json_encode(["status" => "error", "message" => "NO JSON RECEIVED"]);
    exit;
}

$cart = $data['cart'];
$total = 0;

foreach ($cart as $item) {
    $total += $item['price'] * $item['qty'];
}
/* GET CUSTOMER DATA */
$fullname = mysqli_real_escape_string($conn, $data['fullname'] ?? 'Guest');
$surname = mysqli_real_escape_string($conn, $data['surname'] ?? 'Guest');
$contact_number = mysqli_real_escape_string($conn, $data['contact_number'] ?? '');

if (!preg_match('/^09[0-9]{9}$/', $contact_number)) { 
    echo json_encode([
        "status" => "error", 
        "message" => "Invalid Contact Number. Your number must start with 09 with 11 numbers total."
    ]);
    exit;
}
$fullname = mysqli_real_escape_string($conn, $_POST['fullname'] ?? '');
$surname  = mysqli_real_escape_string($conn, $_POST['surname'] ?? '');
$contact = mysqli_real_escape_string($conn, $_POST['contact_number'] ?? '');
$tel = mysqli_real_escape_string($conn, $_POST['tel_no'] ?? '');
$address = mysqli_real_escape_string($conn, $_POST['full_address'] ?? '');
$landmark = mysqli_real_escape_string($conn, $_POST['landmark'] ?? '');
$email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
$ref_no = mysqli_real_escape_string($conn, $_POST['ref_no'] ?? '');
$payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? '');
$payment_scheme = $_POST['payment_scheme'] ?? 'fullpayment';
$total_amount = (float)($_POST['total_price'] ?? 0);

// Kung 50% ang pinili, i-calculate
$amount_to_pay = ($payment_type == 'down') ? ($total * 0.5) : $total;

$cart_json = json_encode($cart);
$cart_json_escaped = mysqli_real_escape_string($conn, $cart_json);
if ($payment_scheme === 'downpayment' && $payment_method === 'COD') {
    die("Security Error: COD is not allowed for Downpayment.");
}
// Sa loob ng placeorder.php, bago ang INSERT query:
if ($total_amount > 4000 && $payment_method === 'COD') {
    die("Error: COD is only allowed for orders 4,000 and below.");
}
/* --- BACKEND VALIDATION --- */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Error: Invalid email format.");
}

if (!preg_match('/^09[0-9]{9}$/', $contact)) {
    die("Error: Invalid contact number. Must be 11 digits starting with 09.");
}
/* --- HANDLING FILE UPLOAD (PROOF OF PAYMENT) --- */
$proof_of_payment_path = ""; 

if ($payment_method === "GCash" || $payment_method === "Maya") {
    if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] == 0) {
        $target_dir = "../uploads/payments/"; 
        
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['proof_of_payment']['name'], PATHINFO_EXTENSION);
        $new_filename = "PROOF_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        $check = getimagesize($_FILES['proof_of_payment']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $target_file)) {
                $proof_of_payment_path = "uploads/payments/" . $new_filename;
                // BUG REMOVED: Inalis ang linya na nagpapalit ng payment_method patungong COD.
            } else {
                die("Error: Nagkaroon ng problema sa pag-save ng iyong resibo sa server.");
            }
        } else {
            die("Error: Ang file na in-upload mo ay hindi isang valid na larawan.");
        }
    } else {
        die("Error: Mangyaring mag-upload ng screenshot ng iyong resibo (Proof of Payment).");
    }
}
// DITO ANG DAPAT MONG AYUSIN SA SQL:
// Sa checkout.php
$sql = "INSERT INTO orders 
(fullname, surname, contact_number, tel_no, full_address, landmark, email, total_amount, cart, proof_of_payment, order_date, status)
VALUES
('$fullname','$surname','$contact_number', '$tel_no', '$full_address', '$landmark', '$email', '$total', '$cart_json_escaped', '$proof_image', NOW(), 'pending')";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        "status" => "error",
        "message" => "DATABASE ERROR",
        "error" => mysqli_error($conn)
    ]);
    exit;
}

$order_id = mysqli_insert_id($conn);

foreach ($cart as $item) {
    $id = (int)$item['id'];
    $name = mysqli_real_escape_string($conn, $item['name']);
    $price = (float)$item['price'];
    $qty = (int)$item['qty'];
    $subtotal = $price * $qty;
    // Insert sa order_items table
    $item_sql = "INSERT INTO order_items (order_id, product_id, product_name, price, qty, subtotal)
                 VALUES ($order_id, $id, '$name', $price, $qty, $subtotal)";
    mysqli_query($conn, $item_sql);
}

/* SUCCESS RESPONSE */
echo json_encode([
    "status" => "success",
    "message" => "Order placed successfully ",
    "order_id" => $order_id
]);

// Clear cart after checkout
unset($_SESSION['cart']);
?>