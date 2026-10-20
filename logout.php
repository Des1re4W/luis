```php
<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    $username = $_SESSION['username'] ?? 'Unknown';
    $role = $_SESSION['role'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $action = 'Logout';
    $details = "User $username logged out successfully.";

    try {
        // Insert logout activity into audit_log using PDO
        $stmt = $conn->prepare("
            INSERT INTO audit_log
                (username, role, action, details, ip_address)
            VALUES
                (:username, :role, :action, :details, :ip_address)
        ");

        $stmt->execute([
            ':username' => $username,
            ':role' => $role,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => $ip_address
        ]);
    } catch (PDOException $e) {
        // Log the error without preventing logout
        error_log("Logout audit log error: " . $e->getMessage());
    }
}

// Destroy the session even if audit logging fails
$_SESSION = [];
session_destroy();

header("Location: login.php");
exit();
?>
```