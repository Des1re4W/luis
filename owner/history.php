<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != "owner") {
    header("Location: ../login.php");
    exit();
}
$owner_name = isset($_SESSION['username']) ? $_SESSION['username'] : "owner";
$date_selected = isset($_GET['filter_date']) ? $_GET['filter_date'] : ''; 
$where_online = " WHERE 1=1 "; 
$where_pos = " WHERE 1=1 "; 

if (!empty($date_selected)) { 
    $where_online .= " AND DATE(order_date) = '$date_selected'"; 
    $where_pos .= " AND DATE(transaction_date) = '$date_selected'"; 
} 

$orders = $pdo->query("SELECT * FROM orders $where_online ORDER BY id DESC"); 
$pos = $pdo->query("SELECT * FROM pos_transaction $where_pos ORDER BY id DESC"); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History | Owner Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
body{
    margin:0;
    overflow-x:hidden;
    background:#f8f9fa;
    padding-top: 56px;
}

.navbar{
    height:56px;
    z-index:1100;
    position:fixed;
    top:0;
    left:0;
    right:0;
}

.sidebar{
    position:fixed;
    top:56px;
    left:0;
    width:260px;
    height:calc(100vh - 56px);
    background:#001f4d;
    padding:20px;
    overflow-y:auto;
    display:flex;
    flex-direction:column;
    box-shadow:4px 0 15px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
    z-index: 1040;
}

.sidebar-link{
    color:#adb5bd;
    text-decoration:none;
    padding:12px 14px;
    border-radius:8px;
    margin-bottom:8px;
    font-size:15px;
    font-weight:500;
    display:block;
    transition:0.3s;
}

.sidebar-link i{
    margin-right:8px;
}

.sidebar-link:hover{
    background:#0d6efd;
    color:#fff;
    padding-left:20px;
}

.sidebar-link.active{
    background:#0d6efd;
    color:#fff;
}

.logout-section{
    margin-top:auto;
    padding-top:15px;
    border-top:1px solid rgba(255,255,255,0.2);
}

.main-content{
    margin-left:260px;
    padding:20px;
    min-height:calc(100vh - 56px);
    box-sizing:border-box;
    transition: margin 0.3s ease;
}

.sidebar::-webkit-scrollbar{
    width:5px;
}

.sidebar::-webkit-scrollbar-thumb{
    background:#0d6efd;
    border-radius:10px;
}

.receipt-body {
    background: #fff;
    padding: 20px;
    font-family: monospace;
    border: 1px solid #ddd;
}
.receipt-header {
    text-align: center;
    margin-bottom: 15px;
}
.receipt-logo {
    font-size: 18px;
    font-weight: bold;
}
.receipt-line {
    border-top: 1px dashed #000;
    margin: 10px 0;
}

/* MOBILE RESPONSIVENESS */
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0;
    }
}
    </style>
</head>
<body>

<nav class="navbar navbar-dark px-3" style="background-color:#051c3c;">
    <div class="d-flex align-items-center w-100 justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <span class="navbar-brand fw-bold mb-0">Owner Panel</span>
        </div>
        <div class="d-flex align-items-center text-white gap-3">
            <span>
                Welcome, <strong><?= htmlspecialchars($owner_name); ?></strong>
            </span>
            <button class="btn btn-outline-light btn-sm d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="bi bi-list fs-5"></i>
            </button>
        </div>
    </div>
</nav>

<div class="sidebar" id="appSidebar">
    <a href="dashboard.php" class="sidebar-link"><i class="bi bi-graph-up-arrow"></i> Dashboard</a>
    <a href="sales.php" class="sidebar-link"><i class="bi bi-cash-stack"></i> Sales</a>
    <a href="inventory.php" class="sidebar-link"><i class="bi bi-box-seam"></i> Inventory Report</a>
    <a href="orders.php" class="sidebar-link"><i class="bi bi-clipboard-check"></i> Order Mgt. Monitoring</a>
    <a href="history.php" class="sidebar-link active"><i class="bi bi-clock-history"></i> History</a>
    <a href="auditTrail.php" class="sidebar-link"><i class="bi bi-shield-lock"></i> Audit Trail</a>
    <div class="logout-section">
        <a href="../logout.php" class="sidebar-link text-danger" onclick="return confirm('Are you sure you want to log out?')">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="container-fluid p-0">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold text-dark m-0">Transaction History</h2>
                <small class="text-muted font-monospace">Manage and review web orders and physical POS register counter logs.</small>
            </div>
        </div>

        <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-sm"> 
                <div class="modal-content">
                    <div class="modal-body" id="receiptContent"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-3 mb-4 shadow-sm border-0">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold text-muted">Filter by Date</label>
                    <input type="date" name="filter_date" class="form-control form-control-sm" value="<?php echo $date_selected; ?>">
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-filter"></i> Apply Filter</button>
                </div>
                <div class="col-6 col-md-2">
                    <a href="history.php" class="btn btn-secondary btn-sm w-100">Clear</a>
                </div>
            </form>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white p-0 border-bottom">
                <ul class="nav nav-tabs" id="historyTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="online-tab" data-bs-toggle="tab" data-bs-target="#online-orders" type="button" role="tab"><i class="bi bi-globe text-primary me-2"></i>Online Orders</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pos-tab" data-bs-toggle="tab" data-bs-target="#pos-transactions" type="button" role="tab"><i class="bi bi-shop text-success me-2"></i>Walk-in (POS)</button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body p-0 tab-content">
                
                <div class="tab-pane fade show active" id="online-orders" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">Order ID</th>
                                    <th>Customer Name</th>
                                    <th>Total Amount</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $orderRows = $orders->fetchAll(PDO::FETCH_ASSOC);
                                if(count($orderRows) > 0) {
                                    foreach($orderRows as $row) { 
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold">#<?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['fullname'] . " " . $row['surname']); ?></td>
                                    <td class="fw-bold text-success">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewOnlineItems(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['fullname'] . ' ' . $row['surname']); ?>', <?php echo $row['total_amount']; ?>, '<?php echo htmlspecialchars($row['status']); ?>')">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php } } else { ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-folder-x d-block fs-3 mb-2"></i>No online orders found.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="pos-transactions" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">Transaction ID</th>
                                    <th>Cashier Name</th>
                                    <th>Total Amount</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $posRows = $pos->fetchAll(PDO::FETCH_ASSOC);
                                if(count($posRows) > 0) {
                                    foreach($posRows as $row) { 
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold">#<?php echo $row['id']; ?></td>
                                    <td><i class="bi bi-person me-1 text-muted"></i> <?php echo htmlspecialchars($row['cashier_name']); ?></td>
                                    <td class="fw-bold text-success">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-success" onclick="viewPOSitems(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['cashier_name']); ?>', <?php echo $row['total_amount']; ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php } } else { ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-folder-x d-block fs-3 mb-2"></i>No POS transactions found.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="itemsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt"></i> Official Invoice</h5>
            </div>
            <div class="modal-body bg-light">
                <div class="receipt-body shadow-sm" id="receiptContainer">
                    <div class="receipt-header">
                        <div class="receipt-logo" style="letter-spacing: 1px;">R. Taipan Hardware</div>
                        <small class="text-muted">Transaction Statement</small>
                    </div>
                    
                    <div class="receipt-line"></div>
                    <div id="receiptHeaderInfo" class="small fw-semibold" style="line-height: 1.6;"></div>
                    <div class="receipt-line"></div>
                    
                    <table class="w-100 small">
                        <thead>
                            <tr class="fw-bold">
                                <td style="width: 55%;">Item Description</td>
                                <td style="width: 15%; text-align: center;">Qty</td>
                                <td style="width: 30%; text-align: right;">Amount</td>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody"></tbody>
                    </table>
                    
                    <div class="receipt-line"></div>
                    <div id="receiptFooterInfo"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let invoiceModal;
document.addEventListener("DOMContentLoaded", function() {
    invoiceModal = new bootstrap.Modal(document.getElementById('itemsModal'));

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

function viewOnlineItems(id, customer, total, status) {
    const headerInfo = document.getElementById('receiptHeaderInfo');
    const itemsTable = document.getElementById('itemsTableBody');
    const footerInfo = document.getElementById('receiptFooterInfo');

    itemsTable.innerHTML = "<tr><td colspan='3' class='text-center py-3 text-muted'>Loading items...</td></tr>";
    headerInfo.innerHTML = "<em>Loading receipt details...</em>";
    invoiceModal.show();

    fetch(`getOnlineItems.php?id=${id}`)
    .then(res => { if (!res.ok) throw new Error("Network response was not ok"); return res.json(); })
    .then(items => {
        itemsTable.innerHTML = "";
        if(items.length === 0) {
            headerInfo.innerHTML = `ORDER TYPE : ONLINE ORDER<br>ORDER NO   : ${id}<br>CUSTOMER   : ${customer.toUpperCase()}<br>STATUS     : ${status.toUpperCase()}<br>DATE       : N/A`;
            itemsTable.innerHTML = "<tr><td colspan='3' class='text-center py-2 text-danger'>No items found.</td></tr>";
            return;
        }
        let dbDate = items[0].order_date; 
        headerInfo.innerHTML = `
            ORDER TYPE : ONLINE ORDER<br>
            ORDER NO   : ${id}<br>
            CUSTOMER   : ${customer.toUpperCase()}<br>
            STATUS     : ${status.toUpperCase()}<br>
            DATE       : ${dbDate}
        `;
        items.forEach(item => {
            let tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.product_name || "Unknown Item"}</td>
                <td class="text-center">x${item.qty || 1}</td>
                <td class="text-end">₱${parseFloat(item.subtotal || 0).toFixed(2)}</td>
            `;
            itemsTable.appendChild(tr);
        });
        footerInfo.innerHTML = `
            <div class="d-flex justify-content-between fw-bold fs-6 mt-2 pt-2 border-top border-secondary">
                <span>TOTAL AMOUNT:</span>
                <span class="text-success">₱${parseFloat(total).toFixed(2)}</span>
            </div>
        `;
    })
    .catch(err => {
        itemsTable.innerHTML = `<tr><td colspan='3' class='text-center py-2 text-danger'>Error loading items.</td></tr>`;
    });
}

function viewPOSitems(id, cashier, total) {
    const headerInfo = document.getElementById('receiptHeaderInfo');
    const itemsTable = document.getElementById('itemsTableBody');
    const footerInfo = document.getElementById('receiptFooterInfo');

    itemsTable.innerHTML = "<tr><td colspan='3' class='text-center py-3 text-muted'>Loading items...</td></tr>";
    headerInfo.innerHTML = "<em>Loading receipt details...</em>";
    invoiceModal.show();

    fetch(`getPOSitems.php?id=${id}`)
    .then(res => { if (!res.ok) throw new Error("Network response was not ok"); return res.json(); })
    .then(items => {
        itemsTable.innerHTML = "";
        if(items.length === 0) {
            headerInfo.innerHTML = `ORDER TYPE : WALK-IN (POS)<br>T NO : #POS-${id}<br>CASHIER    : ${cashier.toUpperCase()}<br>DATE       : N/A`;
            itemsTable.innerHTML = "<tr><td colspan='3' class='text-center py-2 text-danger'>No items found.</td></tr>";
            return;
        }
        let dbDate = items[0].transaction_date;
        headerInfo.innerHTML = `
            ORDER TYPE : WALK-IN (POS)<br>
            ORDER NO : ${id}<br>
            CASHIER    : ${cashier.toUpperCase()}<br>
            DATE       : ${dbDate}
        `;
        items.forEach(item => {
            let tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.product_name || "Unknown Item"}</td>
                <td class="text-center">x${item.qty || 1}</td>
                <td class="text-end">₱${parseFloat(item.subtotal || 0).toFixed(2)}</td>
            `;
            itemsTable.appendChild(tr);
        });
        footerInfo.innerHTML = `
            <div class="d-flex justify-content-between fw-bold fs-6 mt-2 pt-2 border-top border-secondary">
                <span>TOTAL AMOUNT:</span>
                <span class="text-success">₱${parseFloat(total).toFixed(2)}</span>
            </div>
        `;
    })
    .catch(err => {
        itemsTable.innerHTML = `<tr><td colspan='3' class='text-center py-2 text-danger'>Error loading items.</td></tr>`;
    });
}

function closeModal() {
    if(invoiceModal) { invoiceModal.hide(); }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>