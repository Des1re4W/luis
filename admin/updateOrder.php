<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// 1. Check login
if (!isset($_SESSION['user_id'])) {
    die("Error: Admin is not logged in.");
}

if (isset($_GET['id']) && isset($_GET['status'])) {

    $id = (int) $_GET['id'];
    $new_status = mysqli_real_escape_string($conn, $_GET['status']);
    $admin_id = (int) $_SESSION['user_id'];

    // 2. Get order
    $res = mysqli_query($conn, "SELECT status, cart FROM orders WHERE id = $id");
    $order = mysqli_fetch_assoc($res);

    if (!$order) {
        die("Order not found");
    }

    $old_status = strtolower($order['status']);

    $items = json_decode($order['cart'], true);

    // =========================
    // STOCK ONLY ON COMPLETED
    // =========================
    if ($new_status == 'completed' && $old_status != 'completed') {

        if (is_array($items)) {
            foreach ($items as $item) {
                $qty = (int)$item['qty'];
                $p_id = (int)$item['id'];

                mysqli_query($conn, "
                    UPDATE products 
                    SET stock = stock - $qty 
                    WHERE id = $p_id
                ");
            }
        }
    }

    // OPTIONAL: if you want refund stock when cancelling AFTER completed
    if ($new_status == 'declined' && $old_status == 'completed') {

        if (is_array($items)) {
            foreach ($items as $item) {
                $qty = (int)$item['qty'];
                $p_id = (int)$item['id'];

                mysqli_query($conn, "
                    UPDATE products 
                    SET stock = stock + $qty 
                    WHERE id = $p_id
                ");
            }
        }
    }

    // =========================
    // UPDATE ORDER STATUS
    // =========================
    if ($new_status == 'approved') {

        $update_query = "
            UPDATE orders 
            SET status = '$new_status',
                approved_by = $admin_id 
            WHERE id = $id
        ";

    } elseif ($new_status == 'declined') {

        $update_query = "
            UPDATE orders 
            SET status = '$new_status',
                declined_by = $admin_id 
            WHERE id = $id
        ";

    } else {

        $update_query = "
            UPDATE orders 
            SET status = '$new_status' 
            WHERE id = $id
        ";
    }

    if (mysqli_query($conn, $update_query)) {
        header("Location: orders.php?success=1");
        exit();
    } else {
        echo "Error updating record: " . mysqli_error($conn);
    }

} else {
    echo "Invalid request parameters.";
}
?>