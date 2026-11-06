<?php
session_start();
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "cashier") {
    header("Location: ../login.php");
    exit();
}

if (isset($_POST['remove_order'])) {
    $order_id = (int)$_POST['order_id'];
    $stmt = $pdo->prepare("UPDATE orders SET sent_to_cashier = 0 WHERE id = ?");
    $stmt->execute([$order_id]);
    header("Location: onlineOrders.php");
    exit();
}

$online_orders_stmt = $pdo->query("SELECT * FROM orders WHERE sent_to_cashier = 1 ORDER BY id DESC");
$online_orders = $online_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Orders Printing | R. Taipan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .order-card { border-radius: 15px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .modal-receipt .modal-content { width: 380px; margin: auto; border-radius: 8px; }
        .receipt-preview {
            background: #fff;
            padding: 12px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.3;
            border: 1px solid #ddd;
            color: #000;
        }
        .receipt-preview pre {
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
            font-family: 'Courier New', Courier, monospace;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-printer-fill me-2 text-primary"></i>Online Orders</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back to POS</a> 
    </div>

    <div class="row g-4">
        <?php if (count($online_orders) > 0): ?>
            <?php foreach($online_orders as $row): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card order-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <h5 class="fw-bold mb-0">Order #<?php echo $row['id']; ?></h5>
                                <span class="badge bg-primary"><?php echo strtoupper($row['status']); ?></span>
                            </div>
                            <p class="mb-1 small"><strong>Customer Name:</strong> <?php echo htmlspecialchars($row['fullname'] . ' ' . $row['surname']); ?></p>
                            <p class="mb-3 small text-muted"><?php echo htmlspecialchars($row['full_address']); ?></p>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-dark" onclick='viewReceipt(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)'>
                                    <i class="bi bi-printer me-2"></i>View & Print Receipt
                                </button>

                                <?php if (in_array($row['status'], ['preparing', 'out_for_delivery', 'completed'])): ?>
                                    <form method="POST" onsubmit="return confirm('Remove this order from the cashier list?')">
                                        <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="remove_order" class="btn btn-outline-danger w-100">
                                            <i class="bi bi-trash me-2"></i>Remove
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <p>No online orders sent to cashier at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- RECEIPT MODAL PREVIEW -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-receipt modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt"></i> Official Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="receipt-preview shadow-sm" id="modalReceiptView"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary btn-sm" id="btnPrintNow">
                    <i class="bi bi-printer me-1"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentOrderData = null;

function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text ?? "";
    return div.innerHTML;
}

function formatLine(left, right, isBold = false) {
    let l = String(left);
    let r = String(right);
    let spaceCount = Math.max(1, 32 - (l.length + r.length));
    let line = l + " ".repeat(spaceCount) + r;
    return isBold ? `<b>${line}</b>` : line;
}

function viewReceipt(data) {
    currentOrderData = data;

    let items = [];
    try {
        let cartObj = (typeof data.cart === "string") ? JSON.parse(data.cart) : data.cart;
        items = Object.values(cartObj || {});
    } catch (e) {
        console.error("Cart parsing error:", e);
        items = [];
    }

    let cartHTML = "";
    let total = 0;

    items.forEach(i => {
        let name = escapeHtml(i.name || i.product_name || "Item");
        let price = parseFloat(i.price || 0);
        let qty = parseInt(i.qty || 0);
        let subtotal = qty * price;
        total += subtotal;

        let qtyPrice = `${qty} x Php ${price.toFixed(2)}`;
        let subStr = `Php ${subtotal.toFixed(2)}`;
        let spacesNeeded = Math.max(1, 32 - (qtyPrice.length + subStr.length));
        let spaceStr = " ".repeat(spacesNeeded);

        cartHTML += `<b>${name}</b>\n${qtyPrice}${spaceStr}<b>${subStr}</b>\n`;
    });

    let totalAmt = parseFloat(data.total_amount || total);
    let paymentMethodName = (data.payment_method || 'COD').toUpperCase();
    let paymentScheme = (data.payment_scheme || '').toLowerCase();
    
    let paymentBreakdownText = "";

    if (totalAmt >= 5000 && (paymentScheme.includes('50') || paymentScheme.includes('down'))) {
        let downpayment = parseFloat(data.paid_amount || (totalAmt * 0.5));
        let balance = totalAmt - downpayment;

        paymentBreakdownText += formatLine("TOTAL AMOUNT:", "Php " + totalAmt.toFixed(2), true) + "\n";
        paymentBreakdownText += formatLine("PAID VIA " + paymentMethodName + ":", "Php " + downpayment.toFixed(2), true) + "\n";
        paymentBreakdownText += "--------------------------------\n";
        paymentBreakdownText += formatLine("PAY UPON DELIVERY:", "Php " + balance.toFixed(2), true) + "\n";
    } else if (paymentMethodName === 'GCASH' || paymentMethodName === 'MAYA') {
        let paidAmount = parseFloat(data.paid_amount || totalAmt);
        let refNoField = data.refund_reference ? data.refund_reference : (data.ref_no ? data.ref_no : '');

        paymentBreakdownText += formatLine("TOTAL AMOUNT:", "Php " + totalAmt.toFixed(2), true) + "\n";
        paymentBreakdownText += formatLine("PAID VIA " + paymentMethodName + ":", "Php " + paidAmount.toFixed(2), true) + "\n";
        if (refNoField) {
            paymentBreakdownText += `Ref No: ${refNoField}\n`;
        }
    } else {
        paymentBreakdownText += formatLine("TOTAL AMOUNT:", "Php " + totalAmt.toFixed(2), true) + "\n";
        paymentBreakdownText += `Mode of Payment: ${paymentMethodName}\n`;
    }

    let customerName = ((data.fullname || '') + ' ' + (data.surname || '')).trim().toUpperCase();
    let landmarkText = data.landmark ? data.landmark.toUpperCase() : "N/A";
    let fullAddress = (data.full_address || 'N/A').toUpperCase();

    let receiptContent = `
<pre style="font-family:'Courier New', Courier, monospace; font-size:10px; line-height:1.3; margin:0;">
<span style="text-align:center; display:block;"><h4 class="receipt-title">*** R. TAIPAN HARDWARE ***</h4>
R. Taipan Construction Supply
and Services, OPC
190 Cabrera Rd. Sitio Burol
Brgy. San Juan, Taytay, Rizal
Contact: 0907 456 5632

<h3 class="receipt-title">*** OFFICIAL RECEIPT ***</h3>
(ONLINE ORDER)</span>
--------------------------------
${formatLine("Order ID:", "#" + data.id)}
${formatLine("Date:", data.order_date || 'N/A')}
Customer: ${customerName}
Contact : ${data.contact_number || 'N/A'}
Address : ${fullAddress}
Landmark: ${landmarkText}
--------------------------------
${cartHTML}--------------------------------
${paymentBreakdownText}--------------------------------
<span style="text-align:center; display:block;"><b>THANK YOU FOR SHOPPING WITH US!
Please come again.</b></span>


&nbsp;
&nbsp;
&nbsp;
</pre>`;

    document.getElementById('modalReceiptView').innerHTML = receiptContent;
    
    let btnPrint = document.getElementById('btnPrintNow');
    btnPrint.onclick = function() {
        printThermalReceipt(data.id, receiptContent);
    };

    let myModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    myModal.show();
}

function printThermalReceipt(orderId, receiptHTML) {
    let oldFrame = document.getElementById("receipt-print-frame");
    if (oldFrame) oldFrame.remove();

    let iframe = document.createElement("iframe");
    iframe.id = "receipt-print-frame";
    iframe.style.position = "fixed";
    iframe.style.right = "0";
    iframe.style.bottom = "0";
    iframe.style.width = "0";
    iframe.style.height = "0";
    iframe.style.border = "0";
    iframe.style.visibility = "hidden";
    document.body.appendChild(iframe);

    let printDocument = iframe.contentWindow.document;
    printDocument.open();

    printDocument.write(`
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Receipt Order #${orderId}</title>
<style>
@page {
    size: 58mm auto;
    margin: 0mm;
}
* {
    box-sizing: border-box;
}
html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    background: #fff;
    color: #000;
}
pre.receipt {
    font-family: 'Courier New', Courier, monospace;
    font-size: 10px;
    line-height: 1.3;
    white-space: pre-wrap;
    word-break: break-all;
    margin: 0;
    padding: 2mm 1mm 15mm 1mm;
}
h3.receipt-title, h4.receipt-title {
    font-size: 12px;
    font-weight: bold;
    margin: 0;
    padding: 0;
    display: inline;
}
@media print {
    html, body {
        width: 100%;
        height: auto;
    }
}
</style>
</head>
<body>
${receiptHTML}
</body>
</html>
`);

    printDocument.close();

    setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(() => { iframe.remove(); }, 1000);
    }, 300);
}
</script>

</body>
</html>