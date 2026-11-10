<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/google_drive.php';
require_once __DIR__ . '/../config/image.php';

// Helper function para sa Checkout Item Image resolution
function getCheckoutItemImageSrc($image_value) {
    return getProductImageSrc($image_value);
}

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
    
    // Retrieve OCR Values
    $ref_no = !empty($_POST['ref_no']) ? trim($_POST['ref_no']) : null;
    $receiptdt = !empty($_POST['receiptdt']) ? trim($_POST['receiptdt']) : null;
    $detected_paid_amount = !empty($_POST['paid_amount']) ? (float)$_POST['paid_amount'] : 0;

    // BACKEND STRICT VALIDATION FOR ONLINE PAYMENTS (GCash, Maya, Bank Transfer)
    if (in_array($payment_method, ['GCash', 'Maya', 'Bank Transfer'])) {
        if (empty($ref_no) || $detected_paid_amount <= 0) {
            die("<script>alert('Order Failed: Invalid receipt photo. No valid Reference Number or Paid Amount detected by the OCR scanner.'); history.back();</script>");
        }
        $paid_amount = $detected_paid_amount;
    } else {
        $paid_amount = 0;
    }

    // UPLOAD CUSTOMER PROOF OF PAYMENT DIRECTLY TO "Customer Receipt" GOOGLE DRIVE FOLDER
    if (in_array($payment_method, ['GCash', 'Maya', 'Bank Transfer'])) {
        if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['proof_of_payment']['tmp_name'];
            $file_name = $_FILES['proof_of_payment']['name'];
            
            if (!empty($tmp) && file_exists($tmp)) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                $file_mime_check = mime_content_type($tmp);

                if (in_array($file_mime_check, $allowed_types)) {
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $new_filename = "proof_" . time() . "_" . rand(1000, 9999) . "." . $ext;

                    // Dedicated Folder ID para sa Customer Receipt
                    $customerFolderId = "1K63T1f7Z2JucT5lgyBuNobLIRJZFYNno";

                    try {
                        if (function_exists('uploadProductImageToDrive')) {
                            $file_id = uploadProductImageToDrive($tmp, $new_filename, $customerFolderId);
                            if (!empty($file_id)) {
                                $proof_of_payment = "https://drive.google.com/uc?export=view&id=" . $file_id;
                            }
                        }
                    } catch (Throwable $e) {
                        error_log("CUSTOMER PROOF GOOGLE DRIVE UPLOAD ERROR: " . $e->getMessage());
                    }

                    // Fallback to local uploads kung sakaling mag-fail ang Drive API
                    if (empty($proof_of_payment)) {
                        $target_dir = "../uploads/proofs/";
                        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                        
                        if (move_uploaded_file($tmp, $target_dir . $new_filename)) {
                            $proof_of_payment = "uploads/proofs/" . $new_filename;
                        }
                    }
                }
            }
        }
    }

    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();

        // Insert order into database
        $order_query = "INSERT INTO orders 
            (fullname, surname, contact_number, tel_no, email, full_address, landmark, total_amount, paid_amount, cart, payment_method, payment_scheme, ref_no, proof_of_payment, receiptdt, order_date, status) 
            VALUES 
            (:fullname, :surname, :contact_number, :tel_no, :email, :full_address, :landmark, :total_amount, :paid_amount, :cart, :payment_method, :payment_scheme, :ref_no, :proof_of_payment, :receiptdt, CURRENT_TIMESTAMP, 'pending')";
        
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
            ':proof_of_payment' => $proof_of_payment,
            ':receiptdt' => $receiptdt
        ]);

        $order_id = $pdo->lastInsertId();

        // Insert order items & update stock
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
                        <?php $item_img_src = getCheckoutItemImageSrc($item['image'] ?? ''); ?>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                           <img src="<?php echo htmlspecialchars($item_img_src, ENT_QUOTES, 'UTF-8'); ?>" 
                                alt="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                class="rounded me-3" 
                                style="width: 50px; height: 50px; object-fit: cover;"
                                onerror="this.onerror=null; this.src='../assets/images/no-image.png';"
                                loading="lazy">
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
                            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="Bank Transfer" onclick="togglePaymentFields()" id="bank_radio"><label class="form-check-label" for="bank_radio">Bank Transfer (MariBank, InstaPay, BDO, UnionBank, etc.)</label></div>
                            <div id="cod_message" class="alert alert-warning mt-2 d-none">Pay exact amount upon delivery.</div>
                        </div>

                        <!-- ONLINE PAYMENT FIELDS WITH AUTOMATIC OCR EXTRACTOR -->
                        <div id="online_payment_fields" class="col-12 d-none bg-light p-3 border rounded">
                            <a href="qr.php" target="_blank" class="btn btn-sm btn-outline-primary mb-2">View QR Code / Bank Details</a>
                            
                            <!-- Hidden Inputs: Automatically saved to Database -->
                            <input type="hidden" name="ref_no" id="ref_no_input">
                            <input type="hidden" name="paid_amount" id="paid_amount_input">
                            <input type="hidden" name="receiptdt" id="receiptdt_input">

                            <!-- OCR Status Message Box -->
                            <div id="ocr_status" class="alert alert-info py-2 d-none small">
                                <span id="ocr_status_text">Reading receipt image...</span>
                            </div>

                            <label class="form-label small fw-bold">Upload Proof of Payment (GCash / Maya / Bank Receipt)</label>
                            <input type="file" name="proof_of_payment" id="proof_of_payment_input" class="form-control mb-1" accept=".jpg,.jpeg,.png,.webp" onchange="processReceiptOCR(this)">
                            <small class="text-muted d-block mb-2"><i>Note: For best results, please upload a direct screenshot of your payment receipt.</i></small>
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

    if (selectedMethod === 'GCash' || selectedMethod === 'Maya' || selectedMethod === 'Bank Transfer') {
        if (onlineFields) onlineFields.classList.remove('d-none');
        if (codMessage) codMessage.classList.add('d-none');
        if (optionBox) optionBox.classList.remove('d-none');
        if (proofInput) proofInput.required = true;
    } else if (selectedMethod === 'Cash on Delivery (COD)') {
        if (onlineFields) onlineFields.classList.add('d-none');
        if (codMessage) codMessage.classList.remove('d-none');
        if (optionBox) optionBox.classList.add('d-none');
        if (proofInput) proofInput.required = false;
        
        document.getElementById('ref_no_input').value = "";
        document.getElementById('paid_amount_input').value = "";
        document.getElementById('receiptdt_input').value = "";
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

async function processReceiptOCR(fileInput) {
    const file = fileInput.files[0];
    if (!file) return;

    const statusBox = document.getElementById('ocr_status');
    const statusText = document.getElementById('ocr_status_text');
    const refInput = document.getElementById('ref_no_input');
    const paidInput = document.getElementById('paid_amount_input');
    const dtInput = document.getElementById('receiptdt_input');

    refInput.value = "";
    paidInput.value = "";
    dtInput.value = "";

    statusBox.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
    statusBox.classList.add('alert-info');
    statusText.innerHTML = '<div class="spinner-border spinner-border-sm me-1" role="status"></div> Scanning receipt image via OCR...';

    try {
        const result = await Tesseract.recognize(
            file,
            'eng',
            {
                logger: m => {
                    if (m.status === 'recognizing text') {
                        let progress = Math.round(m.progress * 100);
                        statusText.innerHTML = `<div class="spinner-border spinner-border-sm me-1" role="status"></div> Reading text: ${progress}%`;
                    }
                }
            }
        );

        const text = result.data.text;
        console.log("OCR Extracted Raw Text:\n", text);

        let detectedRef = '';
        let detectedAmount = '';
        let detectedDateTime = '';

        // -------------------------------------------------------------
        // 1. UNIVERSAL REFERENCE NUMBER DETECTION (GCash, Maya, MariBank, InstaPay, BDO, BPI, UnionBank)
        // -------------------------------------------------------------
        
        // Match standard keyword labels: "Reference ID", "Reference No", "Ref No", "Transaction ID", "Trace No", "InstaPay Ref"
        let refMatch = text.match(/(?:Reference\s*(?:ID|No\.?|Num(?:ber)?)|Ref\.?\s*(?:No\.?|ID|Num(?:ber)?)?|Transaction\s*(?:ID|No\.?)|Trace\s*No\.?|InstaPay\s*Ref)\s*:?\s*([A-Z0-9\s]{8,24})/i);
        
        if (refMatch) {
            let cleaned = refMatch[1].replace(/[\s\-_]+/g, '').trim();
            // Siguraduhing may halo o may sapat na haba (halimbawa: 06C04EC5CA75 ng Maya o 10-13 digits ng GCash/MariBank)
            if (cleaned.length >= 8 && cleaned.length <= 20) {
                detectedRef = cleaned;
            }
        }

        // Fallback A: Maya Alphanumeric Hex pattern (hal. 06C0 4EC5 CA75)
        if (!detectedRef) {
            let mayaHexMatch = text.match(/\b([A-Z0-9]{4}\s+[A-Z0-9]{4}\s+[A-Z0-9]{4})\b/i);
            if (mayaHexMatch) {
                detectedRef = mayaHexMatch[1].replace(/\s+/g, '').trim();
            }
        }

        // Fallback B: GCash 13-digit space separated numbers (hal. 1001 234 567 890 o 0019 123 4567)
        if (!detectedRef) {
            let spaceSeparatedMatch = text.match(/\b\d{3,4}\s+\d{3,4}\s+\d{3,4}(?:\s+\d{1,4})?\b/);
            if (spaceSeparatedMatch) {
                detectedRef = spaceSeparatedMatch[0].replace(/\s+/g, '').trim();
            }
        }

        // Fallback C: Any isolated continuous block of 9 to 16 alphanumeric characters
        if (!detectedRef) {
            let standaloneMatch = text.match(/\b[A-Z0-9]{9,16}\b/i);
            if (standaloneMatch && !/^(COMPLETED|SUCCESSFUL|TRANSFERRED|PAYMENT)$/i.test(standaloneMatch[0])) {
                detectedRef = standaloneMatch[0].trim();
            }
        }

        // -------------------------------------------------------------
        // 2. UNIVERSAL AMOUNT DETECTION (Handles ₱, PHP, Maya '- ₱1.00', MariBank, InstaPay)
        // -------------------------------------------------------------
        
        // Priority 1: Direct amount labels
        let amountMatch = text.match(/(?:Total\s*Amount\s*(?:Sent|Paid)?|Amount\s*(?:Sent|Paid)?|Sent|Paid|Total)\s*:?\s*[-–]?\s*(?:PHP|₱)?\s*([\d,]+\.\d{2})/i);
        
        if (amountMatch) {
            detectedAmount = amountMatch[1].replace(/,/g, '');
        } else {
            // Priority 2: Maya or general format with currency symbol (halimbawa: - ₱1.00 o ₱ 1,500.00)
            let currencyMatch = text.match(/[-–]?\s*(?:PHP|₱)\s*([\d,]+\.\d{2})/i);
            if (currencyMatch) {
                detectedAmount = currencyMatch[1].replace(/,/g, '');
            } else {
                // Priority 3: Any decimal number matching financial amount format (xx.xx)
                let fallbackAmount = text.match(/\b[\d,]+\.\d{2}\b/);
                if (fallbackAmount) {
                    detectedAmount = fallbackAmount[0].replace(/,/g, '');
                }
            }
        }

        // -------------------------------------------------------------
        // 3. DATE & TIME DETECTION
        // -------------------------------------------------------------
        let dateTimeMatch = text.match(/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4}(?:\s*,?\s*\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM)?)?\b/i);
        if (!dateTimeMatch) {
            dateTimeMatch = text.match(/(?:\d{1,2}[\/\.-]\d{1,2}[\/\.-]\d{2,4})\s*(?:\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM)?)?/i);
        }
        if (dateTimeMatch) {
            detectedDateTime = dateTimeMatch[0].trim();
        }

        // -------------------------------------------------------------
        // 4. VALIDATION AND DISPLAY RESULT
        // -------------------------------------------------------------
        if (!detectedRef || !detectedAmount) {
            statusBox.classList.remove('alert-info');
            statusBox.classList.add('alert-danger');
            statusText.innerHTML = '❌ <b>Invalid Payment Receipt!</b> No valid Reference Number or Amount detected. Please upload a clear GCash, Maya, or Bank Transfer receipt screenshot.';
            fileInput.value = "";
            return;
        }

        refInput.value = detectedRef;
        paidInput.value = detectedAmount;
        dtInput.value = detectedDateTime ? detectedDateTime : "N/A";

        statusBox.classList.remove('alert-info');
        statusBox.classList.add('alert-success');
        
        let message = `
            <strong>✓ Receipt scanned successfully!</strong><br>
            <small class="text-warning">
                Reminder: Please ensure that the payment amount exactly matches the total amount due to avoid order rejection.
            </small><br>
        `;
        message += `Ref No: <b>${detectedRef}</b>`;
        message += `<br>Amount Sent: <b>₱${parseFloat(detectedAmount).toLocaleString('en-US', {minimumFractionDigits: 2})}</b>`;
        if (detectedDateTime !== '') {
            message += `<br>Date & Time: <b>${detectedDateTime}</b>`;
        }
        statusText.innerHTML = message;

    } catch (error) {
        console.error("OCR Reading Error:", error);
        statusBox.classList.remove('alert-info');
        statusBox.classList.add('alert-danger');
        statusText.innerHTML = '❌ Could not process image. Please upload a clear valid payment receipt screenshot.';
        fileInput.value = "";
    }
}

document.querySelector('form').addEventListener('submit', function(e) {
    const selectedRadio = document.querySelector('input[name="payment_method"]:checked');
    if (selectedRadio && (selectedRadio.value === 'GCash' || selectedRadio.value === 'Maya' || selectedRadio.value === 'Bank Transfer')) {
        const refNo = document.getElementById('ref_no_input').value;
        const paidAmt = document.getElementById('paid_amount_input').value;
        
        if (!refNo || !paidAmt) {
            alert("Checkout Blocked: Valid payment receipt details were not detected. Please upload a valid payment receipt.");
            e.preventDefault();
            return false;
        }
    }

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