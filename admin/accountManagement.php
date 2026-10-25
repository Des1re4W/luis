<?php
// Assuming $pdo is your PDO connection instance. Example:
// $pdo = new PDO("mysql:host=localhost;dbname=your_db;charset=utf8mb4", "username", "password", [
//     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
// ]);

try {
    // =========================
    // EMAIL SENDING LOGIC (Example Block)
    // =========================
    // Assuming $order_id, $current_status, and $order are already defined/fetched via PDO before this block.
    
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
    if ($current_status == 'declined' || $current_status == 'cancelled') {
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

        // =========================
        // GCASH / MAYA
        // =========================
        } elseif ($payment_method === 'GCASH' || $payment_method === 'MAYA') {

            // 50% DOWNPAYMENT
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

            // FULLY PAID
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
    $stmt = $pdo->prepare("UPDATE orders SET email_status = ? WHERE id = ?");
    $stmt->execute([$current_status, $order_id]);

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

// =========================
// STATUS UPDATE (KASAMA NA ANG REFUND SA DECLINE)
// =========================
if (isset($_POST['update_status'])) {
    $order_id = (int) $_POST['order_id'];
    $new_status = $_POST['status'];
    $admin_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $decline_reason = $_POST['decline_reason'] ?? '';

    // Fetch order details via PDO
    $stmt = $pdo->prepare("SELECT status, cart, payment_method FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
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
    $order_id = $_POST['order_id'];
    
    $stmt = $pdo->prepare("SELECT sent_to_cashier, status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order_data = $stmt->fetch();

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

// Fetch orders again for view generation
$orders_result = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
$orders_for_modal = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
?>