<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);
$get = mysqli_query($conn, "SELECT * FROM products WHERE id='$id'");
$row = mysqli_fetch_assoc($get);

if (!$row) {
    header("Location: products.php");
    exit();
}

$message = "";

if (isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $cost_price = mysqli_real_escape_string($conn, $_POST['cost_price']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $stock = mysqli_real_escape_string($conn, $_POST['stock']);
    $description = mysqli_real_escape_string($conn, $_POST['product_description']);

    // --- PRICE VALIDATION: Cost price must not be higher than selling price ---
    if (floatval($cost_price) > floatval($price)) {
        $message = "<div class='alert alert-danger'>Error: Cost price cannot be higher than the selling price!</div>";
    } else {
        // Check kung may bagong image na inupload[cite: 10]
        if ($_FILES['image']['name'] != "") {
            $image = $_FILES['image']['name'];
            $tmp = $_FILES['image']['tmp_name'];
            $folder = "../uploads/" . $image;
            move_uploaded_file($tmp, $folder);
            $db_path = "uploads/" . $image;

            $query = "UPDATE products SET product_name='$name', cost_price='$cost_price', price='$price', category='$category', stock='$stock', product_description='$description', image='$db_path' WHERE id='$id'";
        } else {
            $query = "UPDATE products SET product_name='$name', cost_price='$cost_price', price='$price', category='$category', stock='$stock', product_description='$description' WHERE id='$id'";
        }

       if (mysqli_query($conn, $query)) {
            // ==========================================
            // DETAILED AUDIT TRAIL LOGGING (COMPARE CHANGES)[cite: 10]
            // ==========================================
            $changes = [];

            if ($row['product_name'] !== $_POST['product_name']) {
                $changes[] = "name ('{$row['product_name']}' to '$name')";
            }
            if (floatval($row['cost_price']) !== floatval($cost_price)) {
                $changes[] = "cost price ({$row['cost_price']} to $cost_price)";
            }
            if (floatval($row['price']) !== floatval($price)) {
                $changes[] = "selling price ({$row['price']} to $price)";
            }
            if ($row['category'] !== $category) {
                $changes[] = "category ('{$row['category']}' to '$category')";
            }
            if (intval($row['stock']) !== intval($stock)) {
                $changes[] = "stock ({$row['stock']} to $stock)";
            }
            if ($row['product_description'] !== $_POST['product_description']) {
                $changes[] = "description";
            }
            if ($_FILES['image']['name'] != "") {
                $changes[] = "image";
            }

            $username = $_SESSION['username'] ?? 'Admin';
            $role = $_SESSION['role'] ?? 'admin';
            $action = 'Update Product';
            
            $safe_p_name = mysqli_real_escape_string($conn, $name);
            
            if (count($changes) > 0) {
                $details = "Updated product '$safe_p_name': changed " . implode(", ", $changes);
            } else {
                $details = "Updated product '$safe_p_name' with no property changes";
            }

            $ip_address = $_SERVER['REMOTE_ADDR'];
            $safe_details = mysqli_real_escape_string($conn, $details);

            $log_query = "INSERT INTO audit_log (username, role, action, details, ip_address, timestamp) 
                          VALUES ('$username', '$role', '$action', '$safe_details', '$ip_address', NOW())";
            mysqli_query($conn, $log_query);
            // ==========================================

            header("Location: products.php?msg=Product updated successfully");
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Error updating product: " . mysqli_error($conn) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width: 600px;">
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
                        $selected = ($row['category'] == $cat) ? "selected" : "";
                        echo "<option $selected>$cat</option>";
                    }
                    ?>
                </select>

                <label class="form-label fw-bold">Stock Quantity</label>
                <input type="number" name="stock" min="0" class="form-control mb-3" value="<?php echo htmlspecialchars($row['stock'] ?? '0'); ?>" required>

                <label class="form-label fw-bold">Product Description</label>
                <textarea name="product_description" class="form-control mb-3" rows="5" required><?php echo htmlspecialchars($row['product_description'] ?? ''); ?></textarea>

                <label class="form-label fw-bold d-block">Current Image</label>
                <?php if(!empty($row['image'])): ?>
                    <img src="../<?php echo $row['image']; ?>" width="120" class="mb-3 border rounded shadow-sm"><br>
                <?php else: ?>
                    <img src="../assets/no-image.jpg" width="120" class="mb-3 border rounded shadow-sm"><br>
                <?php endif; ?>

                <label class="form-label fw-bold">Change Image (Optional)</label>
                <input type="file" name="image" class="form-control mb-4" accept="image/*">

                <div class="d-flex gap-2">
                    <a href="products.php" class="btn btn-secondary w-50 fw-bold">Cancel</a>
                    <button type="submit" name="update" class="btn btn-warning w-50 text-white fw-bold">Update Product</button>
                </div>
            </form>
            
        </div>
    </div>
</div>

</body>
</html>