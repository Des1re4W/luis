<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/google_drive.php';

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
    $description = trim($_POST['product_description']);

    if (floatval($cost_price) > floatval($price)) {
        $message = "Error: Cost price cannot be higher than the selling price!";
    } else {
        $check_stmt = $pdo->prepare("SELECT id FROM products WHERE LOWER(product_name) = LOWER(:name)");
        $check_stmt->execute([':name' => $name]);

        if ($check_stmt->rowCount() > 0) {
            $message = "Error: Product '$name' already exists in the inventory!";
        } else {
            if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                $message = "Error: Please select a valid product image to upload.";
            } else {
                $upload_error = $_FILES['image']['error'];

                if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
                    $message = "Error: File size is too large for the server limit (Max 20MB). Please choose a smaller image.";
                } elseif ($upload_error !== UPLOAD_ERR_OK) {
                    $message = "Error: Image upload failed. (Code: " . $upload_error . ")";
                } else {
                    $image_original_name = $_FILES['image']['name'];
                    $tmp = $_FILES['image']['tmp_name'];

                    if (empty($tmp) || !file_exists($tmp)) {
                        $message = "Error: Uploaded file temporary path is empty or invalid.";
                    } else {
                        // Case-insensitive extension check - EXCLUDING GIF
                        $image_extension = strtolower(pathinfo($image_original_name, PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

                        $is_valid_image = false;
                        if (function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mime = finfo_file($finfo, $tmp);
                            finfo_close($finfo);
                            // Bawal ang image/gif MIME type
                            $is_valid_image = str_starts_with($mime, 'image/') && $mime !== 'image/gif';
                        } else {
                            $check_image = @getimagesize($tmp);
                            $is_valid_image = ($check_image !== false && $check_image[2] !== IMAGETYPE_GIF);
                        }

                        if (!$is_valid_image || !in_array($image_extension, $allowed_extensions, true)) {
                            $message = "Error: Invalid image file format! Please upload a valid JPG, JPEG, PNG, or WEBP photo.";
                        } else {
                            $new_image_name = uniqid("prod_", true) . '.' . $image_extension;

                            try {
                                $file_id = uploadProductImageToDrive($tmp, $new_image_name);

                                if (empty($file_id)) {
                                    throw new Exception("Google Drive did not return an image ID.");
                                }

                                $savePath = "https://drive.google.com/uc?export=view&id=" . $file_id;

                                $query = "INSERT INTO products
                                    (product_name, cost_price, price, category, image, stock, product_description)
                                    VALUES
                                    (:name, :cost_price, :price, :category, :image, :stock, :description)";

                                $stmt = $pdo->prepare($query);
                                $stmt->execute([
                                    ':name' => $name,
                                    ':cost_price' => $cost_price,
                                    ':price' => $price,
                                    ':category' => $category,
                                    ':image' => $savePath,
                                    ':stock' => $stock,
                                    ':description' => $description
                                ]);

                                $username = $_SESSION['username'] ?? 'Admin';
                                $role = $_SESSION['role'] ?? 'admin';
                                $user_id = $_SESSION['user_id'] ?? null;

                                $log_query = "INSERT INTO audit_log
                                    (user_id, username, role, action, details, timestamp)
                                    VALUES
                                    (:user_id, :username, :role, :action, :details, CURRENT_TIMESTAMP)";

                                $log_stmt = $pdo->prepare($log_query);
                                $log_stmt->execute([
                                    ':user_id' => $user_id,
                                    ':username' => $username,
                                    ':role' => $role,
                                    ':action' => 'Add Product',
                                    ':details' => 'Added new product: ' . $name
                                ]);

                                $message = "Product added successfully!";

                            } catch (Throwable $e) {
                                error_log("PRODUCT GOOGLE DRIVE UPLOAD ERROR: " . $e->getMessage());
                                $message = "Error uploading product image: " . $e->getMessage();
                            }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container py-4 py-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6">
                <div class="card shadow-sm border-0 rounded-3 p-3 p-md-4">
                    <h4 class="text-center fw-bold mb-4 text-primary">Add New Product</h4>

                    <?php if ($message != "") { ?>
                        <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show"
                            role="alert">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>

                    <form method="POST" enctype="multipart/form-data">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Product Name</label>
                            <input type="text" name="product_name" class="form-control" placeholder="Enter product name"
                                required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Cost Price</label>
                                <input type="number" name="cost_price" step="0.01" min="0" class="form-control"
                                    placeholder="0.00" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Selling Price</label>
                                <input type="number" name="price" step="0.01" min="0" class="form-control"
                                    placeholder="0.00" required>
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
                            <textarea name="product_description" class="form-control" rows="4"
                                placeholder="Enter product details here..." required></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Product Image <small class="text-muted">(JPG, JPEG,
                                    PNG, WEBP only)</small></label>
                            <input type="file" name="image" class="form-control" accept=".jpg, .jpeg, .png, .webp"
                                required>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>