<?php
session_start();
require_once __DIR__ . '/config/db.php';

// SECURITY GUARD: Owner lang ang pwedeng makakita ng Audit Trail
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "owner") {
    header("Location: ../login.php");
    exit();
}
$owner_name = isset($_SESSION['username']) ? $_SESSION['username'] : "owner";
// ==========================================
// FILTERS & QUERY PARA SA AUDIT TRAIL
// ==========================================
$role_filter = $_GET['role_filter'] ?? 'all';
$action_filter = $_GET['action_filter'] ?? 'all';
$date_filter = $_GET['date_filter'] ?? '';

$where_clauses = ["1=1"];

if ($role_filter !== 'all') {
    $role = mysqli_real_escape_string($conn, $role_filter);
    $where_clauses[] = "role = '$role'";
}

if ($action_filter !== 'all') {
    $action = mysqli_real_escape_string($conn, $action_filter);
    $where_clauses[] = "action LIKE '%$action%'";
}

if (!empty($date_filter)) {
    $date = mysqli_real_escape_string($conn, $date_filter);
    $where_clauses[] = "DATE(timestamp) = '$date'";
}

$where_sql = implode(" AND ", $where_clauses);

$query_string = "SELECT * FROM audit_log WHERE $where_sql ORDER BY timestamp DESC";
$logs_query = mysqli_query($conn, $query_string);
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


<!-- 🔥 FIXED WRAPPER (IMPORTANT) -->
<div class="app-wrapper">

    <div class="sidebar">
        <a href="dashboard.php" class="sidebar-link"><i class="bi bi-graph-up-arrow"></i> Dashboard</a>
        <a href="sales.php" class="sidebar-link"><i class="bi bi-cash-stack"></i> Sales</a>
        <a href="inventory.php" class="sidebar-link"><i class="bi bi-box-seam"></i> Inventory Report</a>
        <a href="orders.php" class="sidebar-link"><i class="bi bi-clipboard-check"></i> Order Mgt. Monitoring</a>
        <a href="history.php" class="sidebar-link"><i class="bi bi-clock-history"></i> History</a>
        <a href="auditTrail.php" class="sidebar-link active"><i class="bi bi-shield-lock"></i> Audit Trail</a>
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
                    <h2 class="fw-bold text-dark m-0">
                        <i class="bi bi-shield-logs text-danger"></i> System Audit Trail
                    </h2>
                    <small class="text-muted">View and monitor all activities and transactions within the system.</small>
                </div>
            </div>

            <!-- FILTERS -->
            <div class="card p-3 mb-4 shadow-sm border-0">
                <form method="GET" action="auditTrail.php" class="row g-3 align-items-end">

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Role</label>
                        <select name="role_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Cashier</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Action</label>
                        <select name="action_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all">All Actions</option>
                            <option value="Login">Login / Logout</option>
                            <option value="Stock">Stock</option>
                            <option value="Checkout">Checkout</option>
                            <option value="Order">Order</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Date</label>
                        <input type="date" name="date_filter" class="form-control form-control-sm" onchange="this.form.submit()">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Search</label>
                        <input type="text" class="form-control form-control-sm" id="logSearch" onkeyup="filterLogs()">
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
                        <?php while($row = mysqli_fetch_assoc($logs_query)) { ?>
                            <tr class="log-row">
                                <td><?php echo $row['timestamp']; ?></td>
                                <td><?php echo $row['username']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo $row['role']; ?></span></td>
                                <td><?php echo $row['action']; ?></td>
                                <td class="text-details"><?php echo $row['details']; ?></td>
                                <td class="text-end"><?php echo $row['ip_address'] ?? 'N/A'; ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>

                    </table>

                </div>
            </div>

        </div>

    </div>

</div>

<script>
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