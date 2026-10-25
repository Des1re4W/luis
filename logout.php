<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown';
    $role = $_SESSION['role'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $action = "Logout";
    $details = "User $username logged out successfully.";

    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, username, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $username, $role, $action, $details, $ip_address]);
    } catch (PDOException $e) {
         error_log("Logout audit log error: " . $e->getMessage());
    }
}

session_destroy();
header("Location: login.php");
exit();
?>