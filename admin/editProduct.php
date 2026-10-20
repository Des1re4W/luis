<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    $username = $_SESSION['username'] ?? 'Unknown';
    $role = $_SESSION['role'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $action = 'Logout';
    $details = "User $username logged out successfully.";

    try {
        // Insert logout activity into audit_log using PDO
        $stmt = $conn->prepare("
            INSERT INTO audit_log
                (username, role, action, details, ip_address)
            VALUES
                (:username, :role, :action, :details, :ip_address)
        ");

        $stmt->execute([
            ':username' => $username,
            ':role' => $role,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => $ip_address
        ]);
    } catch (PDOException $e) {
        // Log the error without preventing logout
        error_log("Logout audit log error: " . $e->getMessage());
    }
}

// Destroy the session even if audit logging fails
$_SESSION = [];
session_destroy();

header("Location: login.php");
exit();
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