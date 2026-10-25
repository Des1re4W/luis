<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}

$owner_name = $_SESSION['username'] ?? "owner";

// DATA
$sales_query = $pdo->query("SELECT SUM(total_amount) as total_sales FROM orders WHERE status = 'completed'");
$sales_data = $sales_query->fetch(PDO::FETCH_ASSOC);

$pending_query = $pdo->query("SELECT COUNT(*) as pending_count FROM orders WHERE status = 'pending'");
$pending_data = $pending_query->fetch(PDO::FETCH_ASSOC);

$orders_query = $pdo->query("SELECT * FROM orders ORDER BY order_date DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Dashboard</title>

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

/* SIDEBAR */
.sidebar{
    position:fixed;
    top:56px; /* below navbar */
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

/* MAIN CONTENT */
.main-content{
    margin-left:260px;
    margin-top:56px;
    padding:20px;
    min-height:100vh;
    transition: margin 0.3s ease;
}

/* SCROLLBAR */
.sidebar::-webkit-scrollbar{
    width:5px;
}

.sidebar::-webkit-scrollbar-thumb{
    background:#0d6efd;
    border-radius:10px;
}

/* CARDS */
.card-stats{border-left:5px solid #0d6efd;}
.bg-warning-stats{border-left:5px solid #ffc107;}

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

<!-- NAVBAR -->
<nav class="navbar navbar-dark px-3" style="background-color:#051c3c;">
    <div class="d-flex align-items-center w-100 justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <img src="../assets/logo.jpg"
                 style="width:38px; height:38px; border-radius:50%; border:2px solid white; object-fit:cover;" onerror="this.style.display='none'">
            <span class="navbar-brand fw-bold mb-0">Owner Panel</span>
        </div>
        <!-- KANANG ITAAS (TOP RIGHT) - Welcome message at Burger Menu -->
        <div class="d-flex align-items-center text-white gap-3">
            <span class="d-none d-sm-inline">
                Welcome, <strong><?= htmlspecialchars($owner_name); ?></strong>
            </span>
            <button class="btn btn-outline-light btn-sm" type="button" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="bi bi-list fs-5"></i>
            </button>
        </div>
    </div>
</nav>

<!-- SIDEBAR -->
<div class="sidebar" id="appSidebar">

    <a href="dashboard.php" class="sidebar-link active">
        <i class="bi bi-graph-up-arrow"></i> Dashboard
    </a>

    <a href="sales.php" class="sidebar-link">
        <i class="bi bi-cash-stack"></i> Sales
    </a>

    <a href="inventory.php" class="sidebar-link">
        <i class="bi bi-box-seam"></i> Inventory
    </a>

    <a href="orders.php" class="sidebar-link">
        <i class="bi bi-clipboard-check"></i> Order Mgt. Monitoring
    </a>

    <a href="history.php" class="sidebar-link">
        <i class="bi bi-clock-history"></i> History
    </a>

    <a href="auditTrail.php" class="sidebar-link">
        <i class="bi bi-shield-lock"></i> Audit Trail
    </a>

    <div class="logout-section">
         <a href="../logout.php" class="sidebar-link text-danger" onclick="return confirm('Are you sure you want to log out?')">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>

</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <h3 class="mb-4">Dashboard Overview</h3>

    <div class="row">

        <div class="col-md-6 mb-3">
            <div class="card shadow-sm card-stats h-100">
                <div class="card-body">
                    <h6 class="text-muted">Total Completed Sales</h6>
                    <h3 class="text-primary fw-bold">
                        ₱<?= number_format($sales_data['total_sales'] ?? 0, 2); ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card shadow-sm bg-warning-stats h-100">
                <div class="card-body">
                    <h6 class="text-muted">Pending Orders</h6>
                    <h3 class="text-warning fw-bold">
                        <?= $pending_data['pending_count'] ?? 0; ?>
                    </h3>
                </div>
            </div>
        </div>

    </div>

    <div class="card mt-4 shadow-sm">
        <div class="card-header bg-white fw-bold">
            Recent Orders
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php while($row = $orders_query->fetch(PDO::FETCH_ASSOC)) { ?>
                        <tr>
                            <td>#<?= $row['id']; ?></td>
                            <td><?= htmlspecialchars($row['fullname'].' '.$row['surname']); ?></td>
                            <td>₱<?= number_format($row['total_amount'],2); ?></td>
                            <td>
                                <span class="badge <?= $row['status']=='pending'?'bg-warning text-dark':'bg-success'; ?>">
                                    <?= ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($row['order_date'])); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>

                </table>
            </div>
        </div>
    </div>

</div>

<script>
// Mobile Sidebar Toggle Script
document.addEventListener("DOMContentLoaded", function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const appSidebar = document.getElementById('appSidebar');
    if(sidebarToggle && appSidebar) {
        sidebarToggle.addEventListener('click', function() {
            appSidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile/desktop toggle
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