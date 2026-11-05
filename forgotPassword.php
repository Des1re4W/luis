<?php
session_start();
require_once __DIR__ . "/config/db.php";

require_once __DIR__ . "/libs/src/Exception.php";
require_once __DIR__ . "/libs/src/PHPMailer.php";
require_once __DIR__ . "/libs/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

date_default_timezone_set('Asia/Manila');

$message = "";
$messageType = "info";

// ================= SMTP SETTINGS =================
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_USERNAME = 'rtaipancss@gmail.com';
const SMTP_PASSWORD = 'miohifpavqdzvzvn'; 
const SMTP_FROM_NAME = 'R. Taipan Hardware';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sendResetOtp(string $email, string $otp): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        $mail->Debugoutput = function ($str, $level) {
            error_log("SMTP DEBUG [$level]: $str");
        };
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;

        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($email);
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
        $mail->AltBody = "Your R. Taipan Hardware password reset OTP is: {$otp}. It expires in 5 minutes. Do not share it.";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log("OTP EMAIL ERROR: " . $e->getMessage());
        return false;
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

    // STEP 1: Search Account via Username, Email, or Phone (FB-style Search)
    if ($action === 'search_account') {
        $input = trim($_POST['search_credential'] ?? '');

        if (empty($input)) {
            $message = "Please enter your username, email, or phone number.";
            $messageType = "danger";
        } else {
            // Hinahanap sa username, email, o phone column (Baguhin ang column names depende sa DB schema mo)
            $stmt = $pdo->prepare("SELECT id, name, username, email, phone FROM users WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) OR phone = ?) AND status = 'Active' LIMIT 1");
            
            if ($stmt && $stmt->execute([$input, $input, $input])) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $message = "No active account found matching those details.";
                    $messageType = "danger";
                } else {
                    clearResetSession();
                    // I-save sa session ang nahanap na account
                    $_SESSION['pw_reset_account'] = [
                        'id' => $user['id'],
                        'name' => $user['name'] ?? $user['username'] ?? 'User',
                        'email' => $user['email']
                    ];
                }
            } else {
                $message = "A system error occurred. Please try again later.";
                $messageType = "danger";
            }
        }
    }

    // STEP 2: Send OTP to the found account's email
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

            if (sendResetOtp($email, $otp)) {
                $_SESSION['pw_reset_email']     = $email;
                $_SESSION['pw_reset_otp_hash']  = password_hash($otp, PASSWORD_DEFAULT);
                $_SESSION['pw_reset_expires']   = time() + 300;
                $_SESSION['pw_reset_attempts']  = 0;
                $_SESSION['pw_reset_last_sent'] = time();

                $message = "An OTP code has been sent to your email.";
                $messageType = "success";
            } else {
                $message = "Unable to send the OTP email. Please try again.";
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

    // Action para mag-cancel o mag-search ng ibang account
    elseif ($action === 'reset_flow') {
        clearResetSession();
    }
}

// State Handlers para sa UI
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
            font-family: Arial, sans-serif;
            display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .reset-card {
            width: 100%; max-width: 480px; background: #fff;
            border-radius: 18px; padding: clamp(22px, 5vw, 38px);
            box-shadow: 0 12px 38px rgba(0, 31, 77, .12);
        }
        .brand-icon {
            width: 66px; height: 66px; border-radius: 50%;
            background: var(--navy); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 29px; margin: 0 auto 16px;
        }
        .user-avatar {
            width: 70px; height: 70px; border-radius: 50%;
            background: #e9ecef; color: var(--navy);
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; margin: 0 auto 12px;
        }
        h1 { font-size: clamp(1.4rem, 4vw, 1.8rem); font-weight: 700; color: var(--navy); text-align: center; }
        .subtitle { text-align: center; color: #6c757d; font-size: .95rem; margin-bottom: 24px; }
        .form-control { min-height: 48px; border-radius: 10px; }
        .btn-primary { background: var(--navy); border-color: var(--navy); min-height: 48px; border-radius: 10px; font-weight: 600; }
        .btn-primary:hover { background: #06336f; border-color: #06336f; }
        .alert { border-radius: 10px; font-size: .92rem; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: var(--navy); text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>

<body>
    <main class="reset-card">
        <div class="brand-icon"><i class="bi bi-shield-lock"></i></div>
        
        <?php if ($showSearchForm): ?>
            <h1>Find Your Account</h1>
            <p class="subtitle">Please enter your email, username, or mobile number to search for your account.</p>
        <?php elseif ($showAccountFound): ?>
            <h1>Is this your account?</h1>
            <p class="subtitle">We found an account linked to your search details.</p>
        <?php elseif ($showOtpForm): ?>
            <h1>Enter Verification Code</h1>
            <p class="subtitle">Enter the 6-digit OTP code sent to <strong><?= h($maskedEmail) ?></strong>.</p>
        <?php elseif ($showPasswordForm): ?>
            <h1>Create New Password</h1>
            <p class="subtitle">Your identity is verified. Set a new password for your account.</p>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= h($messageType) ?>"><?= h($message) ?></div>
        <?php endif; ?>

        <!-- STEP 1: Search Form -->
        <?php if ($showSearchForm): ?>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="search_account">
                <div class="mb-3">
                    <label for="search_credential" class="form-label fw-semibold">Email, Username, or Phone</label>
                    <input type="text" class="form-control" id="search_credential" name="search_credential" required placeholder="Enter credentials">
                </div>
                <button type="submit" class="btn btn-primary w-100">Search Account</button>
            </form>

        <!-- STEP 2: Display Account Found & Trigger Send OTP -->
        <?php elseif ($showAccountFound): ?>
            <div class="text-center p-3 mb-3 border rounded bg-light">
                <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                <h5 class="fw-bold text-dark mb-1"><?= h($_SESSION['pw_reset_account']['name']) ?></h5>
                <p class="text-muted small mb-0">Email: <?= h($maskedEmail) ?></p>
            </div>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="send_otp">
                <button type="submit" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-envelope me-2"></i>Send Verification Code to Email
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
                    <input type="text" class="form-control text-center" id="otp" name="otp" inputmode="numeric"
                        pattern="[0-9]{6}" maxlength="6" minlength="6" required placeholder="Enter OTP"
                        autocomplete="one-time-code" style="letter-spacing:8px;font-size:1.25rem">
                </div>
                <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
            </form>
            <form method="POST" class="mt-2" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="send_otp">
                <button type="submit" class="btn btn-link text-decoration-none w-100 text-center">Resend OTP Code</button>
            </form>

        <!-- STEP 4: Password Reset Form -->
        <?php elseif ($showPasswordForm): ?>
            <form method="POST" autocomplete="off" onsubmit="showLoading(this)">
                <input type="hidden" name="action" value="reset_password">

                <div class="mb-3">
                    <label for="new_password" class="form-label fw-semibold">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8"
                            required autocomplete="new-password" placeholder="At least 8 characters">
                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                            <i class="bi bi-eye" id="iconNewPassword"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            minlength="8" required autocomplete="new-password" placeholder="Re-enter new password">
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
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