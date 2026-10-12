<?php
require_once __DIR__ . '/config/db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$order_no = $_POST['order_no'];
$email = $_POST['email'];
$service = $_POST['service_type'];

// 1. CHECK ORDER
$query = mysqli_query($conn, "
    SELECT * FROM orders 
    WHERE id='$order_no' AND customer_email='$email'
");

if(mysqli_num_rows($query) == 0){
    echo json_encode([
        "success" => false,
        "message" => "Order not found or email does not match."
    ]);
    exit;
}

$order = mysqli_fetch_assoc($query);
$status = strtolower(trim($order['status']));
$payment_method = strtoupper(trim($order['payment_method']));
$payment_scheme = strtolower(trim($order['payment_scheme'] ?? ''));
$total_amount = floatval($order['total_amount'] ?? 0);

// Harangin kung ang order ay cancelled na
if ($status === 'cancelled') {
    echo json_encode([
        "success" => false,
        "message" => "This order has already been cancelled and cannot accept further requests."
    ]);
    exit;
}

// [BAGONG DAGDAG]: Harangin ang cancel request kung fully paid o may down payment na
if ($service === "Cancel") {
    // Check kung GCash/Maya (Fully Paid) o kaya ay may 50% down payment scheme
    $is_fully_paid = in_array($payment_method, ['GCASH', 'MAYA']) || str_contains($payment_scheme, 'full');
    $has_downpayment = str_contains($payment_scheme, '50') || str_contains($payment_scheme, 'down');

    if ($is_fully_paid || $has_downpayment) {
        echo json_encode([
            "success" => false,
            "message" => "Cancellation is not allowed for orders with online payment (fully paid) or down payment schemes. Please contact customer support directly."
        ]);
        exit;
    }
}

// I-check kung mayroon nang naka-pending o aprubadong request para sa order na ito
$check_existing_req = mysqli_query($conn, "
    SELECT * FROM customer_requests 
    WHERE order_number = '$order_no' AND request_type = 'cancel' AND status != 'Declined'
");
if (mysqli_num_rows($check_existing_req) > 0 && $service == "Cancel") {
    echo json_encode([
        "success" => false,
        "message" => "A cancellation request has already been submitted for this order."
    ]);
    exit;
}

// 2. VALIDATE SERVICE TYPE BASED ON STATUS
$status_lower = strtolower(trim($order['status']));

if ($service == "Cancel") {

    // Cancellation is only allowed while the order is Approved or Preparing
    $allowed_cancel_statuses = ['approved', 'preparing'];

    if (!in_array($status_lower, $allowed_cancel_statuses)) {
        echo json_encode([
            "success" => false,
            "message" => "Cancellation is not allowed once the order reaches its current status: " . ucwords($order['status']) . "."
        ]);
        exit;
    }
}

// 3. GET ORDER ITEMS
$items_query = mysqli_query($conn, "
    SELECT * FROM order_items 
    WHERE order_id='$order_no'
");

$html = "";

while($item = mysqli_fetch_assoc($items_query)){
    $html .= "
    <div class='border p-2 mb-2 rounded'>
        <strong>{$item['product_name']}</strong><br>
        Quantity: {$item['quantity']}<br>

        <label>Select Quantity</label>
        <input type='number' 
               name='items[{$item['id']}]' 
               min='0' 
               max='{$item['quantity']}' 
               class='form-control'>
    </div>
    ";
}

// 4. RETURN RESPONSE
echo json_encode([
    "success" => true,
    "order_id" => $order_no,
    "itemsHtml" => $html
]);