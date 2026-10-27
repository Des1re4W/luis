<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// --- 1. PHPMAILER START ---
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
    $order_id = filter_var($_POST['order_id'] ?? 0, FILTER_VALIDATE_INT);

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo "<script>alert('Order not found!'); window.location.href='orders.php';</script>";
        exit();
    }

    $current_status = $order['status'];

    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rtaipancss@gmail.com';
        $mail->Password   = 'untvtpzeiqyrkrpx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(
            'rtaipancss@gmail.com',
            'R. Taipan Construction Supply and Services'
        );

        $mail->addAddress(
            $order['email'],
            $order['fullname']
        );

        $mail->isHTML(true);
        $mail->Subject = "Order Status Update: #$order_id";

        // =========================
        // APPROVED
        // =========================
        if ($current_status == 'approved') {

            $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt_items->execute([$order_id]);
            $items_query = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            $totalAmt = (float)$order['total_amount'];

            $paymentMethodName = strtoupper(
                trim($order['payment_method'] ?? 'COD')
            );

            $paymentScheme = strtolower(
                trim($order['payment_scheme'] ?? '')
            );
            $paymentBreakdownHtml = '';

            // COD
            if (strpos($paymentMethodName, 'COD') !== false) {
                $paymentBreakdownHtml = "
                    <div style='display:flex;justify-content:space-between;font-size:14px;font-weight:bold;margin-bottom:5px;'>
                        <span>TOTAL AMOUNT:</span>
                        <span>₱" . number_format($totalAmt, 2) . "</span>
                    </div>
                    <div style='color:#6c757d;font-size:13px;'>
                        Cash on Delivery (Pay upon delivery)
                    </div>
                ";
            } 
            // 50% DOWNPAYMENT
            elseif (
                $totalAmt >= 5000 &&
                (
                    strpos($paymentScheme, '50') !== false ||
                    strpos($paymentScheme, 'down') !== false
                )
            ) {
                $downpayment = isset($order['paid_amount'])
                    ? (float)$order['paid_amount']
                    : ($totalAmt * 0.5);
                $balance = $totalAmt - $downpayment;
                $paymentBreakdownHtml = "
                    <div style='display:flex;justify-content:space-between;font-size:14px;font-weight:bold;margin-bottom:5px;'>
                        <span>TOTAL AMOUNT:</span>
                        <span>₱" . number_format($totalAmt, 2) . "</span>
                    </div>
                    <div style='display:flex;justify-content:space-between;font-size:14px;color:#198754;font-weight:bold;margin-bottom:5px;'>
                        <span>PAID VIA {$paymentMethodName}:</span>
                        <span>₱" . number_format($downpayment, 2) . "</span>
                    </div>
                    <div style='border-top:1px dashed #000;margin:8px 0;'></div>
                    <div style='display:flex;justify-content:space-between;font-size:14px;font-weight:bold;color:#dc3545;'>
                        <span>PAY UPON DELIVERY:</span>
                        <span>₱" . number_format($balance, 2) . "</span>
                    </div>";
            } 
            // GCASH / MAYA FULL PAYMENT
            elseif (
                $paymentMethodName === 'GCASH' ||
                $paymentMethodName === 'MAYA'
            ) {
                $paidAmount = isset($order['paid_amount'])
                    ? (float)$order['paid_amount']
                    : $totalAmt;
                $paymentBreakdownHtml = "
                    <div style='display:flex;justify-content:space-between;font-size:14px;font-weight:bold;margin-bottom:5px;'>
                        <span>TOTAL AMOUNT:</span>
                        <span>₱" . number_format($totalAmt, 2) . "</span>
                    </div>
                    <div style='display:flex;justify-content:space-between;font-size:14px;color:#198754;font-weight:bold;margin-bottom:5px;'>
                        <span>PAID VIA {$paymentMethodName}:</span>
                        <span>₱" . number_format($paidAmount, 2) . "</span>
                    </div>
                ";
            } else {
                $paymentBreakdownHtml = "
                    <div style='display:flex;justify-content:space-between;font-size:14px;font-weight:bold;'>
                        <span>TOTAL AMOUNT:</span>
                        <span>₱" . number_format($totalAmt, 2) . "</span>
                    </div>
                ";
            }
        
            // RECEIPT HTML
            ob_start();
            ?>
            <div style="
                border:1px solid #ddd;
                padding:20px;
                max-width:600px;
                margin:0 auto;
                font-family:'Courier New',Courier,monospace;
                background:#ffffff;
                color:#000000;
            ">
                <div style="text-align:center;margin-bottom:15px;">
                    <h3 style="margin:0;text-transform:uppercase;">
                        R. Taipan Hardware
                    </h3>
                    <span style="font-size:12px;">
                        190 Cabrera Rd. Sitio Burol Brgy. San Juan Taytay, Rizal
                    </span>
                    <br>
                    <span style="font-size:12px;">
                        Contact: 0907 456 5632
                    </span>
                    <div style="border-top:1px dashed #000;margin:10px 0;"></div>
                    <span style="font-size:12px;">
                        <b>OFFICIAL RECEIPT (ONLINE ORDER)</b>
                    </span>
                    <div style="border-top:1px dashed #000;margin:10px 0;"></div>
                </div>
                <p style="font-size:13px;margin:3px 0;">
                    <b>Order ID:</b> #<?php echo $order_id; ?>
                </p>
                <p style="font-size:13px;margin:3px 0;">
                    <b>Customer:</b>
                    <?php echo htmlspecialchars($order['fullname']); ?>
                </p>
                <div style="border-top:1px dashed #000;margin:10px 0;"></div>
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <tbody>
                    <?php foreach ($items_query as $item) {
                        $price = (float)$item['price'];
                        $qty = (int)$item['qty'];
                        $subtotal = $price * $qty;
                    ?>
                        <tr>
                            <td style="padding:4px 0;">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                                (x<?php echo $qty; ?>)
                            </td>
                            <td style="padding:4px 0;text-align:right;">
                                ₱<?php echo number_format($subtotal, 2); ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>

                <div style="border-top:1px dashed #000;margin:10px 0;"></div>
                <?php echo $paymentBreakdownHtml; ?>
                <div style="border-top:1px dashed #000;margin:10px 0;"></div>
                <div style="font-size:13px;">
                    <b>Mode of Payment:</b>
                    <?php echo htmlspecialchars($order['payment_method']); ?>
                </div>
                <div style="text-align:center;margin-top:15px;font-size:13px;">
                    <b>STATUS: APPROVED</b>
                </div>
            </div>
            <?php
            $receiptHtml = ob_get_clean();

            $mail->Body = "
                <div style='font-family:Arial,sans-serif;'>
                    <h3>
                        Hi " . htmlspecialchars($order['fullname']) . ",
                    </h3>
                    <p>
                        Good news! Your order with Order Number
                        <b>#$order_id</b>
                        has been <b>APPROVED</b> successfully.
                    </p>
                    <p>
                        Below is your official receipt for this transaction:
                    </p>
                    $receiptHtml
                    <br>
                    <p>
                        We are now preparing your items.
                        We will keep you updated via email!
                    </p>
                    <br>
                    <h4>
                        Best regards,<br>
                        R. Taipan Construction Supply and Services, OPC
                    </h4>
                </div>
            ";

        // =========================
        // DECLINED / CANCELLED
        // =========================
        } elseif ($current_status == 'declined' || $current_status == 'cancelled') {
            $decline_reason_text = !empty($order['decline_reason']) ? nl2br(htmlspecialchars($order['decline_reason'])) : 'No specific reason provided.';
            $pay_method_email = strtoupper(trim($order['payment_method'] ?? 'COD'));
            
            $refundEmailSection = "";
            if ($current_status == 'declined' && ($pay_method_email === 'GCASH' || $pay_method_email === 'MAYA') && !empty($order['refund_receipt'])) {
                $mail->addEmbeddedImage("../uploads/" . $order['refund_receipt'], 'order_refund_cid');
                $totalAmtEmail = (float)$order['total_amount'];
                $paymentSchemeEmail = strtolower(trim($order['payment_scheme'] ?? ''));
                $refAmtEmail = ($totalAmtEmail >= 5000 && strpos($paymentSchemeEmail, '50') !== false) ? ($totalAmtEmail * 0.5) : $totalAmtEmail;

                $refundEmailSection = "
                    <div style='background:#f8f9fa; padding:15px; border-radius:5px; margin:15px 0;'>
                        <p style='margin:0 0 10px 0;'><strong>Refund Details:</strong></p>
                        <p style='margin:3px 0;'><strong>Refund Amount:</strong> ₱" . number_format($refAmtEmail, 2) . "</p>
                        <p style='margin:3px 0;'><strong>Reference Number:</strong> " . htmlspecialchars($order['refund_reference']) . "</p>
                        <br>
                        <p style='margin:0 0 5px 0;'><strong>Refund Proof:</strong></p>
                        <img src='cid:order_refund_cid' style='max-width:250px; border:1px solid #ddd; border-radius:4px;'>
                    </div>
                ";
            }

            $mail->Body = "
                <h3>Hi " . htmlspecialchars($order['fullname']) . ",</h3>
                <p>We regret to inform you that your order <b>#$order_id</b> has been <b>DECLINED</b>.</p>
                <p><strong>Reason:</strong><br>$decline_reason_text</p>
                $refundEmailSection
                <p>If you have any questions, please contact us through our Facebook page below:</p>
                <p>
                    <a href='https://www.facebook.com/RSRT22' style='background-color:#0d6efd; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>
                        View Facebook Page
                    </a>
                </p>
                <h4>Best regards,<br>R. Taipan Construction Supply and Services, OPC</h4>
            ";

        // =========================
        // OUT FOR DELIVERY
        // =========================
        } elseif ($current_status == 'out_for_delivery') {

            $total = (float)$order['total_amount'];
            $totalFormatted = number_format($total, 2);

            $payment_method = strtoupper(trim($order['payment_method'] ?? ''));
            $payment_scheme = strtolower(trim($order['payment_scheme'] ?? ''));

            $paymentReminderHtml = "";
            if (strpos($payment_method, 'COD') !== false || strpos($payment_method, 'CASH ON DELIVERY') !== false) {

                $paymentReminderHtml = "
                    <div style='background:#fff3cd; padding:15px; border:1px solid #ffeeba; border-radius:5px; margin:20px 0;'>
                        <h4 style='margin-top:0; color:#856404;'>💵 Payment Reminder</h4>
                        <p>Please prepare the <b>EXACT AMOUNT</b> upon delivery.</p>
                        <p style='font-size:18px;'>
                            <b>Total Amount to Pay:</b>
                            <span style='color:#d9534f;'>₱{$totalFormatted}</span>
                        </p>
                    </div>
                ";

            } elseif ($payment_method === 'GCASH' || $payment_method === 'MAYA') {

                if (strpos($payment_scheme, '50') !== false || strpos($payment_scheme, 'down') !== false) {

                    $downPayment = isset($order['paid_amount']) ? (float)$order['paid_amount'] : ($total * 0.50);
                    $remainingBalance = $total - $downPayment;

                    $downFormatted = number_format($downPayment, 2);
                    $balanceFormatted = number_format($remainingBalance, 2);

                    $paymentReminderHtml = "
                        <div style='background:#fff3cd; padding:15px; border:1px solid #ffeeba; border-radius:5px; margin:20px 0;'>
                            <h4 style='margin-top:0; color:#856404;'>💳 Payment Status</h4>
                            <p>Your <b>50% down payment</b> through <b>{$payment_method}</b> has been recorded.</p>
                            <p><b>Total Order Amount:</b> ₱{$totalFormatted}</p>
                            <p style='color:#198754;'><b>50% Down Payment:</b> ₱{$downFormatted}</p>
                            <p style='color:#dc3545; font-size:18px; margin-bottom:0;'><b>Remaining Balance:</b> ₱{$balanceFormatted}</p>
                            <p>Please prepare <b>₱{$balanceFormatted}</b> upon delivery.</p>
                        </div>
                    ";

                } else {

                    $paymentReminderHtml = "
                        <div style='background:#d4edda; padding:15px; border:1px solid #c3e6cb; border-radius:5px; margin:20px 0;'>
                            <h4 style='margin-top:0; color:#155724;'>✅ Payment Status</h4>
                            <p style='color:#155724; margin-bottom:0;'>
                                Your order is <b>FULLY PAID</b> online through <b>{$payment_method}</b>. No payment is required upon delivery.
                            </p>
                        </div>
                    ";
                }
            }

            $customer_name = htmlspecialchars($order['fullname']);

            $mail->Body = "
                <div style='font-family:Arial,sans-serif; color:#333; line-height:1.6;'>
                    <h3>Hi {$customer_name},</h3>
                    <p>Your order <b>#{$order_id}</b> is now <b style='color:#d39e00;'>OUT FOR DELIVERY</b>!</p>
                    <p>Our delivery personnel will contact you shortly. Please keep your phone line available.</p>
                    {$paymentReminderHtml}
                    <p>Thank you for choosing <b>R. Taipan Construction Supply and Services, OPC</b>.</p>
                    <br>
                    <h4>Best regards,<br>R. Taipan Construction Supply and Services, OPC</h4>
                </div>
            ";

        // =========================
        // COMPLETED
        // =========================
        } elseif ($current_status == 'completed') {

            $mail->Body = "
                <h3>Hi " . htmlspecialchars($order['fullname']) . ",</h3>
                <p>Your order <b>#$order_id</b> status has been <b>DELIVERED</b>.</p>
                <p>
                    Thank you for choosing <b>R. Taipan Construction Supply and Services, OPC.</b>
                    We sincerely appreciate your trust and support, and we look forward to serving you again in the future.
                </p>
                <br>
                <h4>Best regards,<br>R. Taipan Construction Supply and Services, OPC</h4>
            ";

        // =========================
        // OTHER STATUS
        // =========================
        } else {

            $mail->Body = "
                <h3>Hi " . htmlspecialchars($order['fullname']) . ",</h3>
                <p>Your order <b>#$order_id</b> status has been updated to <b>" . strtoupper($current_status) . "</b>.</p>
                <br>
                <h4>Best regards,<br>R. Taipan Construction Supply and Services, OPC</h4>
            ";
        }

        $mail->send();

        // Update email status using PDO Prepared Statement
        $stmt_up = $pdo->prepare("UPDATE orders SET email_status = ? WHERE id = ?");
        $stmt_up->execute([$current_status, $order_id]);

        echo "<script>
            alert('Email notification sent!');
            window.location.href='orders.php';
        </script>";
        exit();

    } catch (Exception $e) {
        echo "<script>
            alert('Email failed: " . addslashes($mail->ErrorInfo) . "');
            window.location.href='orders.php';
        </script>";
        exit();
    }
}

// =========================
// STATUS UPDATE
// =========================
if (isset($_POST['update_status'])) {
    $order_id = (int) $_POST['order_id'];
    $new_status = $_POST['status'];
    $admin_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $decline_reason = $_POST['decline_reason'] ?? '';

    $stmt = $pdo->prepare("SELECT status, cart, payment_method FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $old_status = $order['status'];
    $pm = strtoupper(trim($order['payment_method'] ?? 'COD'));

    $ref_no = $_POST['refund_reference'] ?? '';
    $proof_filename = "";

    if ($new_status == 'declined') {
        if (($pm === 'GCASH' || $pm === 'MAYA') && isset($_FILES['refund_receipt']) && $_FILES['refund_receipt']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['refund_receipt']['tmp_name'];
            $file_name = $_FILES['refund_receipt']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($file_ext, $allowed_exts)) {
                $proof_filename = "order_refund_" . $order_id . "_" . time() . "." . $file_ext;
                move_uploaded_file($file_tmp, "../uploads/" . $proof_filename);
            }
        }
    }

    if ($new_status == 'approved' && $old_status != 'approved') {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->execute([$new_status, $admin_id, $order_id]);
    } elseif ($new_status == 'declined' && $old_status != 'declined') {
        if (!empty($proof_filename)) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, declined_by = ?, decline_reason = ?, refund_receipt = ?, refund_reference = ? WHERE id = ?");
            $stmt->execute([$new_status, $admin_id, $decline_reason, $proof_filename, $ref_no, $order_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, declined_by = ?, decline_reason = ? WHERE id = ?");
            $stmt->execute([$new_status, $admin_id, $decline_reason, $order_id]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
    }

    // Ibalik ang stock kapag naging declined o cancelled
    if (($new_status == 'declined' || $new_status == 'cancelled') && $old_status != 'declined' && $old_status != 'cancelled') {
        $cart_items = json_decode($order['cart'], true);
        if (!empty($cart_items)) {
            foreach ($cart_items as $item) {
                $qty = (int)$item['qty'];
                $product_id = $item['id'];
                $variant_id = !empty($item['variant_id']) ? $item['variant_id'] : null;

                if (!empty($variant_id)) {
                    $stmtStock = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
                    $stmtStock->execute([$qty, $variant_id]);
                } else {
                    $stmtStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                    $stmtStock->execute([$qty, $product_id]);
                }
            }
        }
    }

    header("Location: orders.php#order-" . $order_id);
    exit();
}

// =========================
// SEND TO CASHIER LOGIC
// =========================
if (isset($_POST['send_to_cashier'])) {
    $order_id = filter_var($_POST['order_id'] ?? 0, FILTER_VALIDATE_INT);
    
    $stmt = $pdo->prepare("SELECT sent_to_cashier, status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order_data['sent_to_cashier'] == 1) {
        echo "<script>alert('Receipt already sent to cashier!'); window.location.href='orders.php';</script>";
        exit();
    } elseif ($order_data['status'] == 'pending') {
        echo "<script>alert('Please approve the order first before sending to cashier.'); window.location.href='orders.php';</script>";
        exit();
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE orders SET sent_to_cashier = 1 WHERE id = ?");
        if($stmtUpdate->execute([$order_id])) {
            echo "<script>alert('Receipt successfully sent to cashier!'); window.location.href='orders.php';</script>";
            exit();
        }
    }
}

// Fetch orders via PDO
$orders_result = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
body{
    background:#f4f6f9;
    margin:0;
    overflow-x:hidden;
}

/* SIDEBAR */
.sidebar{
    position:fixed;
    top:0;
    left:0;
    width:260px;
    height:100vh;
    background:#001f4d;
    padding:20px;
    overflow-y:auto;
    z-index:1040;
    display:flex;
    flex-direction:column;
    transition: transform 0.3s ease;
}

.sidebar h2{
    color:white;
    text-align:center;
    font-weight:bold;
    margin-bottom:40px;
    line-height:1.3;
    font-size: 1.5rem;
}

.sidebar a{
    color:white;
    text-decoration:none;
    padding:12px 14px;
    border-radius:8px;
    margin-bottom:8px;
    transition:0.3s;
    font-size:15px;
    display:block;
    font-weight: 500;
}

.sidebar a i{
    margin-right:8px;
}

.sidebar a:hover{
    background:#0d6efd;
    padding-left:20px;
}

.sidebar .active{
    background:#0d6efd;
}

/* LOGOUT SA BABA */
.logout-section{
    margin-top:auto;
    padding-top:15px;
    border-top:1px solid rgba(255,255,255,0.2);
}

/* MAIN CONTENT */
.main-content{
    margin-left:260px;
    padding:25px;
    min-height:100vh;
    transition: margin 0.3s ease;
}

/* TABLE */
.table-responsive{
    border-radius:12px;
}

/* RECEIPT */
.thermal-preview{
    width:80mm;
    margin:auto;
    padding:10px;
    background:#fff;
    font-family:'Courier New', Courier, monospace;
    border:1px solid #eee;
    color:#000;
}

.line{
    border-top:1px dashed #000;
    margin:10px 0;
}

/* SCROLLBAR */
.sidebar::-webkit-scrollbar{
    width:5px;
}

.sidebar::-webkit-scrollbar-thumb{
    background:#0d6efd;
    border-radius:10px;
}

/* MOBILE RESPONSIVENESS */
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        width: 260px;
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
}
</style>
</head>

<body>

<!-- Mobile Header Toggle Bar -->
<nav class="navbar navbar-dark bg-dark d-lg-none px-3">
    <span class="navbar-brand fw-bold fs-6"><i class="bi bi-receipt"></i> Orders Management</span>
    <button class="btn btn-outline-light btn-sm" type="button" id="sidebarToggle">
        <i class="bi bi-list fs-4"></i>
    </button>
</nav>

<!-- SIDEBAR -->
<div class="sidebar" id="appSidebar">
    <h2>
        Admin<br>
        Dashboard
    </h2>

    <a href="dashboard.php">
        <i class="bi bi-grid"></i> Dashboard
    </a>
    <a href="products.php">
        <i class="bi bi-box-seam"></i> Inventory
    </a>
    <a href="orders.php" class="active">
        <i class="bi bi-receipt"></i> Orders
    </a>
    <a href="ItemsRequest.php">
        <i class="bi bi-arrow-return-left"></i> Customer Service request
    </a>
    <a href="damageItems.php"><i class="bi bi-exclamation-triangle"></i> Damaged Items</a>
    <a href="accountManagement.php"><i class="bi bi-person-circle"></i> Account Management</a>
    <a href="profile.php" class="sidebar-link"><i class="bi bi-person-badge"></i> My Profile</a>

    <div class="logout-section">
        <a href="../logout.php"
           class="text-danger"
           onclick="return confirm('Are you sure you want to logout?')">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <div class="d-flex justify-content-between mb-3 shadow-sm p-3 bg-white rounded align-items-center">
        <h4 class="mb-0 fs-5 fw-bold text-dark">📦 Orders Management</h4>
    </div>

    <!-- TABLE -->
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
                <?php while($row = $orders_result->fetch(PDO::FETCH_ASSOC)): ?>
                <tr id="order-<?php echo $row['id']; ?>">
                    <td class="text-center">#<?php echo $row['id']; ?></td>
                    <td class="text-center"><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                    
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
                        <?php if($row['status'] == 'pending'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="status" value="approved" class="btn btn-success btn-sm mb-1">Approve</button>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                            <button type="button" class="btn btn-sm btn-danger mb-1" data-bs-toggle="modal" data-bs-target="#declineModal" onclick="openDeclineModal('<?php echo $row['id']; ?>', '<?php echo strtoupper(trim($row['payment_method'] ?? 'COD')); ?>')">Decline</button>
                        
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                
                                <?php if($row['status'] == 'approved'): ?>
                                    <button type="submit" name="status" value="preparing" class="btn btn-info btn-sm text-white">Preparing</button>
                                
                                <?php elseif($row['status'] == 'preparing'): ?>
                                    <button type="submit" name="status" value="out_for_delivery" class="btn btn-warning btn-sm">Out for Delivery</button>
                                
                                <?php elseif($row['status'] == 'out_for_delivery'): ?>
                                    <button type="submit" name="status" value="completed" class="btn btn-primary btn-sm">Complete Order</button>

                                <?php elseif($row['status'] == 'declined'): ?>
                                    <span class="badge bg-danger mb-1">Order Declined</span>
                                    <?php 
                                    $pm = strtoupper(trim($row['payment_method'] ?? 'COD'));
                                    $isEwallet = ($pm === 'GCASH' || $pm === 'MAYA');
                                    ?>
                                    <?php if($isEwallet && !empty($row['refund_receipt'])): ?>
                                        <br><a href="../uploads/<?php echo $row['refund_receipt']; ?>" target="_blank" class="small text-success fw-bold">
                                            <i class="bi bi-image"></i> View Refund Proof
                                        </a>
                                    <?php endif; ?>

                                <?php elseif($row['status'] == 'completed'): ?>
                                    <span class="text-success small fw-bold">Order Delivered</span>

                                <?php else: ?>
                                    <span class="text-muted small"><?php echo ucfirst($row['status']); ?></span>
                                <?php endif; ?>

                                <input type="hidden" name="update_status" value="1">
                            </form>
                        <?php endif; ?>
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

<!-- RECEIPT MODAL -->
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

<!-- DECLINE REASON & REFUND MODAL -->
<div class="modal fade" id="declineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="orders.php" enctype="multipart/form-data">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Decline Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <input type="hidden" name="order_id" id="decline_order_id">
                    <input type="hidden" name="status" value="declined">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Reason for Declining / Additional Comment</label>
                        <textarea name="decline_reason" class="form-control" rows="3" placeholder="Please provide the reason for declining the order." required></textarea>
                    </div>

                    <!-- E-WALLET REFUND FIELDS-->
                    <div id="ewalletRefundFields" style="display: none;" class="border-top pt-3 mt-3">
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-info-circle-fill"></i> The mode of payment is <strong id="paymentMethodLabel"></strong>. Please provide the refund details.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Refund Reference Number</label>
                            <input type="text" class="form-control" name="refund_reference" id="refundReferenceInput" placeholder="Reference Number">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Upload Receipt Photo (GCash/Maya)</label>
                            <input type="file" class="form-control" name="refund_receipt" id="refundReceiptInput" accept=".jpg,.jpeg,.png,.webp">
                        </div>
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

<!-- CUSTOMER MODAL -->
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

<script>
function openDeclineModal(orderId, paymentMethod) {
    document.getElementById('decline_order_id').value = orderId;
    
    const ewalletFields = document.getElementById('ewalletRefundFields');
    const paymentMethodLabel = document.getElementById('paymentMethodLabel');
    const refInput = document.getElementById('refundReferenceInput');
    const receiptInput = document.getElementById('refundReceiptInput');

    if (paymentMethod === 'GCASH' || paymentMethod === 'MAYA') {
        ewalletFields.style.display = 'block';
        paymentMethodLabel.innerText = paymentMethod;
        refInput.setAttribute('required', 'required');
        receiptInput.setAttribute('required', 'required');
    } else {
        ewalletFields.style.display = 'none';
        refInput.removeAttribute('required');
        receiptInput.removeAttribute('required');
    }
}

function viewCustomer(data) {
    document.getElementById('c-name').innerText = data.fullname;
    document.getElementById('c-surname').innerText = data.surname;
    document.getElementById('c-phone').innerText = data.contact_number;
    document.getElementById('c-email').innerText = data.email;
    document.getElementById('c-address').innerText = data.full_address;
    document.getElementById('c-landmark').innerText = data.landmark ? data.landmark : "No specific landmark provided.";
    
    const proofContainer = document.getElementById('proof-container');
    let currentMOP = data.payment_method ? data.payment_method.toUpperCase() : "COD";
    
    let htmlContent = "";
    if (currentMOP === "GCASH" || currentMOP === "MAYA") {
        let referenceNumber = data.refund_reference ? data.refund_reference : (data.ref_no ? data.ref_no : "Not Provided");
        let paymentScheme = data.payment_scheme ? data.payment_scheme : 'fullpayment';
        htmlContent += `
            <div class="alert alert-secondary py-1 px-2 mb-2 text-start small">
                <b>Mode of Payment:</b> <span class="badge bg-primary">${currentMOP}</span><br>
                <b>Reference No:</b> <span class="text-danger fw-bold">${referenceNumber}</span><br>
                <b>Payment Type:</b> <span class="text-danger fw-bold">${paymentScheme}</span>
            </div>
        `;
    }

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

    proofContainer.innerHTML = htmlContent;
    
    var myModal = new bootstrap.Modal(document.getElementById('customerModal'));
    myModal.show();
}

function viewReceipt(data) {
    document.getElementById('modal_order_id').value = data.id;

    const orderStatus = (data.status || 'pending').toUpperCase();

    let items = [];
    try {
        let cartObj = (typeof data.cart === "string")
            ? JSON.parse(data.cart)
            : data.cart;

        items = Object.values(cartObj || {});
    } catch (e) {
        console.error("Cart parsing error:", e);
        items = [];
    }

    let itemsHtml = '';
    items.forEach(item => {
        let price = parseFloat(item.price || 0);
        let qty = parseInt(item.qty || 0);
        let subtotal = price * qty;

        itemsHtml += `
            <tr>
                <td>${item.name} (x${qty})</td>
                <td align="right">₱${subtotal.toFixed(2)}</td>
            </tr>
        `;
    });

    let paymentMethodName = (data.payment_method || 'COD').toUpperCase();
    let paymentScheme = (data.payment_scheme || '').toLowerCase();
    let totalAmt = parseFloat(data.total_amount || 0);

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
        let downpayment = parseFloat(data.paid_amount || (totalAmt * 0.5));
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
        let paidAmount = parseFloat(data.paid_amount || totalAmt);
        let refNoField = data.refund_reference ? data.refund_reference : (data.ref_no ? data.ref_no : '');

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

        if (refNoField) {
            paymentBreakdownHtml += `
                <div class="small text-muted">
                    Reference No: ${refNoField}
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

        <p class="small mb-1"><b>Order ID:</b> #${data.id}</p>
        <p class="small mb-1"><b>Date:</b> ${data.order_date}</p>
        <p class="small mb-1"><b>Customer:</b> ${data.fullname} ${data.surname}</p>
        <p class="small mb-1"><b>Contact:</b> ${data.contact_number}</p>
        <p class="small mb-1"><b>Address:</b> ${data.full_address}</p>
        <p class="small mb-2"><b>Landmark:</b> ${data.landmark || 'N/A'}</p>

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

    document.getElementById('receiptContent').innerHTML = fullReceiptHtml;

    let receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    receiptModal.show();

    document.activeElement.blur();
}

// Mobile Sidebar Toggle Script
document.addEventListener("DOMContentLoaded", function() {
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
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>