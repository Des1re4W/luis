<?php
require_once __DIR__ . '/../config/db.php';

// I-check kung defined o konektado ang $pdo mula sa db.php
if (!isset($pdo)) {
    echo '
    <div class="col-12 text-center py-5">
        <div class="alert alert-danger shadow-sm">
            May problema sa koneksyon ng database.
        </div>
    </div>';
    exit;
}

// Kunin ang search input, kung blangko (clear/X), magiging empty string ito para lumabas lahat
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

try {
    $sql = "SELECT p.*, COALESCE(SUM(oi.qty), 0) AS total_sold 
            FROM products p 
            LEFT JOIN order_items oi ON p.id = oi.product_id 
            WHERE p.product_name ILIKE :search 
            AND p.is_archived = 0 
            GROUP BY p.id 
            ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['search' => '%' . $search . '%']);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($products) > 0) {
        foreach ($products as $row) {

            // Dynamic Image Resolver (Kaparehas ng sa Dashboard)
            $p_image = $row['image'] ?? '';
            $final_image_src = '../assets/images/no-image.png';

            if (!empty($p_image)) {
                $image_value = trim($p_image);

                if (stripos($image_value, 'data:image/') === 0) {
                    $final_image_src = $image_value;
                } elseif (filter_var($image_value, FILTER_VALIDATE_URL)) {
                    $parsed_url = parse_url($image_value);
                    $host = strtolower($parsed_url['host'] ?? '');

                    if ($host === 'drive.google.com' || $host === 'docs.google.com') {
                        parse_str($parsed_url['query'] ?? '', $query_params);
                        $file_id = '';

                        if (!empty($query_params['id'])) {
                            $file_id = $query_params['id'];
                        } elseif (preg_match('#/file/d/([A-Za-z0-9_-]+)#', $parsed_url['path'] ?? '', $matches)) {
                            $file_id = $matches[1];
                        }

                        if ($file_id !== '') {
                            $final_image_src = '../drive-image.php?id=' . urlencode($file_id);
                        }
                    } else {
                        $final_image_src = $image_value;
                    }
                } elseif (preg_match('/^[A-Za-z0-9_-]{20,}$/', $image_value) && !str_contains($image_value, '/')) {
                    $final_image_src = '../drive-image.php?id=' . urlencode($image_value);
                } else {
                    $clean_path = ltrim($image_value, '/');
                    if (str_starts_with($clean_path, '../')) {
                        $final_image_src = $clean_path;
                    } else {
                        $final_image_src = '../' . $clean_path;
                    }
                }
            }

            echo '
            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                <div class="card product-card shadow-sm h-100">
                    
                    <img src="' . htmlspecialchars($final_image_src, ENT_QUOTES, 'UTF-8') . '" 
                         class="card-img-top product-img" 
                         alt="' . htmlspecialchars($row['product_name']) . '" 
                         style="height: 200px; object-fit: cover;"
                         onerror="this.onerror=null; this.src=\'../assets/images/no-image.png\';" 
                         loading="lazy">

                    <div class="card-body d-flex flex-column text-center">
                        
                        <h6 class="card-title fw-bold text-dark mb-1">' . htmlspecialchars($row['product_name']) . '</h6>
                        
                        <p class="text-success fw-bold fs-6 mb-1">
                            ₱' . number_format($row['price'], 2) . '
                        </p>

                        <div class="d-flex justify-content-between small mb-3">
                            <span class="text-muted italic">
                                ' . $row['total_sold'] . ' sold
                            </span>
                        </div>

                        <div class="mt-auto">';

            // OUT OF STOCK
            if ($row['stock'] <= 0) {
                echo '
                    <button class="btn btn-secondary w-100 py-2 fw-bold disabled">
                        Out of Stock
                    </button>';
            } 
            // AVAILABLE STOCK
            else {
                echo '
                    <button type="button" class="btn btn-outline-primary w-100 py-2 fw-bold" onclick="openProductModal(' . $row['id'] . ')">
                        <i class="bi bi-eye"></i> View Product
                    </button>';
            }

            echo '
                        </div>

                    </div>
                </div>
            </div>';
        }
    } else {
        echo '
        <div class="col-12 text-center py-5">
            <div class="alert alert-light border shadow-sm">
                No products found!
            </div>
        </div>';
    }
} catch (PDOException $e) {
    echo '
    <div class="col-12 text-center py-5">
        <div class="alert alert-danger shadow-sm">
            Query Error: ' . htmlspecialchars($e->getMessage()) . '
        </div>
    </div>';
}
?>