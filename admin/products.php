<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ==========================================
// DELETE PRODUCT LOGIC WITH AUDIT TRAIL
// ==========================================
if (isset($_GET['id']) || isset($_GET['delete_id']) || isset($_GET['product_id'])) {
    $delete_id = filter_var(
        $_GET['id'] ?? $_GET['delete_id'] ?? $_GET['product_id'],
        FILTER_VALIDATE_INT
    );

    if ($delete_id === false || $delete_id === null || $delete_id <= 0) {
        $_SESSION['message'] = "Invalid product ID.";
        header("Location: products.php");
        exit();
    }

    try {
        // FIXED: $conn -> $pdo
        $stmt = $pdo->prepare(
            "SELECT product_name FROM products WHERE id = :id"
        );
        $stmt->execute([':id' => $delete_id]);
        $p_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p_data) {
            $_SESSION['message'] = "Product not found.";
            header("Location: products.php");
            exit();
        }

        $deleted_p_name = $p_data['product_name'] ?? 'Unknown Product';

        // Delete product
        $stmt = $pdo->prepare(
            "DELETE FROM products WHERE id = :id"
        );
        $stmt->execute([':id' => $delete_id]);

        // Record deletion in audit log
        $username = $_SESSION['username'] ?? 'Admin';
        $role = $_SESSION['role'] ?? 'admin';
        $action = 'Delete Product';
        $details = "Deleted product: " . $deleted_p_name;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        $log_stmt = $pdo->prepare("
            INSERT INTO audit_log
                (username, role, action, details, ip_address, timestamp)
            VALUES
                (:username, :role, :action, :details, :ip_address, CURRENT_TIMESTAMP)
        ");

        $log_stmt->execute([
            ':username' => $username,
            ':role' => $role,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => $ip_address
        ]);

        $_SESSION['message'] = "Product deleted successfully!";
        header("Location: products.php");
        exit();

    } catch (PDOException $e) {
        error_log("Delete product error: " . $e->getMessage());
        $_SESSION['message'] = "Unable to delete product. Check whether it is linked to other records.";
        header("Location: products.php");
        exit();
    }
}

// ==========================================
// SEARCH AND FILTER
// ==========================================
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? '';

try {
    if ($filter === 'low_stock') {

        // Low-stock products without variants + low-stock variants
        $query = "
            SELECT *
            FROM (
                SELECT
                    p.id AS product_id,
                    NULL AS variant_id,
                    p.product_name,
                    p.category,
                    p.image,
                    'Standard Single Item (No Variants)' AS variant_name,
                    p.stock AS item_stock,
                    p.price AS item_price
                FROM products p
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM product_variants v
                    WHERE v.product_id = p.id
                )
                  AND p.stock <= 10
                  AND p.stock > 0

                UNION ALL

                SELECT
                    p.id AS product_id,
                    v.id AS variant_id,
                    p.product_name,
                    p.category,
                    p.image,
                    v.variant_name,
                    v.stock AS item_stock,
                    v.price AS item_price
                FROM product_variants v
                JOIN products p ON p.id = v.product_id
                WHERE v.stock <= 10
                  AND v.stock > 0
            ) AS low_stock_items
        ";

        $params = [];

        if ($search !== '') {
            $query .= "
                WHERE (
                    product_name ILIKE :search_name
                    OR category ILIKE :search_category
                    OR variant_name ILIKE :search_variant
                )
            ";

            $searchTerm = '%' . $search . '%';
            $params = [
                ':search_name' => $searchTerm,
                ':search_category' => $searchTerm,
                ':search_variant' => $searchTerm
            ];
        }

        $query .= " ORDER BY product_id DESC";

    } else {

        // Normal inventory list
        $query = "
            SELECT
                p.*,
                COUNT(v.id) AS total_variants,
                p.price AS min_price,
                CASE
                    WHEN COUNT(v.id) > 0 THEN MAX(v.price)
                    ELSE p.price
                END AS max_price,
                p.stock AS total_stock,
                STRING_AGG(v.variant_name, ', ') AS variant_list
            FROM products p
            LEFT JOIN product_variants v ON p.id = v.product_id
            WHERE 1 = 1
        ";

        $params = [];

        if ($search !== '') {
            $query .= "
                AND (
                    p.product_name ILIKE :search_name
                    OR p.category ILIKE :search_category
                )
            ";

            $searchTerm = '%' . $search . '%';
            $params = [
                ':search_name' => $searchTerm,
                ':search_category' => $searchTerm
            ];
        }

        $query .= "
            GROUP BY p.id
            ORDER BY p.id DESC
        ";
    }

    // Execute query using PDO ($pdo instead of $conn)
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt;

} catch (PDOException $e) {
    error_log("Products query error: " . $e->getMessage());
    die("Unable to load products. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | Admin</title>

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

        .logout-section {
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .main-content {
            margin-left: 260px;
            padding: 25px;
            min-height: 100vh;
            transition: margin 0.3s ease;
        }

        .main-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }

        .table img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }

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

            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <!-- Mobile Header Toggle Bar -->
    <nav class="navbar navbar-dark bg-dark d-lg-none px-3">
        <span class="navbar-brand fw-bold fs-6"><i class="bi bi-box-seam"></i> Inventory Management</span>
        <button class="btn btn-outline-light btn-sm" type="button" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
    </nav>

    <!-- SIDEBAR -->
    <div class="sidebar" id="appSidebar">
        <h2>Admin<br>Dashboard</h2>
        <a href="dashboard.php"><i class="bi bi-grid"></i> Dashboard</a>
        <a href="products.php" class="active"><i class="bi bi-box-seam"></i> Inventory</a>
        <a href="orders.php"><i class="bi bi-receipt"></i> Orders</a>
        <a href="ItemsRequest.php"><i class="bi bi-arrow-return-left"></i> Customer Service request</a>
        <a href="damageItems.php"><i class="bi bi-exclamation-triangle"></i> Damaged Items</a>
        <a href="accountManagement.php"><i class="bi bi-person-circle"></i> Account Management</a>
        <div class="logout-section">
            <a href="../logout.php" class="text-danger" onclick="return confirm('Are you sure you want to logout?')">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="main-container">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h3 class="mb-0 text-dark fw-bold fs-4">
                    📦 Product Inventory Management
                    <?php echo ($filter == 'low_stock') ? '<span class="text-warning fs-5">(Low Stock Filtered)</span>' : ''; ?>
                </h3>

                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($filter == 'low_stock'): ?>
                        <a href="products.php" class="btn btn-secondary fw-bold btn-sm">
                            <i class="bi bi-arrow-counterclockwise"></i> Show All Products
                        </a>
                    <?php else: ?>
                        <a href="products.php?filter=low_stock" class="btn btn-warning fw-bold text-dark btn-sm">
                            <i class="bi bi-exclamation-triangle-fill"></i> Low Stocks
                        </a>
                    <?php endif; ?>

                    <a href="addProduct.php" class="btn btn-primary fw-bold btn-sm">
                        <i class="bi bi-plus-circle"></i> Add New Product
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo $_SESSION['message'];
                    unset($_SESSION['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="search-input" class="form-control"
                            placeholder="Search product name, category, or variant..."
                            value="<?php echo htmlspecialchars($search); ?>" onkeyup="liveSearch()">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle border-start border-end">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 80px;">Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th><?php echo ($filter == 'low_stock') ? 'Specific Item / Variant' : 'Variants List'; ?></th>
                            <th class="text-center">
                                <?php echo ($filter == 'low_stock') ? 'Stock Level' : 'Main Stock'; ?></th>
                            <th><?php echo ($filter == 'low_stock') ? 'Price' : 'Price Range / Single Price'; ?></th>
                            <th class="text-center" style="width: 260px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventory-table">
                        <?php
                        $row = $result->fetch(PDO::FETCH_ASSOC);

                        if ($row !== false) {
                            do {
                                if ($filter == 'low_stock') {
                                    $p_id = $row['product_id'];
                                    $p_name = $row['product_name'];
                                    $p_category = $row['category'];
                                    $p_image = $row['image'];
                                    $v_name = $row['variant_name'];
                                    $t_stock = $row['item_stock'];
                                    $price_range = "₱" . number_format((float) $row['item_price'], 2);

                                } else {
                                    $p_id = $row['id'];
                                    $p_name = $row['product_name'];
                                    $p_category = $row['category'];
                                    $p_image = $row['image'];

                                    $v_name = ((int) $row['total_variants'] > 0)
                                        ? htmlspecialchars($row['variant_list'] ?? '', ENT_QUOTES, 'UTF-8')
                                        : 'Standard Single Item (No Variants)';

                                    if ((int) $row['total_variants'] > 0) {
                                        $price_range = "₱" . number_format((float) $row['min_price'], 2);

                                        if ((float) $row['min_price'] != (float) $row['max_price']) {
                                            $price_range .= " - ₱" . number_format((float) $row['max_price'], 2);
                                        }
                                    } else {
                                        $price_range = "₱" . number_format((float) $row['price'], 2);
                                    }

                                    $t_stock = $row['total_stock'];
                                }
                                ?>
                                <tr>
                                    <td class="text-center">
                                        <img src="../<?php echo !empty($p_image) ? $p_image : 'assets/no-image.jpg'; ?>"
                                            alt="Product Image">
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($p_name); ?></span>
                                        <small class="text-muted">Product ID: #<?php echo $p_id; ?></small>
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-light text-dark border px-2 py-1"><?php echo htmlspecialchars($p_category); ?></span>
                                    </td>
                                    <td>
                                        <small
                                            class="<?php echo ($filter == 'low_stock') ? 'fw-bold text-primary' : 'text-muted'; ?>">
                                            <?php echo htmlspecialchars($v_name); ?>
                                        </small>
                                    </td>
                                    <td class="text-center fw-bold">
                                        <?php if ($t_stock <= 0): ?>
                                            <span class="badge bg-danger fs-6">
                                                <i class="bi bi-x-circle-fill"></i> Out of Stock
                                            </span>
                                        <?php elseif ($t_stock <= 10): ?>
                                            <span class="badge bg-warning text-dark fs-6">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                                <?php echo $t_stock; ?> Low Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success fs-6">
                                                <i class="bi bi-check-circle-fill"></i>
                                                <?php echo $t_stock; ?> In Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-success">
                                        <?php echo $price_range; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="manageVariants.php?product_id=<?php echo $p_id; ?>"
                                                class="btn btn-success btn-sm fw-bold">
                                                <i class="bi bi-boxes"></i> Variants
                                            </a>

                                            <a href="editProduct.php?product_id=<?php echo $p_id; ?>"
                                                class="btn btn-warning btn-sm fw-bold">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>

                                            <a href="products.php?product_id=<?php echo $p_id; ?>"
                                                onclick="return confirm('Are you sure you want to delete this product?')"
                                                class="btn btn-danger btn-sm fw-bold">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            } while ($row = $result->fetch(PDO::FETCH_ASSOC));
                        } else {
                            echo "<tr><td colspan='7' class='text-center py-4 text-muted'>No products found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function liveSearch() {
            let query = document.getElementById('search-input').value;
            let tableBody = document.getElementById('inventory-table');
            let currentFilter = "<?php echo $filter; ?>";

            let url = window.location.pathname + '?search=' + encodeURIComponent(query);
            if (currentFilter) {
                url += '&filter=' + currentFilter;
            }

            fetch(url)
                .then(response => response.text())
                .then(data => {
                    let parser = new DOMParser();
                    let doc = parser.parseFromString(data, 'text/html');
                    let newRows = doc.getElementById('inventory-table').innerHTML;
                    tableBody.innerHTML = newRows;
                })
                .catch(error => {
                    console.error('Error fetching search results:', error);
                });
        }

        // Mobile Sidebar Toggle Script
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>