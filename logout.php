<?php
session_start();
include "config/db.php"; // Siguraduhin na tama ang path ng db.php

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown';
    $role = $_SESSION['role'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $action = "Logout";
    $details = "User $username logged out successfully.";

    // Mag-insert sa audit_log table
    $stmt = $conn->prepare("INSERT INTO audit_log (username, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $role, $action, $details, $ip_address);
    $stmt->execute();
}

session_destroy();
header("Location: login.php");
exit();
?>