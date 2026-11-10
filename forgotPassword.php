<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Siguraduhing naha-hire o nai-load ang Composer Autoloader
$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    die("Error: Vendor autoloader not found. Please run 'composer install' or 'composer require phpmailer/phpmailer'.");
}
// Standard PHPMailer Autoload / Imports
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

date_default_timezone_set('Asia/Manila');

// 1. Automatically load a local .env file if running in local development
$envFilePath = __DIR__ . '/.env';
if (file_exists($envFilePath)) {
    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || empty($line)) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

$message = "";
$messageType = "info";

// Built-in Default Avatar (SVG Data URI - pareho sa dashboard.php)
$default_avatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='%23001f4d'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 interim0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z'/></svg>";

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sends Reset OTP via PHPMailer SMTP
 * Returns array: ['success' => bool, 'error' => string]
 */
function sendResetOtp(string $email, string $otp): array
{
    // Function to safely check env variables
    $getEnvVar = function($key, $default = '') {
        $val = getenv($key);
        if ($val !== false && $val !== '') return $val;
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        return $default;
    };

   // Direct configuration check with environment & hardcoded fallbacks
    $smtpHost   = $getEnvVar('SMTP_HOST', 'smtp.gmail.com');
    $smtpUser   = $getEnvVar('SMTP_USER', 'rtaipancss@gmail.com');
    $smtpPass   = $getEnvVar('SMTP_PASS', 'nfgairnkprpxitkk');
    $smtpPort   = (int) $getEnvVar('SMTP_PORT', 587);
    $smtpSecure = $getEnvVar('SMTP_SECURE', 'tls');
    $fromEmail  = $getEnvVar('SMTP_FROM_EMAIL', 'rtaipancss@gmail.com');
    $fromName   = $getEnvVar('SMTP_FROM_NAME', 'R. Taipan Hardware');

    if (empty($smtpUser) || empty($smtpPass)) {
        error_log("OTP PHPMailer ERROR: SMTP_USER or SMTP_PASS is not defined.");
        return ['success' => false, 'error' => 'Mail Configuration Error: SMTP credentials are missing in .env environment variables.'];
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = ($smtpSecure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;
        $mail->Timeout    = 15;

        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - R. Taipan Hardware';
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:12px;'>
                <h2 style='color:#001f4d;margin-top:0;'>Password Reset Verification</h2>
                <p>We received a request to reset your R. Taipan Hardware account password.</p>
                <p>Your one-time password (OTP) is:</p>
                <div style='font-size:30px;font-weight:bold;letter-spacing:8px;background:#f3f6fb;padding:18px;text-align:center;border-radius:8px;color:#001f4d;'>" . h($otp) . "</div>
                <p>This OTP expires in <strong>5 minutes</strong>. Do not share it with anyone.</p>
                <p>If you did not request a password reset, you can ignore this email.</p>
            </div>";
        $mail->AltBody = "Your password reset OTP is: {$otp}. It expires in 5 minutes.";

        $mail->send();
        return ['success' => true, 'error' => ''];
    } catch (Exception $e) {
        error_log("OTP PHPMailer Exception: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => 'Mailer Error: ' . $mail->ErrorInfo];
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

    // STEP 1: Search Account gamit ang username, email, o phone_number
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
                    
                    // Logic para sa profile picture at default avatar fallback
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

    // STEP 2: Send OTP sa email ng nahanap na account
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

    // STEP 3: Verify OTP Code
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

    // STEP 4: Reset Password
    elseif ($action === 'reset_password') {
        if (empty($_SESSION['pw_reset_verified']) || empty($_SESSION['pw_reset_email'])) {
            $message = "Please verify your OTP first.";
            $messageType = "danger";
        } else {
            $password        = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (strlen($password) < 8) {
                $message = "Password must be at least 8 characters long.";
                $messageType = "danger";
            } elseif ($password !== $confirmPassword) {
                $message = "Passwords do not match.";
                $messageType = "danger";
            } else {
                $email          = $_SESSION['pw_reset_email'];
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

    // Action para mag-search ng ibang account
    elseif ($action === 'reset_flow') {
        clearResetSession();
    }
}

// UI State Management
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
        :root { 
            --navy: #001f4d; 
            --blue: #0d6efd; 
        }

        * { 
            box-sizing: border-box; 
        }

        body {
            margin: 0; 
            min-height: 100vh;
            background: linear-gradient(135deg, #edf3fb, #f8fafc);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 16px;
        }

        .reset-card {
            width: 100%; 
            max-width: 440px; 
            background: #ffffff;
            border-radius: 16px; 
            padding: clamp(20px, 5vw, 32px);
            box-shadow: 0 10px 30px rgba(0, 31, 77, 0.08);
            border: 1px solid rgba(0, 31, 77, 0.05);
        }

        .brand-icon {
            width: 60px; 
            height: 60px; 
            border-radius: 50%;
            background: var(--navy); 
            color: #ffffff;
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 26px; 
            margin: 0 auto 16px;
        }

        .user-profile-img {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--navy);
            background-color: #ffffff;
            margin: 0 auto 12px;
            display: block;
        }

        h1 { 
            font-size: clamp(1.3rem, 4vw, 1.6rem); 
            font-weight: 700; 
            color: var(--navy); 
            text-align: center;
            line-height: 1.3;
        }

        .subtitle { 
            text-align: center; 
            color: #6c757d; 
            font-size: clamp(0.875rem, 2.5vw, 0.95rem); 
            margin-bottom: 20px; 
        }

        .form-control { 
            min-height: 46px; 
            border-radius: 8px; 
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--navy);
            box-shadow: 0 0 0 0.25rem rgba(0, 31, 77, 0.15);
        }

        .input-group .btn-outline-secondary {
            border-color: #dee2e6;
            color: #6c757d;
        }

        .btn-primary { 
            background: var(--navy); 
            border-color: var(--navy); 
            min-height: 46px; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 0.95rem;
        }

        .btn-primary:hover, .btn-primary:focus { 
            background: #06336f; 
            border-color: #06336f; 
        }

        .btn-outline-secondary {
            min-height: 44px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert { 
            border-radius: 8px; 
            font-size: 0.88rem; 
            word-wrap: break-word;
        }

        .back-link { 
            display: inline-block; 
            width: 100%;
            text-align: center; 
            margin-top: 18px; 
            color: var(--navy); 
            text-decoration: none; 
            font-weight: 600;
            font-size: 0.9rem;
        }

        .back-link:hover { 
            text-decoration: underline; 
        }

        @media (max-width: 480px) {
            body { 
                padding: 12px; 
            }
            .reset-card { 
                border-radius: 12px; 
                padding: 20px 16px;
            }
        }
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

        <!-- STEP 1: Search Account Form -->
        <?php if ($showSearchForm): ?>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="search_account">
                <div class="mb-3">
                    <label for="search_credential" class="form-label fw-semibold">Email, Username, or Phone</label>
                    <input type="text" class="form-control" id="search_credential" name="search_credential" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Search Account</button>
            </form>

        <!-- STEP 2: Show Found Account & Confirmation -->
        <?php elseif ($showAccountFound): ?>
            <div class="text-center p-3 mb-3 border rounded bg-light">
                <img src="<?= h($_SESSION['pw_reset_account']['profile_image']) ?>" 
                     alt="Profile Picture" 
                     class="user-profile-img"
                     onerror="this.onerror=null;this.src='<?= $default_avatar; ?>';">
                
                <h5 class="fw-bold text-dark mb-1 text-break"><?= h($_SESSION['pw_reset_account']['name']) ?></h5>
                <p class="text-muted small mb-0 text-break">Email: <?= h($maskedEmail) ?></p>
            </div>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="send_otp">
                <button type="submit" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-envelope me-2"></i>Send Code to Email
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="action" value="reset_flow">
                <button type="submit" class="btn btn-outline-secondary w-100">Not your account? Search again</button>
            </form>

        <!-- STEP 3: OTP Input Form -->
        <?php elseif ($showOtpForm): ?>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="verify_otp">
                <div class="mb-3">
                    <label for="otp" class="form-label fw-semibold">6-Digit OTP</label>
                    <input type="text" class="form-control text-center fw-bold" id="otp" name="otp" inputmode="numeric"
                        pattern="[0-9]{6}" maxlength="6" minlength="6" required placeholder="******"
                        autocomplete="one-time-code" style="letter-spacing:6px; font-size:1.2rem;">
                </div>
                <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
            </form>
            <form method="POST" class="mt-2" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="send_otp">
                <button type="submit" class="btn btn-link text-decoration-none w-100 text-center text-muted small">Resend OTP Code</button>
            </form>

        <!-- STEP 4: Reset Password Form -->
        <?php elseif ($showPasswordForm): ?>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="reset_password">

                <div class="mb-3">
                    <label for="new_password" class="form-label fw-semibold">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8"
                            required autocomplete="new-password" placeholder="At least 8 characters">
                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" aria-label="Toggle password visibility">
                            <i class="bi bi-eye" id="iconNewPassword"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            minlength="8" required autocomplete="new-password" placeholder="Re-enter new password">
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" aria-label="Toggle password visibility">
                            <i class="bi bi-eye" id="iconConfirmPassword"></i>
                        </button>
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