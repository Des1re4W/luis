<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$date_selected = $_GET['filter_date'] ?? '';

$where_online = '';
$where_pos = '';

$params_online = [];
$params_pos = [];

if (!empty($date_selected)) {
    // Online orders
    $where_online = " WHERE CAST(order_date AS DATE) = :filter_date";
    $params_online['filter_date'] = $date_selected;

    // Walk-in POS transactions
    $where_pos = " WHERE CAST(transaction_date AS DATE) = :filter_date";
    $params_pos['filter_date'] = $date_selected;
}

// Get online orders
$sql_orders = "SELECT * FROM orders" . $where_online . " ORDER BY id DESC";
$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->execute($params_online);
$orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

// Get POS transactions
$sql_pos = "SELECT * FROM pos_transaction" . $where_pos . " ORDER BY id DESC";
$stmt_pos = $conn->prepare($sql_pos);
$stmt_pos->execute($params_pos);
$pos = $stmt_pos->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; }
        .main-card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .nav-tabs .nav-link { color: #495057; font-weight: 500; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; }
        
        /* Thermal Receipt Style*/
        .receipt-font {
            font-family: 'Courier New', Courier, monospace;
            background-color: #fff;
            color: #000;
        }
        .dashed-line {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>📊 Transaction History</h3>
         <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">      
        <form method="GET" class="d-flex align-items-end g-2">
            <div class="me-2">
                <label class="form-label small fw-bold">Filter by Date:</label>
                <input type="date" name="filter_date" class="form-control" value="<?php echo $date_selected; ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="history.php" class="btn btn-secondary ms-1">Reset</a>
        </form>
    </div>

    <?php if(!empty($date_selected)): ?>
        <div class="alert alert-info py-2">
            Showing transactions for: <strong><?php echo date('F d, Y', strtotime($date_selected)); ?></strong>
        </div>
    <?php endif; ?>

    <div class="card main-card">
        <div class="card-body">
    <div class="card main-card p-4 bg-white">
        <ul class="nav nav-tabs mb-4" id="historyTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="online-tab" data-bs-toggle="tab" data-bs-target="#online" type="button">Online Orders</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="pos-tab" data-bs-toggle="tab" data-bs-target="#pos" type="button">POS Transactions</button>
            </li>
        </ul>

        <div class="tab-content" id="historyTabsContent">
            <div class="tab-pane fade show active" id="online" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($orders)): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td><span class="badge bg-primary text-uppercase"><?php echo $row['status']; ?></span></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($row['order_date'])); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="showItems('<?php echo base64_encode($row['cart']); ?>', '<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['fullname'], ENT_QUOTES); ?>', '<?php echo $row['total_amount']; ?>', '<?php echo $row['status']; ?>', '<?php echo htmlspecialchars($row['proof_of_payment'], ENT_QUOTES); ?>')">
                                        View Receipt
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="pos" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Trans ID</th>
                                <th>Cashier</th>
                                <th>Total Amount</th>
                                <th>Paid</th>
                                <th>Change</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row_pos = mysqli_fetch_assoc($pos)): ?>
                            <tr>
                                <td>#<?php echo $row_pos['id']; ?></td>
                                <td><?php echo htmlspecialchars($row_pos['cashier_name']); ?></td>
                                <td class="fw-bold">₱<?php echo number_format($row_pos['total_amount'], 2); ?></td>
                                <td>₱<?php echo number_format($row_pos['amount_paid'], 2); ?></td>
                                <td>₱<?php echo number_format($row_pos['change_amount'], 2); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($row_pos['transaction_date'])); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="viewPOSReceipt('<?php echo $row_pos['id']; ?>', '<?php echo $row_pos['cashier_name']; ?>', '<?php echo $row_pos['total_amount']; ?>')">
                                        View Receipt
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="itemsModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content receipt-font">
            <div class="modal-body">
                <div class="text-center mb-2">
                    <h5 class="fw-bold m-0">R. TAIPAN HARDWARE</h5>
                    <small>Official Receipt</small>
                </div>
                <div class="dashed-line"></div>
                <div id="receipt-header-info" class="small mb-2"></div>
                <div class="dashed-line"></div>
                
                <table class="table table-borderless table-sm small mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody id="items-list"></tbody>
                </table>
                
                <div class="dashed-line"></div>
                <div id="receipt-footer-info"></div>
                <div class="dashed-line"></div>
                <div class="text-center mt-2 small">
                    *** THANK YOU ***
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary btn-sm w-100" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ONLINE ORDERS
function showItems(encodedData, id, customer, total, status, proofOfPayment) {
    const list = document.getElementById('items-list');
    const headerInfo = document.getElementById('receipt-header-info');
    const footerInfo = document.getElementById('receipt-footer-info');
    
    list.innerHTML = "";
    headerInfo.innerHTML = `
        Order Type: ONLINE<br>
        Ref: #${id}<br>
        Customer: ${customer}
    `;
    
    try {
        let cartString = atob(encodedData);
        let items = JSON.parse(cartString);
        let calculatedTotal = 0;
        
        items.forEach(item => {
            const tr = document.createElement('tr');
            let name = item.product_name || item.name;
            let qty = item.qty || item.quantity;
            let price = parseFloat(item.price);
            let subtotal = price * parseInt(qty);
            calculatedTotal += subtotal;

            tr.innerHTML = `
                <td>${name}</td>
                <td class="text-center">x${qty}</td>
                <td class="text-end">₱${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            `;
            list.appendChild(tr);
        });

        // BAGO AT KORREK: Pagpapakita ng Breakdown at Order Status sa ibaba ng resibo
        let breakdownHtml = "";
        if (status !== 'pending' && status !== 'declined' && proofOfPayment && proofOfPayment.trim() !== "") {
            const downpayment = calculatedTotal * 0.50;
            const balance = calculatedTotal - downpayment;
            
            breakdownHtml = `
                <div class="d-flex justify-content-between small">
                    <span>SUBTOTAL:</span>
                    <span>₱${calculatedTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
                <div class="d-flex justify-content-between small text-success">
                    <span>DOWNPAYMENT (PAID):</span>
                    <span>- ₱${downpayment.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
                <div class="d-flex justify-content-between fw-bold text-danger mt-1">
                    <span>BAL. TO COLLECT:</span>
                    <span>₱${balance.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
            `;
        } else {
            breakdownHtml = `
                <div class="d-flex justify-content-between fw-bold">
                    <span>TOTAL:</span>
                    <span>₱${calculatedTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
            `;
        }

        // Idinagdag ang malinaw na "STATUS: COMPLETED / APPROVED / atbp." sa pinakailalim ng breakdowns
        footerInfo.innerHTML = `
            ${breakdownHtml}
            <div class="mt-2 text-center bg-light py-1 rounded small fw-bold">
                STATUS: <span class="text-primary">${status.toUpperCase()}</span>
            </div>
        `;
    } catch (e) {
        list.innerHTML = "<tr><td colspan='3' class='text-center text-danger'>Data Error</td></tr>";
    }
    
    new bootstrap.Modal(document.getElementById('itemsModal')).show();
}

// POS RECEIPTS
function viewPOSReceipt(id, cashier, total) {
    const itemsTable = document.getElementById('items-list');
    const headerInfo = document.getElementById('receipt-header-info');
    const footerInfo = document.getElementById('receipt-footer-info');
    
    itemsTable.innerHTML = "<tr><td colspan='3' class='text-center text-muted small'>Loading items...</td></tr>";
    headerInfo.innerHTML = `
        Order Type: WALK-IN<br>
        Ref: #${id}<br>
        Cashier: ${cashier}
    `;
    footerInfo.innerHTML = "";

    new bootstrap.Modal(document.getElementById('itemsModal')).show();

    fetch(`getPOSitems.php?id=${id}`)
    .then(res => res.json())
    .then(items => {
        itemsTable.innerHTML = "";
        if (items.length > 0) {
            items.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.product_name}</td>
                    <td class="text-center">x${item.qty}</td>
                    <td class="text-end">₱${parseFloat(item.subtotal).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                `;
                itemsTable.appendChild(tr);
            });
            
            footerInfo.innerHTML = `
                <div class="d-flex justify-content-between fw-bold">
                    <span>TOTAL:</span>
                    <span>₱${parseFloat(total).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
            `;
        } else {
            itemsTable.innerHTML = "<tr><td colspan='3' class='text-center'>No items found.</td></tr>";
        }
    })
    .catch(err => {
        itemsTable.innerHTML = "<tr><td colspan='3' class='text-center text-danger'>Error loading items.</td></tr>";
    });
}
</script>

</body>
</html>