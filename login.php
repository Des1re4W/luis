<?php
session_start();
require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Manila');

$error = '';
$success = '';

$mode = $_GET['mode'] ?? 'login';
if (!in_array($mode, ['login', 'register'], true)) {
    $mode = 'login';
}

$redirectAfter = trim((string) ($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
// Allow only relative local paths to prevent open redirects
if ($redirectAfter !== '' && (!str_starts_with($redirectAfter, '/') && !str_starts_with($redirectAfter, 'customer/'))) {
    $redirectAfter = '';
}

/**
 * Redirect users to the correct dashboard based on their role.
 */
function redirectByRole(string $role, string $safeRedirect = ''): void
{
    if ($role === 'customer' && $safeRedirect !== '') {
        header('Location: ' . $safeRedirect);
        exit;
    }

    switch ($role) {
        case 'owner':
        case 'super_admin':
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
            session_unset();
            session_destroy();
            header('Location: login.php?mode=login&err=role');
            exit;
    }
}

/**
 * Audit trail logging. A failed audit insert must not interrupt auth flow.
 */
function logAction($conn, int $userId, string $username, string $role, string $action, string $details): void
{
    try {
        $sql = "INSERT INTO audit_log
                (user_id, username, role, action, details, ip_address)
                VALUES (:user_id, :username, :role, :action, :details, :ip)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id'  => $userId,
            ':username' => $username,
            ':role'     => $role,
            ':action'   => $action,
            ':details'  => $details,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Throwable $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

/*
 * Already authenticated -> route by role.
 */
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirectByRole((string) $_SESSION['role'], $redirectAfter);
}

/*
 * Handle Customer Registration (always assigns 'customer' role).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $mode = 'register';

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    if ($fullName === '' || $username === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all registration fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!hash_equals($password, $confirm)) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $checkStmt = $pdo->prepare(
                "SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1"
            );
            $checkStmt->execute([
                ':username' => $username,
                ':email'    => $email
            ]);

            if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $error = 'Username or email is already registered.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Force role to 'customer' for self-registration.
                $insertStmt = $pdo->prepare(
                    "INSERT INTO users (full_name, username, email, password, role, status)
                     VALUES (:full_name, :username, :email, :password, 'customer', 'Active')
                     RETURNING id"
                );
                $insertStmt->execute([
                    ':full_name' => $fullName,
                    ':username'  => $username,
                    ':email'     => $email,
                    ':password'  => $hashedPassword
                ]);

                $newUserId = (int) $insertStmt->fetchColumn();

                session_regenerate_id(true);
                $_SESSION['user_id']   = $newUserId;
                $_SESSION['username']  = $username;
                $_SESSION['full_name'] = $fullName;
                $_SESSION['role']      = 'customer';

                logAction(
                    $pdo,
                    $newUserId,
                    $username,
                    'customer',
                    'Register',
                    'New customer account created'
                );

                redirectByRole('customer', $redirectAfter);
            }
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            $error = 'Unable to create account right now. Please try again later.';
        }
    }
}

/*
 * Handle Existing Account Login (staff & customers).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $mode = 'login';

    $loginInput = trim((string) ($_POST['username'] ?? ''));
    $password   = (string) ($_POST['password'] ?? '');

    if ($loginInput === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $sql = "SELECT *
                    FROM users
                    WHERE (username = :login_user OR email = :login_email)
                      AND status = 'Active'
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':login_user'  => $loginInput,
                ':login_email' => $loginInput
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                logAction(
                    $pdo,
                    0,
                    $loginInput,
                    'Guest/Unknown',
                    'Login Failed',
                    'Attempted login with non-existent account or inactive status'
                );

                $error = 'Invalid username/email or password.';
            } else {
                $failedAttempts = (int) ($user['failed_attempts'] ?? 0);
                $currentTime    = date('Y-m-d H:i:s');
                $lockoutUntil   = $user['lockout_until'] ?? null;

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
                    $resetStmt = $pdo->prepare(
                        "UPDATE users
                         SET failed_attempts = 0, lockout_until = NULL
                         WHERE id = :id"
                    );
                    $resetStmt->execute([':id' => $user['id']]);

                    session_regenerate_id(true);

                    $_SESSION['user_id']   = (int) $user['id'];
                    $_SESSION['username']  = (string) $user['username'];
                    $_SESSION['full_name'] = (string) ($user['full_name'] ?? $user['username']);
                    $_SESSION['role']      = (string) $user['role'];

                    logAction(
                        $pdo,
                        (int) $user['id'],
                        (string) $user['username'],
                        (string) $user['role'],
                        'Login',
                        'User successfully logged into the system'
                    );

                    redirectByRole((string) $user['role'], $redirectAfter);
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
                            ':lockout'  => $lockoutTime,
                            ':id'       => $user['id']
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
                            ':id'       => $user['id']
                        ]);

                        $attemptsLeft = 5 - $newFailedAttempts;
                        $error = "Invalid username/email or password. You have {$attemptsLeft} attempt(s) left before temporary lockout.";

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

$redirectQuery = $redirectAfter !== '' ? '&redirect=' . urlencode($redirectAfter) : '';
$googleAuthUrl = 'google-auth.php?mode=' . urlencode($mode) . $redirectQuery;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'register' ? 'Create Account' : 'Sign In' ?> | R. Taipan Hardware</title>
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
        .auth-card {
            width: 100%;
            max-width: 450px;
            padding: 32px;
            border: 0;
            border-radius: 20px;
            background: rgba(255, 255, 255, .97);
            box-shadow: 0 12px 35px rgba(0, 0, 0, .25);
        }
        .brand-logo {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #051c3c;
        }
        .form-control { padding: 11px 14px; }
        .btn-primary-navy {
            background: #051c3c;
            color: #fff;
            padding: 11px;
            font-weight: 600;
        }
        .btn-primary-navy:hover { background: #0b3267; color: #fff; }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #6c757d;
            font-size: 0.85rem;
            margin: 18px 0;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .divider:not(:empty)::before { margin-right: 10px; }
        .divider:not(:empty)::after { margin-left: 10px; }
    </style>
</head>
<body>
    <main class="auth-card">
        <div class="text-center mb-3">
            <img src="assets/logo.jpg" class="brand-logo" alt="R. Taipan Hardware logo">
            <h4 class="mt-2 mb-0">R. Taipan Hardware</h4>
            <small class="text-muted">
                <?= $mode === 'register' ? 'Create a Customer Account' : 'Sign In to Your Account' ?>
            </small>
        </div>

        <ul class="nav nav-pills nav-fill mb-3">
            <li class="nav-item">
                <a class="nav-link <?= $mode === 'login' ? 'active' : '' ?>"
                   href="login.php?mode=login<?= htmlspecialchars($redirectQuery, ENT_QUOTES, 'UTF-8') ?>">
                    Login
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $mode === 'register' ? 'active' : '' ?>"
                   href="login.php?mode=register<?= htmlspecialchars($redirectQuery, ENT_QUOTES, 'UTF-8') ?>">
                    Create Account
                </a>
            </li>
        </ul>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger text-center py-2" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success text-center py-2" role="alert">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'register'): ?>
            <form method="POST" action="login.php?mode=register<?= htmlspecialchars($redirectQuery, ENT_QUOTES, 'UTF-8') ?>" autocomplete="on">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectAfter, ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-2">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control"
                           placeholder="Enter your full name" required>
                </div>

                <div class="mb-2">
                    <label for="reg_username" class="form-label">Username</label>
                    <input type="text" name="username" id="reg_username" class="form-control"
                           placeholder="Choose a username" autocomplete="username" required>
                </div>

                <div class="mb-2">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control"
                           placeholder="name@example.com" autocomplete="email" required>
                </div>

                <div class="mb-2">
                    <label for="reg_password" class="form-label">Password</label>
                    <input type="password" name="password" id="reg_password" class="form-control"
                           placeholder="At least 8 characters" autocomplete="new-password" required>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                           placeholder="Re-enter your password" autocomplete="new-password" required>
                </div>

                <button type="submit" name="register" value="1" class="btn btn-primary-navy w-100">
                    <i class="bi bi-person-plus me-1"></i> Create Account
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="login.php?mode=login<?= htmlspecialchars($redirectQuery, ENT_QUOTES, 'UTF-8') ?>" autocomplete="on">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectAfter, ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <input type="text" name="username" id="username" class="form-control"
                           placeholder="Enter username or email" autocomplete="username" required>
                </div>

                <div class="mb-3">
                    <label for="passwordField" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="passwordField" class="form-control"
                               placeholder="Enter your password" autocomplete="current-password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn" aria-label="Show password">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login" value="1" class="btn btn-primary-navy w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                </button>

                <div class="text-center mt-2">
                    <a href="forgotPassword.php" class="small">Forgot Password?</a>
                </div>
            </form>
        <?php endif; ?>

        <div class="divider">OR</div>

        <a href="<?= htmlspecialchars($googleAuthUrl, ENT_QUOTES, 'UTF-8') ?>"
           class="btn btn-outline-dark w-100 py-2 fw-semibold">
            <i class="bi bi-google me-2"></i> Continue with Google
        </a>

        <hr class="my-3">

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