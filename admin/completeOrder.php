<?php
session_start();
include "../config/db.php";

if (isset($_POST['complete_order'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);

    // Siguraduhin na 'pending' pa ang order para hindi mag-double deduct kung ma-click ulit
    $check = mysqli_query($conn, "SELECT status FROM orders WHERE id = '$order_id'");
    $order = mysqli_fetch_assoc($check);

    if ($order['status'] == 'pending') {
        mysqli_begin_transaction($conn);
        try {
            //Gawing 'completed' ang status
            mysqli_query($conn, "UPDATE orders SET status = 'completed' WHERE id = '$order_id'");

            //Kunin ang lahat ng items sa order na iyon
            $items = mysqli_query($conn, "SELECT product_id, qty FROM order_items WHERE order_id = '$order_id'");

            // DITO LANG MAGBABAWAS NG STOCK SA PRODUCTS TABLE
            while ($item = mysqli_fetch_assoc($items)) {
                $p_id = $item['product_id'];
                $qty = $item['qty'];
                mysqli_query($conn, "UPDATE products SET stock = stock - $qty WHERE id = '$p_id'");
            }

            mysqli_commit($conn);
            header("Location: orders.php?success=Order completed and stock updated");
        } catch (Exception $e) {
            mysqli_rollback($conn);
            header("Location: orders.php?error=Failed to update");
        }
    } else {
        header("Location: orders.php?error=Order already processed");
    }
}