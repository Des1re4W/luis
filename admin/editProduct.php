<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = ""; 
$row = [];

$product_id = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT);

if (!$product_id) {
    $_SESSION['message'] = "Invalid product ID.";
    header("Location: products.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
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

// Kapag pinindot ang Update button
if (isset($_POST['update'])) {
    $product_name = trim($_POST['product_name']);
    $cost_price = $_POST['cost_price'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];
    $product_description = trim($_POST['product_description']);
    $image_path = $row['image']; // Default sa lumang image

    $image_changed = false;

    // I-check kung may in-upload na bagong image
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['image']['tmp_name'];
        if (!empty($tmp) && file_exists($tmp)) {
            $check_image = getimagesize($tmp);
            if ($check_image !== false) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($ext, $allowed_extensions)) {
                    $new_image_name = uniqid("prod_", true) . '.' . $ext;
                    $folder = "../uploads/" . $new_image_name;

                    if (move_uploaded_file($tmp, $folder)) {
                        $image_path = "uploads/" . $new_image_name;
                        $image_changed = true;
                    }
                }
            }
        }
    }

    try {
        // 1. I-update ang produkto sa database
        $update_stmt = $pdo->prepare("
            UPDATE products 
            SET product_name = :product_name, 
                cost_price = :cost_price, 
                price = :price, 
                category = :category, 
                stock = :stock, 
                product_description = :product_description, 
                image = :image 
            WHERE id = :id
        ");

        $update_stmt->execute([
            ':product_name' => $product_name,
            ':cost_price' => $cost_price,
            ':price' => $price,
            ':category' => $category,
            ':stock' => $stock,
            ':product_description' => $product_description,
            ':image' => $image_path,
            ':id' => $product_id
        ]);

        // 2. Mag-buo ng detalyadong pagbabago para sa Audit Trail
        $changes = [];
        if ($row['product_name'] !== $product_name) {
            $changes[] = "Name changed from '{$row['product_name']}' to '{$product_name}'";
        }
        if ((float)$row['cost_price'] !== (float)$cost_price) {
            $changes[] = "Cost Price changed from {$row['cost_price']} to {$cost_price}";
        }
        if ((float)$row['price'] !== (float)$price) {
            $changes[] = "Selling Price changed from {$row['price']} to {$price}";
        }
        if ((int)$row['stock'] !== (int)$stock) {
            $changes[] = "Stock changed from {$row['stock']} to {$stock}";
        }
        if ($row['category'] !== $category) {
            $changes[] = "Category changed from '{$row['category']}' to '{$category}'";
        }
        if ($row['product_description'] !== $product_description) {
            $changes[] = "Description updated";
        }
        if ($image_changed) {
            $changes[] = "Product photo updated";
        }

        $details_text = "Updated product: " . $product_name;
        if (!empty($changes)) {
            $details_text .= " (" . implode(", ", $changes) . ")";
        }

        // 3. I-save sa audit_log table (kasama ang user_id para hindi mag-error)
        $username = $_SESSION['username'] ?? 'Admin';
        $role = $_SESSION['role'] ?? 'admin';
        $user_id = $_SESSION['user_id'] ?? null; 

        $log_stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, username, role, action, details, timestamp) 
            VALUES (:user_id, :username, :role, :action, :details, CURRENT_TIMESTAMP)
        ");
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':role' => $role,
            ':action' => 'Edit Product',
            ':details' => $details_text
        ]);

        $_SESSION['message'] = "Product updated successfully!";
        header("Location: products.php");
        exit();

    } catch (PDOException $e) {
        error_log("Update product error: " . $e->getMessage());
        $message = '<div class="alert alert-danger">Error updating product in database.</div>';
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
                    <img src="../<?php echo htmlspecialchars($row['image']); ?>" width="120" class="mb-3 border rounded shadow-sm"><br>
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