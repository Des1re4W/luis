<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/supabase_storage.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";

if (isset($_POST['submit'])) {

    $name = trim($_POST['product_name'] ?? '');
    $cost_price = $_POST['cost_price'] ?? 0;
    $price = $_POST['price'] ?? 0;
    $category = $_POST['category'] ?? '';
    $stock = $_POST['stock'] ?? 0;
    $description = trim($_POST['product_description'] ?? '');

    $uploadedImageUrl = null;

    try {

        // Validate prices
        if (!is_numeric($cost_price) || !is_numeric($price) ||
            $cost_price < 0 || $price < 0) {
            throw new Exception("Invalid product price.");
        }

        if ((float)$cost_price > (float)$price) {
            throw new Exception(
                "Cost price cannot be higher than the selling price!"
            );
        }

        // Check duplicate product
        $check = $pdo->prepare("
            SELECT id FROM products
            WHERE LOWER(product_name) = LOWER(:name)
            LIMIT 1
        ");

        $check->execute([':name' => $name]);

        if ($check->fetch()) {
            throw new Exception(
                "Product '$name' already exists in the inventory!"
            );
        }

        // Upload directly to Supabase Storage
        if (!isset($_FILES['image'])) {
            throw new Exception("Please select a product image.");
        }

        $uploadedImageUrl = uploadProductImage($_FILES['image']);

        // Save permanent image URL to database
        $pdo->beginTransaction();

        $query = "
            INSERT INTO products (
                product_name,
                cost_price,
                price,
                category,
                image,
                stock,
                product_description
            )
            VALUES (
                :name,
                :cost_price,
                :price,
                :category,
                :image,
                :stock,
                :description
            )
        ";

        $stmt = $pdo->prepare($query);

        $stmt->execute([
            ':name' => $name,
            ':cost_price' => $cost_price,
            ':price' => $price,
            ':category' => $category,
            ':image' => $uploadedImageUrl,
            ':stock' => $stock,
            ':description' => $description
        ]);

        // Audit Trail
        $log_stmt = $pdo->prepare("
            INSERT INTO audit_log (
                user_id,
                username,
                role,
                action,
                details,
                timestamp
            )
            VALUES (
                :user_id,
                :username,
                :role,
                :action,
                :details,
                CURRENT_TIMESTAMP
            )
        ");

        $log_stmt->execute([
            ':user_id' => $_SESSION['user_id'] ?? null,
            ':username' => $_SESSION['username'] ?? 'Admin',
            ':role' => $_SESSION['role'],
            ':action' => 'Add Product',
            ':details' => "Added new product: " . $name
        ]);

        $pdo->commit();

        $message = "Product added successfully!";

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Remove uploaded image if database operation failed
        if ($uploadedImageUrl) {
            deleteProductImage($uploadedImageUrl);
        }

        error_log("Add product error: " . $e->getMessage());

        $message = "Error: " . $e->getMessage();

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($uploadedImageUrl) {
            deleteProductImage($uploadedImageUrl);
        }

        error_log("Add product database error: " . $e->getMessage());

        $message = "Error: Unable to save product. Please try again.";
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