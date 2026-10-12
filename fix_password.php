<?php
require_once __DIR__ . '/config/db.php';

$hash = password_hash("renz123", PASSWORD_DEFAULT);
mysqli_query($conn, "UPDATE users SET password='$hash' WHERE username='renz'");

echo "Password updated successfully!";
//http://localhost/hardwareSystem/fix_password.php
?>