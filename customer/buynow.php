<?php
session_start();
include "../config/db.php";

// Siguraduhing may ID na ipinasok sa URL (e.g., buynow.php?id=5)
if (isset($_GET['id'])) {
    $product_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Kunin ang quantity, default ay 1 kung walang pinasa
    $qty = (isset($_GET['qty']) && (int)$_GET['qty'] > 0) ? (int)$_GET['qty'] : 1;
    
    // Alamin kung may pinasang variant_id sa URL
    $variant_id = isset($_GET['variant_id']) ? mysqli_real_escape_string($conn, $_GET['variant_id']) : '';

    // Kunin muna ang detalye ng main product (gaya ng pangalan at imahe)
    $p_res = mysqli_query($conn, "SELECT id, product_name, price, image FROM products WHERE id = '$product_id'");
    $p_data = mysqli_fetch_assoc($p_res);

    if (!$p_data) {
        header("Location: dashboard.php");
        exit();
    }

    // 🔥 KUNG VARIANT ANG BINIBILI
    if (!empty($variant_id) && $variant_id !== 'MAIN_PRODUCT') {
        
        // Kunin ang presyo at pangalan ng variant mula sa product_variants table
        $v_res = mysqli_query($conn, "SELECT variant_name, price, variant_image FROM product_variants WHERE id = '$variant_id' AND product_id = '$product_id'");
        $v_data = mysqli_fetch_assoc($v_res);

        if ($v_data) {
    // DITO ANG PAGBABAGO: Gamitin ang variant_image kung meron, 
    // kung wala, fallback sa main product image
    $final_image = !empty($v_data['variant_image']) ? $v_data['variant_image'] : $p_data['image'];

    $_SESSION['buy_now'] = [
        [
            'id'           => $product_id,
            'name'         => $v_data['variant_name'],
            'variant_id'   => $variant_id,
            'variant_name' => $v_data['variant_name'],
            'price'        => (float)$v_data['price'],
            'qty'          => $qty,
            'image'        => $final_image // Ginamit na ang tamang image variable
        ]
    ];
}else {
            header("Location: dashboard.php");
            exit();
        }

    } 
    // 🔥 KUNG MAIN PRODUCT/REGULAR ITEM ANG BINIBILI (Walang Variant)
    else {
        $_SESSION['buy_now'] = [
            [
                'id'           => $p_data['id'],
                'name'         => $p_data['product_name'],
                'variant_id'   => null,
                'variant_name' => null,
                'price'        => (float)$p_data['price'], 
                'qty'          => $qty,
                'image'        => $p_data['image']
            ]
        ];
    }

    // Pagkatapos maihanda ang Session, idiretso na agad sa Checkout Page
    header("Location: placeorder.php?from=buynow");
    exit();

} else {
    header("Location: dashboard.php");
    exit();
}
?>