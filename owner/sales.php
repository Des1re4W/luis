<?php
session_start();
require_once __DIR__ . '/config/db.php';

// 1. SECURITY: Siguraduhin na 'owner' lang ang makakapasok
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}
$owner_name = isset($_SESSION['username']) ? $_SESSION['username'] : "owner";
$filter = $_GET['filter'] ?? 'all';
$date_selected = $_GET['date'] ?? "";
$type_filter = $_GET['type'] ?? 'all';

// Helper: Date filtering logic
function getCleanWhere($filter, $date_selected, $date_column) {
    $where = " WHERE 1=1 ";
    if (!empty($date_selected)) {
        $where .= " AND DATE($date_column) = '$date_selected'";
    } else {
        if ($filter == 'daily') { $where .= " AND DATE($date_column) = CURDATE()"; }
        elseif ($filter == 'weekly') { $where .= " AND YEARWEEK($date_column, 1) = YEARWEEK(CURDATE(), 1)"; }
        elseif ($filter == 'monthly') { $where .= " AND MONTH($date_column) = MONTH(CURDATE()) AND YEAR($date_column) = YEAR(CURDATE())"; }
        elseif ($filter == 'yearly') { $where .= " AND YEAR($date_column) = YEAR(CURDATE())"; }
    }
    return $where;
}

$where_orders = getCleanWhere($filter, $date_selected, 'order_date');
$where_pos = getCleanWhere($filter, $date_selected, 'transaction_date');

$grand_total_sales = 0;
$grand_total_profit = 0;
$sales_ledger_data = [];

// A. Online Orders (Only 'completed')
if ($type_filter == 'all' || $type_filter == 'online') {

    $orders_query = mysqli_query($conn, "
        SELECT id,
               CONCAT(fullname, ' ', surname) AS name,
               total_amount,
               order_date
        FROM orders
        $where_orders
        AND status = 'completed'
    ");

    while ($ord = mysqli_fetch_assoc($orders_query)) {

        $total_cost = 0;

        $items_q = mysqli_query($conn, "
            SELECT product_id,
                   variant_id,
                   qty,
                   cost_price
            FROM order_items
            WHERE order_id = " . (int)$ord['id']
        );

        while ($item = mysqli_fetch_assoc($items_q)) {

            $item_cost = 0;

            // Use saved cost_price if available
            if (!empty($item['cost_price']) && $item['cost_price'] > 0) {

                $item_cost = (float)$item['cost_price'];

            } else {

                // Variant item
                if (!empty($item['variant_id'])) {

                    $variant_q = mysqli_query(
                        $conn,
                        "SELECT cost_price
                         FROM product_variants
                         WHERE id = " . (int)$item['variant_id']
                    );

                    if ($variant_q && mysqli_num_rows($variant_q) > 0) {
                        $variant = mysqli_fetch_assoc($variant_q);
                        $item_cost = (float)($variant['cost_price'] ?? 0);
                    }

                }
                // Regular product
                else {

                    $product_q = mysqli_query(
                        $conn,
                        "SELECT cost_price
                         FROM products
                         WHERE id = " . (int)$item['product_id']
                    );

                    if ($product_q && mysqli_num_rows($product_q) > 0) {
                        $product = mysqli_fetch_assoc($product_q);
                        $item_cost = (float)($product['cost_price'] ?? 0);
                    }
                }
            }

            $total_cost += ($item_cost * (int)$item['qty']);
        }

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

// B. POS Walk-in Calculation
if ($type_filter == 'all' || $type_filter == 'walkin') {
    $pos_query = mysqli_query($conn, "SELECT id, cashier_name, total_amount, transaction_date FROM pos_transaction $where_pos");
    while ($pos = mysqli_fetch_assoc($pos_query)) {
        $total_cost = 0;
        $items_q = mysqli_query($conn, "SELECT product_id, variant_id, qty FROM pos_transaction_items WHERE transaction_id = " . (int)$pos['id']);
        while ($item = mysqli_fetch_assoc($items_q)) {
            if ($item['variant_id'] > 0) {
                $v = mysqli_fetch_assoc(mysqli_query($conn, "SELECT cost_price FROM product_variants WHERE id = " . (int)$item['variant_id']));
                $total_cost += ($v['cost_price'] ?? 0) * $item['qty'];
            } elseif (!empty($item['product_id'])) {
                $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT cost_price FROM products WHERE id = " . (int)$item['product_id']));
                $total_cost += ($p['cost_price'] ?? 0) * $item['qty'];
            }
        }
        $profit = (float)$pos['total_amount'] - $total_cost;
        $grand_total_sales += (float)$pos['total_amount'];
        $grand_total_profit += $profit;
        $sales_ledger_data[] = ['id' => $pos['id'], 'customer' => $pos['cashier_name'], 'type' => 'Walk-in', 'sales' => $pos['total_amount'], 'profit' => $profit, 'date' => $pos['transaction_date']];
    }
}

usort($sales_ledger_data, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
}

/* LINKS */
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

    width: calc(100% - 260px);
    min-height:100vh;

    box-sizing:border-box;
}

.sidebar::-webkit-scrollbar{
    width:5px;
}

.sidebar::-webkit-scrollbar-thumb{
    background:#0d6efd;
    border-radius:10px;
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


<div class="d-flex">
    <div class="sidebar">
        <a href="dashboard.php" class="sidebar-link"><i class="bi bi-graph-up-arrow"></i> Dashboard</a>
        <a href="sales.php" class="sidebar-link active"><i class="bi bi-cash-stack"></i> Sales</a>
        <a href="inventory.php" class="sidebar-link"><i class="bi bi-box-seam"></i> Inventory</a>
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
    <h3>Sales & Profit Report</h3>
    
    <form method="GET" class="row g-3 mb-4 mt-2">
            <div class="col-auto">
                <select name="type" class="form-select">
                    <option value="all" <?=$type_filter=='all'?'selected':''?>>All Channels</option>
                    <option value="online" <?=$type_filter=='online'?'selected':''?>>Online</option>
                    <option value="walkin" <?=$type_filter=='walkin'?'selected':''?>>Walk-in</option>
                </select>
            </div>
            <div class="col-auto">
            <select name="filter" class="form-select">
            <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Time</option>
            <option value="daily" <?= $filter == 'daily' ? 'selected' : '' ?>>Today (Daily)</option>
            <option value="weekly" <?= $filter == 'weekly' ? 'selected' : '' ?>>This Week (Weekly)</option>
            <option value="monthly" <?= $filter == 'monthly' ? 'selected' : '' ?>>This Month (Monthly)</option>
            <option value="yearly" <?= $filter == 'yearly' ? 'selected' : '' ?>>This Year (Yearly)</option>
            </select>
            </div>
            <div class="col-auto">
                <input type="date" name="date" class="form-control" value="<?=$date_selected?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="sales.php" class="btn btn-secondary">Clear</a>
            </div>
        </form>

   <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card card-counter bg-white p-3 border-start border-primary border-4">
            <div class="text-muted small fw-bold text-uppercase">Total Sales</div>
            <div class="fs-3 fw-bold text-primary mt-1">₱<?=number_format($grand_total_sales, 2)?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-counter bg-white p-3 border-start border-success border-4">
            <div class="text-muted small fw-bold text-uppercase">Total Profit</div>
            <div class="fs-3 fw-bold text-success mt-1">₱<?=number_format($grand_total_profit, 2)?></div>
        </div>
    </div>
</div>
    
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
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
</body>
</html>