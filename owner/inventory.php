<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}
$owner_name = isset($_SESSION['username']) ? $_SESSION['username'] : "owner";
$low_stock_threshold = 10;

// Kunin ang napiling filter o sort mula sa GET request
$filter_mode = $_GET['filter_mode'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');

// Dynamic Image Resolver Function
function resolveProductImage($raw_image) {
    $img_src = '../assets/images/no-image.png';
    if (!empty($raw_image)) {
        $image_value = trim($raw_image);
        
        if (stripos($image_value, 'data:image/') === 0) {
            $img_src = $image_value;
        } elseif (filter_var($image_value, FILTER_VALIDATE_URL)) {
            $parsed_url = parse_url($image_value);
            $host = strtolower($parsed_url['host'] ?? '');
            
            if ($host === 'drive.google.com' || $host === 'docs.google.com') {
                parse_str($parsed_url['query'] ?? '', $query_params);
                $file_id = '';
                if (!empty($query_params['id'])) {
                    $file_id = $query_params['id'];
                } elseif (preg_match('#/file/d/([A-Za-z0-9_-]+)#', $parsed_url['path'] ?? '', $matches)) {
                    $file_id = $matches[1];
                }
                if ($file_id !== '') {
                    $img_src = '../drive-image.php?id=' . urlencode($file_id);
                }
            } else {
                $img_src = $image_value;
            }
        } elseif (preg_match('/^[A-Za-z0-9_-]{20,}$/', $image_value) && !str_contains($image_value, '/')) {
            $img_src = '../drive-image.php?id=' . urlencode($image_value);
        } else {
            $clean_path = ltrim($image_value, '/');
            if (str_starts_with($clean_path, '../')) {
                $img_src = $clean_path;
            } else {
                $img_src = '../' . $clean_path;
            }
        }
    }
    return $img_src;
}

// ==========================================
// 1. SUMMARY CARDS DATA FETCHING
// ==========================================
$count_query = $pdo->query("SELECT COUNT(*) as total FROM products UNION ALL SELECT COUNT(*) as total FROM product_variants");
$total_products = 0; 
while($cq = $count_query->fetch(PDO::FETCH_ASSOC)) { $total_products += $cq['total']; }

$stock_query = $pdo->query("SELECT SUM(stock) as total_stock FROM products UNION ALL SELECT SUM(stock) as total_stock FROM product_variants");
$total_stock = 0; 
while($sq = $stock_query->fetch(PDO::FETCH_ASSOC)) { $total_stock += $sq['total_stock']; }

$low_query = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock <= $low_stock_threshold AND stock > 0 UNION ALL SELECT COUNT(*) as total FROM product_variants WHERE stock <= $low_stock_threshold AND stock > 0");
$low_stock_count = 0; 
while($lq = $low_query->fetch(PDO::FETCH_ASSOC)) { $low_stock_count += $lq['total']; }

$out_query = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock = 0 UNION ALL SELECT COUNT(*) as total FROM product_variants WHERE stock = 0");
$out_of_stock_count = 0; 
while($oq = $out_query->fetch(PDO::FETCH_ASSOC)) { $out_of_stock_count += $oq['total']; }

// ==========================================
// 2. MAIN PRODUCTS & VARIANTS QUERY WITH FILTERS & SORTS
// ==========================================
$base_sql = "
    SELECT id, product_name, price, stock, image, is_archived, 'Main Product' AS item_type FROM products
    UNION ALL
    SELECT v.id, v.variant_name AS product_name, v.price, v.stock, COALESCE(NULLIF(v.variant_image, ''), p.image) AS image, p.is_archived, 'Variant Item' AS item_type 
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
";

// Pagbuo ng WHERE conditions batay sa napiling filter
$where_clauses = [];
if ($filter_mode === 'main_items') {
    $where_clauses[] = "item_type = 'Main Product'";
} elseif ($filter_mode === 'variant_items') {
    $where_clauses[] = "item_type = 'Variant Item'";
} elseif ($filter_mode === 'active_items') {
    $where_clauses[] = "is_archived = 0";
} elseif ($filter_mode === 'archive_items') {
    $where_clauses[] = "is_archived = 1";
} elseif ($filter_mode === 'low_stocks') {
    $where_clauses[] = "stock <= $low_stock_threshold AND stock > 0";
} elseif ($filter_mode === 'out_of_stocks') {
    $where_clauses[] = "stock = 0";
}

$final_query_sql = "SELECT * FROM ($base_sql) AS inventory_combined";
if (!empty($where_clauses)) {
    $final_query_sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Pagbuo ng ORDER BY batay sa sorting option
if ($filter_mode === 'az') {
    $final_query_sql .= " ORDER BY product_name ASC";
} elseif ($filter_mode === 'za') {
    $final_query_sql .= " ORDER BY product_name DESC";
} else {
    // Default or sort by item id
    $final_query_sql .= " ORDER BY id DESC";
}

$inventory_query = $pdo->query($final_query_sql);
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
        padding-top: 56px;
    }

    .wrapper {
        display: flex;
        width: 100%;
        min-height: calc(100vh - 56px);
    }

    nav.navbar {
        height: 56px;
        z-index: 1100;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
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
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 15px rgba(0,0,0,0.15);
        transition: transform 0.3s ease;
        z-index: 1040;
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
        margin-left: 260px;   
        padding: 20px;
        background: #f8f9fa;
        min-height: calc(100vh - 56px);
        overflow-x: hidden;
        transition: margin 0.3s ease;
    }

    .item-img {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        background-color: #f1f5f9;
        cursor: zoom-in;
        transition: transform 0.2s ease, opacity 0.2s ease;
    }
    
    .item-img:hover {
        transform: scale(1.05);
        opacity: 0.9;
    }

    #imageZoomModal {
        z-index: 2050 !important;
    }

    #imageZoomModal .modal-backdrop {
        z-index: 2040 !important;
    }

    .sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: #0d6efd;
        border-radius: 10px;
    }

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

    @media print {
        body {
            background: #fff;
            padding-top: 0;
        }
        .sidebar, nav, .btn, .filter-box {
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

<div class="wrapper">

    <div class="sidebar" id="appSidebar">
        <a href="dashboard.php" class="sidebar-link"><i class="bi bi-graph-up-arrow"></i> Dashboard</a>
        <a href="sales.php" class="sidebar-link"><i class="bi bi-cash-stack"></i> Sales</a>
        <a href="inventory.php" class="sidebar-link active"><i class="bi bi-box-seam"></i> Inventory Report</a>
        <a href="orders.php" class="sidebar-link"><i class="bi bi-clipboard-check"></i> Order Mgt. Monitoring</a>
        <a href="history.php" class="sidebar-link"><i class="bi bi-clock-history"></i> History</a>
        <a href="auditTrail.php" class="sidebar-link"><i class="bi bi-shield-lock"></i> Audit Trail</a>
         <a href="accountManagement.php" class="sidebar-link"><i class="bi bi-person-gear"></i> Account Management</a>
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
                    <div class="card card-counter shadow-sm bg-white p-3 border-start border-primary border-4 h-100">
                        <div class="text-muted small fw-bold text-uppercase">Total Items</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?php echo number_format($total_products); ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card card-counter shadow-sm bg-white p-3 border-start border-success border-4 h-100">
                        <div class="text-muted small fw-bold text-uppercase">Quantity On Hand</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?php echo number_format($total_stock); ?> Pcs</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card card-counter shadow-sm bg-white p-3 border-start border-warning border-4 h-100">
                        <div class="text-muted small fw-bold text-uppercase">Low Stock Warnings</div>
                        <div class="fs-3 fw-bold text-warning mt-1"><?php echo number_format($low_stock_count); ?> Items</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card card-counter shadow-sm bg-white p-3 border-start border-danger border-4 h-100">
                        <div class="text-muted small fw-bold text-uppercase">Out Of Stock</div>
                        <div class="fs-3 fw-bold text-danger mt-1"><?php echo number_format($out_of_stock_count); ?> Items</div>
                    </div>
                </div>
            </div>

            <!-- SEARCH AND FILTER BAR -->
            <div class="card p-3 mb-4 shadow-sm border-0 filter-box">
                <form method="GET" action="inventory.php" class="row g-2 align-items-center">
                    <div class="col-12 col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="liveSearch" name="search" value="<?php echo htmlspecialchars($search_query); ?>" onkeyup="filterInventory()" class="form-control border-start-0" placeholder="Type product or variant name to search instantly...">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <select name="filter_mode" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?php echo ($filter_mode === 'all') ? 'selected' : ''; ?>>All Items (Default)</option>
                            <option value="item_id" <?php echo ($filter_mode === 'item_id') ? 'selected' : ''; ?>>Sort by: Item ID (Newest)</option>
                            <option value="az" <?php echo ($filter_mode === 'az') ? 'selected' : ''; ?>>Sort by: A-Z</option>
                            <option value="za" <?php echo ($filter_mode === 'za') ? 'selected' : ''; ?>>Sort by: Z-A</option>
                            <option value="main_items" <?php echo ($filter_mode === 'main_items') ? 'selected' : ''; ?>>Only Main Items</option>
                            <option value="variant_items" <?php echo ($filter_mode === 'variant_items') ? 'selected' : ''; ?>>Only Variant Items</option>
                            <option value="active_items" <?php echo ($filter_mode === 'active_items') ? 'selected' : ''; ?>>Only Active Items</option>
                            <option value="archive_items" <?php echo ($filter_mode === 'archive_items') ? 'selected' : ''; ?>>Only Archive Items</option>
                            <option value="low_stocks" <?php echo ($filter_mode === 'low_stocks') ? 'selected' : ''; ?>>Only Low Stocks</option>
                            <option value="out_of_stocks" <?php echo ($filter_mode === 'out_of_stocks') ? 'selected' : ''; ?>>Only Out of Stocks</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <a href="inventory.php" class="btn btn-outline-secondary btn-sm w-100 fw-bold">Reset Filters</a>
                    </div>
                </form>
            </div>

            <div class="card table-card shadow-sm bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="inventoryTable">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3" style="width: 10%;">Item ID</th>
                                <th style="width: 10%;">Image</th>
                                <th style="width: 20%;">Products</th>
                                <th style="width: 12%;">Type</th>
                                <th style="width: 13%;">Price</th>
                                <th style="width: 10%;">Stock Qty</th>
                                <th class="text-center" style="width: 12%;">Stock Status</th>
                                <th class="pe-4 text-center" style="width: 13%;">Item Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rows = $inventory_query->fetchAll(PDO::FETCH_ASSOC);
                            if (count($rows) > 0) {
                                foreach($rows as $row) { 
                                    // Stock Level Status Badge
                                    if ($row['stock'] == 0) {
                                        $stock_status_badge = '<span class="badge bg-danger-subtle text-danger px-2 py-1 rounded">Out of Stock</span>';
                                    } elseif ($row['stock'] <= $low_stock_threshold) {
                                        $stock_status_badge = '<span class="badge bg-warning-subtle text-warning px-2 py-1 rounded">Low Stock</span>';
                                    } else {
                                        $stock_status_badge = '<span class="badge bg-success-subtle text-success px-2 py-1 rounded">Good Stock</span>';
                                    }

                                    // Active vs Archived Badge (Green = Active, Red = Archived)
                                    $item_status_badge = ($row['is_archived'] == 1)
                                        ? '<span class="badge bg-danger text-white px-2 py-1 rounded"><i class="bi bi-archive-fill me-1"></i> Archived</span>'
                                        : '<span class="badge bg-success text-white px-2 py-1 rounded"><i class="bi bi-check-circle-fill me-1"></i> Active</span>';

                                    $type_badge = ($row['item_type'] == 'Variant Item') 
                                        ? '<span class="text-purple fw-semibold small"><i class="bi bi-layers-half"></i> Variant</span>' 
                                        : '<span class="text-primary fw-semibold small"><i class="bi bi-box"></i> Main</span>';

                                    $image_path = resolveProductImage($row['image']);
                                    ?>
                                    <tr class="inventory-row" data-name="<?php echo strtolower($row['product_name']); ?>">
                                        <td class="ps-4 text-muted font-monospace">#<?php echo $row['id']; ?></td>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8'); ?>" 
                                                 class="item-img" 
                                                 alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                 onerror="this.onerror=null; this.src='../assets/images/no-image.png';"
                                                 loading="lazy"
                                                 onclick="openProductImageZoom(this.src)" 
                                                 title="Click to zoom image">
                                        </td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['product_name']); ?></td>
                                        <td><?php echo $type_badge; ?></td>
                                        <td class="fw-semibold">₱<?php echo number_format($row['price'], 2); ?></td>
                                        <td class="fw-bold"><?php echo $row['stock']; ?></td>
                                        <td class="text-center"><?php echo $stock_status_badge; ?></td>
                                        <td class="pe-4 text-center"><?php echo $item_status_badge; ?></td>
                                    </tr>
                                <?php } 
                            } else { ?>
                                <tr><td colspan="8" class="text-center py-5 text-muted">No operational inventory records found.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- FLOATING IMAGE ZOOM MODAL WITH CLOSE BUTTON -->
<div class="modal fade" id="imageZoomModal" tabindex="-1" aria-hidden="true" style="z-index: 2050;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="text-center">
                <img id="zoomImage" src="" class="img-fluid rounded shadow" style="max-height: 85vh; border: 3px solid #fff;">
            </div>
            <div class="text-center mt-3">
                <button type="button" class="btn px-4 py-2 fw-bold shadow" style="background:#6c757d; color:#fff; border:0;" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Function para buksan ang floating image zoom modal
function openProductImageZoom(imgSrc) {
    document.getElementById('zoomImage').src = imgSrc;
    var modal = new bootstrap.Modal(document.getElementById('imageZoomModal'), {
        backdrop: true,
        keyboard: true
    });
    modal.show();
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