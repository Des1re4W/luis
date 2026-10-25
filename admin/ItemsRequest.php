<?php
session_start();
require_once __DIR__ . '/../config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libs/src/Exception.php';
require '../libs/src/PHPMailer.php';
require '../libs/src/SMTP.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['username']) ? $_SESSION['username'] : "Admin";

// --- 1. EMAILER ACTION LOGIC (Para sa Manual Send Button) ---
if (isset($_POST['send_email_update'])) {
    $req_id = (int)$_POST['ticket_id'];
    
    $check_req = $pdo->prepare("SELECT cr.*, o.email, o.fullname, o.surname, o.total_amount, o.payment_method, o.payment_scheme, o.cart, o.order_date 
                                FROM customer_requests cr 
                                JOIN orders o ON CAST(cr.order_number AS INTEGER) = o.id 
                                WHERE cr.id = ? AND cr.status != 'Pending'");
    $check_req->execute([$req_id]);
    
    if ($check_req->rowCount() > 0) {
        $ticket_data = $check_req->fetch(PDO::FETCH_ASSOC);
        
        $customer_email = $ticket_data['email'];
        $customer_name = $ticket_data['fullname'] . ' ' . $ticket_data['surname'];
        $request_type = $ticket_data['request_type'];
        $order_number = $ticket_data['order_number'];
        $total_amount = $ticket_data['total_amount'];
        $current_status = $ticket_data['status'];
        $payment_method = strtoupper($ticket_data['payment_method']);
        
        // Kunin ang decline/admin reason mula sa database
        $decline_reason_text = !empty($ticket_data['admin_notes']) ? nl2br(htmlspecialchars($ticket_data['admin_notes'])) : 'No specific reason provided.';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'rtaipancss@gmail.com'; 
            $mail->Password   = 'untvtpzeiqyrkrpx';   
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('rtaipancss@gmail.com', 'R. Taipan Construction Supply and Services');
            $mail->addAddress($customer_email, $customer_name);
            $mail->isHTML(true);
            $mail->Subject = "Customer Service Request Update: Ticket #$req_id";

            if ($request_type == 'cancel') {
                if ($current_status == 'Approved') {
                    $paymentMethodClean = strtoupper(trim($ticket_data['payment_method']));

                    if ($paymentMethodClean === 'CASH ON DELIVERY (COD)' || $paymentMethodClean === 'COD') {
                        $paymentMessage = "<p>Since your payment method is <strong>Cash on Delivery (COD)</strong>, no payment has been collected for this order. Therefore, no refund is required.</p>";
                    } else {
                        $paymentMessage = "<p>Since your payment method is <strong>$payment_method</strong>, the admin will process your refund. You will receive another notification once the refund and its receipt have been sent.</p>";
                    }

                    $mail->Body = "
                        <h3>Dear " . htmlspecialchars($customer_name) . ",</h3>
                        <p>Your request to cancel <strong>Order #$order_number</strong> has been approved.</p>
                        <p>Your order is now cancelled and will no longer be processed for delivery.</p>
                        <p>
                            <strong>Order Total:</strong> ₱" . number_format((float)$total_amount, 2) . "<br>
                            <strong>Mode of Payment:</strong> " . htmlspecialchars($payment_method) . "<br>
                            <strong>Status:</strong> Approved
                        </p>
                        $paymentMessage
                        <p>Thank you for choosing <strong>R. Taipan Hardware</strong>.</p>
                        <br>
                        <p>Sincerely,<br><strong>R. Taipan Hardware Customer Service</strong></p>
                    ";
                } elseif ($current_status == 'Completed') {
                    $mail->Body = "
                        <h3>Hi " . htmlspecialchars($customer_name) . ",</h3>
                        <p>Good day!</p>
                        <p>All processes for your cancellation and refund request regarding Order Number <strong>#$order_number</strong> have been completed.</p>
                        <p>Thank you for choosing R. Taipan Construction Supply and Services, OPC.</p>
                        <br>
                        <h4>Sincerely,<br>R. Taipan Hardware Customer Service</h4>
                    ";
                } elseif ($current_status == 'Declined') {
                    $mail->Body = "
                        <h3>Hi " . htmlspecialchars($customer_name) . ",</h3>
                        <p>Good day!</p>
                        <p>We regret to inform you that your <strong>CANCELLATION request</strong> for Order Number <strong>#$order_number</strong> has been <strong>DECLINED</strong>.</p>
                        <p><strong>Reason:</strong><br>$decline_reason_text</p>
                        <br>
                        <h4>Sincerely,<br>R. Taipan Construction Supply and Services, OPC</h4>
                    ";
                }
            } else {
                if ($current_status == 'Approved') {
                    $item_to_replace_text = !empty($ticket_data['item_to_replace']) ? nl2br(htmlspecialchars($ticket_data['item_to_replace'])) : 'N/A';

                    $mail->Body = "
                        <h3>Dear " . htmlspecialchars($customer_name) . ",</h3>
                        <p>Your exchange item(s) request for <strong>Order #$order_number</strong> has been <strong>APPROVED</strong>.</p>
                        <p><strong>Item(s) to be Replaced:</strong><br>$item_to_replace_text</p>
                        <p>You may now bring the approved item to <strong>R. Taipan Hardware</strong> for exchange.</p>
                        <br>
                        <p>Sincerely,<br><strong>R. Taipan Hardware Customer Service</strong></p>
                    ";
                } elseif ($current_status == 'Completed') {
                    $mail->Body = "
                        <h3>Hi " . htmlspecialchars($customer_name) . ",</h3>
                        <p>Good day!</p>
                        <p>Your request regarding Order Number <strong>#$order_number</strong> is now marked as <strong>COMPLETED</strong>.</p>
                        <br>
                        <h4>Sincerely,<br>R. Taipan Hardware Customer Service</h4>
                    ";
                } elseif ($current_status == 'Declined') {
                    $mail->Body = "
                        <h3>Hi " . htmlspecialchars($customer_name) . ",</h3>
                        <p>Good day!</p>
                        <p>We regret to inform you that your request for Order Number <strong>#$order_number</strong> has been <strong>DECLINED</strong>.</p>
                        <p><strong>Reason:</strong><br>$decline_reason_text</p>
                        <br>
                        <h4>Sincerely,<br>R. Taipan Construction Supply and Services, OPC</h4>
                    ";
                }
            }

            $mail->send();
            $update_mail = $pdo->prepare("UPDATE customer_requests SET email_status = ? WHERE id = ?");
            $update_mail->execute([$current_status, $req_id]);
            $_SESSION['msg'] = "🎉 Email notification successfully sent to $customer_name!";

        } catch (Exception $e) {
            $_SESSION['msg'] = "❌ Failed to send email: {$mail->ErrorInfo}";
        }
    }
    
    $pane_target = ($request_type == 'cancel') ? '#cancel-pane' : '#exchange-pane';
    header("Location: ItemsRequest.php" . $pane_target);
    exit();
}

// --- 2. STATUS UPDATE LOGIC (Kasama ang pag-save ng Decline Reason) ---
if (isset($_POST['update_status'])) {
    $req_id = (int)$_POST['ticket_id'];
    $status = $_POST['status'];
    $decline_reason = isset($_POST['decline_reason']) ? trim($_POST['decline_reason']) : '';
    
    if (in_array($status, ['Approved', 'Declined', 'Completed'])) {
        $check_req = $pdo->prepare("SELECT cr.*, o.cart, o.payment_method 
                                     FROM customer_requests cr 
                                     JOIN orders o ON CAST(cr.order_number AS INTEGER) = o.id 
                                     WHERE cr.id = ?");
        $check_req->execute([$req_id]);
        
        if ($check_req->rowCount() > 0) {
            $ticket_data = $check_req->fetch(PDO::FETCH_ASSOC);
            $request_type = $ticket_data['request_type'];
            $order_number = $ticket_data['order_number'];
            $cart_array = json_decode($ticket_data['cart'], true);

            try {
                $pdo->beginTransaction();

                $extra_updates_sql = "";
                $extra_params = [];
                
                if ($status === 'Completed') {
                    $extra_updates_sql .= ", email_status = ?";
                    $extra_params[] = 'Completed';
                }
                
                if ($status === 'Declined' && !empty($decline_reason)) {
                    $extra_updates_sql .= ", admin_notes = ?";
                    $extra_params[] = $decline_reason;
                }
                
                $extra_params[] = $status;
                $extra_params[] = $req_id;

                $update_stmt = $pdo->prepare("UPDATE customer_requests SET status = ?" . $extra_updates_sql . " WHERE id = ?");
                $update_stmt->execute($extra_params);

                if ($status === 'Approved' && $request_type === 'cancel') {
                    if (is_array($cart_array)) {
                        foreach ($cart_array as $item) {
                            $qty = (int)$item['qty'];
                            $product_id = $item['id'];
                            $variant_id = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;

                            if ($variant_id) {
                                $up_var = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
                                $up_var->execute([$qty, $variant_id]);
                            } else {
                                $up_prod = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                                $up_prod->execute([$qty, $product_id]);
                            }
                        }
                    }
                    $up_ord = $pdo->prepare("UPDATE orders SET status = 'cancelled', sent_to_cashier = 0 WHERE id = ?");
                    $up_ord->execute([$order_number]);
                }

                $pdo->commit();
                $_SESSION['msg'] = "🎉 Ticket #$req_id status successfully updated to $status!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['msg'] = "❌ Failed to update status: " . $e->getMessage();
            }
        }
    }
    $pane_target = ($request_type == 'cancel') ? '#cancel-pane' : '#exchange-pane';
    header("Location: ItemsRequest.php" . $pane_target);
    exit();
}

// --- QUICK GET STATUS UPDATE HANDLER (Para sa Approve links) ---
if (isset($_GET['id']) && isset($_GET['status'])) {
    $req_id = (int)$_GET['id'];
    $status = $_GET['status'];
    
    if (in_array($status, ['Approved', 'Completed'])) {
        $check_req = $pdo->prepare("SELECT cr.*, o.cart, o.payment_method 
                                     FROM customer_requests cr 
                                     JOIN orders o ON CAST(cr.order_number AS INTEGER) = o.id 
                                     WHERE cr.id = ?");
        $check_req->execute([$req_id]);
        
        if ($check_req->rowCount() > 0) {
            $ticket_data = $check_req->fetch(PDO::FETCH_ASSOC);
            $request_type = $ticket_data['request_type'];
            $order_number = $ticket_data['order_number'];
            $cart_array = json_decode($ticket_data['cart'], true);

            try {
                $pdo->beginTransaction();

                $up_req = $pdo->prepare("UPDATE customer_requests SET status = ? WHERE id = ?");
                $up_req->execute([$status, $req_id]);

                if ($status === 'Approved' && $request_type === 'cancel') {
                    if (is_array($cart_array)) {
                        foreach ($cart_array as $item) {
                            $qty = (int)$item['qty'];
                            $product_id = $item['id'];
                            $variant_id = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;

                            if ($variant_id) {
                                $up_var = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
                                $up_var->execute([$qty, $variant_id]);
                            } else {
                                $up_prod = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                                $up_prod->execute([$qty, $product_id]);
                            }
                        }
                    }
                    $up_ord = $pdo->prepare("UPDATE orders SET status = 'cancelled', sent_to_cashier = 0 WHERE id = ?");
                    $up_ord->execute([$order_number]);
                }

                $pdo->commit();
                $_SESSION['msg'] = "🎉 Ticket #$req_id status successfully updated to $status!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['msg'] = "❌ Failed to update status: " . $e->getMessage();
            }
        }
    }
    
    $pane_target = ($request_type == 'cancel') ? '#cancel-pane' : '#exchange-pane';
    header("Location: ItemsRequest.php" . $pane_target);
    exit();
}

// --- 3. REFUND SUBMISSION LOGIC ---
if (isset($_POST['submit_refund'])) {
    $req_id = (int)$_POST['ticket_id'];
    $ref_no = trim($_POST['refund_reference']);
    
    $proof_filename = "";
    if (isset($_FILES['refund_receipt']) && $_FILES['refund_receipt']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['refund_receipt']['tmp_name'];
        $file_name = $_FILES['refund_receipt']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed_exts)) {
            $proof_filename = "refund_" . $req_id . "_" . time() . "." . $file_ext;
            move_uploaded_file($file_tmp, "../uploads/" . $proof_filename);
        }
    }

    if (!empty($proof_filename)) {
        $up_ref = $pdo->prepare("UPDATE customer_requests SET status = 'Completed', refund_status = 'Refunded', refund_receipt = ?, refund_reference = ?, email_status = 'Completed' WHERE id = ?");
        $up_ref->execute([$proof_filename, $ref_no, $req_id]);

        $get_info = $pdo->prepare("SELECT cr.*, o.email, o.fullname, o.surname, o.total_amount, o.payment_method, o.payment_scheme 
                                   FROM customer_requests cr 
                                   JOIN orders o ON CAST(cr.order_number AS INTEGER) = o.id 
                                   WHERE cr.id = ?");
        $get_info->execute([$req_id]);
        
        if ($get_info->rowCount() > 0) {
            $t_data = $get_info->fetch(PDO::FETCH_ASSOC);
            $customer_email = $t_data['email'];
            $customer_name = $t_data['fullname'] . ' ' . $t_data['surname'];
            $order_number = $t_data['order_number'];
            $total_amount = $t_data['total_amount'];
            $payment_method = strtoupper($t_data['payment_method']);
            $payment_scheme = strtolower($t_data['payment_scheme']);
            
            $ref_amount = $total_amount;
            if ($total_amount >= 5000 && strpos($payment_scheme, '50') !== false) {
                $ref_amount = $total_amount * 0.5;
            }

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'rtaipancss@gmail.com'; 
                $mail->Password   = 'untvtpzeiqyrkrpx';   
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('rtaipancss@gmail.com', 'R. Taipan Construction Supply and Services');
                $mail->addAddress($customer_email, $customer_name);
                $mail->isHTML(true);
                $mail->Subject = "Refund Update: Order #$order_number has been refunded";

                $proofImgTag = "";
                if (!empty($proof_filename)) {
                    $mail->addEmbeddedImage("../uploads/$proof_filename", 'refund_receipt_cid');
                    $proofImgTag = "<br><p><strong>Refund Proof (Receipt):</strong><br><img src='cid:refund_receipt_cid' style='max-width:300px; border:1px solid #ddd; border-radius:6px;'></p>";
                }

                $mail->Body = "
                    <h3>Hi " . htmlspecialchars($customer_name) . ",</h3>
                    <p>Good day!</p>
                    <p>We would like to inform you that your refund for Order Number <strong>#$order_number</strong> has been sent via <strong>$payment_method</strong></p>
                    <p>
                        <strong>Refund Amount:</strong> ₱" . number_format((float)$ref_amount, 2) . "<br>
                        <strong>Reference Number:</strong> " . htmlspecialchars($ref_no) . "<br>
                    </p>
                    $proofImgTag
                    <p>Please check your e-wallet account. Thank you for your trust.</p>
                    <br>
                    <h4>Sincerely,<br>R. Taipan Construction Supply and Services, OPC</h4>
                ";

                $mail->send();
                $_SESSION['msg'] = "🎉 Refund receipt uploaded, email successfully sent, and ticket marked as Completed!";
            } catch (Exception $e) {
                $_SESSION['msg'] = "⚠️ Receipt uploaded and completed, but failed to send email: {$mail->ErrorInfo}";
            }
        }
    } else {
        $_SESSION['msg'] = "❌ Please upload a valid image for the refund receipt.";
    }

    header("Location: ItemsRequest.php#cancel-pane");
    exit();
}

// Inayos ang mga JOIN query sa ibaba gamit ang CAST(cr.order_number AS INTEGER)
$cancel_result   = $pdo->query("SELECT cr.*, o.order_date, o.fullname, o.surname, o.contact_number, o.full_address, o.landmark, o.cart, o.payment_method, o.payment_scheme, o.total_amount, o.ref_no, o.status as order_status FROM customer_requests cr LEFT JOIN orders o ON CAST(cr.order_number AS INTEGER) = o.id WHERE cr.request_type = 'cancel' ORDER BY cr.id DESC");
$exchange_result = $pdo->query("SELECT cr.*, o.order_date, o.fullname, o.surname, o.contact_number, o.full_address, o.landmark, o.cart, o.payment_method, o.payment_scheme, o.total_amount, o.ref_no, o.status as order_status FROM customer_requests cr LEFT JOIN orders o ON CAST(cr.order_number AS INTEGER) = o.id WHERE cr.request_type IN ('exchange', 'return', 'others') ORDER BY cr.id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Items Request Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; margin: 0; overflow-x: hidden; padding-top: 56px; }
        
        .navbar-custom {
            height: 56px;
            z-index: 1100;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #051c3c !important;
        }

        .sidebar { 
            position: fixed; 
            top: 56px; 
            left: 0; 
            width: 260px; 
            height: calc(100vh - 56px); 
            background: #001f4d; 
            padding: 20px; 
            overflow-y: auto; 
            z-index: 1040; 
            display: flex; 
            flex-direction: column; 
            box-shadow: 4px 0 15px rgba(0,0,0,0.15);
            transition: transform 0.3s ease; 
        }

        .sidebar a { color: white; text-decoration: none; padding: 12px 14px; border-radius: 8px; margin-bottom: 8px; transition: 0.3s; font-size: 15px; display: block; font-weight: 500; }
        .sidebar a i { margin-right: 8px; }
        .sidebar a:hover { background: #0d6efd; padding-left: 20px; }
        .sidebar .active { background: #0d6efd; }
        .logout-section { margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2); }
        
        .main { 
            margin-left: 260px; 
            padding: 20px; 
            min-height: calc(100vh - 56px); 
            transition: margin 0.3s ease; 
        }
        
        .card-custom { border: none; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .nav-pills .nav-link { color: #495057; font-weight: 500; border-radius: 8px; margin-right: 5px; font-size: 0.9rem; }
        .nav-pills .nav-link.active { background-color: #0d6efd; color: white; }
        .img-thumb-preview { width: 45px; height: 45px; object-fit: cover; border-radius: 6px; border: 2px solid #dee2e6; cursor: pointer; transition: 0.2s; }
        .img-thumb-preview:hover { transform: scale(1.05); border-color: #0d6efd; }
        .details-box { width: 100%; min-width: 130px; white-space: pre-wrap; word-wrap: break-word; font-size: 0.78rem !important; }

        .table-responsive { overflow-x: auto; position: relative; }
        .table th, .table td { vertical-align: middle; padding: 0.6rem 0.5rem; font-size: 0.85rem; }
        
        .sticky-action { position: sticky; right: 85px; background: #fff; z-index: 2; box-shadow: -2px 0 5px rgba(0,0,0,0.03); }
        .sticky-emailer { position: sticky; right: 0; background: #fff; z-index: 2; box-shadow: -2px 0 5px rgba(0,0,0,0.03); }
        table.table-hover tbody tr:hover td.sticky-action,
        table.table-hover tbody tr:hover td.sticky-emailer { background-color: #f8f9fa; }

        .thermal-preview { width: 80mm; margin: auto; padding: 10px; background: #fff; font-family: 'Courier New', Courier, monospace; border: 1px solid #eee; color: #000; }
        .line { border-top: 1px dashed #000; margin: 10px 0; }

        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; padding: 15px 10px; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom px-3 d-lg-none">
    <div class="d-flex align-items-center w-100 justify-content-between">
        <span class="navbar-brand fw-bold fs-6"><i class="bi bi-headset"></i> Admin Service Request</span>
        <button class="btn btn-outline-light btn-sm" type="button" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
    </div>
</nav>

<nav class="navbar navbar-dark navbar-custom px-3 d-none d-lg-flex">
    <div class="d-flex align-items-center w-100 justify-content-between">
        <span class="navbar-brand fw-bold mb-0">Admin Panel</span>
        <div class="d-flex align-items-center text-white gap-3">
            <span>Welcome, <strong><?= htmlspecialchars($admin_name); ?></strong></span>
        </div>
    </div>
</nav>

<div class="sidebar" id="appSidebar">
    <a href="dashboard.php"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="products.php"><i class="bi bi-box-seam"></i> Inventory</a>
    <a href="orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a href="ItemsRequest.php" class="active"><i class="bi bi-arrow-return-left"></i> Customer Service Request</a>
    <a href="damageItems.php"><i class="bi bi-exclamation-triangle"></i> Damaged Items</a>
    <a href="accountManagement.php"><i class="bi bi-person-circle"></i> Account Management</a>
    <div class="logout-section">
        <a href="../logout.php" class="text-danger" onclick="return confirm('Are you sure you want to log out?')"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="main">
    <div class="card card-custom p-3 p-md-4 bg-white mt-2 mt-md-3">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-4 gap-2">
            <div>
                <h3 class="fw-bold text-dark mb-0 fs-4 fs-md-3"><i class="bi bi-headset text-primary"></i> Customer Ticket Request Center</h3>
                <p class="text-muted small mb-0">View and process customer cancellations, returns, and exchanges.</p>
            </div>
        </div>

        <?php if(isset($_SESSION['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show card-custom mb-4">
                <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <ul class="nav nav-pills mb-4 bg-light p-2 rounded-3 flex-column flex-sm-row" id="requestCenterTabs" role="tablist">
            <li class="nav-item mb-2 mb-sm-0"><button class="nav-link active w-100 text-center text-sm-start" id="cancel-tab" data-bs-toggle="pill" data-bs-target="#cancel-pane" type="button" role="tab"><i class="bi bi-x-circle"></i> Cancellations</button></li>
            <li class="nav-item"><button class="nav-link w-100 text-center text-sm-start" id="exchange-tab" data-bs-toggle="pill" data-bs-target="#exchange-pane" type="button" role="tab"><i class="bi bi-arrow-left-right"></i> Exchanges & Inquiries</button></li>
        </ul>

        <div class="tab-content" id="requestCenterTabsContent">
            
            <!-- CANCELLATIONS TAB -->
            <div class="tab-pane fade show active" id="cancel-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle border-top text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Account</th>
                                <th>Payment Type</th>
                                <th>Reason</th>
                                <th>Comments</th>
                                <th>Status</th>
                                <th class="text-center sticky-action bg-light">Action</th>
                                <th class="text-center sticky-emailer bg-light">Emailer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $cancel_rows = $cancel_result->fetchAll(PDO::FETCH_ASSOC);
                            if(count($cancel_rows) == 0): 
                            ?>
                                <tr><td colspan="8" class="text-center text-muted py-3">No cancellation requests found.</td></tr>
                            <?php endif; ?>
                            <?php foreach($cancel_rows as $row): ?>
                                <tr>
                                    <td class="fw-bold text-primary">#<?php echo htmlspecialchars($row['order_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['account']); ?></td>
                                    <td>
                                        <span class="badge bg-dark"><?php echo strtoupper(htmlspecialchars($row['payment_method'])); ?></span>
                                        <?php if(!empty($row['payment_scheme'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($row['payment_scheme']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['reason']); ?></span></td>
                                    <td class="small text-muted details-box" style="white-space: normal;">
                                        <?php 
                                        $details_text = $row['details'] ?: 'None';
                                        if (strpos($details_text, 'User Notes:') !== false) {
                                            $parts = explode('User Notes:', $details_text);
                                            $details_text = trim(end($parts));
                                        }
                                        echo htmlspecialchars($details_text); 
                                        ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1 align-items-start">
                                            <span class="badge <?php 
                                                if ($row['status'] == 'Pending') echo 'bg-warning text-dark';
                                                elseif ($row['status'] == 'Approved') echo 'bg-success';
                                                elseif ($row['status'] == 'Completed') echo 'bg-primary';
                                                elseif ($row['status'] == 'Declined') echo 'bg-danger';
                                                else echo 'bg-dark';
                                            ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                            
                                            <?php if($row['status'] == 'Declined' && !empty($row['admin_notes'])): ?>
                                                <small class="text-danger" style="white-space: normal;"><strong>Decline Reason:</strong> <?php echo htmlspecialchars($row['admin_notes']); ?></small>
                                            <?php endif; ?>

                                            <a href="javascript:void(0)" class="small text-primary text-decoration-none" onclick='viewReceipt(<?php echo htmlspecialchars(json_encode([
                                                'id' => $row['order_number'],
                                                'order_date' => $row['order_date'],
                                                'fullname' => $row['fullname'],
                                                'surname' => $row['surname'],
                                                'contact_number' => $row['contact_number'],
                                                'full_address' => $row['full_address'],
                                                'landmark' => $row['landmark'],
                                                'cart' => $row['cart'],
                                                'payment_method' => $row['payment_method'],
                                                'payment_scheme' => $row['payment_scheme'],
                                                'total_amount' => $row['total_amount'],
                                                'ref_no' => $row['ref_no'] ?? '',
                                                'status' => $row['order_status']
                                            ]), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                <i class="bi bi-receipt"></i> View Receipt
                                            </a>
                                        </div>
                                    </td>
                                    <td class="text-center sticky-action">
                                        <?php if($row['status'] == 'Pending'): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <a href="ItemsRequest.php?id=<?php echo $row['id']; ?>&status=Approved" class="btn btn-sm btn-success px-2 py-1" onclick="return confirm('Approve this cancellation request?')">Approve</a>
                                                <button type="button" class="btn btn-sm btn-danger px-2 py-1" data-bs-toggle="modal" data-bs-target="#declineModal<?php echo $row['id']; ?>">Decline</button>
                                            </div>
                                        <?php elseif($row['status'] == 'Approved'): ?>
                                            <?php 
                                            $pay_method = strtoupper($row['payment_method']);
                                            $needs_refund = ($pay_method === 'GCASH' || $pay_method === 'MAYA');
                                            ?>
                                            <?php if($needs_refund): ?>
                                                <button class="btn btn-sm btn-warning px-2 py-1 w-100" data-bs-toggle="modal" data-bs-target="#refundModal<?php echo $row['id']; ?>">
                                                    <i class="bi bi-cash-coin"></i> Refund Payment
                                                </button>
                                            <?php else: ?>
                                                <a href="ItemsRequest.php?id=<?php echo $row['id']; ?>&status=Completed" class="btn btn-sm btn-primary px-2 py-1 w-100" onclick="return confirm('Mark as Completed?')">Mark Completed</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="bi bi-check-circle"></i> Handled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center sticky-emailer">
                                        <form method="POST">
                                            <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
                                            <?php 
                                            $isCompleted = ($row['status'] === 'Completed');
                                            $alreadySent = (isset($row['email_status']) && $row['email_status'] === $row['status']) || $isCompleted;
                                            $canSend = ($row['status'] !== 'Pending' && !$alreadySent);
                                            ?>
                                            <?php if($alreadySent): ?>
                                                <button type="button" class="btn btn-sm btn-success" disabled>
                                                    <i class="bi bi-check2-all"></i> Sent
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="send_email_update" class="btn btn-sm btn-outline-dark" <?php echo $canSend ? '' : 'disabled'; ?>>
                                                    <i class="bi bi-envelope-at"></i> Send
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>

                                <!-- DECLINE REASON MODAL -->
                                <?php if($row['status'] == 'Pending'): ?>
                                <div class="modal fade" id="declineModal<?php echo $row['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="ItemsRequest.php" method="POST">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Decline Ticket #<?php echo $row['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-start">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="status" value="Declined">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Reason for Declining</label>
                                                        <textarea name="decline_reason" class="form-control" rows="3" placeholder="Enter reason why this request is declined..." required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger btn-sm">Confirm Decline</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- REFUND PAYMENT MODAL -->
                                <?php if($row['status'] == 'Approved' && ($pay_method === 'GCASH' || $pay_method === 'MAYA')): ?>
                                <div class="modal fade" id="refundModal<?php echo $row['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="ItemsRequest.php" method="POST" enctype="multipart/form-data">
                                                <div class="modal-header bg-warning text-dark">
                                                    <h5 class="modal-title fw-bold">Process Refund for Ticket #<?php echo $row['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-start">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">E-Wallet Reference Number</label>
                                                        <input type="text" name="refund_reference" class="form-control" placeholder="Enter transaction reference number..." required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Upload Refund Receipt / Proof</label>
                                                        <input type="file" name="refund_receipt" class="form-control" accept="image/*" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="submit_refund" class="btn btn-warning btn-sm fw-bold">Submit Refund & Complete</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- EXCHANGES & INQUIRIES TAB -->
            <div class="tab-pane fade" id="exchange-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle border-top text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Account</th>
                                <th>Type</th>
                                <th>Proof</th>
                                <th>Item to be Replaced</th>
                                <th>Reason / Comments</th>
                                <th>Status</th>
                                <th class="text-center sticky-action bg-light">Action</th>
                                <th class="text-center sticky-emailer bg-light">Emailer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $exchange_rows = $exchange_result->fetchAll(PDO::FETCH_ASSOC);
                            if(count($exchange_rows) == 0): 
                            ?>
                                <tr><td colspan="9" class="text-center text-muted py-3">No ticket requests found.</td></tr>
                            <?php endif; ?>
                            <?php foreach($exchange_rows as $row): ?>
                                <tr>
                                    <td class="fw-bold text-primary">#<?php echo htmlspecialchars($row['order_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['account']); ?></td>
                                    <td>
                                        <div class="d-flex flex-column gap-1 align-items-start">
                                            <span class="badge <?php echo ($row['request_type'] === 'others') ? 'bg-secondary' : 'bg-info text-dark'; ?>">
                                                <?php echo strtoupper(htmlspecialchars($row['request_type'])); ?>
                                            </span>
                                            <a href="javascript:void(0)" class="small text-primary text-decoration-none" onclick='viewReceipt(<?php echo htmlspecialchars(json_encode([
                                                'id' => $row['order_number'],
                                                'order_date' => $row['order_date'],
                                                'fullname' => $row['fullname'],
                                                'surname' => $row['surname'],
                                                'contact_number' => $row['contact_number'],
                                                'full_address' => $row['full_address'],
                                                'landmark' => $row['landmark'],
                                                'cart' => $row['cart'],
                                                'payment_method' => $row['payment_method'],
                                                'payment_scheme' => $row['payment_scheme'],
                                                'total_amount' => $row['total_amount'],
                                                'ref_no' => $row['ref_no'] ?? '',
                                                'status' => $row['order_status']
                                            ]), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                <i class="bi bi-receipt"></i> View Receipt
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $img_path = "";
                                        if(!empty($row['product_image'])) {
                                            if(file_exists("../uploads/" . $row['product_image'])) { $img_path = "../uploads/" . $row['product_image']; }
                                            elseif(file_exists("uploads/" . $row['product_image'])) { $img_path = "uploads/" . $row['product_image']; }
                                        }
                                        if(!empty($img_path)): 
                                        ?>
                                            <a href="<?php echo $img_path; ?>" target="_blank">
                                                <img src="<?php echo $img_path; ?>" class="img-thumb-preview" title="View Image">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small italic"><i class="bi bi-image"></i> None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="p-2 bg-light border text-dark rounded font-monospace details-box">
                                            <?php 
                                            $replace_text = $row['item_to_replace'] ?? '';
                                            echo !empty(trim($replace_text)) ? nl2br(htmlspecialchars($replace_text)) : '<span class="text-muted italic">N/A</span>'; 
                                          ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="p-2 bg-white border text-secondary rounded details-box">
                                            <strong>Reason:</strong> <?php echo htmlspecialchars($row['reason']); ?><br>
                                            <strong>Comment:</strong> 
                                            <?php 
                                            $ex_details = $row['details'] ?? '';
                                            if (strpos($ex_details, 'User Notes:') !== false) {
                                                $ex_parts = explode('User Notes:', $ex_details);
                                                $ex_details = trim(end($ex_parts));
                                            }
                                            echo !empty(trim($ex_details)) ? nl2br(htmlspecialchars($ex_details)) : '<span class="text-muted italic">None</span>'; 
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1 align-items-start">
                                            <span class="badge <?php 
                                                if ($row['status'] == 'Pending') echo 'bg-warning text-dark';
                                                elseif ($row['status'] == 'Approved') echo 'bg-success';
                                                elseif ($row['status'] == 'Completed') echo 'bg-primary';
                                                elseif ($row['status'] == 'Declined') echo 'bg-danger';
                                                else echo 'bg-dark';
                                            ?>"><?php echo htmlspecialchars($row['status']); ?></span>

                                            <?php if($row['status'] == 'Declined' && !empty($row['admin_notes'])): ?>
                                                <small class="text-danger" style="white-space: normal;"><strong>Decline Reason:</strong> <?php echo htmlspecialchars($row['admin_notes']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center sticky-action">
                                        <?php if($row['status'] == 'Pending'): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <a href="ItemsRequest.php?id=<?php echo $row['id']; ?>&status=Approved" class="btn btn-sm btn-success px-2 py-1" onclick="return confirm('Approve this request?')">Approve</a>
                                                <button type="button" class="btn btn-sm btn-danger px-2 py-1" data-bs-toggle="modal" data-bs-target="#declineModalEx<?php echo $row['id']; ?>">Decline</button>
                                            </div>
                                        <?php elseif($row['status'] == 'Approved'): ?>
                                            <a href="ItemsRequest.php?id=<?php echo $row['id']; ?>&status=Completed" class="btn btn-sm btn-primary px-2 py-1 w-100" onclick="return confirm('Mark as Completed?')">Mark Completed</a>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="bi bi-check-circle-fill text-primary"></i> Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center sticky-emailer">
                                        <form method="POST">
                                            <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
                                            <?php 
                                            $isCompleted = ($row['status'] === 'Completed');
                                            $alreadySent = (isset($row['email_status']) && $row['email_status'] === $row['status']) || $isCompleted;
                                            $canSend = ($row['status'] !== 'Pending' && !$alreadySent);
                                            ?>
                                            <?php if($alreadySent): ?>
                                                <button type="button" class="btn btn-sm btn-success" disabled>
                                                    <i class="bi bi-check2-all"></i> Sent
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="send_email_update" class="btn btn-sm btn-outline-dark" <?php echo $canSend ? '' : 'disabled'; ?>>
                                                    <i class="bi bi-envelope-at"></i> Send
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>

                                <!-- DECLINE REASON MODAL FOR EXCHANGE/RETURN -->
                                <?php if($row['status'] == 'Pending'): ?>
                                <div class="modal fade" id="declineModalEx<?php echo $row['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="ItemsRequest.php" method="POST">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Decline Ticket #<?php echo $row['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-start">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="status" value="Declined">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Reason for Declining</label>
                                                        <textarea name="decline_reason" class="form-control" rows="3" placeholder="Enter reason why this request is declined..." required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger btn-sm">Confirm Decline</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- RECEIPT MODAL -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-dark py-4">
                <div class="thermal-preview" id="receiptContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    let currentHash = window.location.hash;
    if (currentHash) {
        let targetTabButton = document.querySelector(`button[data-bs-target="${currentHash}"]`);
        if (targetTabButton) {
            let triggerEl = new bootstrap.Tab(targetTabButton);
            triggerEl.show();
        }
    }

    document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(button => {
        button.addEventListener('shown.bs.tab', function(e) {
            history.replaceState(null, null, e.target.getAttribute('data-bs-target'));
        });
    });

    const sidebarToggle = document.getElementById('sidebarToggle');
    const appSidebar = document.getElementById('appSidebar');
    if(sidebarToggle && appSidebar) {
        sidebarToggle.addEventListener('click', function() {
            appSidebar.classList.toggle('show');
        });
        
        document.addEventListener('click', function(event) {
            if (window.innerWidth < 992) {
                if (!appSidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                    appSidebar.classList.remove('show');
                }
            }
        });
    }
});

function viewReceipt(data) {
    const orderStatus = (data.status || 'pending').toUpperCase();

    let items = [];
    try {
        let cartObj = (typeof data.cart === "string") ? JSON.parse(data.cart) : data.cart;
        items = Object.values(cartObj || {});
    } catch (e) {
        items = [];
    }

    let itemsHtml = '';
    items.forEach(item => {
        let price = parseFloat(item.price || 0);
        let qty = parseInt(item.qty || 0);
        let subtotal = price * qty;
        itemsHtml += `<tr><td>${item.name} (x${qty})</td><td align="right">₱${subtotal.toFixed(2)}</td></tr>`;
    });

    let paymentMethodName = (data.payment_method || 'COD').toUpperCase();
    let paymentScheme = (data.payment_scheme || '').toLowerCase();
    let totalAmt = parseFloat(data.total_amount || 0);
    let paymentBreakdownHtml = '';

    if (paymentMethodName === 'COD') {
        paymentBreakdownHtml = `<div class="d-flex justify-content-between small fw-bold mb-2"><span>TOTAL AMOUNT:</span><span>₱${totalAmt.toFixed(2)}</span></div><div class="text-muted small">Cash on Delivery</div>`;
    } else if (totalAmt >= 5000 && paymentScheme.includes('50')) {
        let downpayment = totalAmt * 0.5;
        let balance = totalAmt - downpayment;
        paymentBreakdownHtml = `<div class="d-flex justify-content-between small fw-bold mb-1"><span>TOTAL AMOUNT:</span><span>₱${totalAmt.toFixed(2)}</span></div><div class="d-flex justify-content-between small text-success fw-bold mb-1"><span>PAID (50%):</span><span>₱${downpayment.toFixed(2)}</span></div><div class="line"></div><div class="d-flex justify-content-between small fw-bold text-danger"><span>BALANCE:</span><span>₱${balance.toFixed(2)}</span></div>`;
    } else {
        paymentBreakdownHtml = `<div class="d-flex justify-content-between small fw-bold mb-1"><span>TOTAL AMOUNT:</span><span>₱${totalAmt.toFixed(2)}</span></div><div class="d-flex justify-content-between small text-success fw-bold mb-1"><span>PAID VIA ${paymentMethodName}:</span><span>₱${totalAmt.toFixed(2)}</span></div>`;
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
        <p class="small mb-1"><b>Order ID:</b> #${data.id}</p>
        <p class="small mb-1"><b>Date:</b> ${data.order_date}</p>
        <p class="small mb-1"><b>Customer:</b> ${data.fullname} ${data.surname}</p>
        <p class="small mb-1"><b>Contact:</b> ${data.contact_number}</p>
        <p class="small mb-1"><b>Address:</b> ${data.full_address}</p>
        <p class="small mb-2"><b>Landmark:</b> ${data.landmark || 'N/A'}</p>
        <div class="line"></div>
        <table width="100%" class="small mb-2">${itemsHtml}</table>
        <div class="line"></div>
        ${paymentBreakdownHtml}
        <div class="line mt-2"></div>
        <div class="text-start small"><b>Mode of Payment:</b> ${paymentMethodName}</div>
        <div class="text-center mt-2"><small class="fw-bold">STATUS: ${orderStatus}</small></div>
    `;

    document.getElementById('receiptContent').innerHTML = fullReceiptHtml;
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
    document.activeElement.blur();
}
</script>
</body> 
</html>