<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/google_drive.php';
require_once __DIR__ . '/../config/image.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";
$messageType = "";

$product_id = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT);

if (!$product_id) {
    die("Invalid product ID.");
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute([':id' => $product_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Product not found.");
}

if (isset($_POST['update'])) {

    $name = trim($_POST['product_name']);
    $cost_price = $_POST['cost_price'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];
    $description = trim($_POST['product_description']);

    $image_path = $row['image'];

    try {
        if (!is_numeric($cost_price) || !is_numeric($price) || floatval($cost_price) < 0 || floatval($price) < 0) {
            throw new Exception("Please enter valid prices.");
        }

        if (floatval($cost_price) > floatval($price)) {
            throw new Exception("Cost price cannot be higher than the selling price.");
        }

        $check_stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE LOWER(product_name) = LOWER(:name)
            AND id != :id
        ");

        $check_stmt->execute([
            ':name' => $name,
            ':id' => $product_id
        ]);

        if ($check_stmt->fetch()) {
            throw new Exception("Another product with this name already exists.");
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_error = $_FILES['image']['error'];

            if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
                throw new Exception("Uploaded file size exceeds the server limit (Max 20MB). Please choose a smaller image.");
            } elseif ($upload_error !== UPLOAD_ERR_OK) {
                throw new Exception("Image upload failed. Error code: " . $upload_error);
            }

            $tmp = $_FILES['image']['tmp_name'];
            $original_name = $_FILES['image']['name'];

            if (empty($tmp) || !file_exists($tmp)) {
                throw new Exception("Uploaded image temporary file is invalid.");
            }

            // Exclude GIF extension
            $image_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

            $is_valid_image = false;
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                $is_valid_image = str_starts_with($mime, 'image/') && $mime !== 'image/gif';
            } else {
                $check_image = @getimagesize($tmp);
                $is_valid_image = ($check_image !== false && $check_image[2] !== IMAGETYPE_GIF);
            }

            if (!$is_valid_image || !in_array($image_extension, $allowed_extensions, true)) {
                throw new Exception("Invalid image file format. GIF images are not allowed. Please upload JPG, JPEG, PNG, or WEBP.");
            }

            $new_image_name = uniqid("prod_", true) . '.' . $image_extension;
            $file_id = uploadProductImageToDrive($tmp, $new_image_name);

            if (empty($file_id)) {
                throw new Exception("Google Drive did not return an image file ID.");
            }

            $image_path = "https://drive.google.com/uc?export=view&id=" . $file_id;
        }

        $update_query = "
            UPDATE products
            SET
                product_name = :name,
                cost_price = :cost_price,
                price = :price,
                category = :category,
                image = :image,
                stock = :stock,
                product_description = :description
            WHERE id = :id
        ";

        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([
            ':name' => $name,
            ':cost_price' => $cost_price,
            ':price' => $price,
            ':category' => $category,
            ':image' => $image_path,
            ':stock' => $stock,
            ':description' => $description,
            ':id' => $product_id
        ]);

        $username = $_SESSION['username'] ?? 'Admin';
        $role = $_SESSION['role'] ?? 'admin';
        $user_id = $_SESSION['user_id'] ?? null;

        $log_query = "
            INSERT INTO audit_log
            (user_id, username, role, action, details, timestamp)
            VALUES
            (:user_id, :username, :role, :action, :details, CURRENT_TIMESTAMP)
        ";

        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':role' => $role,
            ':action' => 'Update Product',
            ':details' => 'Updated product: ' . $name
        ]);

        $message = "Product updated successfully!";
        $messageType = "success";

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $product_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        error_log("EDIT PRODUCT ERROR: " . $e->getMessage());
        $message = "Error updating product: " . $e->getMessage();
        $messageType = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>

<body class="bg-light">

    <div class="container mt-5 mb-5" style="max-width: 600px;">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-warning text-white fw-bold py-3">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Product Details</h5>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="bi bi-<?php echo ($messageType === 'success') ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="editProduct.php?product_id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data">

                    <label class="form-label fw-bold">Product Name</label>
                    <input type="text" name="product_name" class="form-control mb-3"
                        value="<?php echo htmlspecialchars($row['product_name'] ?? ''); ?>" required>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cost Price (Capital)</label>
                            <input type="number" step="0.01" name="cost_price" class="form-control mb-3"
                                value="<?php echo htmlspecialchars($row['cost_price'] ?? '0.00'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Selling Price</label>
                            <input type="number" step="0.01" name="price" class="form-control mb-3"
                                value="<?php echo htmlspecialchars($row['price'] ?? '0.00'); ?>" required>
                        </div>
                    </div>

                    <label class="form-label fw-bold">Category</label>
                    <select name="category" class="form-control mb-3" required>
                        <?php
                        $cats = ["Tools", "Electrical", "Construction", "Plumbing", "Paint and Supplies", "Chemicals"];
                        foreach ($cats as $cat) {
                            $selected = (($row['category'] ?? '') === $cat) ? "selected" : "";
                            echo "<option value=\"$cat\" $selected>$cat</option>";
                        }
                        ?>
                    </select>

                    <label class="form-label fw-bold">Stock Quantity</label>
                    <input type="number" name="stock" min="0" class="form-control mb-3"
                        value="<?php echo htmlspecialchars($row['stock'] ?? '0'); ?>" required>

                    <label class="form-label fw-bold">Product Description</label>
                    <textarea name="product_description" class="form-control mb-3" rows="5"
                        required><?php echo htmlspecialchars($row['product_description'] ?? ''); ?></textarea>

                    <label class="form-label fw-bold d-block">Current Image</label>

                    <?php if (!empty($row['image'])): ?>
                        <?php $img_src = getProductImageSrc($row['image']); ?>

                        <img src="<?php echo htmlspecialchars($img_src, ENT_QUOTES, 'UTF-8'); ?>" width="120" height="120"
                            class="mb-3 border rounded shadow-sm" style="object-fit: cover;" alt="Current Product Image"
                            onerror="this.onerror=null; this.src='../assets/images/no-image.png';">

                    <?php else: ?>

                        <img src="../assets/images/no-image.png" width="120" height="120"
                            class="mb-3 border rounded shadow-sm" alt="No Product Image">

                    <?php endif; ?>

                    <label class="form-label fw-bold">Change Image (Optional)</label>
                    <input type="file" name="image" class="form-control mb-4" accept=".jpg, .jpeg, .png, .webp">

                    <div class="d-flex gap-2">
                        <a href="products.php" class="btn btn-secondary w-50 fw-bold">Cancel</a>
                        <button type="submit" name="update" class="btn btn-warning w-50 text-white fw-bold">Update
                            Product</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>