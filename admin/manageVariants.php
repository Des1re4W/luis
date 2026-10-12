<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

$admin_user = isset($_SESSION['username']) ? $_SESSION['username'] : "Admin";
$admin_role = isset($_SESSION['role']) ? $_SESSION['role'] : "admin";
$admin_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$ip_address = $_SERVER['REMOTE_ADDR'];

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Kunin ang info ng main product
$p_res = mysqli_query($conn, "SELECT * FROM products WHERE id = '$product_id'");
$product = mysqli_fetch_assoc($p_res);

if (!$product) {
    echo "Product not found!";
    exit();
}

// LOGIC PARA SA ADD NEW VARIANT WITH DUPLICATION CHECK
if (isset($_POST['add_variant'])) {
    $variant_name = mysqli_real_escape_string($conn, trim($_POST['variant_name']));
    $variant_description = mysqli_real_escape_string($conn, trim($_POST['variant_description']));
    $cost_price = floatval($_POST['cost_price']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $variant_image_path = "";

    if ($cost_price < 0 || $price < 0 || $stock < 0) {
        echo "<script>alert('Error: Negative values are not allowed!'); window.history.back();</script>";
        exit();
    }

    // --- PRICE VALIDATION: Cost price must not be higher than selling price ---
    if ($cost_price > $price) {
        $_SESSION['error_message'] = "Error: Cost price cannot be higher than the selling price!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    // RIGID PREVENT DUPLICATION LOGIC
    $check_dup = mysqli_query($conn, "SELECT id FROM product_variants WHERE product_id = '$product_id' AND LOWER(variant_name) = LOWER('$variant_name')");
    if (mysqli_num_rows($check_dup) > 0) {
        $_SESSION['error_message'] = "Error: The variant name '$variant_name' already exist!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    // Pag-handle sa file upload ng variant image
    if (isset($_FILES['variant_image']) && $_FILES['variant_image']['error'] == 0) {
        $target_dir = "../uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $filename = time() . "_" . basename($_FILES["variant_image"]["name"]);
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES["variant_image"]["tmp_name"], $target_file)) {
            $variant_image_path = "uploads/" . $filename;
        }
    }

    $insert_sql = "INSERT INTO product_variants (product_id, variant_name, variant_description, cost_price, price, stock, variant_image) 
                   VALUES ('$product_id', '$variant_name', '$variant_description', '$cost_price', '$price', '$stock', '$variant_image_path')";
    
    if (mysqli_query($conn, $insert_sql)) {
        // --- AUDIT LOGGING FOR ADD ---
        $audit_action = "Add Variant";
        $audit_details = mysqli_real_escape_string($conn, "Added new variant '$variant_name' for product '{$product['product_name']}'");
        
        $audit_query = "INSERT INTO audit_log (user_id, username, role, action, details, ip_address) 
                        VALUES ('$admin_user_id', '$admin_user', '$admin_role', '$audit_action', '$audit_details', '$ip_address')";
        mysqli_query($conn, $audit_query);

        $_SESSION['message'] = "Variant added successfully!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }
}

// LOGIC PARA SA UPDATE / EDIT VARIANT
if (isset($_POST['update_variant'])) {
    $v_id = intval($_POST['edit_variant_id']);
    $v_name = mysqli_real_escape_string($conn, trim($_POST['edit_variant_name']));
    $v_desc = mysqli_real_escape_string($conn, trim($_POST['edit_variant_description']));
    $v_cost = floatval($_POST['edit_cost_price']);
    $v_price = floatval($_POST['edit_price']);
    $v_stock = intval($_POST['edit_stock']);

    if ($v_cost < 0 || $v_price < 0 || $v_stock < 0) {
        $_SESSION['error_message'] = "Error: Negative values are not allowed!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    // --- PRICE VALIDATION: Cost price must not be higher than selling price ---
    if ($v_cost > $v_price) {
        $_SESSION['error_message'] = "Error: Cost price cannot be higher than the selling price!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    // Prevent duplication tuwing nag-e-edit (maliban sa kanyang sarili)
    $check_dup_edit = mysqli_query($conn, "SELECT id FROM product_variants WHERE product_id = '$product_id' AND LOWER(variant_name) = LOWER('$v_name') AND id != '$v_id'");
    if (mysqli_num_rows($check_dup_edit) > 0) {
        $_SESSION['error_message'] = "Error: The variant name '$v_name' cannot be updated because it is already exist!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    $update_sql = "UPDATE product_variants SET 
                    variant_name='$v_name', 
                    variant_description='$v_desc', 
                    cost_price='$v_cost', 
                    price='$v_price', 
                    stock='$v_stock'";

    // Kung may bagong picture na pinili habang nag-e-edit
    if (isset($_FILES['edit_variant_image']) && $_FILES['edit_variant_image']['error'] == 0) {
        $target_dir = "../uploads/";
        $filename = time() . "_" . basename($_FILES["edit_variant_image"]["name"]);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES["edit_variant_image"]["tmp_name"], $target_file)) {
            $img_path = "uploads/" . $filename;
            $update_sql .= ", variant_image='$img_path'";
        }
    }

    $update_sql .= " WHERE id='$v_id' AND product_id='$product_id'";
    
    if (mysqli_query($conn, $update_sql)) {
        // --- AUDIT LOGGING FOR UPDATE ---
        $audit_action = "Update Variant";
        $audit_details = mysqli_real_escape_string($conn, "Updated variant '$v_name' for product '{$product['product_name']}'");

        $audit_query = "INSERT INTO audit_log (user_id, username, role, action, details, ip_address) 
                        VALUES ('$admin_user_id', '$admin_user', '$admin_role', '$audit_action', '$audit_details', '$ip_address')";
        mysqli_query($conn, $audit_query);

        $_SESSION['message'] = "Variant updated successfully!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }
}

// LOGIC PARA SA DELETE VARIANT
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Kunin muna ang pangalan ng variant bago burahin para sa audit log
    $get_v_name = mysqli_query($conn, "SELECT variant_name FROM product_variants WHERE id = '$delete_id'");
    $v_data = mysqli_fetch_assoc($get_v_name);
    $deleted_v_name = $v_data ? $v_data['variant_name'] : 'Unknown';

    if (mysqli_query($conn, "DELETE FROM product_variants WHERE id = '$delete_id'")) {
        // --- AUDIT LOGGING FOR DELETE ---
        $audit_action = "Delete Variant";
        $audit_details = mysqli_real_escape_string($conn, "Deleted variant '$deleted_v_name' from product '{$product['product_name']}'");

        $audit_query = "INSERT INTO audit_log (user_id, username, role, action, details, ip_address) 
                        VALUES ('$admin_user_id', '$admin_user', '$admin_role', '$audit_action', '$audit_details', '$ip_address')";
        mysqli_query($conn, $audit_query);

        $_SESSION['message'] = "Variant deleted successfully!";
    }
    
    header("Location: manageVariants.php?product_id=$product_id");
    exit();
}

// Kunin lahat ng kasalukuyang variants ng product na ito
$variants_res = mysqli_query($conn, "SELECT * FROM product_variants WHERE product_id = '$product_id' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Product Variants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light py-4">
<div class="container-fluid px-4" style="max-width: 1300px;">

    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm">
        <div>
            <h4 class="mb-0 fw-bold">Variants for: <span class="text-primary"><?php echo htmlspecialchars($product['product_name']); ?></span></h4>
            <small class="text-muted">Main Product Category: <?php echo $product['category']; ?></small>
        </div>
        <a href="products.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Inventory</a>
    </div>

    <?php if(isset($_SESSION['message'])): ?>
        <div class="alert alert-success py-2 alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm p-3 sticky-top" style="top: 20px; z-index: 10;">
                <h5 class="fw-bold mb-3"><i class="bi bi-plus-square"></i> Add New Product Variant</h5>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Variant Name</label>
                        <input type="text" name="variant_name" class="form-control form-control-sm" placeholder="Product variant name" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Variant Description (Customer Side)</label>
                        <textarea name="variant_description" class="form-control form-control-sm" rows="3" placeholder="Product details"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Cost Price (₱)</label>
                        <input type="number" name="cost_price" min="0" step="0.01" required class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Selling Price (₱)</label>
                        <input type="number" name="price" min="0" step="0.01" required class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Initial Stock Amount</label>
                        <input type="number" name="stock" min="0" step="1" required class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Variant Specific Photo</label>
                        <input type="file" name="variant_image" class="form-control form-control-sm" accept="image/*">
                    </div>
                    <button type="submit" name="add_variant" class="btn btn-primary btn-sm w-100 fw-bold">Save Variant</button>
                </form>
            </div>
        </div>

        <div class="col-lg-9 col-md-8">
            <div class="card border-0 shadow-sm p-3">
                <h5 class="fw-bold mb-3"><i class="bi bi-list-stars"></i> Current Variants Inventory Matrix</h5>
                <div class="table-responsive">
                    <table class="table align-middle table-bordered small">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center" style="width: 60px;">Photo</th>
                                <th style="width: 150px;">Variant Name</th>
                                <th>Variant Description (Customer Side)</th>
                                <th style="width: 110px;">Cost Price</th>
                                <th style="width: 110px;">Selling Price</th>
                                <th style="width: 80px;">Stock</th>
                                <th class="text-center" style="width: 130px;">Action Matrix</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($variants_res) > 0): ?>
                                <?php while($v = mysqli_fetch_assoc($variants_res)): ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if(!empty($v['variant_image'])): ?>
                                                <img src="../<?php echo $v['variant_image']; ?>" class="rounded" style="width:45px; height:45px; object-fit:cover; border:1px solid #ddd;">
                                            <?php else: ?>
                                                <img src="../<?php echo $product['image']; ?>" class="rounded" style="width:45px; height:45px; object-fit:cover; opacity: 0.5;" title="Using product default image">
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($v['variant_name']); ?></td>
                                        <td>
                                            <span class="text-muted small">
                                                <?php echo !empty($v['variant_description']) ? nl2br(htmlspecialchars($v['variant_description'])) : '<i class="text-danger">No description configured. Fallback to main item text.</i>'; ?>
                                            </span>
                                        </td>
                                        <td class="text-secondary fw-bold">₱<?php echo number_format($v['cost_price'], 2); ?></td>
                                        <td class="text-success fw-bold">₱<?php echo number_format($v['price'], 2); ?></td>
                                        <td class="fw-bold"><?php echo $v['stock']; ?></td>
                                        <td class="text-center">
                                            <div class="btn-group gap-1">
                                                <button type="button" 
                                                        class="btn btn-warning btn-sm px-2 py-1 fw-bold text-white btn-edit-variant"
                                                        data-id="<?php echo $v['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($v['variant_name']); ?>"
                                                        data-desc="<?php echo htmlspecialchars($v['variant_description']); ?>"
                                                        data-cost="<?php echo $v['cost_price']; ?>"
                                                        data-price="<?php echo $v['price']; ?>"
                                                        data-stock="<?php echo $v['stock']; ?>">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>

                                                <a href="manageVariants.php?product_id=<?php echo $product_id; ?>&delete_id=<?php echo $v['id']; ?>" 
                                                   class="btn btn-danger btn-sm px-2 py-1" 
                                                   onclick="return confirm('Are you sure you want to delete this variant?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted fw-bold">No variants created yet for this item. Fill out the form to start.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editVariantModal" tabindex="-1" aria-labelledby="editVariantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title fw-bold" id="editVariantModalLabel">📝 Edit Variant Configuration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="edit_variant_id" id="edit_variant_id">
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Variant Name</label>
                        <input type="text" name="edit_variant_name" id="edit_variant_name" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Variant Description (Customer Side)</label>
                        <textarea name="edit_variant_description" id="edit_variant_description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="row mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Cost Price (₱)</label>
                            <input type="number" name="edit_cost_price" id="edit_cost_price" step="0.01" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Selling Price (₱)</label>
                            <input type="number" name="edit_price" id="edit_price" step="0.01" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Current Available Stock</label>
                        <input type="number" name="edit_stock" id="edit_stock" class="form-control" required>
                    </div>

                    <div class="mb-1">
                        <label class="form-label small fw-bold">Change Variant Image (Optional)</label>
                        <input type="file" name="edit_variant_image" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_variant" class="btn btn-warning btn-sm text-white fw-bold">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-edit-variant').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit_variant_id').value = this.getAttribute('data-id');
        document.getElementById('edit_variant_name').value = this.getAttribute('data-name');
        document.getElementById('edit_variant_description').value = this.getAttribute('data-desc');
        document.getElementById('edit_cost_price').value = this.getAttribute('data-cost');
        document.getElementById('edit_price').value = this.getAttribute('data-price');
        document.getElementById('edit_stock').value = this.getAttribute('data-stock');
        
        var editModal = new bootstrap.Modal(document.getElementById('editVariantModal'));
        editModal.show();
    });
});
</script>
</body>
</html>