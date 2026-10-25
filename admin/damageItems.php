<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['username']) ? $_SESSION['username'] : "Admin";

$sql = "SELECT * FROM damaged_items ORDER BY id DESC";
$damaged_result = $pdo->query($sql);
$damaged_rows = $damaged_result->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Damaged Items Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; margin: 0; overflow-x: hidden; padding-top: 56px; }
        
        .navbar-custom {
            height: 56px;
            z-index: 1100;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #051c3c !important;
        }

        /* Sidebar Styles */
        .sidebar { 
            position: fixed; 
            top: 56px; 
            left: 0; 
            width: 260px; 
            height: calc(100vh - 56px); 
            background: #001f4d; 
            padding: 20px; 
            overflow-y: auto; 
            z-index: 1040; 
            display: flex; 
            flex-direction: column; 
            box-shadow: 4px 0 15px rgba(0,0,0,0.15);
            transition: transform 0.3s ease; 
        }

        .sidebar a { color: white; text-decoration: none; padding: 12px 14px; border-radius: 8px; margin-bottom: 8px; transition: 0.3s; font-size: 15px; display: block; font-weight: 500; }
        .sidebar a i { margin-right: 8px; }
        .sidebar a:hover { background: #0d6efd; padding-left: 20px; }
        .sidebar .active { background: #0d6efd; }
        .logout-section { margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2); }
        
        /* Main Content Styles */
        .main { 
            margin-left: 260px; 
            padding: 20px; 
            min-height: calc(100vh - 56px); 
            transition: margin 0.3s ease; 
        }

        .card-custom { border: none; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .img-thumb-preview { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 2px solid #dee2e6; cursor: pointer; transition: 0.2s; }
        .img-thumb-preview:hover { transform: scale(1.05); border-color: #0d6efd; }
        .details-box { max-width: 350px; white-space: pre-wrap; word-wrap: break-word; }

        /* Responsive Breakpoints para sa Mobile at Tablet */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; padding: 15px 10px; }
        }
    </style>
</head>
<body>

<!-- Mobile Navbar na may Hamburger Button -->
<nav class="navbar navbar-dark navbar-custom px-3 d-lg-none">
    <div class="d-flex align-items-center w-100 justify-content-between">
        <span class="navbar-brand fw-bold fs-6"><i class="bi bi-exclamation-triangle"></i> Damaged Items</span>
        <button class="btn btn-outline-light btn-sm" type="button" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
    </div>
</nav>

<nav class="navbar navbar-dark navbar-custom px-3 d-none d-lg-flex">
    <div class="d-flex align-items-center w-100 justify-content-between">
        <span class="navbar-brand fw-bold mb-0">Admin Panel</span>
        <div class="d-flex align-items-center text-white gap-3">
            <span>Welcome, <strong><?= htmlspecialchars($admin_name); ?></strong></span>
        </div>
    </div>
</nav>

<!-- Sidebar Menu -->
<div class="sidebar" id="appSidebar">
    <a href="dashboard.php"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="products.php"><i class="bi bi-box-seam"></i> Inventory</a>
    <a href="orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a href="ItemsRequest.php"><i class="bi bi-arrow-return-left"></i> Customer Service Request</a>
    <a href="damageItems.php" class="active"><i class="bi bi-exclamation-triangle"></i> Damaged Items</a>
    <a href="accountManagement.php"><i class="bi bi-person-circle"></i> Account Management</a>  
    <div class="logout-section">
        <a href="../logout.php" class="text-danger" onclick="return confirm('Are you sure you want to logout?')">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <div class="card card-custom p-3 p-md-4 bg-white mt-2 mt-md-3">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-4 gap-2">
            <div>
                <h3 class="fw-bold text-dark mb-0 fs-4 fs-md-3"><i class="bi bi-exclamation-triangle text-danger"></i> Damaged Items Management</h3>
                <p class="text-muted small mb-0">Monitor and track returned products that were successfully processed as damaged items.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle border-top">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Order #</th>
                        <th>Customer Account</th>
                        <th>Uploaded Proof</th>
                        <th>Item Details</th>
                        <th>Date Logged</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($damaged_rows) == 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No damaged items recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach($damaged_rows as $row): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo htmlspecialchars($row['id']); ?></td>
                            <td><span class="badge bg-primary">#<?php echo htmlspecialchars($row['order_number']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['account']); ?></td>
                            <td>
                                <?php 
                                $img_path = "";
                                if(!empty($row['product_image'])) {
                                    if(file_exists("../uploads/" . $row['product_image'])) { $img_path = "../uploads/" . $row['product_image']; }
                                    elseif(file_exists("uploads/" . $row['product_image'])) { $img_path = "uploads/" . $row['product_image']; }
                                }
                                if(!empty($img_path)): 
                                ?>
                                    <a href="<?php echo $img_path; ?>" target="_blank">
                                        <img src="<?php echo $img_path; ?>" class="img-thumb-preview" title="Open Image Proof">
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small italic"><i class="bi bi-image"></i> None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="p-2 bg-light border text-danger rounded small font-monospace details-box" style="font-size: 0.85rem; max-width: 250px;">
                                    <?php echo nl2br(htmlspecialchars($row['item_details'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small"><i class="bi bi-calendar-event"></i> <?php echo htmlspecialchars($row['date_logged']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
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