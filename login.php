<?php
session_start();
require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Manila');

$error = "";

// Audit trail logging using PDO
function logAction($conn, $user_id, $username, $role, $action, $details)
{
    try {
        $sql = "INSERT INTO audit_log
                (user_id, username, role, action, details, ip_address)
                VALUES (:user_id, :username, :role, :action, :details, :ip)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => (int) $user_id,
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

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $sql = "SELECT * FROM users
                    WHERE username = :username
                    AND status = 'Active'
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // 1. Check kung naka-lock ang account dahil sa maraming failed attempts
                $currentTime = date('Y-m-d H:i:s');
                if (!empty($user['lockout_until']) && $user['lockout_until'] > $currentTime) {
                    $remainingMinutes = ceil((strtotime($user['lockout_until']) - time()) / 60);
                    $error = "Account is temporarily locked due to multiple failed login attempts. Please try again in about {$remainingMinutes} minute(s).";

                    // I-log sa audit trail ang pagtatangkang mag-login habang naka-lock
                    logAction(
                        $pdo,
                        $user['id'],
                        $user['username'],
                        $user['role'],
                        'Login Failed (Locked)',
                        'Attempted login on a temporarily locked account'
                    );
                } else {
                    // 2. I-verify ang password
                    if (password_verify($password, $user['password'])) {
                        // Reset failed attempts at lockout kapag nagtagumpay ang login
                        $resetStmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = :id");
                        $resetStmt->execute([':id' => $user['id']]);

                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];

                        logAction(
                            $pdo,
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
                        // 3. Kapag mali ang password, dagdagan ang failed_attempts
                        $newFailedAttempts = (int)$user['failed_attempts'] + 1;
                        
                        if ($newFailedAttempts >= 5) {
                            // I-lock ng 5 minuto kapag umabot sa 5 beses[cite: 3]
                            $lockoutTime = date('Y-m-d H:i:s', time() + 300); // 300 seconds = 5 minutes
                            $lockStmt = $pdo->prepare("UPDATE users SET failed_attempts = :attempts, lockout_until = :lockout WHERE id = :id");
                            $lockStmt->execute([
                                ':attempts' => $newFailedAttempts,
                                ':lockout' => $lockoutTime,
                                ':id' => $user['id']
                            ]);
                            
                            $error = "Account temporarily locked due to 5 repeated failed login attempts. Please try again after 5 minutes.";

                            // I-log sa audit trail na na-lock ang account
                            logAction(
                                $pdo,
                                $user['id'],
                                $user['username'],
                                $user['role'],
                                'Account Locked',
                                'Account temporarily locked after 5 failed login attempts'
                            );
                        } else {
                            // I-update lang ang bilang ng failed attempts
                            $updateStmt = $pdo->prepare("UPDATE users SET failed_attempts = :attempts WHERE id = :id");
                            $updateStmt->execute([
                                ':attempts' => $newFailedAttempts,
                                ':id' => $user['id']
                            ]);

                            $attemptsLeft = 5 - $newFailedAttempts;
                            $error = "Invalid username or password. You have {$attemptsLeft} attempt(s) left before temporary lockout.";

                            // I-log sa audit trail ang bawat failed attempt
                            logAction(
                                $pdo,
                                $user['id'],
                                $user['username'],
                                $user['role'],
                                'Login Failed',
                                "Invalid password attempt ({$newFailedAttempts}/5)"
                            );
                        }
                    }
                }
            } else {
                $error = "Invalid username or password";
                
                // I-log ang attempt gamit ang username na in-input kahit walang user ID
                logAction(
                    $pdo,
                    0,
                    $username,
                    'Guest/Unknown',
                    'Login Failed',
                    'Attempted login with non-existent username'
                );
            }
        } catch (PDOException $e) {
            error_log('Login database error: ' . $e->getMessage());
            $error = "Unable to process login. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Login System | R. Taipan Hardware</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-image: url('assets/R.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .card {
            background-color: rgba(199, 209, 229, 0.95) !important;
            border-radius: 20px;
            border: none;
            width: 100%;
            max-width: 450px;
            padding: 40px !important;
            margin: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
        }

        .form-control {
            padding: 12px 15px;
            font-size: 16px;
        }

        .input-group .btn-outline-secondary {
            background-color: #fff;
            border-color: #ced4da;
            color: #6c757d;
        }

        .input-group .btn-outline-secondary:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
            color: #495057;
        }

        .btn {
            padding: 12px;
            font-size: 18px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card shadow p-4">
            <div class="text-center mb-3">
                <img src="assets/logo.jpg"
                    style="width:70px; height:70px; border-radius:50%; object-fit:cover; border:2px solid #1f1b38;" alt="Logo">
                <h4 class="mt-3 mb-1">R. Taipan Hardware</h4>
                <small class="text-muted">Please login to continue</small>
            </div>

            <h3 class="text-center mb-3">Login</h3>

            <?php if ($error != "") { ?>
                <div class="alert alert-danger text-center">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php } ?>

            <form method="POST" action="login.php">
                <div class="mb-2">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="mb-3">
                    <div class="input-group">
                        <input type="password" name="password" id="passwordField" class="form-control"
                            placeholder="Password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>
                <button class="btn w-100 text-white" name="login" style="background:#051c3c;">
                    Login
                </button>
                <div class="forgot-password mt-2">
                    <a href="forgotPassword.php">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const passwordField = document.getElementById('passwordField');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');

        if (togglePasswordBtn && passwordField && togglePasswordIcon) {
            togglePasswordBtn.addEventListener('click', function () {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);

                togglePasswordIcon.classList.toggle('bi-eye');
                togglePasswordIcon.classList.toggle('bi-eye-slash');
            });
        }
    </script>
</body>

</html>