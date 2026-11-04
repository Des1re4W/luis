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

// Get logged-in user data from database
$user_id = $_SESSION['user_id'] ?? null;
$user = null;

if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Dashboard fetch user error: " . $e->getMessage());
    }
}

// Set display name (Full name, or Username, or Session Admin Name)
$admin_name = $_SESSION['username'] ?? 'Admin';
$display_name = !empty($user['fullname']) ? $user['fullname'] : (!empty($user['username']) ? $user['username'] : $admin_name);

// Built-in Default Avatar (SVG Data URI - Hindi nawawala kahit walang physical file)
$default_avatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='%23001f4d'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z'/></svg>";

$user_display_photo = $default_avatar;

if (!empty($user['profile_image'])) {
    // Kung Base64
    if (stripos($user['profile_image'], 'data:image/') === 0) {
        $user_display_photo = $user['profile_image'];
    } 
    // Kung local file path at umiiral pa sa server
    else {
        $photo_path = __DIR__ . '/../' . $user['profile_image'];
        if (file_exists($photo_path)) {
            $user_display_photo = '../' . htmlspecialchars($user['profile_image']);
        }
    }
}
?>

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

        /* PROFILE IMAGE HOVER EFFECT */
        .profile-clickable-link {
            display: inline-block;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .profile-clickable-link:hover {
            transform: scale(1.05);
            opacity: 0.9;
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
        <a href="profile.php" class="sidebar-link"><i class="bi bi-person-badge"></i> My Profile</a>

        <div class="logout-section">
            <a href="../logout.php" class="text-danger" onclick="return confirm('Are you sure you want to logout?')">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">

        <!-- Welcome Section with Clickable Profile Picture -->
        <div class="d-flex align-items-center mb-3 flex-wrap gap-3">
            <a href="profile.php" class="profile-clickable-link" title="Go to Profile">
                <img src="<?php echo $user_display_photo; ?>" 
                     alt="Profile Picture"
                     style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid #0d6efd;background-color:#fff;"
                     onerror="this.onerror=null;this.src='<?php echo $default_avatar; ?>';">
            </a>

            <h2 class="text-dark mb-0 fs-4 fs-md-3">
                Welcome, <strong><?php echo htmlspecialchars($display_name); ?></strong>
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

                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT COUNT(*) 
                            FROM products 
                            WHERE stock <= 10
                        ");
                        $simpleCount = (int) $stmt->fetchColumn();
                        
                        $stmt = $pdo->query("
                            SELECT COUNT(*) 
                            FROM product_variants 
                            WHERE stock <= 10
                        ");
                        $variantCount = (int) $stmt->fetchColumn();

                        $lowStockTotal = $simpleCount + $variantCount;

                        echo "<h2>" . $lowStockTotal . "</h2>";

                    } catch (PDOException $e) {
                        error_log("Low stock query error: " . $e->getMessage());
                        echo "<h2>--</h2>";
                    }
                    ?>

                    <small>Products & Variants with 10 or fewer stocks</small>
                </div>
            </div>

            <!-- TOTAL PRODUCTS -->
            <div class="col-12 col-md-4">
                <div class="card bg-primary text-white card-box">
                    <h4>
                        <i class="bi bi-box-seam"></i>
                        Total Products
                    </h4>
                    <?php
                    try {
                        // Count main products
                        $stmt = $pdo->query("
                            SELECT COUNT(*) FROM products
                        ");
                        $productCount = (int) $stmt->fetchColumn();

                        // Count product variants
                        $stmt = $pdo->query("
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
                    <small>Products + Variant Items</small>
                </div>
            </div>

            <!-- TOTAL ORDERS -->
            <div class="col-12 col-md-4">
                <div class="card bg-success text-white card-box">
                    <h4>
                        <i class="bi bi-receipt"></i>
                        Total Orders
                    </h4>

                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT COUNT(*) FROM orders
                        ");

                        $totalOrders = (int) $stmt->fetchColumn();

                        echo "<h2>" . $totalOrders . "</h2>";

                    } catch (PDOException $e) {
                        error_log("Total orders query error: " . $e->getMessage());
                        echo "<h2>--</h2>";
                    }
                    ?>

                    <small>Online Orders</small>
                </div>
            </div>

            <!-- CUSTOMER SERVICE REQUESTS -->
            <div class="col-12 col-md-4">
                <div class="card bg-info text-white card-box">
                    <h4>
                        <i class="bi bi-arrow-return-left"></i>
                        Service Requests
                    </h4>

                    <?php
                    try {
                        // Subukang kunin ang bilang ng customer service requests
                        $stmt = $pdo->query("
                            SELECT COUNT(*) FROM customer_service_requests
                        ");
                        $totalServiceRequests = (int) $stmt->fetchColumn();
                        echo "<h2>" . $totalServiceRequests . "</h2>";
                    } catch (PDOException $e) {
                        // Fallback kung iba ang pangalan ng table (e.g. items_requests / item_requests)
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) FROM item_requests");
                            $totalServiceRequests = (int) $stmt->fetchColumn();
                            echo "<h2>" . $totalServiceRequests . "</h2>";
                        } catch (PDOException $ex) {
                            error_log("Customer service requests query error: " . $e->getMessage());
                            echo "<h2>0</h2>";
                        }
                    }
                    ?>

                    <small>Total Customer Service Tickets / Requests</small>
                </div>
            </div>

            <!-- TOTAL DAMAGED ITEMS -->
            <div class="col-12 col-md-4">
                <div class="card bg-danger text-white card-box">
                    <h4>
                        <i class="bi bi-slash-circle-fill"></i>
                        Damaged Items
                    </h4>

                    <?php
                    try {
                        // Subukang kunin ang bilang mula sa damaged_items table
                        $stmt = $pdo->query("
                            SELECT COUNT(*) FROM damaged_items
                        ");
                        $totalDamagedItems = (int) $stmt->fetchColumn();
                        echo "<h2>" . $totalDamagedItems . "</h2>";
                    } catch (PDOException $e) {
                        // Fallback kung damage_items ang name ng table
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) FROM damage_items");
                            $totalDamagedItems = (int) $stmt->fetchColumn();
                            echo "<h2>" . $totalDamagedItems . "</h2>";
                        } catch (PDOException $ex) {
                            error_log("Damaged items query error: " . $e->getMessage());
                            echo "<h2>0</h2>";
                        }
                    }
                    ?>

                    <small>Reported Damaged Items</small>
                </div>
            </div>

            <!-- TOTAL USERS -->
            <div class="col-12 col-md-4">
                <div class="card bg-dark text-white card-box">
                    <h4>
                        <i class="bi bi-people-fill"></i>
                        Total Users
                    </h4>

                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT COUNT(*) FROM users
                        ");
                        $totalUsers = (int) $stmt->fetchColumn();
                        echo "<h2>" . $totalUsers . "</h2>";
                    } catch (PDOException $e) {
                        error_log("Total users query error: " . $e->getMessage());
                        echo "<h2>--</h2>";
                    }
                    ?>

                    <small>Active & Registered System Users</small>
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