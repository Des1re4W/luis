<?php
session_start();

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = '';
$messageType = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function loadUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, username, fullname, email, phone_number, profile_image, role
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_unset();
        session_destroy();
        header('Location: ../login.php');
        exit;
    }

    return $user;
}

try {
    $user = loadUser($pdo, $userId);
} catch (Throwable $ex) {
    error_log('Profile fetch error: ' . $ex->getMessage());
    http_response_code(500);
    exit('Unable to load your profile right now. Please try again later.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $message = 'Invalid request token. Please refresh the page and try again.';
        $messageType = 'danger';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));

        $profileImage = (string) ($user['profile_image'] ?? '');
        $newImagePath = null;
        $uploadError = '';

        // Basic field validation
        if ($username === '' || mb_strlen($username) > 50) {
            $message = 'Username is required and must be 50 characters or fewer.';
            $messageType = 'danger';
        } elseif ($fullname !== '' && mb_strlen($fullname) > 150) {
            $message = 'Full name must be 150 characters or fewer.';
            $messageType = 'danger';
        } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';
        } elseif ($phoneNumber !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phoneNumber)) {
            $message = 'Please enter a valid contact number.';
            $messageType = 'danger';
        }

        if ($message === '') {
            try {
                // Prevent duplicate usernames/emails belonging to another account.
                $duplicate = $pdo->prepare(
                    'SELECT id FROM users
                     WHERE (LOWER(username) = LOWER(:username))
                       AND id <> :id
                     LIMIT 1'
                );
                $duplicate->execute([
                    ':username' => $username,
                    ':id' => $userId,
                ]);

                if ($duplicate->fetch(PDO::FETCH_ASSOC)) {
                    $message = 'That username is already in use. Please choose another.';
                    $messageType = 'danger';
                } elseif ($email !== '') {
                    $duplicateEmail = $pdo->prepare(
                        'SELECT id FROM users
                         WHERE LOWER(email) = LOWER(:email)
                           AND id <> :id
                         LIMIT 1'
                    );
                    $duplicateEmail->execute([
                        ':email' => $email,
                        ':id' => $userId,
                    ]);

                    if ($duplicateEmail->fetch(PDO::FETCH_ASSOC)) {
                        $message = 'That email address is already in use by another account.';
                        $messageType = 'danger';
                    }
                }
            } catch (Throwable $ex) {
                error_log('Profile duplicate-check error: ' . $ex->getMessage());
                $message = 'Unable to validate your profile details. Please try again.';
                $messageType = 'danger';
            }
        }

        // Validate and save an optional profile picture.
        if (
            $message === '' &&
            isset($_FILES['profile_image']) &&
            $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES['profile_image'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $uploadError = 'The image upload failed. Please try again.';
            } elseif ((int) $file['size'] > 2 * 1024 * 1024) {
                $uploadError = 'Profile picture must be 2 MB or smaller.';
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $uploadError = 'Invalid image upload.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $allowedMimes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                if (!isset($allowedMimes[$mime]) || @getimagesize($file['tmp_name']) === false) {
                    $uploadError = 'Use a valid JPG, PNG, or WEBP image.';
                } else {
                    // Project-root/uploads/profiles (this file is assumed to be in /admin).
                    $uploadDir = dirname(__DIR__) . '/uploads/profiles/';

                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        $uploadError = 'The profile picture folder could not be created.';
                    } else {
                        $filename = 'profile_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowedMimes[$mime];
                        $destination = $uploadDir . $filename;

                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            $newImagePath = 'uploads/profiles/' . $filename;
                            $profileImage = $newImagePath;
                        } else {
                            $uploadError = 'Unable to save the profile picture.';
                        }
                    }
                }
            }

            if ($uploadError !== '') {
                $message = $uploadError;
                $messageType = 'danger';
            }
        }

        if ($message === '') {
            try {
                $update = $pdo->prepare(
                    'UPDATE users
                     SET username = :username,
                         fullname = :fullname,
                         email = :email,
                         phone_number = :phone_number,
                         profile_image = :profile_image
                     WHERE id = :id'
                );
                $update->execute([
                    ':username' => $username,
                    ':fullname' => $fullname !== '' ? $fullname : null,
                    ':email' => $email !== '' ? $email : null,
                    ':phone_number' => $phoneNumber !== '' ? $phoneNumber : null,
                    ':profile_image' => $profileImage !== '' ? $profileImage : null,
                    ':id' => $userId,
                ]);

                // Keep the session username in sync with the database.
                $_SESSION['username'] = $username;
                $user = loadUser($pdo, $userId);

                $message = 'Profile updated successfully.';
                $messageType = 'success';
            } catch (Throwable $ex) {
                error_log('Profile update error: ' . $ex->getMessage());
                $message = 'Unable to update your profile. Check the server logs if the problem continues.';
                $messageType = 'danger';

                // Remove a newly uploaded image if the database update failed.
                if ($newImagePath !== null) {
                    $newImageFullPath = dirname(__DIR__) . '/' . $newImagePath;
                    if (is_file($newImageFullPath)) {
                        @unlink($newImageFullPath);
                    }
                }
            }
        }

        // Keep the form populated with the submitted values after validation errors.
        if ($messageType === 'danger') {
            $user['username'] = $username;
            $user['fullname'] = $fullname;
            $user['email'] = $email;
            $user['phone_number'] = $phoneNumber;
        }
    }
}

$role = (string) ($user['role'] ?? 'User');
$imagePath = trim((string) ($user['profile_image'] ?? ''));
$profileImageUrl = $imagePath !== ''
    ? '../' . ltrim($imagePath, '/')
    : '../assets/no-image.jpg';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile | R. Taipan Hardware</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --navy: #001f4d; --blue: #0d6efd; }
        body { margin: 0; background: #f3f6fa; color: #1f2937; overflow-x: hidden; }
        .sidebar {
            position: fixed; inset: 0 auto 0 0; width: 260px; height: 100vh;
            padding: 22px 16px; background: var(--navy); overflow-y: auto;
            display: flex; flex-direction: column; z-index: 1040;
            transition: transform .25s ease;
        }
        .sidebar h2 { color: #fff; text-align: center; font-size: 1.45rem; font-weight: 750; line-height: 1.3; margin: 8px 0 32px; }
        .sidebar a { display: block; color: #fff; text-decoration: none; padding: 11px 13px; border-radius: 8px; margin-bottom: 6px; font-size: .94rem; }
        .sidebar a i { width: 23px; margin-right: 5px; }
        .sidebar a:hover, .sidebar a.active { background: var(--blue); }
        .logout-section { margin-top: auto; padding-top: 14px; border-top: 1px solid #ffffff33; }
        .main-content { margin-left: 260px; padding: 28px; min-height: 100vh; }
        .profile-card { max-width: 850px; margin: 0 auto; background: #fff; border-radius: 16px; padding: clamp(20px, 4vw, 36px); box-shadow: 0 8px 30px #0b254510; }
        .profile-avatar { width: 86px; height: 86px; object-fit: cover; border-radius: 50%; border: 3px solid #e3edff; background: #f1f5f9; }
        .form-label { font-weight: 600; font-size: .9rem; }
        .form-control { min-height: 44px; }
        .section-heading { font-size: .9rem; font-weight: 750; letter-spacing: .06em; color: #64748b; text-transform: uppercase; }
        .account-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; }
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 18px 12px; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark d-lg-none px-3" style="background:var(--navy)">
    <span class="navbar-brand fw-bold fs-6"><i class="bi bi-person-badge me-2"></i>My Profile</span>
    <button class="btn btn-outline-light btn-sm" type="button" id="sidebarToggle" aria-label="Toggle navigation">
        <i class="bi bi-list fs-4"></i>
    </button>
</nav>

<aside class="sidebar" id="appSidebar">
    <h2>R. Taipan<br>Admin Dashboard</h2>
    <a href="dashboard.php"><i class="bi bi-grid"></i>Dashboard</a>
    <a href="products.php"><i class="bi bi-box-seam"></i>Inventory</a>
    <a href="orders.php"><i class="bi bi-receipt"></i>Orders</a>
    <a href="ItemsRequest.php"><i class="bi bi-arrow-return-left"></i>Customer Service Request</a>
    <a href="damageItems.php"><i class="bi bi-exclamation-triangle"></i>Damaged Items</a>
    <a href="accountManagement.php"><i class="bi bi-people"></i>Account Management</a>
    <a href="profile.php" class="active" aria-current="page"><i class="bi bi-person-badge"></i>My Profile</a>
    <div class="logout-section">
        <a href="../logout.php" class="text-warning" onclick="return confirm('Are you sure you want to log out?')">
            <i class="bi bi-box-arrow-right"></i>Logout
        </a>
    </div>
</aside>

<main class="main-content">
    <div class="profile-card">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show" role="alert">
                <?= e($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex align-items-center gap-3 mb-4 pb-4 border-bottom">
            <img
                src="<?= e($profileImageUrl) ?>"
                class="profile-avatar"
                alt="Profile picture"
                onerror="this.onerror=null;this.src='../assets/no-image.jpg';"
            >
            <div>
                <h1 class="h4 fw-bold mb-1">My Profile</h1>
                <p class="text-secondary mb-2">Manage your personal information and account details.</p>
                <span class="badge text-bg-success">Active Session</span>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

            <h2 class="section-heading mb-3">Personal information</h2>

            <div class="mb-3">
                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= e($user['username'] ?? '') ?>" maxlength="50" autocomplete="username" required>
            </div>

            <div class="mb-3">
                <label for="fullname" class="form-label">Full name</label>
                <input type="text" class="form-control" id="fullname" name="fullname"
                       value="<?= e($user['fullname'] ?? '') ?>" maxlength="150" autocomplete="name">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e($user['email'] ?? '') ?>" maxlength="254" autocomplete="email">
            </div>

            <div class="mb-4">
                <label for="phone_number" class="form-label">Contact number</label>
                <input type="tel" class="form-control" id="phone_number" name="phone_number"
                       value="<?= e($user['phone_number'] ?? '') ?>" maxlength="20"
                       placeholder="e.g. 09XXXXXXXXX" autocomplete="tel">
            </div>

            <div class="mb-4">
                <label for="profile_image" class="form-label">Profile picture (optional)</label>
                <input type="file" class="form-control" id="profile_image" name="profile_image"
                       accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, or WEBP. Maximum file size: 2 MB.</div>
            </div>

            <h2 class="section-heading mb-3">Account information</h2>
            <div class="account-summary row g-3 mb-4">
                <div class="col-sm-6">
                    <div class="text-secondary small">Account ID</div>
                    <div class="fw-semibold">#<?= e($user['id'] ?? '') ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-secondary small">Role</div>
                    <div class="fw-semibold text-uppercase"><?= e($role) ?></div>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-2">
                <button type="submit" name="update_profile" value="1" class="btn btn-primary px-4">
                    <i class="bi bi-check2-circle me-1"></i>Save Changes
                </button>
                <button type="reset" class="btn btn-outline-secondary px-4">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Form
                </button>
            </div>
        </form>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });

        document.addEventListener('click', function (event) {
            if (window.innerWidth < 992 &&
                !sidebar.contains(event.target) &&
                !toggle.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
    }
});
</script>
</body>
</html>
