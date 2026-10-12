<?php
session_start();
include "../config/db.php";

$message = "";
$message_type = "";

// ========================================================
// API ENDPOINT: LIVE FETCH OF ITEMS & ORDER INFO
// ========================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_items') {
    header('Content-Type: application/json');
    $order_id = mysqli_real_escape_string($conn, trim($_GET['order_id'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_GET['email'] ?? ''));
    
    $check = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$order_id' AND email = '$email' LIMIT 1");
    
    if ($check && mysqli_num_rows($check) > 0) {
        $order_data = mysqli_fetch_assoc($check);

        $cust_name = $order_data['fullname'] ?? ($order_data['customer_name'] ?? ($order_data['name'] ?? 'N/A'));
        $cust_surname = $order_data['surname'] ?? '';
        $ord_date = $order_data['order_date'] ?? ($order_data['created_at'] ?? 'N/A');
        $ord_status = $order_data['status'] ?? 'Pending';
        $pay_method = $order_data['payment_method'] ?? ($order_data['mode_of_payment'] ?? 'COD');
        $pay_scheme = $order_data['payment_scheme'] ?? 'fullpayment';
        $total_amount = $order_data['total_amount'] ?? 0;
        $contact_number = $order_data['contact_number'] ?? 'N/A';
        $full_address = $order_data['full_address'] ?? 'N/A';
        $landmark = $order_data['landmark'] ?? 'N/A';
        $ref_no = $order_data['ref_no'] ?? '';
        $paid_amount = $order_data['paid_amount'] ?? 0;

        // Check if this order already has a completed cancellation request
        $check_cancel = mysqli_query($conn, "SELECT status FROM customer_requests WHERE order_number = '$order_id' AND request_type = 'cancel' ORDER BY id DESC LIMIT 1");
        $is_cancel_completed = false;
        if ($check_cancel && $cancel_row = mysqli_fetch_assoc($check_cancel)) {
            if (strtolower(trim($cancel_row['status'])) === 'completed') {
                $is_cancel_completed = true;
            }
        }

        $items = [];
        try {
            $cart_raw = $order_data['cart'] ?? '';
            $cart_obj = is_string($cart_raw) ? json_decode($cart_raw, true) : $cart_raw;
            if (is_array($cart_obj)) {
                foreach ($cart_obj as $cart_item) {
                    $p_name = $cart_item['name'] ?? ($cart_item['product_name'] ?? 'Item');
                    $p_price = floatval($cart_item['price'] ?? 0);
                    $p_qty = intval($cart_item['qty'] ?? 1);
                    $items[] = [
                        'product_name' => $p_name,
                        'price' => $p_price,
                        'qty' => $p_qty,
                        'subtotal' => $p_price * $p_qty
                    ];
                }
            }
        } catch (Exception $e) {
            $items = [];
        }

        echo json_encode([
            'success' => true, 
            'order_info' => [
                'id' => $order_data['id'],
                'customer_name' => $cust_name,
                'surname' => $cust_surname,
                'order_date' => $ord_date,
                'status' => $ord_status,
                'payment_method' => $pay_method,
                'payment_scheme' => $pay_scheme,
                'total_amount' => $total_amount,
                'contact_number' => $contact_number,
                'full_address' => $full_address,
                'landmark' => $landmark,
                'ref_no' => $ref_no,
                'paid_amount' => $paid_amount,
                'is_cancel_completed' => $is_cancel_completed
            ],
            'items' => $items
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found with matching email.']);
        exit;
    }
}

// ========================================================
// POST HANDLER: SUBMISSION OF REQUESTS (CANCELLATION & EXCHANGE)
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $order_id = mysqli_real_escape_string($conn, trim($_POST['order_id']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $request_type = mysqli_real_escape_string($conn, $_POST['request_type']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
    
    $user_comments = trim($_POST['details'] ?? '');
    $final_details = $user_comments;

    $ver_query = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$order_id' AND email = '$email' LIMIT 1");
    
    if ($ver_query && mysqli_num_rows($ver_query) > 0) {
        $v_data = mysqli_fetch_assoc($ver_query);
        $v_status = strtolower(trim($v_data['status'] ?? ''));

        $v_cust_name = $v_data['fullname'] ?? ($v_data['customer_name'] ?? 'N/A');
        $v_ord_date = $v_data['order_date'] ?? ($v_data['created_at'] ?? 'N/A');
        $v_pay_method = strtoupper($v_data['payment_method'] ?? 'COD');
        $v_pay_scheme = $v_data['payment_scheme'] ?? 'fullpayment';
        $v_total_amount = $v_data['total_amount'] ?? 0;

        // Backend Validations for restricted statuses (Kasama na ang declined)
        if ($v_status === 'cancelled' || $v_status === 'declined') {
            $message = "❌ Cannot submit request. This order has already been " . $v_status . ".";
            $message_type = "danger";
        }
        elseif ($request_type === 'exchange' && $v_status !== 'completed') {
            $message = "❌ Cannot request exchange. Item Exchange is only allowed for COMPLETED orders.";
            $message_type = "danger";
        } 
        elseif ($request_type === 'cancel' && ($v_status === 'out for delivery' || $v_status === 'out_for_delivery' || $v_status === 'completed' || $v_status === 'delivered')) {
            $message = "❌ Cannot request cancellation. Order status is " . strtoupper($v_data['status']) . ".";
            $message_type = "danger";
        }
        elseif (($v_status === 'out for delivery' || $v_status === 'out_for_delivery' || $v_status === 'delivered') && $request_type !== 'exchange') {
            $message = "❌ Cannot submit request. Submitting requests for this order status is locked.";
            $message_type = "danger";
        } else {
            $filename = "";
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
                $ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                $filename = "REQ_" . time() . "_" . rand(1000, 9999) . "." . $ext;
                move_uploaded_file($_FILES['product_image']['tmp_name'], "../uploads/" . $filename);
            }

            $receipt_header  = "========================================\n";
            $receipt_header .= "          R. Taipan Hardware            \n";
            $receipt_header .= "190 Cabrera Rd. Sitio Burol Brgy. San Juan Taytay, Rizal\n";
            $receipt_header .= "Contact: 0907 456 5632\n";
            $receipt_header .= "----------------------------------------\n";
            $receipt_header .= "     OFFICIAL RECEIPT (ONLINE ORDER)    \n";
            $receipt_header .= "----------------------------------------\n";
            $receipt_header .= "Order ID: #" . $v_data['id'] . "\n";
            $receipt_header .= "Date: " . $v_ord_date . "\n";
            $receipt_header .= "Customer: " . $v_cust_name . " " . ($v_data['surname'] ?? '') . "\n";
            $receipt_header .= "Contact: " . ($v_data['contact_number'] ?? 'N/A') . "\n";
            $receipt_header .= "Address: " . ($v_data['full_address'] ?? 'N/A') . "\n";
            $receipt_header .= "Landmark: " . ($v_data['landmark'] ?? 'N/A') . "\n";
            $receipt_header .= "----------------------------------------\n";
            $receipt_header .= "ITEMS ORDERED:\n";

            $items_text = "";
            $total_ordered_qty_map = [];
            try {
                $cart_raw = $v_data['cart'] ?? '';
                $cart_obj = is_string($cart_raw) ? json_decode($cart_raw, true) : $cart_raw;
                if (is_array($cart_obj)) {
                    foreach ($cart_obj as $it) {
                        $p_name = $it['name'] ?? ($it['product_name'] ?? 'Item');
                        $p_price = floatval($it['price'] ?? 0);
                        $p_qty = intval($it['qty'] ?? 1);
                        $items_text .= "- {$p_name} (x{$p_qty}) - ₱" . number_format($p_price * $p_qty, 2) . "\n";
                        $total_ordered_qty_map[$p_name] = $p_qty;
                    }
                }
            } catch (Exception $e) {
                $items_text = "- (Unable to parse cart items)\n";
            }
            
            $receipt_footer  = $receipt_header . $items_text;
            $receipt_footer .= "----------------------------------------\n";
            $receipt_footer .= "TOTAL AMOUNT: ₱" . number_format($v_total_amount, 2) . "\n";
            $receipt_footer .= "Mode of Payment: " . $v_pay_method . "\n";
            $receipt_footer .= "STATUS: " . strtoupper($v_status) . "\n";
            $receipt_footer .= "----------------------------------------\n";

            if ($request_type === 'exchange') {
                $exchange_items = $_POST['exchange_items'] ?? [];
                $exchange_qtys = $_POST['exchange_qtys'] ?? [];
                $is_valid_qty = true;
                $submitted_counts = [];
                for ($i = 0; $i < count($exchange_items); $i++) {
                    $item_name = $exchange_items[$i];
                    $item_qty = intval($exchange_qtys[$i] ?? 1);
                    
                    if (!isset($total_ordered_qty_map[$item_name])) {
                        $is_valid_qty = false;
                        break;
                    }
                    
                    $submitted_counts[$item_name] = ($submitted_counts[$item_name] ?? 0) + $item_qty;
                    if ($submitted_counts[$item_name] > $total_ordered_qty_map[$item_name]) {
                        $is_valid_qty = false;
                        break;
                    }
                }

                if (!$is_valid_qty) {
                    $message = "❌ Security Error: The quantity of items requested for exchange exceeds your original order.";
                    $message_type = "danger";
                } else {
                    $multi_items_text = "[Same Item Exchange Policy Selected]\nItems to Return/Replace:\n";
                    $readable_items_list = "";
                    
                    for ($i = 0; $i < count($exchange_items); $i++) {
                        $item_name = $exchange_items[$i];
                        $item_qty = intval($exchange_qtys[$i] ?? 1);
                        $multi_items_text .= "  • {$item_name} - Qty: {$item_qty}x\n";
                        $readable_items_list .= "• {$item_name} (Qty: {$item_qty}x)\n";
                    }
                    
                    $final_details_escaped = mysqli_real_escape_string($conn, $user_comments);
                    $item_to_replace_escaped = mysqli_real_escape_string($conn, trim($readable_items_list));

                    $ins = mysqli_query($conn, "INSERT INTO customer_requests 
                    (order_number, account, request_type, reason, details, item_to_replace, product_image, status) 
                    VALUES 
                    ('$order_id', '$email', '$request_type', '$reason', '$final_details_escaped', '$item_to_replace_escaped', " . ($filename ? "'$filename'" : "NULL") . ", 'Pending')");
                    
                    if ($ins) {
                        $message = "🎉 Your ticket request (" . strtoupper($request_type) . ") has been submitted successfully! Admin will review it.";
                        $message_type = "success";
                    } else {
                        $message = "❌ Failed to submit request. Please try again.";
                        $message_type = "danger";
                    }
                }
            } else {
                $final_details_escaped = mysqli_real_escape_string($conn, $user_comments);

                $ins = mysqli_query($conn, "INSERT INTO customer_requests
                (order_number, account, request_type, reason, details, item_to_replace, product_image, status)
                VALUES
                ('$order_id', '$email', '$request_type', '$reason', '$final_details_escaped', NULL, " . ($filename ? "'$filename'" : "NULL") . ", 'Pending')");

                if ($ins) {
                    $message = "🎉 Your ticket request (" . strtoupper($request_type) . ") has been submitted successfully! Admin will review it.";
                    $message_type = "success";
                } else {
                    $message = "❌ Failed to submit request. Please try again.";
                    $message_type = "danger";
                }
            }
        }
    } else {
        $message = "❌ Verification error. Order connection lost.";
        $message_type = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Service Help Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .thermal-preview {
            width: 100%;
            max-width: 380px;
            margin: auto;
            padding: 15px;
            background: #fff;
            font-family: 'Courier New', Courier, monospace;
            border: 1px solid #ddd;
            color: #000;
            border-radius: 8px;
        }
        .line {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            
            <div class="text-center mb-4">
                <h2 class="fw-bold text-dark"><i class="bi bi-headset text-primary"></i> Customer Service Help Center</h2>
                <p class="text-muted">Input your order details below to file an item exchange, cancellation, or ask questions.</p>
            </div>

            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show card-custom p-3" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card card-custom p-4 bg-white mb-4">
                <form action="customerService.php" method="POST" enctype="multipart/form-data" id="mainRequestForm">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">For Other Questions, Please Contact Us:</label>
                        <div class="small mt-2">
                            <a href="https://www.facebook.com/share/1Acq7mfhEZ/?mibextid=wwXIfr" class="text-info" target="_blank">View Facebook Page</a><br>
                            0907 456 5632<br>
                            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=rtaipancssopc@gmail.com&su=Customer%20Service%20Inquiry" class="text-info" target="_blank">rtaipancssopc@gmail.com</a>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Order ID / Number</label>
                            <input type="number" name="order_id" id="order_id" class="form-control" placeholder="e.g. 1024"[cite: 9] required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Account Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="yourname@email.com" required>
                        </div>
                        <div class="col-12 text-end">
                            <button type="button" id="btnVerify" class="btn btn-primary px-4"><i class="bi bi-shield-check"></i> Verify Order</button>
                            <button type="button" class="btn btn-secondary px-4" onclick="window.location.href='dashboard.php';"><i class="bi bi-arrow-left"></i> Back</button>
                        </div>
                    </div>
                    
                    <div id="errorWrapper" class="alert alert-danger d-none card-custom mb-4"></div>

                    <div id="formBodyWrapper" class="d-none">
                        
                        <div class="card p-3 bg-light card-custom mb-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-receipt"></i> Verified Order & Receipt Preview</h6>
                            <div class="bg-dark p-3 rounded d-flex justify-content-center">
                                <div class="thermal-preview" id="liveReceiptContainer"></div>
                            </div>
                        </div>

                        <div class="border-top pt-4 mb-4">
                            <label class="form-label fw-bold fs-5 text-dark">Select Request Action</label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="radio" class="btn-check" name="request_type" id="req_exchange" value="exchange" checked>
                                    <label class="btn btn-outline-primary w-100 py-3 h-100 rounded-3 d-flex flex-column align-items-center justify-content-center" for="req_exchange" id="lbl_req_exchange">
                                        <i class="bi bi-arrow-left-right fs-4 mb-1"></i> Exchange Items
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <input type="radio" class="btn-check" name="request_type" id="req_cancel" value="cancel">
                                    <label class="btn btn-outline-dark w-100 py-3 h-100 rounded-3 d-flex flex-column align-items-center justify-content-center" for="req_cancel" id="lbl_req_cancel">
                                        <i class="bi bi-x-circle fs-4 mb-1"></i> Cancel Order
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4" id="reasonFieldWrapper">
                            <label class="form-label fw-semibold">Reason for Request</label>
                            <select name="reason" id="requestReason" class="form-select"></select>
                        </div>

                        <div id="exchangeCalculatorWrapper" class="card p-3 bg-light card-custom mb-4 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-dark m-0"><i class="bi bi-arrow-repeat"></i> Same Item Exchange Policy</h6>
                                <button type="button" id="btnAddExchangeItem" class="btn btn-sm btn-primary d-none"><i class="bi bi-plus-circle"></i> Add More Item</button>
                            </div>
                            
                            <div id="exchangeItemsContainer"></div>

                            <div class="alert alert-warning small mb-0 mt-3">
                                ℹ️ <strong>Same Item Exchange Only:</strong> Exchanges are only allowed for the same item. Replacement with a different item is not permitted.
                            </div>
                        </div>

                        <div class="mb-4 d-none" id="photoUploadSection">
                            <label class="form-label fw-semibold">Upload Image Proof / Photo of the Item(s)</label>
                            <input type="file" name="product_image" id="product_image" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold" id="lbl_details_desc">Additional Comments / Specific Details</label>
                            <textarea name="details" id="requestDetails" class="form-control" rows="4" placeholder="Explain your concern comprehensively..."></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" name="submit_request" id="btnSubmitForm" class="btn btn-success px-5 fw-semibold py-2">Submit Ticket Request</button>
                        </div>

                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let verifiedItemsData = [];
let currentOrderInfo = {};

document.getElementById('btnVerify').addEventListener('click', function() {
    const orderId = document.getElementById('order_id').value.trim();
    const email = document.getElementById('email').value.trim();
    const errorWrapper = document.getElementById('errorWrapper');
    const formBodyWrapper = document.getElementById('formBodyWrapper');

    if(!orderId || !email) {
        alert("Please enter both Order ID and Email address.");
        return;
    }

    fetch(`customerService.php?action=get_items&order_id=${orderId}&email=${email}`)
    .then(res => res.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if(data.success) {
                errorWrapper.classList.add('d-none');
                formBodyWrapper.classList.remove('d-none');
                
                verifiedItemsData = data.items;
                currentOrderInfo = data.order_info;
                
                renderLiveReceiptPreview();
                handleFormTypeSetup(currentOrderInfo.status);
            } else {
                formBodyWrapper.classList.add('d-none');
                errorWrapper.classList.remove('d-none');
                errorWrapper.innerHTML = `<strong>Error:</strong> ${data.message}`;
            }
        } catch(e) {
            console.error("Raw response:", text);
            formBodyWrapper.classList.add('d-none');
            errorWrapper.classList.remove('d-none');
            errorWrapper.innerHTML = `<strong>Server Error / JSON Parse Exception:</strong> Check console for response details.`;
        }
    })
    .catch(err => {
        console.error(err);
        alert("An error occurred during verification lookup.");
    });
});

function renderLiveReceiptPreview() {
    const container = document.getElementById('liveReceiptContainer');
    
    let itemsHtml = '';
    verifiedItemsData.forEach(item => {
        let price = parseFloat(item.price || 0);
        let qty = parseInt(item.qty || 0);
        let subtotal = price * qty;

        itemsHtml += `
            <tr>
                <td>${item.product_name} (x${qty})</td>
                <td align="right">₱${subtotal.toFixed(2)}</td>
            </tr>
        `;
    });

    let paymentMethodName = (currentOrderInfo.payment_method || 'COD').toUpperCase();
    let paymentScheme = (currentOrderInfo.payment_scheme || '').toLowerCase();
    let totalAmt = parseFloat(currentOrderInfo.total_amount || 0);
    let orderStatus = (currentOrderInfo.status || 'pending').toUpperCase();

    let paymentBreakdownHtml = '';

    if (paymentMethodName === 'COD') {
        paymentBreakdownHtml = `
            <div class="d-flex justify-content-between small fw-bold mb-2">
                <span>TOTAL AMOUNT:</span>
                <span>₱${totalAmt.toFixed(2)}</span>
            </div>
            <div class="text-muted small">
                Cash on Delivery (Pay upon delivery)
            </div>
        `;
    }
    else if (totalAmt >= 5000 && paymentScheme.includes('50')) {
        let downpayment = parseFloat(currentOrderInfo.paid_amount || (totalAmt * 0.5));
        let balance = totalAmt - downpayment;

        paymentBreakdownHtml = `
            <div class="d-flex justify-content-between small fw-bold mb-1">
                <span>TOTAL AMOUNT:</span>
                <span>₱${totalAmt.toFixed(2)}</span>
            </div>
            <div class="d-flex justify-content-between small text-success fw-bold mb-1">
                <span>PAID VIA ${paymentMethodName}:</span>
                <span>₱${downpayment.toFixed(2)}</span>
            </div>
            <div class="line"></div>
            <div class="d-flex justify-content-between small fw-bold text-danger">
                <span>PAY UPON DELIVERY:</span>
                <span>₱${balance.toFixed(2)}</span>
            </div>
        `;
    }
    else if (paymentMethodName === 'GCASH' || paymentMethodName === 'MAYA') {
        let paidAmount = parseFloat(currentOrderInfo.paid_amount || totalAmt);

        paymentBreakdownHtml = `
            <div class="d-flex justify-content-between small fw-bold mb-1">
                <span>TOTAL AMOUNT:</span>
                <span>₱${totalAmt.toFixed(2)}</span>
            </div>
            <div class="d-flex justify-content-between small text-success fw-bold mb-1">
                <span>PAID VIA ${paymentMethodName}:</span>
                <span>₱${paidAmount.toFixed(2)}</span>
            </div>
        `;

        if (currentOrderInfo.ref_no) {
            paymentBreakdownHtml += `
                <div class="small text-muted">
                    Reference No: ${currentOrderInfo.ref_no}
                </div>
            `;
        }
    }
    else {
        paymentBreakdownHtml = `
            <div class="d-flex justify-content-between small fw-bold">
                <span>TOTAL AMOUNT:</span>
                <span>₱${totalAmt.toFixed(2)}</span>
            </div>
        `;
    }

    let fullReceiptHtml = `
        <div class="text-center mb-2">
            <h5 class="fw-bold my-0 text-uppercase">R. Taipan Hardware</h5>
            <small>190 Cabrera Rd. Sitio Burol Brgy. San Juan Taytay, Rizal</small><br>
            <small>Contact: 0907 456 5632</small>
            <div class="line"></div>
            <small><b>OFFICIAL RECEIPT (ONLINE ORDER)</b></small>
            <div class="line"></div>
        </div>

        <p class="small mb-1"><b>Order ID:</b> #${currentOrderInfo.id}</p>
        <p class="small mb-1"><b>Date:</b> ${currentOrderInfo.order_date}</p>
        <p class="small mb-1"><b>Customer:</b> ${currentOrderInfo.customer_name} ${currentOrderInfo.surname}</p>
        <p class="small mb-1"><b>Contact:</b> ${currentOrderInfo.contact_number}</p>
        <p class="small mb-1"><b>Address:</b> ${currentOrderInfo.full_address}</p>
        <p class="small mb-2"><b>Landmark:</b> ${currentOrderInfo.landmark || 'N/A'}</p>

        <div class="line"></div>

        <table width="100%" class="small mb-2">
            ${itemsHtml}
        </table>

        <div class="line"></div>

        ${paymentBreakdownHtml}

        <div class="line mt-2"></div>

        <div class="text-start small">
            <b>Mode of Payment:</b> ${paymentMethodName}
        </div>

        <div class="text-center mt-2">
            <small class="fw-bold">STATUS: ${orderStatus}</small>
        </div>
    `;
    
    container.innerHTML = fullReceiptHtml;
}

function handleFormTypeSetup(status) {
    const currentStatus = status.toLowerCase().trim();
    const reqCancel = document.getElementById('req_cancel');
    const reqExchange = document.getElementById('req_exchange');
    const lblExchange = document.getElementById('lbl_req_exchange');
    const lblCancel = document.getElementById('lbl_req_cancel');
    const errorWrapper = document.getElementById('errorWrapper');
    const btnSubmitForm = document.getElementById('btnSubmitForm');
    
    errorWrapper.classList.add('d-none');
    errorWrapper.innerHTML = '';
    
    // Default reset states
    btnSubmitForm.disabled = false;
    btnSubmitForm.classList.remove('d-none');
    reqCancel.disabled = false;
    lblCancel.classList.remove('disabled', 'btn-outline-secondary');
    lblCancel.classList.add('btn-outline-dark');

    // 1. If the order is already cancelled, declined, or has a completed cancellation request
    if (currentStatus === 'cancelled' || currentStatus === 'declined' || currentOrderInfo.is_cancel_completed) {
        reqCancel.disabled = true;
        lblCancel.classList.add('disabled', 'btn-outline-secondary');
        lblCancel.classList.remove('btn-outline-dark');
        btnSubmitForm.disabled = true;
        
        errorWrapper.classList.remove('d-none');
        errorWrapper.innerHTML = `⚠️ <strong>Note:</strong> This order has already been <strong>${currentStatus.toUpperCase()}</strong> or has a completed cancellation request. Submitting a new ticket is disabled.`;
    }

 // 2. Kapag OUT FOR DELIVERY, DELIVERED, o COMPLETED - BAWAL NA MAG-CANCEL
    if (currentStatus === 'out for delivery' || currentStatus === 'out_for_delivery' || currentStatus === 'delivered' || currentStatus === 'completed') {
        reqCancel.disabled = true;
        lblCancel.classList.add('disabled', 'btn-outline-secondary');
        lblCancel.classList.remove('btn-outline-dark');
        
        if (reqCancel.checked) {
            reqCancel.checked = false;
            if (currentStatus === 'completed') {
                reqExchange.checked = true;
            }
        }
        
        if (currentStatus !== 'completed' && currentStatus !== 'cancelled' && currentStatus !== 'declined' && !currentOrderInfo.is_cancel_completed) {
            errorWrapper.classList.remove('d-none');
            errorWrapper.innerHTML = `ℹ️ Order status is <strong>${status.toUpperCase()}</strong>. Cancellation option is locked.`;
        }
    }

    // 3. Fully lock/disable submitting any requests if status is 'out for delivery' or 'delivered'
    if (currentStatus === 'out for delivery' || currentStatus === 'out_for_delivery' || currentStatus === 'delivered') {
        btnSubmitForm.disabled = true;
        reqExchange.disabled = true;
        lblExchange.classList.add('disabled', 'btn-outline-secondary');
        lblExchange.classList.remove('btn-outline-primary');
        
        errorWrapper.classList.remove('d-none');
        errorWrapper.innerHTML = `⚠️ <strong>Note:</strong> Order status is <strong>${status.toUpperCase()}</strong>. Ticket submission is currently locked for this status.`;
    }

    // 3. Fully lock/disable submitting any requests if status is 'out for delivery' or 'delivered'
    if (currentStatus === 'out for delivery' || currentStatus === 'delivered') {
        btnSubmitForm.disabled = true;
        reqExchange.disabled = true;
        lblExchange.classList.add('disabled', 'btn-outline-secondary');
        lblExchange.classList.remove('btn-outline-primary');
        
        errorWrapper.classList.remove('d-none');
        errorWrapper.innerHTML = `⚠️ <strong>Note:</strong> Order status is <strong>${status.toUpperCase()}</strong>. Ticket submission is currently locked for this status.`;
    }
    // Exchange button logic specifically for completed orders
    else if (currentStatus === 'completed' && !currentOrderInfo.is_cancel_completed && currentStatus !== 'cancelled' && currentStatus !== 'declined') {
        reqExchange.disabled = false;
        lblExchange.classList.remove('disabled', 'btn-outline-secondary');
        lblExchange.classList.add('btn-outline-primary');
        reqExchange.checked = true; 
    } else {
        reqExchange.disabled = true;
        lblExchange.classList.add('disabled', 'btn-outline-secondary');
        lblExchange.classList.remove('btn-outline-primary');
        reqExchange.checked = false;
    }

    renderReasonOptions();
    resetExchangeItemsList();
}

function renderReasonOptions() {
    const reasonSelect = document.getElementById('requestReason');
    const selectedRadio = document.querySelector('input[name="request_type"]:checked');
    const selectedType = selectedRadio ? selectedRadio.value : 'cancel';
    const reasonFieldWrapper = document.getElementById('reasonFieldWrapper');
    const photoUploadSection = document.getElementById('photoUploadSection');
    const exchangeCalculatorWrapper = document.getElementById('exchangeCalculatorWrapper');
    const lblDetailsDesc = document.getElementById('lbl_details_desc');
    const txtDetails = document.getElementById('requestDetails');
    const productImageInput = document.getElementById('product_image');
    const exchangeRows = document.querySelectorAll('.exchange-item-row');

    reasonSelect.innerHTML = "";
    let options = [];

    reasonFieldWrapper.classList.remove('d-none');
    photoUploadSection.classList.add('d-none');
    exchangeCalculatorWrapper.classList.add('d-none');
    productImageInput.removeAttribute('required');
    lblDetailsDesc.textContent = "Additional Comments / Specific Details";
    txtDetails.placeholder = "Explain your concern comprehensively...";

    exchangeRows.forEach(row => {
        const select = row.querySelector('.exch-item-select');
        const qty = row.querySelector('.exch-qty-input');

        if (select) {
            select.removeAttribute('required');
            select.disabled = true;
        }

        if (qty) {
            qty.removeAttribute('required');
            qty.disabled = true;
        }
    });

    if (selectedType === 'exchange') {
        options = [
            "Wrong size/variant selected",
            "Received Damaged / Broken Item",
            "Wrong item delivered",
            "Other"
        ];

        photoUploadSection.classList.remove('d-none');
        exchangeCalculatorWrapper.classList.remove('d-none');
        productImageInput.setAttribute('required', 'required');

        exchangeRows.forEach(row => {
            const select = row.querySelector('.exch-item-select');
            const qty = row.querySelector('.exch-qty-input');

            if (select) {
                select.setAttribute('required', 'required');
                select.disabled = false;
            }

            if (qty) {
                qty.setAttribute('required', 'required');
            }

            if (select && select.value) {
                if (qty) qty.disabled = false;
            } else {
                if (qty) qty.disabled = true;
            }
        });
    } else if (selectedType === 'cancel') {
        options = [
            "Found mistake in order details",
            "Change of mind",
            "Accidental order placement",
            "Other"
        ];
    }

    options.forEach(opt => {
        const el = document.createElement('option');
        el.value = opt;
        el.textContent = opt;
        reasonSelect.appendChild(el);
    });

    updateExchangeItemControls();
}

document.querySelectorAll('input[name="request_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        renderReasonOptions();
    });
});

function resetExchangeItemsList() {
    const container = document.getElementById('exchangeItemsContainer');
    const btnAddMore = document.getElementById('btnAddExchangeItem');

    container.innerHTML = "";
    btnAddMore.classList.add('d-none');
    btnAddMore.disabled = true;

    if (verifiedItemsData.length > 0) {
        addExchangeItemRow(true);
    }

    updateExchangeItemControls();
}

document.getElementById('btnAddExchangeItem').addEventListener('click', function() {
    if (this.disabled) return;
    if (!canAddAnotherExchangeRow()) return;

    addExchangeItemRow(false);
    updateExchangeItemControls();
});

function getUsedQuantities() {
    const used = {};

    document.querySelectorAll('.exchange-item-row').forEach(row => {
        const select = row.querySelector('.exch-item-select');
        const qty = row.querySelector('.exch-qty-input');

        if (!select || !qty || !select.value) return;

        const itemName = select.value;
        const itemQty = parseInt(qty.value) || 0;

        used[itemName] = (used[itemName] || 0) + itemQty;
    });

    return used;
}

function getItemMaxQty(itemName) {
    const item = verifiedItemsData.find(i => i.product_name === itemName);
    return item ? parseInt(item.qty) || 0 : 0;
}

function getRemainingQty(itemName, currentRow = null) {
    const maxQty = getItemMaxQty(itemName);
    let usedQty = 0;

    document.querySelectorAll('.exchange-item-row').forEach(row => {
        if (row === currentRow) return;

        const select = row.querySelector('.exch-item-select');
        const qty = row.querySelector('.exch-qty-input');

        if (select && qty && select.value === itemName) {
            usedQty += parseInt(qty.value) || 0;
        }
    });

    return Math.max(0, maxQty - usedQty);
}

function canAddAnotherExchangeRow() {
    const used = getUsedQuantities();

    return verifiedItemsData.some(item => {
        const maxQty = parseInt(item.qty) || 0;
        const usedQty = used[item.product_name] || 0;

        return usedQty < maxQty;
    });
}

function addExchangeItemRow(isFirstRow = false) {
    const container = document.getElementById('exchangeItemsContainer');

    const rowDiv = document.createElement('div');
    rowDiv.className = 'row g-3 align-items-center mb-3 exchange-item-row border-bottom pb-3';

    const rowId = 'exch_row_' + Date.now() + Math.floor(Math.random() * 1000);
    rowDiv.id = rowId;

    const colClass = isFirstRow ? 'col-md-9' : 'col-md-7';

    let deleteButtonHtml = '';

    if (!isFirstRow) {
        deleteButtonHtml = `
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger w-100 btn-remove-row mt-4 mt-md-0">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
    }

    rowDiv.innerHTML = `
        <div class="${colClass}">
            <label class="small fw-semibold text-dark">Select Item to Exchange/Replace</label>
            <select name="exchange_items[]" class="form-select exch-item-select">
                <option value="" selected disabled hidden>Select Item</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="small fw-semibold text-dark">Quantity</label>
            <input type="number" name="exchange_qtys[]" class="form-control exch-qty-input" value="1" min="1" disabled>
        </div>
        ${deleteButtonHtml}
    `;

    container.appendChild(rowDiv);

    const selectEl = rowDiv.querySelector('.exch-item-select');
    const qtyInput = rowDiv.querySelector('.exch-qty-input');

    verifiedItemsData.forEach(item => {
        const option = document.createElement('option');
        option.value = item.product_name;
        option.textContent = `${item.product_name} (Bought @ ₱${parseFloat(item.price || 0).toFixed(2)} - Max Qty: ${parseInt(item.qty) || 0})`;
        option.dataset.maxQty = parseInt(item.qty) || 0;
        selectEl.appendChild(option);
    });

    selectEl.selectedIndex = 0;
    selectEl.value = "";
    qtyInput.disabled = true;
    qtyInput.value = 1;
    qtyInput.removeAttribute('max');

    selectEl.addEventListener('change', function() {
        const selectedItem = this.value;

        if (!selectedItem) {
            qtyInput.disabled = true;
            qtyInput.value = 1;
            qtyInput.removeAttribute('max');
            updateExchangeItemControls();
            return;
        }

        const remainingQty = getRemainingQty(selectedItem, rowDiv);

        if (remainingQty <= 0) {
            alert("You have already reached the maximum quantity for this item.");
            this.value = "";
            this.selectedIndex = 0;
            qtyInput.disabled = true;
            qtyInput.value = 1;
            qtyInput.removeAttribute('max');
            updateExchangeItemControls();
            return;
        }

        qtyInput.disabled = false;
        qtyInput.min = 1;
        qtyInput.max = remainingQty;

        let currentQty = parseInt(qtyInput.value) || 1;

        if (currentQty > remainingQty) currentQty = remainingQty;
        if (currentQty < 1) currentQty = 1;

        qtyInput.value = currentQty;
        updateExchangeItemControls();
    });

    qtyInput.addEventListener('input', function() {
        const selectedItem = selectEl.value;

        if (!selectedItem) {
            this.value = 1;
            this.disabled = true;
            return;
        }

        const remainingQty = getRemainingQty(selectedItem, rowDiv);
        let value = parseInt(this.value) || 1;

        if (value < 1) value = 1;
        if (value > remainingQty) value = remainingQty;

        this.value = value;
        updateExchangeItemControls();
    });

    if (!isFirstRow) {
        const removeButton = rowDiv.querySelector('.btn-remove-row');

        removeButton.addEventListener('click', function() {
            rowDiv.remove();
            updateExchangeItemControls();
        });
    }

    updateExchangeItemControls();
}

function updateExchangeItemControls() {
    const rows = document.querySelectorAll('.exchange-item-row');
    const btnAddMore = document.getElementById('btnAddExchangeItem');
    const selectedRadio = document.querySelector('input[name="request_type"]:checked');
    const isExchange = selectedRadio && selectedRadio.value === 'exchange';

    rows.forEach(currentRow => {
        const select = currentRow.querySelector('.exch-item-select');
        const qtyInput = currentRow.querySelector('.exch-qty-input');

        if (!select || !qtyInput) return;

        if (!isExchange) {
            select.removeAttribute('required');
            select.disabled = true;
            qtyInput.removeAttribute('required');
            qtyInput.disabled = true;
            return;
        }

        select.setAttribute('required', 'required');

        Array.from(select.options).forEach(option => {
            if (!option.value) return;

            const itemName = option.value;
            const maxQty = getItemMaxQty(itemName);
            let usedByOtherRows = 0;

            rows.forEach(otherRow => {
                if (otherRow === currentRow) return;

                const otherSelect = otherRow.querySelector('.exch-item-select');
                const otherQty = otherRow.querySelector('.exch-qty-input');

                if (otherSelect && otherQty && otherSelect.value === itemName) {
                    usedByOtherRows += parseInt(otherQty.value) || 0;
                }
            });

            const remainingQty = maxQty - usedByOtherRows;

            option.disabled = remainingQty <= 0;

            if (select.value === itemName) {
                option.disabled = false;
                qtyInput.max = Math.max(1, remainingQty);

                if (parseInt(qtyInput.value) > remainingQty) {
                    qtyInput.value = remainingQty;
                }
            }
        });

        if (!select.value) {
            select.selectedIndex = 0;
            select.value = "";
            qtyInput.removeAttribute('required');
            qtyInput.disabled = true;
            qtyInput.value = 1;
            qtyInput.removeAttribute('max');
        } else {
            qtyInput.setAttribute('required', 'required');
            qtyInput.disabled = false;
        }
    });

    if (!isExchange) {
        btnAddMore.classList.add('d-none');
        btnAddMore.disabled = true;
        btnAddMore.classList.add('disabled');
        return;
    }

    if (canAddAnotherExchangeRow()) {
        btnAddMore.classList.remove('d-none');
        btnAddMore.disabled = false;
        btnAddMore.classList.remove('disabled');
    } else {
        btnAddMore.classList.add('d-none');
        btnAddMore.disabled = true;
        btnAddMore.classList.add('disabled');
    }
}
</script>
</body>
</html>