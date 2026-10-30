<?php
require_once __DIR__ . '/../config/db.php';

// I-check kung defined o konektado ang $pdo mula sa db.php[cite: 2]
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
    // Idinagdag ang AND status = 'active' (o baguhin depende sa database column mo para sa archive)
    $sql = "SELECT p.*, COALESCE(SUM(oi.qty), 0) AS total_sold 
            FROM products p 
            LEFT JOIN order_items oi ON p.id = oi.product_id 
            WHERE p.product_name ILIKE :search 
            AND p.status = 'active' 
            GROUP BY p.id 
            ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['search' => '%' . $search . '%']);
    $products = $stmt->fetchAll();

    if (count($products) > 0) {
        foreach ($products as $row) {

            $image_path = !empty($row['image']) ? $row['image'] : 'assets/no-image.jpg';
            
            if (strpos($image_path, 'uploads/') === 0) {
                $final_image_src = '../' . $image_path;
            } else {
                $final_image_src = '../uploads/' . $image_path;
            }

            echo '
            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                <div class="card product-card shadow-sm h-100">
                    
                    <img src="' . htmlspecialchars($final_image_src) . '" class="card-img-top product-img" alt="Product Image" style="height: 200px; object-fit: cover;">

                    <div class="card-body d-flex flex-column text-center">
                        
                        <h6 class="card-title">' . htmlspecialchars($row['product_name']) . '</h6>
                        
                        <p class="text-success fw-bold fs-5 mb-1">
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