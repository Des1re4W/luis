<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/supabase_storage.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";

$product_id = filter_var(
    $_GET['product_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$product_id || $product_id <= 0) {
    $_SESSION['message'] = "Invalid product ID.";
    header("Location: products.php");
    exit();
}

try {

    $stmt = $pdo->prepare("
        SELECT * FROM products
        WHERE id = :id
    ");

    $stmt->execute([':id' => $product_id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['message'] = "Product not found.";
        header("Location: products.php");
        exit();
    }

} catch (PDOException $e) {

    error_log("Fetch product error: " . $e->getMessage());
    die("Database error occurred.");
}


// UPDATE PRODUCT
if (isset($_POST['update'])) {

    $product_name = trim($_POST['product_name'] ?? '');
    $cost_price = $_POST['cost_price'] ?? 0;
    $price = $_POST['price'] ?? 0;
    $category = $_POST['category'] ?? '';
    $stock = $_POST['stock'] ?? 0;
    $product_description = trim($_POST['product_description'] ?? '');

    $image_path = $row['image'];
    $newImageUploaded = false;

    try {

        // Validate price
        if (!is_numeric($cost_price) || !is_numeric($price) ||
            $cost_price < 0 || $price < 0) {
            throw new Exception("Invalid product price.");
        }

        if ((float)$cost_price > (float)$price) {
            throw new Exception(
                "Cost price cannot be higher than the selling price."
            );
        }

        // Check duplicate product name
        $duplicate = $pdo->prepare("
            SELECT id FROM products
            WHERE LOWER(product_name) = LOWER(:name)
            AND id != :id
            LIMIT 1
        ");

        $duplicate->execute([
            ':name' => $product_name,
            ':id' => $product_id
        ]);

        if ($duplicate->fetch()) {
            throw new Exception("Product name already exists.");
        }

        // Upload new image only if selected
        if (
            isset($_FILES['image']) &&
            $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Image upload failed.");
            }

            $image_path = uploadProductImage($_FILES['image']);
            $newImageUploaded = true;
        }

        $pdo->beginTransaction();

        // Update product
        $update_stmt = $pdo->prepare("
            UPDATE products
            SET
                product_name = :product_name,
                cost_price = :cost_price,
                price = :price,
                category = :category,
                stock = :stock,
                product_description = :description,
                image = :image
            WHERE id = :id
        ");

        $update_stmt->execute([
            ':product_name' => $product_name,
            ':cost_price' => $cost_price,
            ':price' => $price,
            ':category' => $category,
            ':stock' => $stock,
            ':description' => $product_description,
            ':image' => $image_path,
            ':id' => $product_id
        ]);

        // Audit Trail
        $changes = [];

        if ($row['product_name'] !== $product_name) {
            $changes[] = "Name updated";
        }

        if ((float)$row['cost_price'] != (float)$cost_price) {
            $changes[] = "Cost price updated";
        }

        if ((float)$row['price'] != (float)$price) {
            $changes[] = "Selling price updated";
        }

        if ((int)$row['stock'] != (int)$stock) {
            $changes[] = "Stock updated";
        }

        if ($row['category'] !== $category) {
            $changes[] = "Category updated";
        }

        if ($row['product_description'] !== $product_description) {
            $changes[] = "Description updated";
        }

        if ($newImageUploaded) {
            $changes[] = "Product photo updated";
        }

        $details = "Updated product: " . $product_name;

        if (!empty($changes)) {
            $details .= " (" . implode(", ", $changes) . ")";
        }

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
            ':action' => 'Edit Product',
            ':details' => $details
        ]);

        $pdo->commit();

        // Delete old Supabase image only after successful database update
        if ($newImageUploaded) {
            deleteProductImage($row['image']);
        }

        $_SESSION['message'] = "Product updated successfully!";

        header("Location: products.php");
        exit();

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Delete newly uploaded image if update failed
        if ($newImageUploaded) {
            deleteProductImage($image_path);
        }

        error_log("Edit product error: " . $e->getMessage());

        $message = '<div class="alert alert-danger">'
            . htmlspecialchars($e->getMessage())
            . '</div>';

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($newImageUploaded) {
            deleteProductImage($image_path);
        }

        error_log("Edit product database error: " . $e->getMessage());

        $message = '<div class="alert alert-danger">'
            . 'Unable to update product. Please try again.'
            . '</div>';
    }
}


// Helper for displaying old and new image formats
function getProductImageUrl($image)
{
    if (empty($image)) {
        return '../assets/no-image.jpg';
    }

    // Supabase URL or another external URL
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return $image;
    }

    // Legacy local image path
    $image = ltrim($image, '/');

    if (strpos($image, 'uploads/') === 0) {
        return '../' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
    }

    return '../uploads/' . rawurlencode(basename($image));
}

$imagePreview = getProductImageUrl($row['image'] ?? '');
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
            
            <?php echo $message; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                
                <label class="form-label fw-bold">Product Name</label>
                <input type="text" name="product_name" class="form-control mb-3" value="<?php echo htmlspecialchars($row['product_name'] ?? ''); ?>" required>

                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Cost Price (Capital)</label>
                        <input type="number" step="0.01" name="cost_price" class="form-control mb-3" value="<?php echo htmlspecialchars($row['cost_price'] ?? '0.00'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Selling Price</label>
                        <input type="number" step="0.01" name="price" class="form-control mb-3" value="<?php echo htmlspecialchars($row['price'] ?? '0.00'); ?>" required>
                    </div>
                </div>

                <label class="form-label fw-bold">Category</label>
                <select name="category" class="form-control mb-3" required>
                    <?php 
                    $cats = ["Tools", "Electrical", "Construction", "Plumbing", "Paint and Supplies", "Chemicals"];
                    foreach($cats as $cat) {
                        $selected = (($row['category'] ?? '') === $cat) ? "selected" : "";
                        echo "<option value=\"$cat\" $selected>$cat</option>";
                    }
                    ?>
                </select>

                <label class="form-label fw-bold">Stock Quantity</label>
                <input type="number" name="stock" min="0" class="form-control mb-3" value="<?php echo htmlspecialchars($row['stock'] ?? '0'); ?>" required>

                <label class="form-label fw-bold">Product Description</label>
                <textarea name="product_description" class="form-control mb-3" rows="5" required><?php echo htmlspecialchars($row['product_description'] ?? ''); ?></textarea>

                <label class="form-label fw-bold d-block">Current Image</label>
                <?php if(!empty($row['image'])): ?>
                    <img
    src="<?php echo $imagePreview; ?>"
    width="120"
    height="120"
    style="object-fit: cover;"
    class="mb-3 border rounded shadow-sm"
    alt="Product Image"
    onerror="this.onerror=null;this.src='../assets/no-image.jpg';"><br>
                <?php else: ?>
                    <img src="../assets/no-image.jpg" width="120" class="mb-3 border rounded shadow-sm"><br>
                <?php endif; ?>

                <label class="form-label fw-bold">Change Image (Optional)</label>
                <input type="file" name="image" class="form-control mb-4" accept=".jpg, .jpeg, .png, .webp">

                <div class="d-flex gap-2">
                    <a href="products.php" class="btn btn-secondary w-50 fw-bold">Cancel</a>
                    <button type="submit" name="update" class="btn btn-warning w-50 text-white fw-bold">Update Product</button>
                </div>
            </form>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>