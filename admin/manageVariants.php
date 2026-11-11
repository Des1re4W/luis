<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/image.php';

if (file_exists(__DIR__ . '/../config/google_drive.php')) {
    require_once __DIR__ . '/../config/google_drive.php';
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_user = $_SESSION['username'] ?? 'Admin';
$admin_role = $_SESSION['role'] ?? 'admin';
$admin_user_id = $_SESSION['user_id'] ?? 0;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT) ?: 0;

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "Product not found!";
    exit();
}

function logVariantAction($pdo, $user_id, $username, $role, $action, $details, $ip_address)
{
    $sql = "INSERT INTO audit_log
            (user_id, username, role, action, details, ip_address)
            VALUES (:user_id, :username, :role, :action, :details, :ip_address)";

    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        'user_id' => $user_id,
        'username' => $username,
        'role' => $role,
        'action' => $action,
        'details' => $details,
        'ip_address' => $ip_address
    ]);
}

// HANDLER WITHOUT GIF ALLOWANCE
function handleGoogleDriveVariantUpload($fileArray)
{
    if (!isset($fileArray['tmp_name']) || $fileArray['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $upload_error = $fileArray['error'];
    if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
        throw new Exception("Uploaded file size exceeds the server limit (Max 20MB). Please choose a smaller image.");
    } elseif ($upload_error !== UPLOAD_ERR_OK) {
        throw new Exception("Image upload failed. Error code: " . $upload_error);
    }

    $tmp = $fileArray['tmp_name'];
    $original_name = $fileArray['name'];
    
    if (empty($tmp) || !file_exists($tmp)) {
        throw new Exception("Uploaded image temporary file is invalid.");
    }

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
        throw new Exception("Invalid image file format! Please upload JPG, JPEG, PNG, or WEBP.");
    }

    $new_image_name = uniqid("var_", true) . '.' . $image_extension;

    try {
        if (function_exists('uploadProductImageToDrive')) {
            $file_id = uploadProductImageToDrive($tmp, $new_image_name);

            if (!empty($file_id)) {
                return "https://drive.google.com/uc?export=view&id=" . $file_id;
            }
        }
    } catch (Throwable $e) {
        error_log("VARIANT GOOGLE DRIVE UPLOAD ERROR: " . $e->getMessage());
    }

    $target_dir = __DIR__ . '/../uploads/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $target_file = $target_dir . $new_image_name;

    if (move_uploaded_file($tmp, $target_file)) {
        return 'uploads/' . $new_image_name;
    }

    return null;
}

if (isset($_POST['add_variant'])) {
    $variant_name = trim($_POST['variant_name'] ?? '');
    $variant_description = trim($_POST['variant_description'] ?? '');
    $cost_price = (float) ($_POST['cost_price'] ?? 0);
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $variant_image_path = '';

    if ($variant_name === '') {
        $_SESSION['error_message'] = "Error: Variant name is required!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    if ($cost_price < 0 || $price < 0 || $stock < 0) {
        $_SESSION['error_message'] = "Error: Negative values are not allowed!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    if ($cost_price > $price) {
        $_SESSION['error_message'] = "Error: Cost price cannot be higher than the selling price!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM product_variants
        WHERE product_id = :product_id
          AND LOWER(variant_name) = LOWER(:variant_name)
    ");
    $stmt->execute([
        'product_id' => $product_id,
        'variant_name' => $variant_name
    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['error_message'] = "Error: The variant name '$variant_name' already exists!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    try {
        if (isset($_FILES['variant_image']) && $_FILES['variant_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handleGoogleDriveVariantUpload($_FILES['variant_image']);
            if ($upload_result !== null) {
                $variant_image_path = $upload_result;
            }
        }
    } catch (Throwable $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    $insert_sql = "
        INSERT INTO product_variants
        (product_id, variant_name, variant_description,
         cost_price, price, stock, variant_image)
        VALUES
        (:product_id, :variant_name, :variant_description,
         :cost_price, :price, :stock, :variant_image)
    ";

    $stmt = $pdo->prepare($insert_sql);

    if (
        $stmt->execute([
            'product_id' => $product_id,
            'variant_name' => $variant_name,
            'variant_description' => $variant_description,
            'cost_price' => $cost_price,
            'price' => $price,
            'stock' => $stock,
            'variant_image' => $variant_image_path
        ])
    ) {
        logVariantAction(
            $pdo,
            $admin_user_id,
            $admin_user,
            $admin_role,
            'Add Variant',
            "Added new variant '$variant_name' for product '{$product['product_name']}'",
            $ip_address
        );

        $_SESSION['message'] = "Variant added successfully!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }
}

if (isset($_POST['update_variant'])) {
    $v_id = filter_input(INPUT_POST, 'edit_variant_id', FILTER_VALIDATE_INT) ?: 0;
    $v_name = trim($_POST['edit_variant_name'] ?? '');
    $v_desc = trim($_POST['edit_variant_description'] ?? '');
    $v_cost = (float) ($_POST['edit_cost_price'] ?? 0);
    $v_price = (float) ($_POST['edit_price'] ?? 0);
    $v_stock = (int) ($_POST['edit_stock'] ?? 0);

    if ($v_id <= 0 || $v_name === '') {
        $_SESSION['error_message'] = "Error: A valid variant ID and name are required!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    if ($v_cost < 0 || $v_price < 0 || $v_stock < 0) {
        $_SESSION['error_message'] = "Error: Negative values are not allowed!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    if ($v_cost > $v_price) {
        $_SESSION['error_message'] = "Error: Cost price cannot be higher than the selling price!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM product_variants
        WHERE product_id = :product_id
          AND LOWER(variant_name) = LOWER(:variant_name)
          AND id <> :variant_id
    ");
    $stmt->execute([
        'product_id' => $product_id,
        'variant_name' => $v_name,
        'variant_id' => $v_id
    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['error_message'] = "Error: The variant name '$v_name' already exists!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    $update_sql = "
        UPDATE product_variants
        SET variant_name = :variant_name,
            variant_description = :variant_description,
            cost_price = :cost_price,
            price = :price,
            stock = :stock
    ";

    $params = [
        'variant_name' => $v_name,
        'variant_description' => $v_desc,
        'cost_price' => $v_cost,
        'price' => $v_price,
        'stock' => $v_stock,
        'variant_id' => $v_id,
        'product_id' => $product_id
    ];

    try {
        if (isset($_FILES['edit_variant_image']) && $_FILES['edit_variant_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handleGoogleDriveVariantUpload($_FILES['edit_variant_image']);
            if ($upload_result !== null) {
                $update_sql .= ", variant_image = :variant_image";
                $params['variant_image'] = $upload_result;
            }
        }
    } catch (Throwable $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }

    $update_sql .= " WHERE id = :variant_id AND product_id = :product_id";

    $stmt = $pdo->prepare($update_sql);

    if ($stmt->execute($params)) {
        logVariantAction(
            $pdo,
            $admin_user_id,
            $admin_user,
            $admin_role,
            'Update Variant',
            "Updated variant '$v_name' for product '{$product['product_name']}'",
            $ip_address
        );

        $_SESSION['message'] = "Variant updated successfully!";
        header("Location: manageVariants.php?product_id=$product_id");
        exit();
    }
}

if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT) ?: 0;

    if ($delete_id > 0) {
        $stmt = $pdo->prepare("
            SELECT variant_name
            FROM product_variants
            WHERE id = :variant_id AND product_id = :product_id
        ");
        $stmt->execute([
            'variant_id' => $delete_id,
            'product_id' => $product_id
        ]);

        $v_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($v_data) {
            $deleted_v_name = $v_data['variant_name'];

            $stmt = $pdo->prepare("
                DELETE FROM product_variants
                WHERE id = :variant_id AND product_id = :product_id
            ");

            if (
                $stmt->execute([
                    'variant_id' => $delete_id,
                    'product_id' => $product_id
                ])
            ) {
                logVariantAction(
                    $pdo,
                    $admin_user_id,
                    $admin_user,
                    $admin_role,
                    'Delete Variant',
                    "Deleted variant '$deleted_v_name' from product '{$product['product_name']}'",
                    $ip_address
                );

                $_SESSION['message'] = "Variant deleted successfully!";
            }
        }
    }

    header("Location: manageVariants.php?product_id=$product_id");
    exit();
}

$stmt = $pdo->prepare("
    SELECT *
    FROM product_variants
    WHERE product_id = :product_id
    ORDER BY id DESC
");
$stmt->execute(['product_id' => $product_id]);
$variants_res = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Product Variants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .description-scroll-box {
            max-height: 120px;
            overflow-y: auto;
            display: block;
            word-break: break-word;
            padding-right: 4px;
        }
        /* Custom thin scrollbar para sa description */
        .description-scroll-box::-webkit-scrollbar {
            width: 4px;
        }
        .description-scroll-box::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
    </style>
</head>

<body class="bg-light py-3 py-md-4">
    <div class="container-fluid px-2 px-md-4" style="max-width: 1300px;">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 bg-white p-3 rounded shadow-sm">
            <div>
                <h4 class="mb-0 fw-bold fs-5 fs-md-4">Variants for: <span
                        class="text-primary"><?php echo htmlspecialchars($product['product_name']); ?></span></h4>
                <small class="text-muted">Main Product Category: <?php echo htmlspecialchars($product['category']); ?></small>
            </div>
            <a href="products.php" class="btn btn-secondary btn-sm align-self-start align-self-md-auto"><i class="bi bi-arrow-left"></i> Back to Inventory</a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success py-2 alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-4 col-xl-3">
                <div class="card border-0 shadow-sm p-3">
                    <h5 class="fw-bold mb-3 fs-6"><i class="bi bi-plus-square"></i> Add New Product Variant</h5>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Variant Name</label>
                            <input type="text" name="variant_name" class="form-control form-control-sm"
                                placeholder="Product variant name" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Variant Description</label>
                            <textarea name="variant_description" class="form-control form-control-sm" rows="3"
                                placeholder="Product details"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Cost Price (₱)</label>
                            <input type="number" name="cost_price" min="0" step="0.01" required class="form-control form-control-sm">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Selling Price (₱)</label>
                            <input type="number" name="price" min="0" step="0.01" required class="form-control form-control-sm">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Initial Stock Amount</label>
                            <input type="number" name="stock" min="0" step="1" required class="form-control form-control-sm">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Variant Photo <small class="text-muted">(JPG, JPEG, PNG, WEBP)</small></label>
                            <input type="file" name="variant_image" class="form-control form-control-sm"
                                accept=".jpg, .jpeg, .png, .webp">
                        </div>
                        <button type="submit" name="add_variant" class="btn btn-primary btn-sm w-100 fw-bold">Save Variant</button>
                    </form>
                </div>
            </div>

            <div class="col-12 col-lg-8 col-xl-9">
                <div class="card border-0 shadow-sm p-3">
                    <h5 class="fw-bold mb-3 fs-6"><i class="bi bi-list-stars"></i> Current Variants Inventory Matrix</h5>
                    <div class="table-responsive">
                        <table class="table align-middle table-bordered small mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-center" style="width: 60px;">Photo</th>
                                    <th style="width: 150px;">Variant Name</th>
                                    <th style="min-width: 250px;">Description</th>
                                    <th style="width: 100px;">Cost Price</th>
                                    <th style="width: 100px;">Selling Price</th>
                                    <th style="width: 70px;">Stock</th>
                                    <th class="text-center" style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($variants_res)): ?>
                                    <?php foreach ($variants_res as $v): ?>
                                        <?php
                                        $v_image_val = !empty($v['variant_image']) ? trim($v['variant_image']) : trim($product['image'] ?? '');
                                        $img_src = '../assets/images/no-image.png';

                                        if (!empty($v_image_val)) {
                                            $file_id = '';

                                            if (stripos($v_image_val, 'data:image/') === 0) {
                                                $img_src = $v_image_val;
                                            } elseif (filter_var($v_image_val, FILTER_VALIDATE_URL)) {
                                                $parsed_url = parse_url($v_image_val);
                                                $host = strtolower($parsed_url['host'] ?? '');

                                                if ($host === 'drive.google.com' || $host === 'docs.google.com') {
                                                    parse_str($parsed_url['query'] ?? '', $query_params);

                                                    if (!empty($query_params['id'])) {
                                                        $file_id = $query_params['id'];
                                                    } elseif (preg_match('#/file/d/([A-Za-z0-9_-]+)#', $parsed_url['path'] ?? '', $matches)) {
                                                        $file_id = $matches[1];
                                                    }

                                                    if ($file_id !== '') {
                                                        $img_src = '../drive-image.php?id=' . urlencode($file_id);
                                                    } else {
                                                        $img_src = $v_image_val;
                                                    }
                                                } else {
                                                    $img_src = $v_image_val;
                                                }
                                            } elseif (preg_match('/^[A-Za-z0-9_-]{20,}$/', $v_image_val) && !str_contains($v_image_val, '/')) {
                                                $img_src = '../drive-image.php?id=' . urlencode($v_image_val);
                                            } else {
                                                $clean_path = ltrim($v_image_val, '/');
                                                if (str_starts_with($clean_path, '../')) {
                                                    $img_src = $clean_path;
                                                } else {
                                                    $img_src = '../' . $clean_path;
                                                }
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <img src="<?php echo htmlspecialchars($img_src, ENT_QUOTES, 'UTF-8'); ?>"
                                                    alt="<?php echo htmlspecialchars($v['variant_name'] ?? 'Variant Image', ENT_QUOTES, 'UTF-8'); ?>"
                                                    class="rounded"
                                                    style="width:40px; height:40px; object-fit:cover; border:1px solid #ddd;"
                                                    onerror="this.onerror=null; this.src='../assets/images/no-image.png';"
                                                    loading="lazy">
                                            </td>

                                            <td class="fw-bold text-dark">
                                                <?php echo htmlspecialchars($v['variant_name'] ?? ''); ?>
                                            </td>

                                            <td>
                                                <div class="description-scroll-box text-muted small">
                                                    <?php
                                                    echo !empty($v['variant_description'])
                                                        ? nl2br(htmlspecialchars($v['variant_description']))
                                                        : '<i class="text-danger">No description configured.</i>';
                                                    ?>
                                                </div>
                                            </td>

                                            <td class="text-secondary fw-bold">
                                                ₱<?php echo number_format((float) $v['cost_price'], 2); ?>
                                            </td>

                                            <td class="text-success fw-bold">
                                                ₱<?php echo number_format((float) $v['price'], 2); ?>
                                            </td>

                                            <td class="fw-bold">
                                                <?php echo (int) $v['stock']; ?>
                                            </td>

                                            <td class="text-center">
                                                <div class="btn-group gap-1">
                                                    <button type="button"
                                                        class="btn btn-warning btn-sm px-2 py-1 fw-bold text-white btn-edit-variant"
                                                        data-id="<?php echo (int) $v['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($v['variant_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-desc="<?php echo htmlspecialchars($v['variant_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-cost="<?php echo htmlspecialchars((string) $v['cost_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-price="<?php echo htmlspecialchars((string) $v['price'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-stock="<?php echo (int) $v['stock']; ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>

                                                    <a href="manageVariants.php?product_id=<?php echo (int) $product_id; ?>&delete_id=<?php echo (int) $v['id']; ?>"
                                                        class="btn btn-danger btn-sm px-2 py-1"
                                                        onclick="return confirm('Are you sure you want to delete this variant?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted fw-bold">
                                            No variants created yet for this item.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editVariantModal" tabindex="-1" aria-labelledby="editVariantModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title fw-bold fs-6" id="editVariantModalLabel">Edit Variant Configuration</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="edit_variant_id" id="edit_variant_id">

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Variant Name</label>
                            <input type="text" name="edit_variant_name" id="edit_variant_name" class="form-control"
                                required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Variant Description</label>
                            <textarea name="edit_variant_description" id="edit_variant_description" class="form-control"
                                rows="3"></textarea>
                        </div>

                        <div class="row mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Cost Price (₱)</label>
                                <input type="number" name="edit_cost_price" id="edit_cost_price" step="0.01"
                                    class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Selling Price (₱)</label>
                                <input type="number" name="edit_price" id="edit_price" step="0.01" class="form-control"
                                    required>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Current Available Stock</label>
                            <input type="number" name="edit_stock" id="edit_stock" class="form-control" required>
                        </div>

                        <div class="mb-1">
                            <label class="form-label small fw-bold">Change Variant Image <small class="text-muted">(JPG, JPEG, PNG, WEBP)</small></label>
                            <input type="file" name="edit_variant_image" class="form-control" accept=".jpg, .jpeg, .png, .webp">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_variant"
                            class="btn btn-warning btn-sm text-white fw-bold">Update Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.btn-edit-variant').forEach(button => {
            button.addEventListener('click', function () {
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