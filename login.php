<?php
session_start();
require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Manila');

$error = '';
$showStaffLogin = isset($_GET['staff']) && $_GET['staff'] === '1';

/**
 * Redirect users to the correct dashboard based on their role.
 */
function redirectByRole(string $role): void
{
    switch ($role) {
        case 'owner':
            header('Location: owner/dashboard.php');
            exit;

        case 'admin':
            header('Location: admin/dashboard.php');
            exit;

        case 'cashier':
            header('Location: cashier/dashboard.php');
            exit;

        case 'customer':
            header('Location: customer/dashboard.php');
            exit;

        default:
            // Unknown roles must not be allowed into a dashboard.
            session_unset();
            session_destroy();
            header('Location: login.php?staff=1');
            exit;
    }
}

/**
 * Audit trail logging. A failed audit insert must not interrupt login.
 */
function logAction($conn, int $userId, string $username, string $role, string $action, string $details): void
{
    try {
        $sql = "INSERT INTO audit_log
                (user_id, username, role, action, details, ip_address)
                VALUES (:user_id, :username, :role, :action, :details, :ip)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':role' => $role,
            ':action' => $action,
            ':details' => $details,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Throwable $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

/*
 * If a user is already authenticated, send them to their own dashboard.
 */
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirectByRole((string) $_SESSION['role']);
}

/*
 * Staff/customer username-password login.
 * The form uses a hidden "login" field, preserving the POST behavior
 * of the original login.php.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $showStaffLogin = true;

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $sql = "SELECT *
                    FROM users
                    WHERE username = :username
                      AND status = 'Active'
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                logAction(
                    $pdo,
                    0,
                    $username,
                    'Guest/Unknown',
                    'Login Failed',
                    'Attempted login with non-existent username or inactive account'
                );

                $error = 'Invalid username or password.';
            } else {
                $failedAttempts = (int) ($user['failed_attempts'] ?? 0);
                $currentTime = date('Y-m-d H:i:s');
                $lockoutUntil = $user['lockout_until'] ?? null;

                if (!empty($lockoutUntil) && $lockoutUntil > $currentTime) {
                    $remainingMinutes = max(
                        1,
                        (int) ceil((strtotime($lockoutUntil) - time()) / 60)
                    );

                    $error = "Account is temporarily locked. Please try again in about {$remainingMinutes} minute(s).";

                    logAction(
                        $pdo,
                        (int) $user['id'],
                        (string) $user['username'],
                        (string) $user['role'],
                        'Login Failed (Locked)',
                        'Attempted login on a temporarily locked account'
                    );
                } elseif (password_verify($password, (string) $user['password'])) {
                    // Reset the lockout counter after a successful login.
                    $resetStmt = $pdo->prepare(
                        "UPDATE users
                         SET failed_attempts = 0, lockout_until = NULL
                         WHERE id = :id"
                    );
                    $resetStmt->execute([':id' => $user['id']]);

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['username'] = (string) $user['username'];
                    $_SESSION['role'] = (string) $user['role'];

                    logAction(
                        $pdo,
                        (int) $user['id'],
                        (string) $user['username'],
                        (string) $user['role'],
                        'Login',
                        'User successfully logged into the system'
                    );

                    redirectByRole((string) $user['role']);
                } else {
                    $newFailedAttempts = $failedAttempts + 1;

                    if ($newFailedAttempts >= 5) {
                        $lockoutTime = date('Y-m-d H:i:s', time() + 300);

                        $lockStmt = $pdo->prepare(
                            "UPDATE users
                             SET failed_attempts = :attempts,
                                 lockout_until = :lockout
                             WHERE id = :id"
                        );
                        $lockStmt->execute([
                            ':attempts' => $newFailedAttempts,
                            ':lockout' => $lockoutTime,
                            ':id' => $user['id']
                        ]);

                        $error = 'Account temporarily locked after 5 failed login attempts. Please try again after 5 minutes.';

                        logAction(
                            $pdo,
                            (int) $user['id'],
                            (string) $user['username'],
                            (string) $user['role'],
                            'Account Locked',
                            'Account temporarily locked after 5 failed login attempts'
                        );
                    } else {
                        $updateStmt = $pdo->prepare(
                            "UPDATE users
                             SET failed_attempts = :attempts
                             WHERE id = :id"
                        );
                        $updateStmt->execute([
                            ':attempts' => $newFailedAttempts,
                            ':id' => $user['id']
                        ]);

                        $attemptsLeft = 5 - $newFailedAttempts;
                        $error = "Invalid username or password. You have {$attemptsLeft} attempt(s) left before temporary lockout.";

                        logAction(
                            $pdo,
                            (int) $user['id'],
                            (string) $user['username'],
                            (string) $user['role'],
                            'Login Failed',
                            "Invalid password attempt ({$newFailedAttempts}/5)"
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Login processing error: ' . $e->getMessage());
            $error = 'Unable to process login right now. Please try again later.';
        }
    }
}

/*
 * Main public entry point:
 * - Normal visit to /login.php opens the customer storefront.
 * - /login.php?staff=1 opens the staff login page.
 *
 * The actual storefront file stays in customer/dashboard.php so its
 * existing ../assets, ../config, and customer AJAX paths remain valid.
 */
if (!$showStaffLogin && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: customer/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login | R. Taipan Hardware</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(rgba(5, 28, 60, .72), rgba(5, 28, 60, .72)),
                        url('assets/R.png') center / cover fixed no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 36px;
            border: 0;
            border-radius: 20px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 12px 35px rgba(0, 0, 0, .25);
        }
        .brand-logo {
            width: 76px;
            height: 76px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #051c3c;
        }
        .form-control { padding: 12px 14px; }
        .btn-login {
            background: #051c3c;
            color: #fff;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover { background: #0b3267; color: #fff; }
    </style>
</head>
<body>
    <main class="login-card">
        <div class="text-center mb-4">
            <img src="assets/logo.jpg" class="brand-logo" alt="R. Taipan Hardware logo">
            <h4 class="mt-3 mb-1">R. Taipan Hardware</h4>
            <p class="text-muted mb-0">Authorized Staff Login</p>
        </div>

        <h3 class="text-center mb-3">Sign In</h3>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php?staff=1" autocomplete="on">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input
                    type="text"
                    name="username"
                    id="username"
                    class="form-control"
                    placeholder="Enter your username"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="passwordField" class="form-label">Password</label>
                <div class="input-group">
                    <input
                        type="password"
                        name="password"
                        id="passwordField"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn" aria-label="Show password">
                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" value="1" class="btn btn-login w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>

            <div class="text-center mt-3">
                <a href="forgotPassword.php">Forgot Password?</a>
            </div>
        </form>

        <hr class="my-4">

        <div class="text-center">
            <a href="customer/dashboard.php" class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> Back to Store
            </a>
        </div>
    </main>

    <script>
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const passwordField = document.getElementById('passwordField');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');

        togglePasswordBtn?.addEventListener('click', function () {
            const showPassword = passwordField.type === 'password';
            passwordField.type = showPassword ? 'text' : 'password';
            togglePasswordIcon.classList.toggle('bi-eye', !showPassword);
            togglePasswordIcon.classList.toggle('bi-eye-slash', showPassword);
            togglePasswordBtn.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
        });
    </script>
</body>
</html>
