```php
<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if the user is an admin
if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login.php");
    exit();
}

// Get admin username
$admin_name = $_SESSION['username'] ?? 'Admin';
?>
```

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body {
            background: #f4f6f9;
            margin: 0;
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: #001f4d;
            padding: 20px;
            overflow-y: auto;
            z-index: 1040;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }

        .sidebar h2 {
            color: white;
            text-align: center;
            font-weight: bold;
            margin-bottom: 40px;
            line-height: 1.3;
            font-size: 1.5rem;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: 0.3s;
            font-size: 15px;
            display: block;
            font-weight: 500;
        }

        .sidebar a i {
            margin-right: 8px;
        }

        .sidebar a:hover {
            background: #0d6efd;
            padding-left: 20px;
        }

        .sidebar .active {
            background: #0d6efd;
        }

        /* LOGOUT */
        .logout-section {
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* MAIN CONTENT */
        .main {
            margin-left: 260px;
            padding: 25px;
            min-height: 100vh;
            transition: margin 0.3s ease;
        }

        /* CARDS */
        .card-box {
            border: none;
            border-radius: 14px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        /* SCROLLBAR */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #0d6efd;
            border-radius: 10px;
        }

        /* MOBILE RESPONSIVENESS */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <!-- Mobile Header Toggle Bar -->
    <nav class="navbar navbar-dark bg-dark d-lg-none px-3">
        <span class="navbar-brand fw-bold fs-6"><i class="bi bi-grid"></i> Admin Dashboard</span>
        <button class="btn btn-outline-light btn-sm" type="button" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
    </nav>

    <!-- SIDEBAR -->
    <div class="sidebar" id="appSidebar">
        <h2>
            Admin<br> Dashboard
        </h2>

        <a href="dashboard.php" class="active">
            <i class="bi bi-grid"></i> Dashboard
        </a>

        <a href="products.php">
            <i class="bi bi-box-seam"></i> Inventory
        </a>

        <a href="orders.php">
            <i class="bi bi-receipt"></i> Orders
        </a>

        <a href="ItemsRequest.php">
            <i class="bi bi-arrow-return-left"></i> Customer Service request
        </a>
        <a href="damageItems.php"><i class="bi bi-exclamation-triangle"></i> Damaged Items</a>
        <a href="accountManagement.php"><i class="bi bi-person-circle"></i> Account Management</a>

        <div class="logout-section">
            <a href="../logout.php" class="text-danger" onclick="return confirm('Are you sure you want to logout?')">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">

        <div class="d-flex align-items-center mb-3 flex-wrap gap-3">
            <img src="../assets/logo.jpg" alt="Logo"
                style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid #ccc;">

            <h2 class="text-muted mb-0 fs-4 fs-md-3">
                Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong>
            </h2>
        </div>

        <p class="text-secondary mb-4">Hardware System Overview</p>

        <div class="row g-4">

            <!-- LOW STOCK -->
            <div class="col-12 col-md-4">
                <div class="card bg-warning text-dark card-box">
                    <h4>
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Low Stock
                    </h4>

                    ```php
                    <?php
                    try {
                        // Products without variants
                        $stmt = $conn->query("
        SELECT COUNT(*) 
        FROM products 
        WHERE stock <= 10
    ");
                        $simpleCount = (int) $stmt->fetchColumn();

                        // Products with variants
                        $stmt = $conn->query("
        SELECT COUNT(*) 
        FROM product_variants 
        WHERE stock <= 10
    ");
                        $variantCount = (int) $stmt->fetchColumn();

                        // Total low-stock items
                        $lowStockTotal = $simpleCount + $variantCount;

                        echo "<h2>" . $lowStockTotal . "</h2>";

                    } catch (PDOException $e) {
                        error_log("Low stock query error: " . $e->getMessage());
                        echo "<h2>--</h2>";
                    }
                    ?>
                    ```

                    <small>Products & Variants with 10 or fewer stocks</small>
                </div>
            </div>

            <!-- TOTAL PRODUCTS -->
            <div class="col-12 col-md-4">
                <div class="card bg-primary text-white card-box">
                    <h4>Total Products</h4>
                    ```php
                    <?php
                    try {
                        // Count main products
                        $stmt = $conn->query("
        SELECT COUNT(*) FROM products
    ");
                        $productCount = (int) $stmt->fetchColumn();

                        // Count product variants
                        $stmt = $conn->query("
        SELECT COUNT(*) FROM product_variants
    ");
                        $variantCount = (int) $stmt->fetchColumn();

                        // Combined inventory count
                        $totalInventoryItems = $productCount + $variantCount;

                        echo "<h2>" . $totalInventoryItems . "</h2>";

                    } catch (PDOException $e) {
                        error_log("Total products query error: " . $e->getMessage());
                        echo "<h2>--</h2>";
                    }
                    ?>
                    ```
                    <small>Products + Variant Items</small>
                </div>
            </div>

            <!-- TOTAL ORDERS -->
            <div class="col-12 col-md-4">
                <div class="card bg-success text-white card-box">
                    <h4>Total Orders</h4>

                    ```php
                    <?php
                    try {
                        $stmt = $conn->query("
        SELECT COUNT(*) FROM orders
    ");

                        $totalOrders = (int) $stmt->fetchColumn();

                        echo "<h2>" . $totalOrders . "</h2>";

                    } catch (PDOException $e) {
                        error_log("Total orders query error: " . $e->getMessage());
                        echo "<h2>--</h2>";
                    }
                    ?>
                    ```

                    <small>Online Orders</small>
                </div>
            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const appSidebar = document.getElementById('appSidebar');
            if (sidebarToggle && appSidebar) {
                sidebarToggle.addEventListener('click', function () {
                    appSidebar.classList.toggle('show');
                });

                // Isara ang sidebar kapag nag-click sa labas nito sa mobile view
                document.addEventListener('click', function (event) {
                    if (window.innerWidth < 992) {
                        if (!appSidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                            appSidebar.classList.remove('show');
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>