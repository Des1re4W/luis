<?php
session_start();
require_once __DIR__ . '/config/db.php';

// SECURITY: Owner lang ang makakapasok
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}
$owner_name = isset($_SESSION['username']) ? $_SESSION['username'] : "owner";
// Fetch lahat ng orders kasama ang admin name (kung meron)
// Gagamit tayo ng LEFT JOIN sa 'users' table base sa 'approved_by' column
$orders_query = mysqli_query($conn, "SELECT o.*, 
                                     u1.username as approved_admin, 
                                     u2.username as declined_admin 
                                     FROM orders o 
                                     LEFT JOIN users u1 ON o.approved_by = u1.id 
                                     LEFT JOIN users u2 ON o.declined_by = u2.id 
                                     ORDER BY o.order_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management | Owner Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
 body{
    margin:0;
    overflow-x:hidden;
    background:#f8f9fa;
}

/* NAVBAR */
.navbar{
    height:56px;
    z-index:1100;
    position:fixed;
    top:0;
    left:0;
    right:0;
}

/* SIDEBAR (REFERENCE STYLE MO NA CLEAN VERSION) */
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

/* LOGOUT */
.logout-section{
    margin-top:auto;
    padding-top:15px;
    border-top:1px solid rgba(255,255,255,0.2);
}

/* MAIN CONTENT (🔴 FIX NG RIGHT SPACE ISSUE) */
.main-content{
    margin-left:260px;
    margin-top:56px;

    padding:20px;

    width: calc(100% - 260px); /* 🔥 FIX: tanggal right empty space */
    min-height:100vh;

    box-sizing:border-box;
}

/* SCROLLBAR */
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
         <a href="dashboard.php" class="sidebar-link"><i class="bi bi-graph-up-arrow"></i>  Dashboard</a>
        <a href="sales.php" class="sidebar-link"><i class="bi bi-cash-stack"></i> Sales</a>
        <a href="inventory.php" class="sidebar-link"><i class="bi bi-box-seam"></i> Inventory</a>
        <a href="orders.php" class="sidebar-link active"><i class="bi bi-clipboard-check"></i> Order Mgt. Monitoring</a>
        <a href="history.php" class="sidebar-link"><i class="bi bi-clock-history"></i> History</a>
        <a href="auditTrail.php" class="sidebar-link"><i class="bi bi-shield-lock"></i> Audit Trail</a>
         <div class="mt-auto border-top border-secondary">
        <a href="../logout.php" class="sidebar-link text-danger" onclick="return confirm('Are you sure you want to log out?')">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
    </div>

    <div class="main-content">
        <h3>All Customer Orders</h3>
        <div class="card shadow-sm mt-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                     <thead class="table-light">
    <tr>
        <th>Order ID</th>
        <th>Customer</th>
        <th>Status</th>
        <th>Processed By (Admin)</th>
        <th>Date</th>
    </tr>
</thead>
<tbody>
    <?php while($row = mysqli_fetch_assoc($orders_query)) { ?>
    <tr>
        <td>#<?php echo $row['id']; ?></td>
        <td><?php echo htmlspecialchars($row['fullname'] . ' ' . $row['surname']); ?></td>
        <td>
            <?php 
                $status = $row['status'];
                $color = [
                    'pending'         => 'warning', 
                    'preparing'       => 'info', 
                    'out-of-delivery' => 'primary', 
                    'completed'       => 'success', 
                    'declined'        => 'danger'
                ][$status] ?? 'secondary';
            ?>
            <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($status); ?></span>
        </td>
        <td>
            <?php 
                if (!empty($row['approved_admin'])) {
                    echo '<span class="text-success">Approved: <strong>' . htmlspecialchars($row['approved_admin']) . '</strong></span>';
                } elseif (!empty($row['declined_admin'])) {
                    echo '<span class="text-danger">Declined: <strong>' . htmlspecialchars($row['declined_admin']) . '</strong></span>';
                } else {
                    echo '<small class="text-muted">N/A</small>';
                }
            ?>
        </td>
        <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
    </tr>
    <?php } ?>
    <?php if(mysqli_num_rows($orders_query) == 0): ?>
    <tr>
        <td colspan="5" class="text-center py-3 text-muted">No order record</td>
    </tr>
    <?php endif; ?>
</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>