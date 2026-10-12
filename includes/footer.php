<?php
session_start();
include "../config/db.php";

// --- 1. PHPMAILER START (ADDED) ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libs/src/Exception.php';
require '../libs/src/PHPMailer.php';
require '../libs/src/SMTP.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}
// EMAILER ACTION LOGIC
if (isset($_POST['send_email_update'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    
    $res = mysqli_query($conn, "SELECT email, fullname, status, total_amount FROM orders WHERE id = '$order_id'");
    $order = mysqli_fetch_assoc($res);
    $current_status = $order['status'];

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'renzf2016@gmail.com'; 
        $mail->Password   = 'xfoxzotxmuohglwo';   
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('renzf2016@gmail.com', 'R. Taipan Hardware');
        $mail->addAddress($order['email'], $order['fullname']);

        $mail->isHTML(true);
        $mail->Subject = "Order Status Update: #$order_id";
//Approved email message
        if ($current_status == 'approved') {
            $mail->Body = "<h3>Hi {$order['fullname']},</h3>
                           <p>Good news! Your order with Order Number <b>#$order_id</b> has been <b>APPROVED</b> successfully.</p>
                           <p>We are now preparing your items. We will keep you updated via email regarding the progress of your order!</p>
                           <br>
                           <h3>Thank you for your purchase.</h3>
                           <br>
                           <h4>Best regards,<br>
                           R. Taipan Construction Supply and Services, OPC</h4>";
//declined email message
        } elseif ($current_status == 'declined' || $current_status == 'cancelled') {
            $mail->Body = "<h3>Hi {$order['fullname']},</h3>
            <p>We regret to inform you that your order <b>#$order_id</b> has been <b>DECLINED</b>.</p>
            <p>This may be due to your order details. If you have questions, please contact us by clicking the link or try to place a new order.</p>
            <p><a href='https://www.facebook.com/RSRT22' 
            style='background-color: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
            View Facebook Page
            </a></p>
            <p>Visit our shop here: <a href='https://www.google.com/maps/place/Ronel+Steel+Roof+Trading+and+Hardware+Supply/@14.5602582,121.1374471,17z/data=!3m1!4b1!4m6!3m5!1s0x3397c727501f019b:0xe2dfa8a1b3aec65f!8m2!3d14.5602583!4d121.142318!16s%2Fg%2F11t2lf3b8r?entry=ttu&g_ep=EgoyMDI2MDQyOS4wIKXMDSoASAFQAw%3D%3D'>Open in Google Maps</a></p>
            <h4>Best regards,<br>
            R. Taipan Construction Supply and Services, OPC</h4>";
//out of delivery email 
        }elseif($current_status == 'out_for_delivery'){
           $total = number_format($order['total_amount'], 2);
            $mail->Body = "<h3>Hi {$order['fullname']},</h3>
            <p>Your order <b>#$order_id</b> is now <b>OUT FOR DELIVERY</b>!</p>
            <div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeeba; border-radius: 5px;'>
             <h4 style='margin-top:0;'>Payment Reminder:</h4>
            <p>Please prepare the <b>EXACT AMOUNT</b> upon delivery.</p>
            <p style='font-size: 18px;'>Total Amount to Pay: <b style='color: #d9534f;'>₱ $total</b></p>
            </div>
            <p>Our delivery man will contact you shortly. Please keep your lines open.</p>
            <br>
            <h4>Best regards,<br>R. Taipan Construction Supply</h4>";
//other order status email
        }elseif($current_status == 'completed'){
            $mail->Body = "<h3>Hi {$order['fullname']},</h3>
            <p>Your order <b>#$order_id</b> status has been <b> DELIVERED </b>.</p>
            <p>Thank you for choosing <b>R. Taipan Construction Supply and Services, OPC.</b> We sincerely appreciate your trust and support, and we look forward to serving you again in the future.</p>
            <br>
            <h4>Best regards,<br>
            R. Taipan Construction Supply and Services, OPC</h4>";
//other order status email
        } else {
            $mail->Body = "<h3>Hi {$order['fullname']},</h3>
            <p>Your order <b>#$order_id</b> status has been updated to <b>" . strtoupper($current_status) . "</b>.</p>
            <br>
            <h4>Best regards,<br>
            R. Taipan Construction Supply and Services, OPC</h4>";
        }

        $mail->send();
        
        mysqli_query($conn, "UPDATE orders SET email_status = '$current_status' WHERE id = '$order_id'");
        echo "<script>alert('Email notification sent!'); window.location.href='orders.php';</script>";
        exit();

    } catch (Exception $e) {
        echo "<script>alert('Email failed: {$mail->ErrorInfo}'); window.location.href='orders.php';</script>";
        exit();
    }
}
    
//STATUS UPDATE
if (isset($_POST['update_status'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);

    // 1. Fetch the old status before updating it
    $status_res = mysqli_query($conn, "SELECT status FROM orders WHERE id = '$order_id'");
    $status_row = mysqli_fetch_assoc($status_res);
    $old_status = $status_row['status'];

    // 2. Update the order status in the database
    mysqli_query($conn, "UPDATE orders SET status = '$new_status' WHERE id = '$order_id'");

    // 3. Deduct stock ONLY when transitioning to 'completed' for the first time
    if ($new_status == 'completed' && $old_status != 'completed') {

        $items_res = mysqli_query($conn, "
            SELECT product_id, qty, variant_id
            FROM order_items
            WHERE order_id = '$order_id'
        ");

        while ($item = mysqli_fetch_assoc($items_res)) {

            $qty = max(0, (int)$item['qty']);

            // 🔥 IF VARIANT
            if (!empty($item['variant_id'])) {
                mysqli_query($conn, "
                    UPDATE product_variants 
                    SET stock = GREATEST(stock - $qty, 0)
                    WHERE id = '{$item['variant_id']}'
                ");
            } 
            // 🔥 IF MAIN PRODUCT
            else {
                mysqli_query($conn, "
                    UPDATE products 
                    SET stock = GREATEST(stock - $qty, 0)
                    WHERE id = '{$item['product_id']}'
                ");
            }
        }
    }
    
    // Refresh the page to show the updated status
    echo "<script>window.location.href='orders.php';</script>";
    exit();
}

// SEND TO CASHIER LOGIC
if (isset($_POST['send_to_cashier'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    
    //Check muna kung sent na o hindi pa approved
    $check_query = mysqli_query($conn, "SELECT sent_to_cashier, status FROM orders WHERE id = '$order_id'");
    $order_data = mysqli_fetch_assoc($check_query);

    if ($order_data['sent_to_cashier'] == 1) {
        echo "<script>alert('Receipt already sent to cashier!'); window.location.href='orders.php';</script>";
        exit();
    } elseif ($order_data['status'] == 'pending') {
        echo "<script>alert('Please approve the order first before sending to cashier.'); window.location.href='orders.php';</script>";
        exit();
    } else {
        $query = "UPDATE orders SET sent_to_cashier = 1 WHERE id = '$order_id'";
        if(mysqli_query($conn, $query)) {
            echo "<script>alert('Receipt successfully sent to cashier!'); window.location.href='orders.php';</script>";
            exit();
        }
    }
}

$orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Orders | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .thermal-preview {
            width: 80mm;
            margin: auto;
            padding: 10px;
            background: #fff;
            font-family: 'Courier New', Courier, monospace;
            border: 1px solid #eee;
            color: #000;
        }
        .line { border-top: 1px dashed #000; margin: 10px 0; }
        .receipt-header h5 { font-weight: bold; margin-bottom: 0; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="d-flex justify-content-between mb-3 shadow-sm p-3 bg-white rounded align-items-center">
        <h4 class="mb-0">📦 Orders Management</h4>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>    
    </div> 
    <div class="table-responsive bg-white p-3 rounded shadow-sm">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-center">Order ID</th>
                    <th class="text-center">Customer</th>
                    <th class="text-center">Total Amount</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Date</th> 
                    <th class="text-center">Customer Info</th>
                    <th class="text-center">Action</th>
                    <th class="text-center">Emailer</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($orders)): ?>
                <tr>
                    <td class="text-center">#<?php echo $row['id']; ?></td>
                    <td class="text-center"><strong><?php echo $row['fullname']; ?></strong></td>
                    
                    <td class="text-center">
                        <div class="fw-bold">₱<?php echo number_format($row['total_amount'], 2); ?></div>
                        <a href="javascript:void(0)" class="small text-primary text-decoration-none" onclick='viewReceipt(<?php echo json_encode($row); ?>)'>
                            <i class="bi bi-receipt"></i> View Receipt
                        </a>
                    </td>
                    <td class="text-center">
                        <span class="badge <?php 
                            echo ($row['status'] == 'pending') ? 'bg-warning text-dark' : 
                                 (($row['status'] == 'completed') ? 'bg-success' : 'bg-info'); 
                        ?>">
                            <?php echo strtoupper($row['status']); ?>
                        </span>
                    </td>
                    <td class="text-center small text-muted">
                        <?php echo date('M d, Y', strtotime($row['order_date'])); ?>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                onclick='viewCustomer(<?php echo json_encode($row); ?>)'>
                            <i class="bi bi-person-lines-fill"></i> Details
                        </button>
                    </td>
                    <td class="text-center"> 
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                            
                            <?php if($row['status'] == 'pending'): ?>
                                <button type="submit" name="status" value="approved" class="btn btn-success btn-sm">Approve</button>
                                <button type="submit" name="status" value="declined" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to decline this order?')">Decline</button>
                            
                            <?php elseif($row['status'] == 'approved'): ?>
                                <button type="submit" name="status" value="preparing" class="btn btn-info btn-sm text-white">Preparing</button>
                            
                            <?php elseif($row['status'] == 'preparing'): ?>
                                <button type="submit" name="status" value="out_for_delivery" class="btn btn-warning btn-sm">Out for Delivery</button>
                            
                            <?php elseif($row['status'] == 'out_for_delivery'): ?>
                                <button type="submit" name="status" value="completed" class="btn btn-primary btn-sm">Complete Order</button>

                            <?php elseif($row['status'] == 'declined'): ?>
                                <span class="badge bg-danger">Order Declined</span>

                            <?php elseif($row['status'] == 'completed'): ?>
                                <span class="text-success small fw-bold">Order Delivered</span>

                            <?php else: ?>
                                <span class="text-muted small"><?php echo ucfirst($row['status']); ?></span>
                            <?php endif; ?>

                            <input type="hidden" name="update_status" value="1">
                        </form>
                    </td>
                    <td class="text-center">
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                            <?php 
                            $alreadySent = (isset($row['email_status']) && $row['email_status'] == $row['status']);
                            $canSend = ($row['status'] != 'pending' && !$alreadySent);
                            ?>
                            <button type="submit" name="send_email_update" 
                                    class="btn btn-sm <?php echo $alreadySent ? 'btn-success' : 'btn-outline-dark'; ?>" 
                                    <?php echo $canSend ? '' : 'disabled'; ?>>
                                <i class="bi bi-envelope-at"></i> <?php echo $alreadySent ? 'Sent' : 'Send Email'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div> 

<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
            </div>
            <div class="modal-body bg-dark py-4">
                <div class="thermal-preview" id="receiptContent"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <form method="POST" action="orders.php">
                    <input type="hidden" name="order_id" id="modal_order_id">
                    <button type="submit" name="send_to_cashier" class="btn btn-warning">
                        Send to Cashier
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Customer Information</h5>
            </div>
            <div class="modal-body p-4">
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase">Name</label>
                        <p id="c-name" class="fw-bold fs-5 mb-0"></p>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase">Surname</label>
                        <p id="c-surname" class="fw-bold fs-5 mb-0"></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase">Contact Number</label>
                        <p id="c-phone" class="mb-0"></p>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase">Email Address</label>
                        <p id="c-email" class="mb-0"></p>
                    </div>
                </div>
                <hr>
                 <div class="row mb-3">
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase">Delivery Address</label>
                        <p id="c-address" class="mb-0"></p>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase">Landmark</label>
                        <p id="c-landmark" class="mb-0"></p>
                    </div>
                </div>
                <div class="bg-light p-3 border-start border-warning border-4 rounded text-center">
                    <label class="text-muted small fw-bold text-uppercase d-block text-start mb-2">Proof of Payment / Downpayment</label>
                    <div id="proof-container"></div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewCustomer(data) {
    document.getElementById('c-name').innerText = data.fullname;
    document.getElementById('c-surname').innerText = data.surname;
    document.getElementById('c-phone').innerText = data.contact_number;
    document.getElementById('c-email').innerText = data.email;
    document.getElementById('c-address').innerText = data.full_address;
    document.getElementById('c-landmark').innerText = data.landmark ? data.landmark : "No specific landmark provided.";
    
    const proofContainer = document.getElementById('proof-container');
    let currentMOP = data.payment_method ? data.payment_method.toUpperCase() : "COD";
    
    // Simulan natin ang content ng proof container block
    let htmlContent = "";

    // Kung GCash o Maya ang ginamit, isingit ang Reference Number text block sa itaas ng image
    if (currentMOP === "GCASH" || currentMOP === "MAYA") {
        let referenceNumber = data.ref_no ? data.ref_no : "Not Provided";
        htmlContent += `
            <div class="alert alert-secondary py-1 px-2 mb-2 text-start small">
                <b>Mode of Payment:</b> <span class="badge bg-primary">${currentMOP}</span><br>
                <b>Reference No:</b> <span class="text-danger fw-bold">${referenceNumber}</span>
            </div>
        `;
    }

    // Isunod ang pag-render ng litrato/photo kung mayroong laman ang database image field
    if (data.proof_of_payment && data.proof_of_payment.trim() !== "" && currentMOP !== "COD") {
        htmlContent += `
            <a href="../${data.proof_of_payment}" target="_blank" title="Click to view full image">
                <img src="../${data.proof_of_payment}" class="img-thumbnail mt-1" style="max-height: 200px; width: auto; object-fit: contain; cursor: pointer; border: 2px solid #ffc107;">
            </a>
            <small class="text-muted d-block mt-1"><i class="bi bi-zoom-in"></i> Click the image to view full size</small>
        `;
    } else {
        htmlContent += `<span class="text-muted italic small">No downpayment required (COD or Amount below ₱5,000)</span>`;
    }
    

    // Inggit ang nabuong design sa proof-container div block
    proofContainer.innerHTML = htmlContent;
    
    var myModal = new bootstrap.Modal(document.getElementById('customerModal'));
    myModal.show();
}
function viewReceipt(data) {
    // I-set ang Order ID para sa modal form submit button
    document.getElementById('modal_order_id').value = data.id;

    // SAFE GUARD: Siguraduhing may laman ang status bago mag-toUpperCase upang maiwasan ang error
    const orderStatus = data.status ? data.status.toUpperCase() : 'PENDING';

    // Decode ang listahan ng mga biniling produkto (cart JSON)
    let items = [];
    try {
        items = JSON.parse(data.cart);
    } catch (e) {
        console.error("Error parsing cart JSON", e);
    }

    // Bumuo ng HTML row para sa bawat item sa resibo
    let itemsHtml = '';
    items.forEach(item => {
        let subtotal = parseFloat(item.price) * parseInt(item.qty);
        itemsHtml += `
            <tr>
                <td>${item.name} (x${item.qty})</td>
                <td align="right">₱${subtotal.toFixed(2)}</td>
            </tr>
        `;
    });
    let paymentMethodName = data.payment_method ? data.payment_method : 'COD';
    let totalAmt = parseFloat(data.total_amount);
    let paymentBreakdownHtml = '';

    if (totalAmt >= 5000) {
        let dpAmount = totalAmt * 0.50;
        let balAmount = totalAmt * 0.50;
        paymentBreakdownHtml = `
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Total Item Amount:</span>
                <span>₱${totalAmt.toFixed(2)}</span>
            </div>
            <div class="d-flex justify-content-between small text-danger mb-1">
                <span>Initial Downpayment (50%):</span>
                <span>₱${dpAmount.toFixed(2)}</span>
            </div>
            <div class="d-flex justify-content-between small fw-bold text-dark">
                <span>REMAINING BALANCE (COD):</span>
                <span>₱${balAmount.toFixed(2)}</span>
            </div>
        `;
    } else {
        paymentBreakdownHtml = `
            <div class="d-flex justify-content-between small fw-bold mb-2">
                <span>TOTAL AMOUNT:</span>
                <span>₱${totalAmt.toFixed(2)}</span>
            </div>
        `;
    }

    // Reference Number section kung gumamit ng GCash o Maya
    let referenceNumberHtml = '';
    if ((paymentMethodName === 'GCash' || paymentMethodName === 'Maya') && data.ref_no) {
        referenceNumberHtml = `
            <div class="text-start small px-1 mb-1">
                <b>Reference No:</b> <span class="text-muted">${data.ref_no}</span>
            </div>
        `;
    }

    // Pagsasama-sama ng lahat ng bahagi para sa kabuuang Thermal Receipt Preview HTML layout
    let fullReceiptHtml = `
        <div class="text-center mb-2">
            <h5 class="fw-bold my-0 text-uppercase">R. Taipan Hardware</h5>
          <small>190 Cabrera Rd. Sitio Burol Brgy. San Juan Taytay, Rizal</small><br>
            <small>Contact: 0907 456 5632</small>
            <div class="line"></div>
           <small><b>OFFICIAL RECEIPT (ONLINE ORDER)</b></small>
            <div class="line"></div>
        </div>
        <p class="small mb-1"><b>Order ID:</b> #${data.id}</p>
        <p class="small mb-1"><b>Date:</b> ${data.order_date}</p>
        <p class="small mb-1"><b>Customer:</b> ${data.fullname} ${data.surname}</p>
        <p class="small mb-1"><b>Contact:</b> ${data.contact_number}</p>
        <p class="small mb-1"><b>Address:</b> ${data.full_address}</p>
        <p class="small mb-2"><b>Landmark:</b> ${data.landmark || 'N/A'}</p> 
        <div class=\"line\"></div>
        <table width="100%" class="small mb-2">${itemsHtml}</table>
        <div class="line"></div>
        
        ${paymentBreakdownHtml}
        
        <div class="line mt-2"></div>
        
        <div class="text-start small px-1 mb-1">
            <b>Mode of Payment:</b> <span class="text-dark fw-bold">${paymentMethodName}</span>
        </div>
       

        <div class="text-center mt-2 bg-light py-1 rounded">
            <small class="text-uppercase fw-bold" style="font-size:0.75rem;">Status: <span class="text-primary">${orderStatus}</span></small>
        </div>
    `;

    // I-inject ang nabuong HTML layout sa receipt layout viewer container ng Modal
    document.getElementById('receiptContent').innerHTML = fullReceiptHtml;

    // Buksan ang Bootstrap Modal instance
    let receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    receiptModal.show();
}
</script>
</body>
</html> 