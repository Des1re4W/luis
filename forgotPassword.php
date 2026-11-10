<?php
session_start();

// 1. Load Database Connection (handles dotenv and PDO)
require_once __DIR__ . "/config/db.php";

date_default_timezone_set('Asia/Manila');

$message = "";
$messageType = "info";

// Built-in Default Avatar (SVG Data URI)
$default_avatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='%23001f4d'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 interim0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z'/></svg>";

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sends Reset OTP via Native cURL HTTP API to Resend
 */
function sendResetOtp(string $email, string $otp): array
{
    $apiKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY');

    if (empty($apiKey)) {
        error_log("OTP RESEND ERROR: RESEND_API_KEY is not defined.");
        return ['success' => false, 'error' => 'Mail Configuration Error: RESEND_API_KEY is missing in environment variables.'];
    }

    $url = 'https://api.resend.com/emails';
    $data = [
        'from'    => 'R. Taipan Hardware <support@r-taipan-hardware.com>',
        'to'      => [$email],
        'subject' => 'Password Reset OTP - R. Taipan Hardware',
        'html'    => "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:12px;'>
                <h2 style='color:#001f4d;margin-top:0;'>Password Reset Verification</h2>
                <p>We received a request to reset your R. Taipan Hardware account password.</p>
                <p>Your one-time password (OTP) is:</p>
                <div style='font-size:30px;font-weight:bold;letter-spacing:8px;background:#f3f6fb;padding:18px;text-align:center;border-radius:8px;color:#001f4d;'>" . h($otp) . "</div>
                <p>This OTP expires in <strong>5 minutes</strong>. Do not share it with anyone.</p>
                <p>If you did not request a password reset, you can ignore this email.</p>
            </div>"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'error' => ''];
    } else {
        error_log("Resend cURL Error: " . $response);
        return ['success' => false, 'error' => 'Mailer Error: Failed to connect to email API.'];
    }
}

function clearResetSession(): void
{
    unset(
        $_SESSION['pw_reset_account'],
        $_SESSION['pw_reset_email'],
        $_SESSION['pw_reset_otp_hash'],
        $_SESSION['pw_reset_expires'],
        $_SESSION['pw_reset_attempts'],
        $_SESSION['pw_reset_verified'],
        $_SESSION['pw_reset_last_sent']
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'search_account') {
        $input = trim($_POST['search_credential'] ?? '');

        if (empty($input)) {
            $message = "Please enter your username, email, or phone number.";
            $messageType = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, fullname, username, email, phone_number, profile_image FROM users WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) OR phone_number = ?) AND status = 'Active' LIMIT 1");
                $stmt->execute([$input, $input, $input]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $message = "No active account found matching those details.";
                    $messageType = "danger";
                } else {
                    clearResetSession();
                    
                    $userPhoto = $default_avatar;
                    if (!empty($user['profile_image'])) {
                        if (stripos($user['profile_image'], 'data:image/') === 0) {
                            $userPhoto = $user['profile_image'];
                        } else {
                            $photoPath = __DIR__ . '/' . $user['profile_image'];
                            if (file_exists($photoPath)) {
                                $userPhoto = htmlspecialchars($user['profile_image']);
                            }
                        }
                    }

                    $_SESSION['pw_reset_account'] = [
                        'id'            => $user['id'],
                        'name'          => $user['fullname'] ?? $user['username'] ?? 'User Account',
                        'email'         => $user['email'],
                        'profile_image' => $userPhoto
                    ];
                }
            } catch (\PDOException $e) {
                error_log("SEARCH ACCOUNT ERROR: " . $e->getMessage());
                $message = "A system error occurred. Please try again later.";
                $messageType = "danger";
            }
        }
    }
    elseif ($action === 'send_otp') {
        if (empty($_SESSION['pw_reset_account']['email'])) {
            $message = "Session expired. Please search for your account again.";
            $messageType = "warning";
        } elseif (!empty($_SESSION['pw_reset_last_sent']) && time() - (int) $_SESSION['pw_reset_last_sent'] < 60) {
            $message = "Please wait 60 seconds before requesting another OTP.";
            $messageType = "warning";
        } else {
            $email = $_SESSION['pw_reset_account']['email'];
            $otp = (string) random_int(100000, 999999);

            $sendResult = sendResetOtp($email, $otp);

            if ($sendResult['success']) {
                $_SESSION['pw_reset_email']     = $email;
                $_SESSION['pw_reset_otp_hash']  = password_hash($otp, PASSWORD_DEFAULT);
                $_SESSION['pw_reset_expires']   = time() + 300;
                $_SESSION['pw_reset_attempts']  = 0;
                $_SESSION['pw_reset_last_sent'] = time();

                $message = "An OTP code has been sent to your email.";
                $messageType = "success";
            } else {
                $message = "Unable to send the OTP email: " . h($sendResult['error']);
                $messageType = "danger";
            }
        }
    }
    elseif ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');

        if (empty($_SESSION['pw_reset_email']) || empty($_SESSION['pw_reset_otp_hash']) || empty($_SESSION['pw_reset_expires'])) {
            $message = "Your OTP session has expired. Please request a new OTP.";
            $messageType = "warning";
        } elseif (time() > (int) $_SESSION['pw_reset_expires']) {
            $message = "Your OTP has expired. Please request a new one.";
            $messageType = "warning";
        } elseif (!preg_match('/^\d{6}$/', $otp)) {
            $message = "Enter the 6-digit OTP sent to your email.";
            $messageType = "danger";
        } elseif ((int) ($_SESSION['pw_reset_attempts'] ?? 0) >= 5) {
            clearResetSession();
            $message = "Too many incorrect attempts. Please search for your account again.";
            $messageType = "danger";
        } elseif (password_verify($otp, $_SESSION['pw_reset_otp_hash'])) {
            $_SESSION['pw_reset_verified'] = true;
            unset($_SESSION['pw_reset_otp_hash'], $_SESSION['pw_reset_expires'], $_SESSION['pw_reset_attempts']);
            $message = "OTP verified. You may now create a new password.";
            $messageType = "success";
        } else {
            $_SESSION['pw_reset_attempts'] = (int) ($_SESSION['pw_reset_attempts'] ?? 0) + 1;
            $message = "Incorrect OTP. Please try again.";
            $messageType = "danger";
        }
    }
    elseif ($action === 'reset_password') {
        if (empty($_SESSION['pw_reset_verified']) || empty($_SESSION['pw_reset_email'])) {
            $message = "Please verify your OTP first.";
            $messageType = "danger";
        } else {
            $password        = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $email           = $_SESSION['pw_reset_email'];

            // Kunin ang kasalukuyang password hash sa database para mai-check
            $stmtUser = $pdo->prepare("SELECT password FROM users WHERE LOWER(email) = LOWER(?) AND status = 'Active' LIMIT 1");
            $stmtUser->execute([$email]);
            $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

            // Validations
            if (strlen($password) < 8) {
                $message = "Password must be at least 8 characters long.";
                $messageType = "danger";
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $message = "Password must contain at least 1 uppercase letter (A–Z).";
                $messageType = "danger";
            } elseif (!preg_match('/[a-z]/', $password)) {
                $message = "Password must contain at least 1 lowercase letter (a–z).";
                $messageType = "danger";
            } elseif (!preg_match('/[0-9]/', $password)) {
                $message = "Password must contain at least 1 number (0–9).";
                $messageType = "danger";
            } elseif (!preg_match('/[\W_]/', $password)) { // Special characters
                $message = "Password must contain at least 1 special character (! @ # $ % ^ & *).";
                $messageType = "danger";
            } elseif (preg_match('/\s/', $password)) {
                $message = "Password cannot contain spaces.";
                $messageType = "danger";
            } elseif ($password !== $confirmPassword) {
                $message = "Passwords do not match.";
                $messageType = "danger";
            } elseif ($currentUser && password_verify($password, $currentUser['password'])) {
                $message = "New password must not match the current password.";
                $messageType = "danger";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE LOWER(email) = LOWER(?) AND status = 'Active'");
                if ($stmt && $stmt->execute([$hashedPassword, $email]) && $stmt->rowCount() > 0) {
                    clearResetSession();
                    $message = "Your password has been changed successfully. You can now log in.";
                    $messageType = "success";
                } else {
                    $message = "Password could not be updated. Please try again.";
                    $messageType = "danger";
                }
            }
        }
    }
    elseif ($action === 'reset_flow') {
        clearResetSession();
    }
}

$showPasswordForm = !empty($_SESSION['pw_reset_verified']) && !empty($_SESSION['pw_reset_email']);
$showOtpForm      = !$showPasswordForm && !empty($_SESSION['pw_reset_otp_hash']);
$showAccountFound = !$showPasswordForm && !$showOtpForm && !empty($_SESSION['pw_reset_account']);
$showSearchForm   = !$showPasswordForm && !$showOtpForm && !$showAccountFound;

$maskedEmail = '';
if (!empty($_SESSION['pw_reset_account']['email'])) {
    $parts       = explode('@', $_SESSION['pw_reset_account']['email']);
    $name        = $parts[0] ?? '';
    $domain      = $parts[1] ?? '';
    $maskedEmail = (strlen($name) > 2 ? substr($name, 0, 2) : substr($name, 0, 1)) . str_repeat('*', max(2, strlen($name) - 2)) . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Your Account | R. Taipan Hardware</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --navy: #001f4d; --blue: #0d6efd; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            background: linear-gradient(135deg, #edf3fb, #f8fafc);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            display: flex; align-items: center; justify-content: center; padding: 16px;
        }
        .reset-card {
            width: 100%; max-width: 440px; background: #ffffff;
            border-radius: 16px; padding: clamp(20px, 5vw, 32px);
            box-shadow: 0 10px 30px rgba(0, 31, 77, 0.08);
            border: 1px solid rgba(0, 31, 77, 0.05);
        }
        .brand-icon {
            width: 60px; height: 60px; border-radius: 50%;
            background: var(--navy); color: #ffffff;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; margin: 0 auto 16px;
        }
        .user-profile-img {
            width: 75px; height: 75px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--navy);
            background-color: #ffffff; margin: 0 auto 12px; display: block;
        }
        h1 { font-size: clamp(1.3rem, 4vw, 1.6rem); font-weight: 700; color: var(--navy); text-align: center; line-height: 1.3; }
        .subtitle { text-align: center; color: #6c757d; font-size: clamp(0.875rem, 2.5vw, 0.95rem); margin-bottom: 20px; }
        .form-control { min-height: 46px; border-radius: 8px; font-size: 0.95rem; }
        .form-control:focus { border-color: var(--navy); box-shadow: 0 0 0 0.25rem rgba(0, 31, 77, 0.15); }
        .btn-primary { background: var(--navy); border-color: var(--navy); min-height: 46px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; }
        .btn-primary:hover, .btn-primary:focus { background: #06336f; border-color: #06336f; }
        .btn-outline-secondary { min-height: 44px; border-radius: 8px; font-weight: 500; }
        .alert { border-radius: 8px; font-size: 0.88rem; word-wrap: break-word; }
        .back-link { display: inline-block; width: 100%; text-align: center; margin-top: 18px; color: var(--navy); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <main class="reset-card">
        <div class="brand-icon"><i class="bi bi-shield-lock"></i></div>
        
        <?php if ($showSearchForm): ?>
            <h1>Find Your Account</h1>
            <p class="subtitle">Enter your email, username, or phone number to search for your account.</p>
        <?php elseif ($showAccountFound): ?>
            <h1>Is this your account?</h1>
            <p class="subtitle">We found an account matching your search criteria.</p>
        <?php elseif ($showOtpForm): ?>
            <h1>Enter Verification Code</h1>
            <p class="subtitle">We sent a 6-digit OTP code to <strong><?= h($maskedEmail) ?></strong>.</p>
        <?php elseif ($showPasswordForm): ?>
            <h1>Create New Password</h1>
            <p class="subtitle">Your identity has been verified. Set a new password below.</p>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= h($messageType) ?> mb-3"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($showSearchForm): ?>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="search_account">
                <div class="mb-3">
                    <label for="search_credential" class="form-label fw-semibold">Email, Username, or Phone</label>
                    <input type="text" class="form-control" id="search_credential" name="search_credential" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Search Account</button>
            </form>
        <?php elseif ($showAccountFound): ?>
            <div class="text-center p-3 mb-3 border rounded bg-light">
                <img src="<?= h($_SESSION['pw_reset_account']['profile_image']) ?>" alt="Profile Picture" class="user-profile-img" onerror="this.onerror=null;this.src='<?= $default_avatar; ?>';">
                <h5 class="fw-bold text-dark mb-1 text-break"><?= h($_SESSION['pw_reset_account']['name']) ?></h5>
                <p class="text-muted small mb-0 text-break">Email: <?= h($maskedEmail) ?></p>
            </div>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="send_otp">
                <button type="submit" class="btn btn-primary w-100 mb-2"><i class="bi bi-envelope me-2"></i>Send Code to Email</button>
            </form>
            <form method="POST">
                <input type="hidden" name="action" value="reset_flow">
                <button type="submit" class="btn btn-outline-secondary w-100">Not your account? Search again</button>
            </form>
        <?php elseif ($showOtpForm): ?>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="verify_otp">
                <div class="mb-3">
                    <label for="otp" class="form-label fw-semibold">6-Digit OTP</label>
                    <input type="text" class="form-control text-center fw-bold" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" required placeholder="******" autocomplete="one-time-code" style="letter-spacing:6px; font-size:1.2rem;">
                </div>
                <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
            </form>
            <form method="POST" class="mt-2" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="send_otp">
                <button type="submit" class="btn btn-link text-decoration-none w-100 text-center text-muted small">Resend OTP Code</button>
            </form>
        <?php elseif ($showPasswordForm): ?>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="reset_password">
                <div class="mb-3">
                    <label for="new_password" class="form-label fw-semibold">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required autocomplete="new-password" placeholder="At least 8 characters">
                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword"><i class="bi bi-eye" id="iconNewPassword"></i></button>
                    </div>
                    <div class="form-text text-muted" style="font-size: 0.78rem;">Must be 8+ chars with uppercase, lowercase, number & special character. No spaces.</div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password" placeholder="Re-enter new password">
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword"><i class="bi bi-eye" id="iconConfirmPassword"></i></button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Change Password</button>
            </form>
        <?php endif; ?>

        <a href="login.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
    </main>

    <script>
        function showLoading(form) {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
            }
        }
        <?php if ($showPasswordForm): ?>
            function setupPasswordToggle(toggleBtnId, fieldId, iconId) {
                const btn = document.getElementById(toggleBtnId);
                const field = document.getElementById(fieldId);
                const icon = document.getElementById(iconId);
                if (btn && field && icon) {
                    btn.addEventListener('click', function () {
                        const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
                        field.setAttribute('type', type);
                        icon.classList.toggle('bi-eye');
                        icon.classList.toggle('bi-eye-slash');
                    });
                }
            }
            setupPasswordToggle('toggleNewPassword', 'new_password', 'iconNewPassword');
            setupPasswordToggle('toggleConfirmPassword', 'confirm_password', 'iconConfirmPassword');
        <?php endif; ?>
    </script>
</body>
</html>