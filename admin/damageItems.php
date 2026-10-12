<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

// Kunin ang lahat ng naka-log na damaged items mula sa database
$damaged_result = mysqli_query($conn, "SELECT * FROM damaged_items ORDER BY id DESC");
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
        body { background: #f4f6f9; margin: 0; overflow-x: hidden; }
        
        /* Sidebar Styles */
        .sidebar { position: fixed; top: 0; left: 0; width: 260px; height: 100vh; background: #001f4d; padding: 20px; overflow-y: auto; z-index: 1000; display: flex; flex-direction: column; transition: all 0.3s ease; }
        .sidebar h2 { color: white; text-align: center; font-weight: bold; margin-bottom: 30px; line-height: 1.3; font-size: 1.5rem; }
        .sidebar a { color: white; text-decoration: none; padding: 12px 14px; border-radius: 8px; margin-bottom: 8px; transition: 0.3s; font-size: 15px; display: block; font-weight: 500; }
        .sidebar a i { margin-right: 8px; }
        .sidebar a:hover { background: #0d6efd; padding-left: 20px; }
        .sidebar .active { background: #0d6efd; }
        .logout-section { margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2); }
        
        /* Main Content Styles */
        .main { margin-left: 260px; padding: 30px; min-height: 100vh; transition: all 0.3s ease; }
        .card-custom { border: none; border-radius: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .img-thumb-preview { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 2px solid #dee2e6; cursor: pointer; transition: 0.2s; }
        .img-thumb-preview:hover { transform: scale(1.05); border-color: #0d6efd; }
        .details-box { max-width: 350px; white-space: pre-wrap; word-wrap: break-word; }

        /* Mobile Top Navbar */
        .mobile-navbar { display: none; background: #001f4d; color: white; padding: 10px 20px; position: fixed; top: 0; left: 0; right: 0; z-index: 1100; }

        /* Responsive Breakpoints para sa Mobile at Tablet */
        @media (max-width: 992px) {
            .mobile-navbar { display: flex; justify-content: space-between; align-items: center; }
            .sidebar { transform: translateX(-100%); top: 56px; height: calc(100vh - 56px); width: 260px; }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; padding: 15px; margin-top: 56px; }
        }
    </style>
</head>
<body>

<!-- Mobile Navbar na may Hamburger Button -->
<div class="mobile-navbar">
    <h5 class="mb-0 fw-bold">Admin Dashboard</h5>
    <button class="btn text-white border-0" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-expanded="false" aria-controls="sidebarMenu">
        <i class="bi bi-list fs-2"></i>
    </button>
</div>

<!-- Sidebar Menu -->
<div class="sidebar collapse d-lg-flex" id="sidebarMenu">
    <h2>Admin<br> Dashboard</h2>
    <a href="dashboard.php"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="products.php"><i class="bi bi-box-seam"></i> Inventory</a>
    <a href="orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a href="ItemsRequest.php"><i class="bi bi-arrow-return-left"></i> Customer Service request</a>
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
    <div class="card card-custom p-4 bg-white mt-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-4 gap-2">
            <div>
                <h3 class="fw-bold text-dark mb-0"><i class="bi bi-exclamation-triangle text-danger"></i> Damaged Items Management</h3>
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
                    <?php if(mysqli_num_rows($damaged_result) == 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No damaged items recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php while($row = mysqli_fetch_assoc($damaged_result)): ?>
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
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>