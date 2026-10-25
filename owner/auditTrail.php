<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "owner") {
    header("Location: ../login.php");
    exit();
}
$owner_name = isset($_SESSION['username']) ? $_SESSION['username'] : "owner";


$role_filter = $_GET['role_filter'] ?? 'all';
$action_filter = $_GET['action_filter'] ?? 'all';
$date_filter = $_GET['date_filter'] ?? '';

$where_clauses = ["1=1"];
$params = [];

if ($role_filter !== 'all') {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
}

if ($action_filter !== 'all') {
    $where_clauses[] = "action LIKE ?";
    $params[] = '%' . $action_filter . '%';
}

if (!empty($date_filter)) {
    $where_clauses[] = "DATE(timestamp) = ?";
    $params[] = $date_filter;
}

$where_sql = implode(" AND ", $where_clauses);
$query_string = "SELECT * FROM audit_log WHERE $where_sql ORDER BY timestamp DESC";
$stmt = $pdo->prepare($query_string);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Audit Trail | Owner Panel</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
body{
    margin:0;
    overflow-x:hidden;
    background:#f8f9fa;
    padding-top: 56px;
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
    <a href="history.php" class="sidebar-link"><i class="bi bi-clock-history"></i> History</a>
    <a href="auditTrail.php" class="sidebar-link active"><i class="bi bi-shield-lock"></i> Audit Trail</a>
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
                <h2 class="fw-bold text-dark m-0">
                    <i class="bi bi-shield-lock text-danger"></i> System Audit Trail
                </h2>
                <small class="text-muted">View and monitor all activities and transactions within the system.</small>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="card p-3 mb-4 shadow-sm border-0">
            <form method="GET" action="auditTrail.php" class="row g-3 align-items-end">

                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted">Role</label>
                    <select name="role_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" <?= $role_filter=='all'?'selected':'' ?>>All Roles</option>
                        <option value="admin" <?= $role_filter=='admin'?'selected':'' ?>>Admin</option>
                        <option value="cashier" <?= $role_filter=='cashier'?'selected':'' ?>>Cashier</option>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted">Action</label>
                    <select name="action_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" <?= $action_filter=='all'?'selected':'' ?>>All Actions</option>
                        <option value="Login" <?= $action_filter=='Login'?'selected':'' ?>>Login / Logout</option>
                        <option value="Stock" <?= $action_filter=='Stock'?'selected':'' ?>>Stock</option>
                        <option value="Checkout" <?= $action_filter=='Checkout'?'selected':'' ?>>Checkout</option>
                        <option value="Order" <?= $action_filter=='Order'?'selected':'' ?>>Order</option>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted">Date</label>
                    <input type="date" name="date_filter" class="form-control form-control-sm" value="<?= htmlspecialchars($date_filter) ?>" onchange="this.form.submit()">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted">Search</label>
                    <input type="text" class="form-control form-control-sm" id="logSearch" onkeyup="filterLogs()" placeholder="Search logs...">
                </div>

            </form>
        </div>

        <!-- TABLE -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 table-audit">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th class="text-end">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(count($logs) > 0) {
                        foreach($logs as $row) { 
                    ?>
                        <tr class="log-row">
                            <td><?php echo $row['timestamp']; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['role']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['action']); ?></td>
                            <td class="text-details"><?php echo htmlspecialchars($row['details']); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($row['ip_address'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php } } else { ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No audit logs found.</td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
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

function filterLogs() {
    let input = document.getElementById("logSearch").value.toLowerCase();
    document.querySelectorAll(".log-row").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>