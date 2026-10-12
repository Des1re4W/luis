<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {

    $id = $_GET['id'];

    //kunin muna image para pwede rin burahin file
    $get = mysqli_query($conn, "SELECT * FROM products WHERE id='$id'");
    $row = mysqli_fetch_assoc($get);

    if ($row) {

        // delete image file if exists
        $imgPath = "../" . $row['image'];
        if (file_exists($imgPath)) {
            unlink($imgPath);
        }

        // delete product in database
        mysqli_query($conn, "DELETE FROM products WHERE id='$id'");

        $_SESSION['message'] = "Product deleted successfully!";
    }
}

header("Location: products.php");
exit();
?>