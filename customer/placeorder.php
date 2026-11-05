<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$checkout_source = isset($_GET['from']) ? $_GET['from'] : 'cart';
$items_to_process = [];

if ($checkout_source == 'buynow' && isset($_SESSION['buy_now'])) {
    $items_to_process = $_SESSION['buy_now'];
} elseif (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $items_to_process = $_SESSION['cart'];
} else {
    header("Location: dashboard.php");
    exit();
}

$grand_total = 0;
foreach ($items_to_process as $item) {
    $grand_total += (float)$item['price'] * (int)$item['qty'];
}

if (isset($_POST['place_order'])) {
    $payment_method = trim($_POST['payment_method'] ?? '');
    $payment_scheme = trim($_POST['payment_scheme'] ?? 'full_payment');

    if ($grand_total >= 5000 && $payment_method === 'Cash on Delivery (COD)') {
        die("<script>alert('Error: COD is unavailable for orders ₱5,000 and above.'); history.back();</script>");
    }

    $fullname = trim($_POST['fullname']);
    $surname = trim($_POST['surname']);
    $contact_number = trim($_POST['contact_number']);
    $tel_no = !empty($_POST['tel_no']) ? trim($_POST['tel_no']) : null;
    $email = trim($_POST['email']);
    $full_address = trim($_POST['full_address']);
    $landmark = trim($_POST['landmark']);
    
    $cart_json = json_encode($items_to_process);

    $proof_of_payment = null;
    
    // Kukunin ang Reference Number at Paid Amount na na-detect ng OCR
    $ref_no = !empty($_POST['ref_no']) ? trim($_POST['ref_no']) : null;
    $detected_paid_amount = !empty($_POST['paid_amount']) ? (float)$_POST['paid_amount'] : 0;

    // Kalkulahin ang default paid amount base sa scheme kung sakaling hindi na-detect ng OCR
    if ($detected_paid_amount > 0) {
        $paid_amount = $detected_paid_amount;
    } elseif ($grand_total >= 5000 && $payment_scheme === '50_percent') {
        $paid_amount = $grand_total * 0.5;
    } else {
        $paid_amount = $grand_total;
    }

    if (in_array($payment_method, ['GCash', 'Maya'])) {
        if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['proof_of_payment']['tmp_name'];
            
            if (!empty($tmp) && file_exists($tmp)) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                $file_mime_check = mime_content_type($tmp);

                if (in_array($file_mime_check, $allowed_types)) {
                    $target_dir = "../uploads/proofs/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                    
                    $ext = pathinfo($_FILES["proof_of_payment"]["name"], PATHINFO_EXTENSION);
                    $new_filename = "proof_" . time() . "_" . rand(1000, 9999) . "." . $ext;
                    
                    if (move_uploaded_file($tmp, $target_dir . $new_filename)) {
                        $proof_of_payment = "uploads/proofs/" . $new_filename;
                    }
                }
            }
        }
    }

    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->beginTransaction();

        // 1. I-insert ang order (naka-save dito ang ref_no at paid_amount mula sa OCR/Form)
        $order_query = "INSERT INTO orders 
            (fullname, surname, contact_number, tel_no, email, full_address, landmark, total_amount, paid_amount, cart, payment_method, payment_scheme, ref_no, proof_of_payment, order_date, status) 
            VALUES 
            (:fullname, :surname, :contact_number, :tel_no, :email, :full_address, :landmark, :total_amount, :paid_amount, :cart, :payment_method, :payment_scheme, :ref_no, :proof_of_payment, CURRENT_TIMESTAMP, 'pending')";
        
        $stmt = $pdo->prepare($order_query);
        $stmt->execute([
            ':fullname' => $fullname,
            ':surname' => $surname,
            ':contact_number' => $contact_number,
            ':tel_no' => $tel_no,
            ':email' => $email,
            ':full_address' => $full_address,
            ':landmark' => $landmark,
            ':total_amount' => $grand_total,
            ':paid_amount' => $paid_amount,
            ':cart' => $cart_json,
            ':payment_method' => $payment_method,
            ':payment_scheme' => $payment_scheme,
            ':ref_no' => $ref_no,
            ':proof_of_payment' => $proof_of_payment
        ]);

        $order_id = $pdo->lastInsertId();

        // 2. I-insert ang order items at i-update ang stock
        foreach ($items_to_process as $item) {
            $qty = (int)$item['qty'];
            $price = (float)$item['price'];
            $product_id = (int)$item['id'];
            $name = $item['name'];
            $v_id = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
            $v_name = !empty($item['variant_name']) ? $item['variant_name'] : null;
            $subtotal = $price * $qty;

            $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, variant_id, variant_name, price, qty, subtotal) 
                                        VALUES (:order_id, :product_id, :product_name, :variant_id, :variant_name, :price, :qty, :subtotal)");
            $item_stmt->execute([
                ':order_id' => $order_id,
                ':product_id' => $product_id,
                ':product_name' => $name,
                ':variant_id' => $v_id,
                ':variant_name' => $v_name,
                ':price' => $price,
                ':qty' => $qty,
                ':subtotal' => $subtotal
            ]);

            if ($v_id !== null) {
                $stock_stmt = $pdo->prepare("UPDATE product_variants SET stock = GREATEST(stock - :qty, 0) WHERE id = :v_id");
                $stock_stmt->execute([':qty' => $qty, ':v_id' => $v_id]);
            } else {
                $stock_stmt = $pdo->prepare("UPDATE products SET stock = GREATEST(stock - :qty, 0) WHERE id = :product_id");
                $stock_stmt->execute([':qty' => $qty, ':product_id' => $product_id]);
            }
        }

        $pdo->commit();

        if ($checkout_source == 'buynow') { 
            unset($_SESSION['buy_now']); 
        } else { 
            unset($_SESSION['cart']); 
        }

        $_SESSION['message'] = "<b>Order Placed Successfully! </b><br>Your Order Number is: <span class='text-primary fw-bold'>#$order_id</span>";
        header("Location: dashboard.php");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Checkout error: " . $e->getMessage());
        
        echo "<div style='color:red; padding:20px; font-family:sans-serif;'>";
        echo "<h3>Checkout Processing Failed</h3>";
        echo "<p><b>Error Details:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><a href='javascript:history.back()'>Go back and try again</a></p>";
        echo "</div>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Hardware System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Tesseract.js OCR Engine -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="row g-4">
        <!-- ORDER SUMMARY -->
        <div class="col-12 col-md-5 order-1">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="mb-3 fw-bold">Items to Purchase</h5>
                <div class="overflow-auto" style="max-height: 300px;">
                    <?php foreach ($items_to_process as $item): ?>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                           <img src="../<?php echo htmlspecialchars($item['image']); ?>" class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-bold small text-truncate"><?php echo htmlspecialchars($item['name']); ?></h6>
                                <small class="text-muted">Qty: <?php echo (int)$item['qty']; ?> × ₱<?php echo number_format((float)$item['price'], 2); ?></small>
                            </div>
                            <div class="text-end fw-bold small">₱<?php echo number_format((float)$item['price'] * (int)$item['qty'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="d-flex justify-content-between align-items-center fs-6 text-secondary mt-3">
                    <span>Subtotal Amount:</span>
                    <span>₱<?php echo number_format($grand_total, 2); ?></span>
                </div>

                <?php if ($grand_total >= 5000): ?>
                    <div id="summary_breakdown" class="mt-2 pt-2 border-top">
                        <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                            <span>Payment Scheme:</span>
                            <span id="label_scheme" class="fw-bold text-dark">50% Downpayment</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center fs-5 fw-bold text-dark">
                            <span id="label_due_text">Amount to Pay Now:</span>
                            <span class="text-primary" id="display_due_amount">₱<?php echo number_format($grand_total * 0.5, 2); ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center fs-5 fw-bold text-dark mt-2 pt-2 border-top">
                        <span>Total Amount:</span>
                        <span class="text-primary">₱<?php echo number_format($grand_total, 2); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- DELIVERY FORM -->
        <div class="col-12 col-md-7 order-2">
            <div class="card border-0 shadow-sm p-4">
                <h4 class="mb-4 text-primary"><i class="bi bi-bag-check-fill"></i> Delivery & Payment Details</h4>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . (isset($_GET['from']) ? '?from=' . htmlspecialchars($_GET['from']) : ''); ?>" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="fullname" class="form-control" 
                            pattern="[A-Za-z\s]+" title="Letters only" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                        </div>
                        <div class="col-12 col-sm-6">
                             <label class="form-label">Last Name</label>
                            <input type="text" name="surname" class="form-control" 
                            pattern="[A-Za-z\s]+" title="Letters only" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="09XXXXXXXXX"
                            maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                        </div>
                        <div class="col-12 col-sm-6">
                           <label class="form-label">Telephone (Optional)</label>
                            <input type="text" name="tel_no" class="form-control" 
                           oninput="this.value = this.value.replace(/[^0-9-]/g, '')">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email Address</label>
                             <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Complete Delivery Address</label>
                            <textarea name="full_address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Landmark</label>
                            <input type="text" name="landmark" class="form-control" required>
                        </div>
                        
                        <!-- PAYMENT SECTION -->
                        <div class="col-12">
                            <?php if ($grand_total >= 5000): ?>
                                <div id="payment_option_box" class="alert alert-info p-3">
                                    <p class="fw-bold small text-danger mb-2">Note: Orders ₱5,000+ require payment scheme selection.</p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_scheme" value="50_percent" checked id="opt1" onchange="updatePaymentDisplay()">
                                        <label class="form-check-label" for="opt1">50% Downpayment</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_scheme" value="full_payment" id="opt2" onchange="updatePaymentDisplay()">
                                        <label class="form-check-label" for="opt2">Full Payment</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <label class="form-label fw-bold">Mode of Payment</label>
                            <?php if ($grand_total < 5000): ?>
                            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="Cash on Delivery (COD)" onclick="togglePaymentFields()" id="cod_radio" required><label class="form-check-label" for="cod_radio">Cash On Delivery</label></div>
                            <?php endif; ?>
                            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="GCash" onclick="togglePaymentFields()" id="gcash_radio"><label class="form-check-label" for="gcash_radio">GCash</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="Maya" onclick="togglePaymentFields()" id="maya_radio"><label class="form-check-label" for="maya_radio">Maya</label></div>
                            <div id="cod_message" class="alert alert-warning mt-2 d-none">Pay exact amount upon delivery.</div>
                        </div>

                        <!-- ONLINE PAYMENT FIELDS WITH AUTOMATIC OCR EXTRACTOR -->
                        <div id="online_payment_fields" class="col-12 d-none bg-light p-3 border rounded">
                            <a href="qr.php" target="_blank" class="btn btn-sm btn-outline-primary mb-2">View QR Code</a>
                            
                            <!-- Hidden Inputs: Awtomatikong masesave sa DATABASE (ref_no at paid_amount) -->
                            <input type="hidden" name="ref_no" id="ref_no_input">
                            <input type="hidden" name="paid_amount" id="paid_amount_input">

                            <!-- OCR Status Message Box -->
                            <div id="ocr_status" class="alert alert-info py-2 d-none small">
                                <span id="ocr_status_text">Binabasa ang receipt...</span>
                            </div>

                            <label class="form-label small fw-bold">Upload Proof of Payment (GCash / Maya)</label>
                            <input type="file" name="proof_of_payment" id="proof_of_payment_input" class="form-control mb-2" accept=".jpg,.jpeg,.png,.webp" onchange="processReceiptOCR(this)">
                        </div>

                        <div class="col-12 form-check ps-4">
                            <input type="checkbox" class="form-check-input" id="termsCheckbox" onclick="toggleSubmitButton()">
                           <label class="form-check-label" for="termsCheckbox" style="font-size: 0.85rem;">
                               I have read and agree to the Terms and Conditions and Privacy Notice. <a href="tac.php" target="_blank" class="text-decoration-underline">Terms and Conditions</a> 
                            </label>
                        </div>
                        <button type="submit" name="place_order" id="submitBtn" class="btn btn-success w-100 py-2" disabled>Confirm & Place Order</button>
                        <button type="button" class="btn btn-secondary w-100" onclick="window.location.href='cart.php';">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const grandTotal = <?php echo $grand_total; ?>;

function togglePaymentFields() {
    const selectedRadio = document.querySelector('input[name="payment_method"]:checked');
    if (!selectedRadio) return;
    
    const selectedMethod = selectedRadio.value;
    const onlineFields = document.getElementById('online_payment_fields');
    const codMessage = document.getElementById('cod_message');
    const optionBox = document.getElementById('payment_option_box');
    const proofInput = document.getElementById('proof_of_payment_input');

    if (selectedMethod === 'GCash' || selectedMethod === 'Maya') {
        if (onlineFields) onlineFields.classList.remove('d-none');
        if (codMessage) codMessage.classList.add('d-none');
        if (optionBox) optionBox.classList.remove('d-none');
        if (proofInput) proofInput.required = true;
    } else if (selectedMethod === 'Cash on Delivery (COD)') {
        if (onlineFields) onlineFields.classList.add('d-none');
        if (codMessage) codMessage.classList.remove('d-none');
        if (optionBox) optionBox.classList.add('d-none');
        if (proofInput) proofInput.required = false;
    }
}

function updatePaymentDisplay() {
    const schemeRadio = document.querySelector('input[name="payment_scheme"]:checked');
    if (!schemeRadio) return;

    const scheme = schemeRadio.value;
    const labelScheme = document.getElementById('label_scheme');
    const dueText = document.getElementById('label_due_text');
    const displayDue = document.getElementById('display_due_amount');

    if (scheme === '50_percent') {
        let half = grandTotal * 0.5;
        if (labelScheme) labelScheme.innerText = "50% Downpayment";
        if (dueText) dueText.innerText = "Amount to Pay Now:";
        if (displayDue) displayDue.innerText = "₱" + half.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    } else {
        if (labelScheme) labelScheme.innerText = "Full Payment";
        if (dueText) dueText.innerText = "Total Amount to Pay:";
        if (displayDue) displayDue.innerText = "₱" + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

function toggleSubmitButton() {
    const checkbox = document.getElementById("termsCheckbox");
    const submitBtn = document.getElementById("submitBtn");
    if (checkbox && submitBtn) {
        submitBtn.disabled = !checkbox.checked;
    }
}

// Tesseract OCR Receipt Scanner Function
async function processReceiptOCR(fileInput) {
    const file = fileInput.files[0];
    if (!file) return;

    const statusBox = document.getElementById('ocr_status');
    const statusText = document.getElementById('ocr_status_text');
    const refInput = document.getElementById('ref_no_input');
    const paidInput = document.getElementById('paid_amount_input');

    statusBox.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusBox.classList.add('alert-info');
    statusText.innerHTML = '<div class="spinner-border spinner-border-sm me-1" role="status"></div> Binabasa ang receipt...';

    try {
        const result = await Tesseract.recognize(
            file,
            'eng',
            {
                logger: m => {
                    if (m.status === 'recognizing text') {
                        let progress = Math.round(m.progress * 100);
                        statusText.innerHTML = `<div class="spinner-border spinner-border-sm me-1" role="status"></div> Scanning receipt: ${progress}%`;
                    }
                }
            }
        );

        const text = result.data.text;
        console.log("OCR Extracted Raw Text:\n", text);

        // 1. REF NO EXTRACTION (Iniiwasan ang dates/times tulad ng 2026-11-05 o 11:15 AM)
        let detectedRef = '';
        
        // Hahanap muna ng keyword (e.g. Ref No: 1234 567 890 o Ref 0012345678)
        let refMatch = text.match(/(?:Ref\.?\s*No\.?|Reference\s*No\.?|Transaction\s*No\.?|Ref)\s*:?\s*([A-Za-z0-9\s]{9,20})/i);
        
        if (refMatch) {
            detectedRef = refMatch[1].replace(/[^A-Za-z0-9]/g, '').trim();
        } else {
            // Fallback: Naghahanap ng 9 hanggang 13-digit continuous number (na walang colon, hyphen o slash na karaniwan sa Date/Time)
            let digitsMatch = text.match(/\b\d{4}\s?\d{3,4}\s?\d{3,4}\b/);
            if (digitsMatch) {
                detectedRef = digitsMatch[0].replace(/\s+/g, '').trim();
            }
        }

        // 2. AMOUNT EXTRACTION (Extracting Total Amount Sent)
        let detectedAmount = '';
        let amountMatch = text.match(/(?:Amount|Total|Sent|PHP|₱)\s*:?\s*₱?\s*([\d,]+\.\d{2})/i);
        if (amountMatch) {
            detectedAmount = amountMatch[1].replace(/,/g, '');
        }

        // 3. DATE & TIME EXTRACTION (Para sa display feedback lamang)
        let detectedDateTime = '';
        let dateTimeMatch = text.match(/(?:\d{1,2}[\/\.-]\d{1,2}[\/\.-]\d{2,4}|\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]* \d{1,2},? \d{4}\b)\s*(?:at\s*)?(?:\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM)?)?/i);
        if (dateTimeMatch) {
            detectedDateTime = dateTimeMatch[0].trim();
        }

        // I-assign ang values sa Hidden Inputs para direktang ma-save sa Database!
        if (detectedRef !== '') {
            refInput.value = detectedRef;
        }
        if (detectedAmount !== '') {
            paidInput.value = detectedAmount;
        }

        // Magpakita ngmalinaw na Feedback UI sa User
        if (detectedRef !== '') {
            statusBox.classList.remove('alert-info');
            statusBox.classList.add('alert-success');
            
            let message = `<strong>✓ Receipt scanned successfully!</strong><br>`;
            message += `Ref No: <b>${detectedRef}</b>`;
            if (detectedAmount !== '') {
                message += `<br>Amount Sent: <b>₱${parseFloat(detectedAmount).toLocaleString('en-US', {minimumFractionDigits: 2})}</b>`;
            }
            if (detectedDateTime !== '') {
                message += `<br>Date & Time: <b>${detectedDateTime}</b>`;
            }
            statusText.innerHTML = message;
        } else {
            statusBox.classList.remove('alert-info');
            statusBox.classList.add('alert-warning');
            statusText.innerHTML = '⚠️ Nabasang resibo ngunit pakitiyak na malinaw ang Reference Number.';
        }

    } catch (error) {
        console.error("OCR Reading Error:", error);
        statusBox.classList.remove('alert-info');
        statusBox.classList.add('alert-danger');
        statusText.innerHTML = '❌ Hindi mabasa ang text sa image. Pakitiyak na malinaw ang resibo.';
    }
}

document.querySelector('form').addEventListener('submit', function(e) {
    const contact = document.querySelector('input[name="contact_number"]').value;
    if (!contact.startsWith('09') || contact.length !== 11) {
        alert("Contact number must start with '09' with 11 digits total");
        e.preventDefault();
        return false;
    }
});
</script>
</body>
</html>