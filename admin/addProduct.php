<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

$message = "";

if (isset($_POST['submit'])) {
    $name = trim($_POST['product_name']);
    $cost_price = $_POST['cost_price'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];
    $description = $_POST['product_description'];

    // --- PRICE VALIDATION: Cost price must not be higher than selling price ---
    if (floatval($cost_price) > floatval($price)) {
        $message = "Error: Cost price cannot be higher than the selling price!";
    } else {
        // --- DUPLICATE CHECK ---
        $check_stmt = $pdo->prepare("SELECT id FROM products WHERE product_name = :name");
        $check_stmt->execute([':name' => $name]);
        
        if ($check_stmt->rowCount() > 0) {
            $message = "Error: Product '$name' already exists in the inventory!";
        } else {
            // --- FILE UPLOAD & VALIDATION CHECK ---
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $message = "Error: Please select a valid product image to upload.";
            } else {
                $image = $_FILES['image']['name'];
                $tmp = $_FILES['image']['tmp_name'];
                
                // Siguraduhing hindi empty ang temporary path bago i-check ang MIME type
                if (empty($tmp) || !file_exists($tmp)) {
                    $message = "Error: Uploaded file temporary path is empty or invalid.";
                } else {
                    // Mga pinapayagang MIME types (JPG, JPEG, PNG, WEBP)
                    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                    $file_mime_check = mime_content_type($tmp);

                    if (!in_array($file_mime_check, $allowed_types)) {
                        $message = "Error: Invalid file type! Only JPG, JPEG, PNG, and WEBP images are allowed.";
                    } else {
                        $image_extension = pathinfo($image, PATHINFO_EXTENSION);
                        $new_image_name = uniqid("prod_", true) . '.' . $image_extension;
                        $folder = "../uploads/" . $new_image_name;

                        if (move_uploaded_file($tmp, $folder)) {
                            $savePath = "uploads/" . $new_image_name;

                            $query = "INSERT INTO products (product_name, cost_price, price, category, image, stock, product_description) 
                                      VALUES (:name, :cost_price, :price, :category, :image, :stock, :description)";
                            
                            $stmt = $pdo->prepare($query);
                            $insert_result = $stmt->execute([
                                ':name' => $name,
                                ':cost_price' => $cost_price,
                                ':price' => $price,
                                ':category' => $category,
                                ':image' => $savePath,
                                ':stock' => $stock,
                                ':description' => $description
                            ]);

                            if($insert_result){
                                // ==========================================
                                // AUDIT TRAIL LOGGING (ADD PRODUCT)
                                // ==========================================
                                $username = $_SESSION['username'] ?? 'Admin';
                                $role = $_SESSION['role'] ?? 'admin';
                                $action = 'Add Product';
                                $details = "Added new product: " . $name;
                                
                                $log_query = "INSERT INTO audit_log (username, role, action, details, timestamp) 
                                              VALUES (:username, :role, :action, :details, CURRENT_TIMESTAMP)";
                                $log_stmt = $pdo->prepare($log_query);
                                $log_stmt->execute([
                                    ':username' => $username,
                                    ':role' => $role,
                                    ':action' => $action,
                                    ':details' => $details
                                ]);
                                // ==========================================

                                $message = "Product added successfully!";
                            } else {
                                $message = "Error: Failed to insert product into database.";
                            }
                        } else {
                            $message = "Error: Failed to move uploaded image file.";
                        }
                    }
                }
            }
        }
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