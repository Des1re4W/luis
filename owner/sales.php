<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role'] ) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}
$owner_name = isset($_SESSION['username']) ? $_SESSION['username'] : "owner";
$filter = $_GET['filter'] ?? 'all';
$date_selected = $_GET['date'] ?? "";
$type_filter = $_GET['type'] ?? 'all';

function getCleanWhere($filter, $date_selected, $date_column) {
    $where = " WHERE 1=1 ";
    if (!empty($date_selected)) {
        $where .= " AND DATE($date_column) = '$date_selected'";
    } else {
        if ($filter == 'daily') { 
            $where .= " AND DATE($date_column) = CURRENT_DATE"; 
        }
        elseif ($filter == 'weekly') { 
            $where .= " AND DATE_TRUNC('week', $date_column) = DATE_TRUNC('week', CURRENT_DATE)"; 
        }
        elseif ($filter == 'monthly') { 
            $where .= " AND EXTRACT(MONTH FROM $date_column) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM $date_column) = EXTRACT(YEAR FROM CURRENT_DATE)"; 
        }
        elseif ($filter == 'yearly') { 
            $where .= " AND EXTRACT(YEAR FROM $date_column) = EXTRACT(YEAR FROM CURRENT_DATE)"; 
        }
    }
    return $where;
}

$where_orders = getCleanWhere($filter, $date_selected, 'o.order_date');
$where_pos = getCleanWhere($filter, $date_selected, 'pt.transaction_date');

$grand_total_sales = 0;
$grand_total_profit = 0;
$sales_ledger_data = [];

// A. Online Orders (Optimized single query with SUM and JOIN)
if ($type_filter == 'all' || $type_filter == 'online') {
    $orders_query = $pdo->query("
        SELECT o.id,
               CONCAT(o.fullname, ' ', o.surname) AS name,
               o.total_amount,
               o.order_date,
               SUM(
                   COALESCE(NULLIF(oi.cost_price, 0), pv.cost_price, p.cost_price, 0) * oi.qty
               ) AS total_cost
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_variants pv ON oi.variant_id = pv.id
        $where_orders
        AND o.status = 'completed'
        GROUP BY o.id, o.fullname, o.surname, o.total_amount, o.order_date
    ");

    while ($ord = $orders_query->fetch(PDO::FETCH_ASSOC)) {
        $total_cost = (float)($ord['total_cost'] ?? 0);
        $profit = (float)$ord['total_amount'] - $total_cost;

        $grand_total_sales += (float)$ord['total_amount'];
        $grand_total_profit += $profit;

        $sales_ledger_data[] = [
            'id'       => $ord['id'],
            'customer' => $ord['name'],
            'type'     => 'Online',
            'sales'    => $ord['total_amount'],
            'profit'   => $profit,
            'date'     => $ord['order_date']
        ];
    }
}

// B. POS Walk-in Calculation (Optimized single query with SUM and JOIN)
if ($type_filter == 'all' || $type_filter == 'walkin') {
    $pos_query = $pdo->query("
        SELECT pt.id,
               pt.cashier_name,
               pt.total_amount,
               pt.transaction_date,
               SUM(
                   COALESCE(pv.cost_price, p.cost_price, 0) * pti.qty
               ) AS total_cost
        FROM pos_transaction pt
        JOIN pos_transaction_items pti ON pt.id = pti.transaction_id
        LEFT JOIN products p ON pti.product_id = p.id
        LEFT JOIN product_variants pv ON pti.variant_id = pv.id
        $where_pos
        GROUP BY pt.id, pt.cashier_name, pt.total_amount, pt.transaction_date
    ");

    while ($pos = $pos_query->fetch(PDO::FETCH_ASSOC)) {
        $total_cost = (float)($pos['total_cost'] ?? 0);
        $profit = (float)$pos['total_amount'] - $total_cost;
        
        $grand_total_sales += (float)$pos['total_amount'];
        $grand_total_profit += $profit;
        
        $sales_ledger_data[] = [
            'id'       => $pos['id'], 
            'customer' => $pos['cashier_name'], 
            'type'     => 'Walk-in', 
            'sales'    => $pos['total_amount'], 
            'profit'   => $profit, 
            'date'     => $pos['transaction_date']
        ];
    }
}

usort($sales_ledger_data, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales & Profit Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
body{
    margin:0;
    overflow-x:hidden;
    background:#f8f9fa;
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
    margin-top:56px;
    padding:20px;
    min-height:100vh;
    transition: margin 0.3s ease;
}

.sidebar::-webkit-scrollbar{
    width:5px;
}

.sidebar::-webkit-scrollbar-thumb{
    background:#0d6efd;
    border-radius:10px;
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
    <a href="sales.php" class="sidebar-link active"><i class="bi bi-cash-stack"></i> Sales</a>
    <a href="inventory.php" class="sidebar-link"><i class="bi bi-box-seam"></i> Inventory</a>
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
    <h3 class="mb-4">Sales & Profit Report</h3>
    
    <form method="GET" class="row g-3 mb-4 mt-2">
        <div class="col-12 col-md-auto">
            <select name="type" class="form-select">
                <option value="all" <?=$type_filter=='all'?'selected':''?>>All Channels</option>
                <option value="online" <?=$type_filter=='online'?'selected':''?>>Online</option>
                <option value="walkin" <?=$type_filter=='walkin'?'selected':''?>>Walk-in</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <select name="filter" class="form-select">
                <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Time</option>
                <option value="daily" <?= $filter == 'daily' ? 'selected' : '' ?>>Today (Daily)</option>
                <option value="weekly" <?= $filter == 'weekly' ? 'selected' : '' ?>>This Week (Weekly)</option>
                <option value="monthly" <?= $filter == 'monthly' ? 'selected' : '' ?>>This Month (Monthly)</option>
                <option value="yearly" <?= $filter == 'yearly' ? 'selected' : '' ?>>This Year (Yearly)</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <input type="date" name="date" class="form-control" value="<?=$date_selected?>">
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="sales.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card card-counter bg-white p-3 border-start border-primary border-4 h-100">
                <div class="text-muted small fw-bold text-uppercase">Total Sales</div>
                <div class="fs-4 fw-bold text-primary mt-1">₱<?=number_format($grand_total_sales, 2)?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-counter bg-white p-3 border-start border-success border-4 h-100">
                <div class="text-muted small fw-bold text-uppercase">Total Profit</div>
                <div class="fs-4 fw-bold text-success mt-1">₱<?=number_format($grand_total_profit, 2)?></div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Customer/Cashier</th>
                        <th>Channel</th>
                        <th>Sales</th>
                        <th>Profit</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales_ledger_data)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No records found for the selected filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales_ledger_data as $row) { ?>
                        <tr>
                            <td>#<?=$row['id']?></td>
                            <td><?=htmlspecialchars($row['customer'])?></td>
                            <td><?=$row['type']?></td>
                            <td>₱<?=number_format($row['sales'], 2)?></td>
                            <td class="text-success fw-bold">₱<?=number_format($row['profit'], 2)?></td>
                            <td><?=date('M d, Y h:i A', strtotime($row['date']))?></td>
                        </tr>
                        <?php } ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
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