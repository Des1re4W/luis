<?php
session_start();
include "../config/db.php";

// SECURITY GUARD: Siguraduhin na owner o admin lang ang makakapasok
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== "owner" && $_SESSION['role'] !== "admin")) {
    header("Location: ../login.php");
    exit();
}
$owner_name = isset($_SESSION['username']) ? $_SESSION['username'] : "owner";
$low_stock_threshold = 10;

// ==========================================
// 1. DATA FETCHING PARA SA MGA SUMMARY CARDS (Kasama ang Variants)
// ==========================================
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM products UNION ALL SELECT COUNT(*) as total FROM product_variants");
$total_products = 0; while($cq = mysqli_fetch_assoc($count_query)) { $total_products += $cq['total']; }

$stock_query = mysqli_query($conn, "SELECT SUM(stock) as total_stock FROM products UNION ALL SELECT SUM(stock) as total_stock FROM product_variants");
$total_stock = 0; while($sq = mysqli_fetch_assoc($stock_query)) { $total_stock += $sq['total_stock']; }

$low_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE stock <= $low_stock_threshold AND stock > 0 UNION ALL SELECT COUNT(*) as total FROM product_variants WHERE stock <= $low_stock_threshold AND stock > 0");
$low_stock_count = 0; while($lq = mysqli_fetch_assoc($low_query)) { $low_stock_count += $lq['total']; }

$out_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE stock = 0 UNION ALL SELECT COUNT(*) as total FROM product_variants WHERE stock = 0");
$out_of_stock_count = 0; while($oq = mysqli_fetch_assoc($out_query)) { $out_of_stock_count += $oq['total']; }

$inventory_query = mysqli_query($conn, "
    SELECT id, product_name, price, stock, 'Main Product' AS item_type FROM products
    UNION ALL
    SELECT v.id, v.variant_name AS product_name, v.price, v.stock, 'Variant Item' AS item_type 
    FROM product_variants v
    ORDER BY product_name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report | Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
    body { 
    background-color: #f8fafc; 
    font-family: 'Segoe UI', system-ui, sans-serif; 
    overflow-x: hidden;
}

.wrapper {
    display: flex;
    width: 100%;
    min-height: calc(100vh - 56px);
}

nav.navbar {
    height: 56px;
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
}

.sidebar-link {
    color: #adb5bd;
    text-decoration: none;

    padding: 12px 14px;
    margin-bottom: 8px;

    border-radius: 12px;

    font-size: 15px;
    font-weight: 500;

    display: flex;
    align-items: center;

    transition: all 0.25s ease;
}

.sidebar-link i {
    margin-right: 10px;
}
.sidebar-link:hover {
    background: #0d6efd;
    color: #fff;
    transform: translateX(4px);
}


.sidebar-link.active {
    background: #0d6efd;
    color: #fff;
}


.logout-section {
    margin-top: auto;
    padding-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.2);
}
.main-content {
    flex: 1;
    margin-left: 280px;   
    margin-right: 0;      
    padding: 20px;

    width: calc(100% - 280px);
    background: #f8f9fa;
    min-height: 100vh;
    overflow-x: hidden;
}
body {
    padding-top: 56px;
}

.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #0d6efd;
    border-radius: 10px;
}
@media print {
    body {
        background: #fff;
    }

    .sidebar,
    nav,
    .btn,
    .filter-box {
        display: none !important;
    }

    .main-content {
        margin: 0;
        padding: 0;
        height: auto;
    }
}
</style>
</head>
<body>

<nav class="navbar navbar-dark px-3" style="background-color:#051c3c; height:56px; position:fixed; top:0; left:0; right:0; z-index:1100;">
    <div class="d-flex align-items-center w-100">
        <div class="d-flex align-items-center gap-2">
            <img src="../assets/logo.jpg"
                 style="width:38px; height:38px; border-radius:50%; border:2px solid white; object-fit:cover;">
            <span class="navbar-brand fw-bold mb-0">Owner Panel</span>
        </div>
        <!-- PUSH RIGHT -->
        <div class="ms-auto d-flex align-items-center text-white gap-2">
            <i class="bi bi-person-circle"></i>
            <span>
                Welcome, <strong><?= htmlspecialchars($owner_name); ?></strong>
            </span>
        </div>
    </div>
</nav>


<div class="wrapper">

    <div class="sidebar">
        <a href="dashboard.php" class="sidebar-link"><i class="bi bi-graph-up-arrow"></i> Dashboard</a>
        <a href="sales.php" class="sidebar-link"><i class="bi bi-cash-stack"></i> Sales</a>
        <a href="inventory.php" class="sidebar-link active"><i class="bi bi-box-seam"></i> Inventory Report</a>
        <a href="orders.php" class="sidebar-link"><i class="bi bi-clipboard-check"></i> Order Mgt. Monitoring</a>
        <a href="history.php" class="sidebar-link"><i class="bi bi-clock-history"></i> History</a>
        <a href="auditTrail.php" class="sidebar-link"><i class="bi bi-shield-lock"></i> Audit Trail</a>
         <div class="mt-auto border-top border-secondary">
        <a href="../logout.php" class="sidebar-link text-danger" onclick="return confirm('Are you sure you want to log out?')">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
    </div>

    <div class="main-content">
        <div class="container-fluid p-0">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark m-0">Inventory Report</h2>
                    <small class="text-muted">Real-time stock level monitoring for both Main Products and Variant Items.</small>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="triggerPrint()" class="btn btn-outline-secondary btn-sm fw-bold"><i class="bi bi-printer"></i> Print / PDF</button>
                    <button onclick="exportExcel()" class="btn btn-success btn-sm fw-bold"><i class="bi bi-file-earmark-excel"></i> Export Excel</button>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card card-counter shadow-sm bg-white p-3 border-start border-primary border-4">
                        <div class="text-muted small fw-bold text-uppercase">Total Items</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?php echo number_format($total_products); ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card card-counter shadow-sm bg-white p-3 border-start border-success border-4">
                        <div class="text-muted small fw-bold text-uppercase">Quantity On Hand</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?php echo number_format($total_stock); ?> Pcs</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card card-counter shadow-sm bg-white p-3 border-start border-warning border-4">
                        <div class="text-muted small fw-bold text-uppercase">Low Stock Warnings</div>
                        <div class="fs-3 fw-bold text-warning mt-1"><?php echo number_format($low_stock_count); ?> Items</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card card-counter shadow-sm bg-white p-3 border-start border-danger border-4">
                        <div class="text-muted small fw-bold text-uppercase">Out Of Stock</div>
                        <div class="fs-3 fw-bold text-danger mt-1"><?php echo number_format($out_of_stock_count); ?> Items</div>
                    </div>
                </div>
            </div>

            <div class="card p-3 mb-4 shadow-sm border-0 filter-box">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="liveSearch" onkeyup="filterInventory()" class="form-control border-start-0" placeholder="Type product or variant name to search instantly...">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card table-card shadow-sm bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="inventoryTable">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3" style="width: 15%;">Item ID</th>
                                <th style="width: 18%;">Products</th>
                                <th style="width: 15%;">Type</th>
                                <th style="width: 15%;">Price</th>
                                <th style="width: 10%;">Stock Qty</th>
                                <th class="pe-4 text-center" style="width: 10%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($inventory_query) > 0) {
                                while($row = mysqli_fetch_assoc($inventory_query)) { 
                                    if ($row['stock'] == 0) {
                                        $status_badge = '<span class="badge bg-danger-subtle text-danger px-2 py-1 rounded">Out of Stock</span>';
                                    } elseif ($row['stock'] <= $low_stock_threshold) {
                                        $status_badge = '<span class="badge bg-warning-subtle text-warning px-2 py-1 rounded">Low Stock</span>';
                                    } else {
                                        $status_badge = '<span class="badge bg-success-subtle text-success px-2 py-1 rounded">Good Stock</span>';
                                    }

                                    $type_badge = ($row['item_type'] == 'Variant Item') 
                                        ? '<span class="text-purple fw-semibold small"><i class="bi bi-layers-half"></i> Variant</span>' 
                                        : '<span class="text-primary fw-semibold small"><i class="bi bi-box"></i> Main</span>';
                                    ?>
                                    <tr class="inventory-row" data-name="<?php echo strtolower($row['product_name']); ?>">
                                        <td class="ps-4 text-muted font-monospace">#<?php echo $row['id']; ?></td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['product_name']); ?></td>
                                        <td><?php echo $type_badge; ?></td>
                                        <td class="fw-semibold">₱<?php echo number_format($row['price'], 2); ?></td>
                                        <td class="fw-bold"><?php echo $row['stock']; ?></td>
                                        <td class="pe-4 text-center"><?php echo $status_badge; ?></td>
                                    </tr>
                                <?php } 
                            } else { ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No operational inventory records found.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// 1. LIVE SEARCH FILTER SYSTEM
function filterInventory() {
    let input = document.getElementById("liveSearch").value.toLowerCase();
    let rows = document.querySelectorAll(".inventory-row");
    rows.forEach(row => {
        let textContent = row.getAttribute("data-name");
        if (textContent.includes(input)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

// 2. NATIVE PRINT UTILITY
function triggerPrint() { window.print(); }

// 3. EXPORT FILE TO EXCEL LOGIC
function exportExcel() {
    let table = document.getElementById("inventoryTable");
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    let downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    let today = new Date().toISOString().slice(0, 10);
    downloadLink.download = 'Master_Inventory_Report_' + today + '.xls';
    downloadLink.href = url;
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>