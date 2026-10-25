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
        
        /* THERMAL STYLE - POS LOOK */
        #receipt-print {
            display: none;
            width: 80mm;
            padding: 10px;
            background: white;
            font-family: 'Courier New', Courier, monospace;
            color: #000;
            font-size: 14px;
        }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .receipt-header { text-align: center; }
        .receipt-header h5 { font-weight: bold; margin: 0; }
        .r-table { width: 100%; border-collapse: collapse; }
        .r-table td { padding: 3px 0; vertical-align: top; }

        .modal-receipt .modal-content { width: 85mm; margin: auto; border-radius: 0; }

        @media print {
            body * { visibility: hidden; }
            #receipt-print, #receipt-print * { visibility: visible; }
            #receipt-print { display: block !important; position: absolute; left: 0; top: 0; width: 80mm; }
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
                            <p class="mb-1 small"><strong>Customer Name:</strong> <?php echo htmlspecialchars($row['fullname']); ?></p>
                            <p class="mb-1 small"><strong>Customer Surname:</strong> <?php echo htmlspecialchars($row['surname']); ?></p>
                            <p class="mb-3 small text-muted"><?php echo htmlspecialchars($row['full_address']); ?></p>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-dark" onclick='viewReceipt(<?php echo json_encode($row); ?>)'>
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

<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-receipt">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modalReceiptView" style="padding: 10px;"></div>
                <div class="p-3">
                    <button class="btn btn-primary w-100" onclick="window.print()">
                        <i class="bi bi-printer me-2"></i>Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="receipt-print"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewReceipt(data) {
    // 1. SAFE CART PARSING: Tinitiyak na magiging Array kahit Object ang format sa DB
    let items = [];
    try {
        let cartObj = (typeof data.cart === "string") ? JSON.parse(data.cart) : data.cart;
        items = Object.values(cartObj || {}); // Ginagawang Array para gumana ang .forEach
    } catch (e) {
        console.error("Cart parsing error:", e);
        items = [];
    }

    let itemsHtml = "";
    let total = 0;

    // 2. ITEMS LOOPING
    items.forEach(i => {
        let name = i.name || i.product_name;
        let price = parseFloat(i.price || 0);
        let qty = parseInt(i.qty || 0);
        let subtotal = qty * price;
        total += subtotal;
        
        itemsHtml += `
            <tr>
                <td style="padding: 3px 0;">
                    ${(name || '').toUpperCase()}<br>
                    <small>${qty} x ${price.toFixed(2)}</small>
                </td>
                <td align="right">₱${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            </tr>`;
    });

    let paymentBreakdownHtml = "";
    const paymentMethod = (data.payment_method || '').toUpperCase();
    const paymentScheme = (data.payment_scheme || '').toLowerCase();

    // 3. PAYMENT BREAKDOWN LOGIC
    if (total >= 5000 && paymentScheme.includes('50')) {
        const downpayment = parseFloat(data.paid_amount || (total * 0.50));
        const balance = total - downpayment;

        paymentBreakdownHtml = `
            <div style="display:flex;justify-content:space-between;font-size:13px;">
                <span>TOTAL AMOUNT:</span>
                <span>₱${total.toLocaleString(undefined,{minimumFractionDigits:2})}</span>
            </div>

            <div style="display:flex;justify-content:space-between;font-size:13px;color:green;">
                <span>DOWNPAYMENT PAID:</span>
                <span>₱${downpayment.toLocaleString(undefined,{minimumFractionDigits:2})}</span>
            </div>

            <div class="line"></div>

            <div style="display:flex;justify-content:space-between;font-weight:bold;font-size:16px;color:red;">
                <span>REMAINING BALANCE:</span>
                <span>₱${balance.toLocaleString(undefined,{minimumFractionDigits:2})}</span>
            </div>
        `;
    } else {
        paymentBreakdownHtml = `
            <div style="display:flex;justify-content:space-between;font-weight:bold;font-size:16px;">
                <span>TOTAL AMOUNT:</span>
                <span>₱${total.toLocaleString(undefined,{minimumFractionDigits:2})}</span>
            </div>
        `;

        if (paymentMethod === 'GCASH' || paymentMethod === 'MAYA') {
            paymentBreakdownHtml += `
                <div style="display:flex;justify-content:space-between;color:green;">
                    <span>PAID VIA ${paymentMethod}:</span>
                    <span>₱${total.toLocaleString(undefined,{minimumFractionDigits:2})}</span>
                </div>
            `;
        }
    }

    let landmarkText = data.landmark ? data.landmark.toUpperCase() : "N/A";

    // 4. RECEIPT TEMPLATE
    const receiptTemplate = `
        <div class="receipt-header">
            <h5>R. TAIPAN HARDWARE</h5>
            <small>190 Cabrera Rd. Sitio Burol Brgy. San Juan Taytay, Rizal</small><br>
            <small>Contact: 0907 456 5632</small>
            <div class="line"></div>
            <small><b>OFFICIAL RECEIPT (ONLINE ORDER)</b></small>
            <div class="line"></div>
        </div>
        <div style="font-size: 12px;">
            ORDER ID: #${data.id}<br>
            DATE: ${data.order_date}<br>
            CUSTOMER: ${(data.fullname || '').toUpperCase()} ${(data.surname || '').toUpperCase()}<br>
            CONTACT: ${data.contact_number || ''}<br>
            ADDRESS: ${(data.full_address || '').toUpperCase()}<br>
            LANDMARK: ${landmarkText}
        </div>
        <div class="line"></div>
        <table class="r-table">
            ${itemsHtml}
        </table>
        <div class="line"></div>
        
        ${paymentBreakdownHtml}
        
        <div class="line"></div>
        <div class="text-center" style="font-size: 11px; margin-top: 5px;">
            THANK YOU FOR SHOPPING WITH US!
        </div>
    `;

    // 5. INJECTION AT MODAL TRIGGER
    document.getElementById('modalReceiptView').innerHTML = `<div style="font-family: 'Courier New', Courier, monospace; color: #000;">${receiptTemplate}</div>`;
    document.getElementById('receipt-print').innerHTML = receiptTemplate;
    
    var myModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    myModal.show();
}
</script>

</body>
</html>