<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json');

$order_id = $_POST['order_id'];
$service_type = $_POST['service_type'];
$reason = $_POST['reason'];
$other_reason = $_POST['other_reason'] ?? null;

// proof upload
$proof_name = null;

if(!empty($_FILES['proof']['name'])){
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
    
    if(in_array($ext, $allowed)){
        $proof_name = time() . "_" . bin2hex(random_bytes(8)) . "." . $ext; // Mas safe na filename
        move_uploaded_file($_FILES['proof']['tmp_name'], "../uploads/" . $proof_name);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid file format."]);
        exit;
    }
}

// 1. INSERT MAIN REQUEST
mysqli_query($conn, "
    INSERT INTO return_requests 
    (order_id, service_type, reason, other_reason, proof_image, status)
    VALUES
    ('$order_id', '$service_type', '$reason', '$other_reason', '$proof_name', 'Pending')
");

$request_id = mysqli_insert_id($conn);

// 2. INSERT ITEMS
if(isset($_POST['items']) && is_array($_POST['items'])){
    foreach($_POST['items'] as $order_item_id => $qty){
        // Siguraduhin na may laman at higit sa zero ang quantity
        if(!empty($qty) && $qty > 0){
            $q = mysqli_real_escape_string($conn, $qty);
            mysqli_query($conn, "
                INSERT INTO return_request_items
                (return_request_id, order_item_id, quantity)
                VALUES
                ('$request_id', '$order_item_id', '$q')
            ");
        }
    }
}

echo json_encode([
    "success" => true,
    "message" => "Request submitted successfully"
]);