<?php
session_start();
require_once __DIR__ . '/../config/db.php';

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
        // --- DUPLICATE CHECK ---
        $check_duplicate = mysqli_query($conn, "SELECT id FROM products WHERE product_name = '$name'");
        if (mysqli_num_rows($check_duplicate) > 0) {
            $message = "Error: Product '$name' already exists in the inventory!";
        } else {
            // --- FILE UPLOAD & VALIDATION ---
            $image = $_FILES['image']['name'];
            $tmp = $_FILES['image']['tmp_name'];
            $file_type = $_FILES['image']['type'];
            
            // Mga pinapayagang MIME types (JPG, JPEG, PNG, WEBP - walang GIF, video, o mp3)
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

            // Kunin ang tunay na mime type ng file para masigurong hindi ito na-bypass
            $file_mime_check = mime_content_type($tmp);

            if (!in_array($file_mime_check, $allowed_types)) {
                $message = "Error: Invalid file type! Only JPG, JPEG, PNG, and WEBP images are allowed (GIFs, videos, and audio files are not permitted).";
            } else {
                // Ayusin ang pangalan ng file para maiwasan ang duplicate o issues sa filename
                $image_extension = pathinfo($image, PATHINFO_EXTENSION);
                $new_image_name = uniqid("prod_", true) . '.' . $image_extension;
                $folder = "../uploads/" . $new_image_name;

                if (move_uploaded_file($tmp, $folder)) {
                    $savePath = "uploads/" . $new_image_name;

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
                } else {
                    $message = "Error: Failed to upload image.";
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | Admin</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 p-3 p-md-4">
                <h4 class="text-center fw-bold mb-4 text-primary">Add New Product</h4>
                
                <?php if($message != "") { ?>
                    <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Name</label>
                        <input type="text" name="product_name" class="form-control" placeholder="Enter product name" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Cost Price</label>
                            <input type="number" name="cost_price" step="0.01" min="0" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Selling Price</label>
                            <input type="number" name="price" step="0.01" min="0" class="form-control" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="" disabled selected>Select Category</option>
                            <option value="Tools">Tools</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Construction">Construction</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Paint and Supplies">Paint and Supplies</option>
                            <option value="Chemicals">Chemicals</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Stock Quantity</label>
                        <input type="number" name="stock" min="0" class="form-control" placeholder="0" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Description</label>
                        <textarea name="product_description" class="form-control" rows="4" placeholder="Enter product details here..." required></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Product Image <small class="text-muted">(JPG, JPEG, PNG, WEBP only)</small></label>
                        <!-- Idinagdag ang accept attribute para i-restrict ang file picker sa mga larawan lang -->
                        <input type="file" name="image" class="form-control" accept=".jpg, .jpeg, .png, .webp" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="submit" class="btn btn-primary btn-lg">Save Product</button>
                        <a href="products.php" class="btn btn-outline-secondary">Back to Products</a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle with Popper JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>