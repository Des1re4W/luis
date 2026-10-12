<?php
session_start();
include "../config/db.php";

$cart = $_SESSION['cart'] ?? [];
$total = 0;

// Kalkulahin ang total nang ligtas
foreach ($cart as $item) {
    $price = $item['price'] ?? 0;
    $qty = $item['qty'] ?? 0;
    $total += (float)$price * (int)$qty;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Shopping Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .cart-img { width: 50px; height: 50px; object-fit: cover; }
        @media (max-width: 576px) {
            .cart-img { width: 40px; height: 40px; }
            .table font-size-sm { font-size: 0.85rem; }
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-3 py-md-5">
    <div class="card border-0 shadow-sm p-3 p-md-4">
        <h3 class="mb-4 text-dark fs-4 fs-md-3"><i class="bi bi-cart4 text-primary"></i> Shopping Cart</h3>
        
        <?php if (!empty($cart)): ?>
            <div class="table-responsive">
                <table class="table align-middle text-nowrap text-md-wrap">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th style="min-width: 140px;">Quantity</th>
                            <th>Subtotal</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php 
    foreach ($cart as $key => $item): 
        // Kunin ang max stock para sa partikular na item na ito[cite: 5]
        if (!empty($item['variant_id'])) {
            $stock_res = mysqli_query($conn, "SELECT stock FROM product_variants WHERE id = '{$item['variant_id']}'");
        } else {
            $stock_res = mysqli_query($conn, "SELECT stock FROM products WHERE id = '{$item['id']}'");
        }
        $stock_data = mysqli_fetch_assoc($stock_res);
        $max_stock = $stock_data['stock'] ?? 0;
    ?>
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <img src="../<?php echo $item['image'] ?? 'images/default.jpg'; ?>" 
                         class="rounded me-2 me-md-3 cart-img">
                    <span class="fw-bold text-wrap" style="max-width: 200px; font-size: 0.9rem;"><?php echo $item['name'] ?? 'Unknown'; ?></span>
                </div>
            </td>
            
            <td>₱<?php echo number_format($item['price'] ?? 0, 2); ?></td>
            
            <td>
                <form action="update_qty.php" method="POST" class="d-flex align-items-center">
                    <input type="hidden" name="key" value="<?php echo $key; ?>">
                    
                    <a href="updateCart.php?id=<?php echo $key; ?>&action=minus" class="btn btn-sm btn-outline-secondary px-2">-</a>
                    
                    <input type="number" name="qty" value="<?php echo $item['qty']; ?>" 
                           class="form-control form-control-sm mx-1 text-center" 
                           style="width: 55px;" 
                           min="1" max="<?php echo $max_stock; ?>" 
                           onchange="if(parseInt(this.value) > <?php echo $max_stock; ?>) { alert('Exceeds available stock! Max: <?php echo $max_stock; ?>'); this.value = <?php echo $max_stock; ?>; } this.form.submit()">
                    
                    <a href="updateCart.php?id=<?php echo $key; ?>&action=plus" class="btn btn-sm btn-outline-secondary px-2">+</a>
                </form>
            </td>
            
            <td class="fw-bold text-primary">₱<?php echo number_format(((float)($item['price'] ?? 0) * (int)($item['qty'] ?? 0)), 2); ?></td>
            
            <td class="text-center">
                <a href="removeCart.php?id=<?php echo $key; ?>" class="btn btn-sm btn-danger"><i class="bi bi-x"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                </table>
            </div>

            <div class="row justify-content-end mt-4">
                <div class="col-12 col-md-6 col-lg-4 text-end">
                    <h4 class="fs-5 fs-md-4">Order Total: <span class="text-primary">₱<?php echo number_format($total, 2); ?></span></h4>
                    <hr>
                    
                    <?php if ($total < 3000): ?>
                        <div class="alert alert-warning py-2 small text-start" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i> Minimum order requirement of ₱3,000.00 not met.
                        </div>
                    <?php endif; ?>

                    <a href="placeorder.php" class="btn btn-primary btn-lg w-100 mt-2 <?php echo ($total < 3000) ? 'disabled' : ''; ?>" <?php echo ($total < 3000) ? 'aria-disabled="true" style="pointer-events: none;"' : ''; ?>>
                        Proceed to Checkout
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Back to Shopping</a>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-cart-x text-muted" style="font-size: 3.5rem;"></i>
                <p class="fs-5 text-muted mt-3">Your cart is currently empty.</p>
                <a href="dashboard.php" class="btn btn-primary mt-2">Back to Shopping</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>