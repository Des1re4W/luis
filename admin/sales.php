<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

$filter = $_GET['filter'] ?? 'all';
$date_selected = $_GET['date'] ?? "";
$view_type = $_GET['view'] ?? 'sales';
$type_filter = $_GET['type'] ?? 'all';

function getWhereClause($filter, $date_selected, $date_column) {
    $where = " WHERE 1=1 ";
    if (!empty($date_selected)) {
        $where .= " AND DATE($date_column) = '$date_selected'";
    } else {
        if ($filter == 'daily') {
            $where .= " AND DATE($date_column) = CURDATE()";
        } elseif ($filter == 'weekly') {
            $where .= " AND YEARWEEK($date_column, 1) = YEARWEEK(CURDATE(), 1)";
        } elseif ($filter == 'monthly') {
            $where .= " AND MONTH($date_column) = MONTH(CURDATE()) AND YEAR($date_column) = YEAR(CURDATE())";
        } elseif ($filter == 'yearly') {
            $where .= " AND YEAR($date_column) = YEAR(CURDATE())";
        }
    }
    return $where;
}

$where_orders = getWhereClause($filter, $date_selected, 'order_date');
$where_pos = getWhereClause($filter, $date_selected, 'transaction_date');

// QUERY SA LISTAHAN NG SALES
$sql = "
    (SELECT id, fullname as customer_name, 'Online' as type, total_amount, order_date as date_recorded 
     FROM orders $where_orders AND status = 'completed')
    UNION ALL
    (SELECT id, cashier_name as customer_name, 'Walk-in' as type, total_amount, transaction_date as date_recorded 
     FROM pos_transaction $where_pos)
    ORDER BY date_recorded DESC";

$result = mysqli_query($conn, $sql);
$totalSales = 0;
$totalProfit = 0;

// Selling Price - Cost Price
// 1. ONLINE PROFIT & SALES
$online_profit_query = "
    SELECT SUM(oi.qty * (oi.price - p.cost_price)) as profit, SUM(oi.qty * oi.price) as sales
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    $where_orders AND o.status = 'completed'";
$online_res = mysqli_fetch_assoc(mysqli_query($conn, $online_profit_query));

// 2. POS PROFIT & SALES (Dito tayo nagdagdag)
$pos_profit_query = "
    SELECT SUM(pi.qty * (pi.price - p.cost_price)) as profit, SUM(pi.qty * pi.price) as sales
    FROM pos_transaction_items pi
    JOIN pos_transaction pt ON pi.transaction_id = pt.id
    JOIN products p ON pi.product_id = p.id
    $where_pos"; 
$pos_res = mysqli_fetch_assoc(mysqli_query($conn, $pos_profit_query));

// 3. COMBINE BOTH (Online + Walk-in)
$totalSales = ($online_res['sales'] ?? 0) + ($pos_res['sales'] ?? 0);
$totalProfit = ($online_res['profit'] ?? 0) + ($pos_res['profit'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .stat-card { border-left: 5px solid #007bff; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .badge-online { background-color: #e3f2fd; color: #0d47a1; }
        .badge-walkin { background-color: #f3e5f5; color: #4a148c; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">Sales & Profit Report</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>

    <div class="card p-4 mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">Select Period</label>
                <select name="filter" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php if($filter == 'all') echo 'selected'; ?>>All Time</option>
                    <option value="daily" <?php if($filter == 'daily') echo 'selected'; ?>>Daily</option>
                    <option value="weekly" <?php if($filter == 'weekly') echo 'selected'; ?>>Weekly</option>
                    <option value="monthly" <?php if($filter == 'monthly') echo 'selected'; ?>>Monthly</option>
                    <option value="yearly" <?php if($filter == 'yearly') echo 'selected'; ?>>Yearly</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Specific Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo $date_selected; ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">View Mode</label>
                <select name="view" class="form-select" onchange="this.form.submit()">
                    <option value="sales" <?php if($view_type == 'sales') echo 'selected'; ?>>Total Sales</option>
                    <option value="profit" <?php if($view_type == 'profit') echo 'selected'; ?>>Total Profit</option>
                </select>
            </div>
        </form>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <?php if($view_type == 'sales'): ?>
                <div class="card p-4 stat-card bg-primary text-white border-0">
                    <small class="text-uppercase fw-bold opacity-75">Estimated Total Sales</small>
                    <h1 class="fw-bold mb-0">₱<?php echo number_format($totalSales, 2); ?></h1>
                </div>
            <?php else: ?>
                <div class="card p-4 stat-card bg-success text-white border-0" style="border-left-color: #1b5e20 !important;">
                    <small class="text-uppercase fw-bold opacity-75">Estimated Total Profit</small>
                    <h1 class="fw-bold mb-0">₱<?php echo number_format($totalProfit, 2); ?></h1>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Order ID</th>
                            <th>Customer / Cashier</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0) { 
                            while($row = mysqli_fetch_assoc($result)) { ?>
                            <tr>
                                <td class="ps-4 fw-bold">#<?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td>
                                    <?php if($row['type'] == 'Online'): ?>
                                        <span class="badge badge-online px-3 py-2">Online</span>
                                    <?php else: ?>
                                        <span class="badge badge-walkin px-3 py-2">Walk-in</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td class="text-center text-muted small">
                                    <?php echo date('M d, Y', strtotime($row['date_recorded'])); ?><br>
                                    <?php echo date('h:i A', strtotime($row['date_recorded'])); ?>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No records found.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>