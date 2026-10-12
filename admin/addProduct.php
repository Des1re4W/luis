<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

$message = "";

if (isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $cost_price = mysqli_real_escape_string($conn, $_POST['cost_price']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $stock = mysqli_real_escape_string($conn, $_POST['stock']);
    $description = mysqli_real_escape_string($conn, $_POST['product_description']);

    // --- PRICE VALIDATION: Cost price must not be higher than selling price ---
    if (floatval($cost_price) > floatval($price)) {
        $message = "Error: Cost price cannot be higher than the selling price!";
    } else {
        // ---DUPLICATE CHECK ---
        $check_duplicate = mysqli_query($conn, "SELECT id FROM products WHERE product_name = '$name'");
        if (mysqli_num_rows($check_duplicate) > 0) {
            $message = "Error: Product '$name' already exists in the inventory!";
        } else {
            // --- PROCEED IF NO DUPLICATE ---
            $image = $_FILES['image']['name'];
            $tmp = $_FILES['image']['tmp_name'];
            $folder = "../uploads/" . $image;

            if (move_uploaded_file($tmp, $folder)) {
                $savePath = "uploads/" . $image;

                $query = "INSERT INTO products (product_name, cost_price, price, category, image, stock, product_description) 
                          VALUES ('$name', '$cost_price', '$price', '$category', '$savePath', '$stock', '$description')";

                if(mysqli_query($conn, $query)){
                    // ==========================================
                    // AUDIT TRAIL LOGGING (ADD PRODUCT)
                    // ==========================================
                    $username = $_SESSION['username'] ?? 'Admin';
                    $role = $_SESSION['role'] ?? 'admin';
                    $action = 'Add Product';
                    $details = "Added new product: " . $name;
                    $ip_address = $_SERVER['REMOTE_ADDR'];

                    $log_query = "INSERT INTO audit_log (username, role, action, details, ip_address, timestamp) 
                                  VALUES ('$username', '$role', '$action', '$details', '$ip_address', NOW())";
                    mysqli_query($conn, $log_query);
                    // ==========================================

                    $message = "Product added successfully!";
                } else {
                    $message = "Error: " . mysqli_error($conn);
                }
            }
        }
    }
}

// ==========================================
// LOGIC PARA SA DELETE PRODUCT WITH AUDIT TRAIL
// ==========================================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Kunin muna ang pangalan ng produkto bago i-delete para sa audit trail details
    $p_get = mysqli_query($conn, "SELECT product_name FROM products WHERE id = '$delete_id'");
    $p_data = mysqli_fetch_assoc($p_get);
    $deleted_p_name = $p_data['product_name'] ?? 'Unknown Product';

    if (mysqli_query($conn, "DELETE FROM products WHERE id = '$delete_id'")) {
        $username = $_SESSION['username'] ?? 'Admin';
        $role = $_SESSION['role'] ?? 'admin';
        $action = 'Delete Product';
        
        $safe_p_name = mysqli_real_escape_string($conn, $deleted_p_name);
        $details = "Deleted product: " . $safe_p_name;
        $ip_address = $_SERVER['REMOTE_ADDR'];

        $log_query = "INSERT INTO audit_log (username, role, action, details, ip_address, timestamp) 
                      VALUES ('$username', '$role', '$action', '$details', '$ip_address', NOW())";
        mysqli_query($conn, $log_query);

        $_SESSION['message'] = "Product deleted successfully!";
        header("Location: products.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Product | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow p-4 mx-auto" style="max-width: 600px;">
        <h4 class="text-center mb-4">Add New Product</h4>
        
        <?php if($message != "") { ?>
            <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php } ?>

        <form method="POST" enctype="multipart/form-data">
            <label>Product Name</label>
            <input type="text" name="product_name" class="form-control mb-2" required>

            <div class="row">
                <div class="col-md-6">
                    <label>Cost Price</label>
                    <input type="number" name="cost_price" step="0.01" min="0" class="form-control mb-2" required>
                </div>
                <div class="col-md-6">
                    <label>Selling Price</label>
                    <input type="number" name="price" step="0.01" class="form-control mb-2" required>
                </div>
            </div>

            <label>Category</label>
            <select name="category" class="form-control mb-2" required>
                <option value="">Select Category</option>
                <option>Tools</option>
                <option>Electrical</option>
                <option>Construction</option>
                <option>Plumbing</option>
                <option>Paint and Supplies</option>
                <option>Chemicals</option>
            </select>

            <label>Stock Quantity</label>
            <input type="number" name="stock" min="0" class="form-control mb-2" required>

            <label>Product Description</label>
            <textarea name="product_description" class="form-control mb-2" rows="4" placeholder="Enter product details here..." required></textarea>

            <label>Product Image</label>
            <input type="file" name="image" class="form-control mb-3" required>

            <button type="submit" name="submit" class="btn btn-primary w-100">Save Product</button>
            <a href="products.php" class="btn btn-secondary w-100 mt-2">Back to Products</a>
        </form>
    </div>
</div>
</body>
</html>