<?php
session_start();
require_once __DIR__ . '/config/db.php';

$error = "";

// Audit trail logging using PDO
function logAction($conn, $user_id, $username, $role, $action, $details) {
    try {
        $sql = "INSERT INTO audit_log
                (user_id, username, role, action, details, ip_address)
                VALUES (:user_id, :username, :role, :action, :details, :ip)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':username' => $username,
            ':role' => $role,
            ':action' => $action,
            ':details' => $details,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $sql = "SELECT * FROM users
                WHERE username = :username
                AND status = 'Active'
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            logAction(
                $conn,
                $user['id'],
                $user['username'],
                $user['role'],
                'Login',
                'User successfully logged into the system'
            );

            if ($user['role'] === 'owner') {
                header('Location: owner/dashboard.php');
                exit();
            } elseif ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
                exit();
            } elseif ($user['role'] === 'cashier') {
                header('Location: cashier/dashboard.php');
                exit();
            } else {
                header('Location: dashboard.php');
                exit();
            }
        } else {
            $error = "Invalid username or password";
        }
    } catch (PDOException $e) {
        error_log('Login database error: ' . $e->getMessage());
        $error = "Unable to process login. Please try again later.";
    }
}
?>
<? echo $error;