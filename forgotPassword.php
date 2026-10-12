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
const SMTP_PASSWORD = 'untvtpzeiqyrkrpx';
const SMTP_FROM_NAME = 'R. Taipan Hardware';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sendResetOtp(string $email, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - R. Taipan Hardware';
        $mail->Body = "
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
    } catch (Exception $e) {
        error_log("Password reset email error: " . $mail->ErrorInfo);
        return false;
    }
}

function clearResetSession(): void {
    unset(
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

    // STEP 1: Validate email and send OTP.
    if ($action === 'send_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = "danger";
        } elseif (!empty($_SESSION['pw_reset_last_sent']) && time() - (int)$_SESSION['pw_reset_last_sent'] < 60) {
            $message = "Please wait 60 seconds before requesting another OTP.";
            $messageType = "warning";
        } else {
            $stmt = $conn->prepare("SELECT id, email FROM users WHERE LOWER(email) = LOWER(?) AND status = 'Active' LIMIT 1");
            if (!$stmt) {
                $message = "A system error occurred. Please try again later.";
                $messageType = "danger";
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                if (!$user) {
                    $message = "No active account was found with that email address.";
                    $messageType = "danger";
                } else {
                    $otp = (string)random_int(100000, 999999);
                    if (sendResetOtp($user['email'], $otp)) {
                        clearResetSession();
                        $_SESSION['pw_reset_email'] = $user['email'];
                        $_SESSION['pw_reset_otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
                        $_SESSION['pw_reset_expires'] = time() + 300;
                        $_SESSION['pw_reset_attempts'] = 0;
                        $_SESSION['pw_reset_last_sent'] = time();
                        $message = "OTP sent to your email. Please check your inbox and spam folder.";
                        $messageType = "success";
                    } else {
                        $message = "Unable to send the OTP email. Please check the Gmail SMTP settings and try again.";
                        $messageType = "danger";
                    }
                }
            }
        }
    }

    // STEP 2: Verify OTP before showing the password form.
    elseif ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');

        if (empty($_SESSION['pw_reset_email']) || empty($_SESSION['pw_reset_otp_hash']) || empty($_SESSION['pw_reset_expires'])) {
            clearResetSession();
            $message = "Your OTP session has expired. Please request a new OTP.";
            $messageType = "warning";
        } elseif (time() > (int)$_SESSION['pw_reset_expires']) {
            clearResetSession();
            $message = "Your OTP has expired. Please request a new one.";
            $messageType = "warning";
        } elseif (!preg_match('/^\d{6}$/', $otp)) {
            $message = "Enter the 6-digit OTP sent to your email.";
            $messageType = "danger";
        } elseif ((int)($_SESSION['pw_reset_attempts'] ?? 0) >= 5) {
            clearResetSession();
            $message = "Too many incorrect attempts. Please request a new OTP.";
            $messageType = "danger";
        } elseif (password_verify($otp, $_SESSION['pw_reset_otp_hash'])) {
            $_SESSION['pw_reset_verified'] = true;
            unset($_SESSION['pw_reset_otp_hash'], $_SESSION['pw_reset_expires'], $_SESSION['pw_reset_attempts']);
            $message = "OTP verified. You may now create a new password.";
            $messageType = "success";
        } else {
            $_SESSION['pw_reset_attempts'] = (int)($_SESSION['pw_reset_attempts'] ?? 0) + 1;
            $message = "Incorrect OTP. Please try again.";
            $messageType = "danger";
        }
    }

    // STEP 3: Change password only after successful OTP verification.
    elseif ($action === 'reset_password') {
        if (empty($_SESSION['pw_reset_verified']) || empty($_SESSION['pw_reset_email'])) {
            $message = "Please verify your OTP first.";
            $messageType = "danger";
        } else {
            $password = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (strlen($password) < 8) {
                $message = "Password must be at least 8 characters long.";
                $messageType = "danger";
            } elseif ($password !== $confirmPassword) {
                $message = "Passwords do not match.";
                $messageType = "danger";
            } else {
                $email = $_SESSION['pw_reset_email'];
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE LOWER(email) = LOWER(?) AND status = 'Active' LIMIT 1");
                if (!$stmt) {
                    $message = "A system error occurred. Please try again later.";
                    $messageType = "danger";
                } else {
                    $stmt->bind_param("ss", $hashedPassword, $email);
                    if ($stmt->execute() && $stmt->affected_rows === 1) {
                        clearResetSession();
                        $message = "Your password has been changed successfully. You can now log in.";
                        $messageType = "success";
                    } else {
                        $message = "Password could not be updated. Please request a new OTP and try again.";
                        $messageType = "danger";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$showPasswordForm = !empty($_SESSION['pw_reset_verified']) && !empty($_SESSION['pw_reset_email']);
$showOtpForm = !$showPasswordForm && !empty($_SESSION['pw_reset_email']) && !empty($_SESSION['pw_reset_otp_hash']);
$maskedEmail = '';
if (!empty($_SESSION['pw_reset_email'])) {
    $parts = explode('@', $_SESSION['pw_reset_email']);
    $name = $parts[0] ?? '';
    $domain = $parts[1] ?? '';
    $maskedEmail = (strlen($name) > 2 ? substr($name, 0, 2) : substr($name, 0, 1)) . str_repeat('*', max(2, strlen($name) - 2)) . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password | R. Taipan Hardware</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--navy:#001f4d;--blue:#0d6efd}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:linear-gradient(135deg,#edf3fb,#f8fafc);font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;padding:20px}
.reset-card{width:100%;max-width:480px;background:#fff;border-radius:18px;padding:clamp(22px,5vw,38px);box-shadow:0 12px 38px rgba(0,31,77,.12)}
.brand-icon{width:66px;height:66px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:29px;margin:0 auto 16px}
h1{font-size:clamp(1.4rem,4vw,1.8rem);font-weight:700;color:var(--navy);text-align:center}
.subtitle{text-align:center;color:#6c757d;font-size:.95rem;margin-bottom:24px}
.form-control{min-height:48px;border-radius:10px}

/* Pagtugma ng background ng eye button sa textfield */
.input-group .btn-outline-secondary {
    background-color: #fff;
    border-color: #dee2e6;
    color: #6c757d;
}
.input-group .btn-outline-secondary:hover {
    background-color: #e9ecef;
    border-color: #dee2e6;
    color: #495057;
}

.btn-primary{background:var(--navy);border-color:var(--navy);min-height:48px;border-radius:10px;font-weight:600}
.btn-primary:hover{background:#06336f;border-color:#06336f}
.alert{border-radius:10px;font-size:.92rem}
.back-link{display:block;text-align:center;margin-top:20px;color:var(--navy);text-decoration:none;font-weight:600}
.back-link:hover{text-decoration:underline}
@media(max-width:420px){body{padding:12px}.reset-card{border-radius:14px}.subtitle{font-size:.88rem}}
</style>
</head>
<body>
<main class="reset-card">
    <div class="brand-icon"><i class="bi bi-shield-lock"></i></div>
    <h1>Forgot Password?</h1>
    <p class="subtitle">
        <?php if ($showPasswordForm): ?>
            OTP verified. Set a new password for your account.
        <?php elseif ($showOtpForm): ?>
            Enter the 6-digit code sent to <strong><?= h($maskedEmail) ?></strong>.
        <?php else: ?>
            Enter your registered email address and we'll send you a one-time verification code.
        <?php endif; ?>
    </p>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= h($messageType) ?>"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($showPasswordForm): ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="reset_password">
            
            <!-- New Password Field na may Toggle Eye Symbol -->
            <div class="mb-3">
                <label for="new_password" class="form-label fw-semibold">New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required autocomplete="new-password" placeholder="At least 8 characters">
                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                        <i class="bi bi-eye" id="iconNewPassword"></i>
                    </button>
                </div>
            </div>

            <!-- Confirm New Password Field na may Toggle Eye Symbol -->
            <div class="mb-3">
                <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password" placeholder="Re-enter new password">
                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                        <i class="bi bi-eye" id="iconConfirmPassword"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Change Password</button>
        </form>
    <?php elseif ($showOtpForm): ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="verify_otp">
            <div class="mb-3">
                <label for="otp" class="form-label fw-semibold">6-Digit OTP</label>
                <input type="text" class="form-control text-center" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" required placeholder="Enter OTP" autocomplete="one-time-code" style="letter-spacing:8px;font-size:1.25rem">
            </div>
            <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
        </form>
        <form method="POST" class="mt-3">
            <input type="hidden" name="action" value="send_otp">
            <input type="hidden" name="email" value="<?= h($_SESSION['pw_reset_email']) ?>">
            <button type="submit" class="btn btn-outline-secondary w-100">Resend OTP</button>
        </form>
    <?php else: ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="send_otp">
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">Registered Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required maxlength="255" autocomplete="email" placeholder="name@example.com">
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-envelope me-2"></i>Send OTP</button>
        </form>
    <?php endif; ?>

    <a href="login.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
</main>

<?php if ($showPasswordForm): ?>
<script>
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
</script>
<?php endif; ?>
</body>
</html>