<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Siguraduhing naka-login ang user
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$msg_type = "";

// Kunin ang kasalukuyang detalye ng user mula sa database
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Fetch user error: " . $e->getMessage());
    die("Database error occurred.");
}

// 1. UPDATE PROFILE (Username & Picture)
if (isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $profile_image = $user['profile_image'] ?? ''; // Lumang litrato

    // I-check kung may in-upload na bagong picture
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['profile_image']['tmp_name'];
        if (!empty($tmp) && file_exists($tmp)) {
            $check_image = getimagesize($tmp);
            if ($check_image !== false) {
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($ext, $allowed)) {
                    $new_filename = "profile_" . $user_id . "_" . time() . "." . $ext;
                    $folder = "../uploads/profiles/" . $new_filename;

                    if (!is_dir("../uploads/profiles/")) {
                        mkdir("../uploads/profiles/", 0777, true);
                    }

                    if (move_uploaded_file($tmp, $folder)) {
                        $profile_image = "uploads/profiles/" . $new_filename;
                    }
                }
            }
        }
    }

    try {
        $update_stmt = $pdo->prepare("UPDATE users SET username = :username, profile_image = :image WHERE id = :id");
        $update_stmt->execute([
            ':username' => $new_username,
            ':image' => $profile_image,
            ':id' => $user_id
        ]);

        $_SESSION['username'] = $new_username; // I-update din ang session username
        $message = "Profile updated successfully!";
        $msg_type = "success";

        // Refresh user data
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Error updating profile: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// 2. CHANGE PASSWORD LOGIC
if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // I-check kung tama ang current password (suportado ang password_hash o plain text depende sa DB ninyo, gamitin ang password_verify kung naka-hash)
    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $message = "All password fields are required!";
        $msg_type = "danger";
    } elseif ($new_pass !== $confirm_pass) {
        $message = "New passwords do not match!";
        $msg_type = "danger";
    } else {
        // Kung gumagamit kayo ng password_hash sa pag-login:
        if (password_verify($current_pass, $user['password']) || $current_pass === $user['password']) {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

            try {
                $pass_stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                $pass_stmt->execute([
                    ':password' => $hashed_pass,
                    ':id' => $user_id
                ]);
                $message = "Password changed successfully!";
                $msg_type = "success";
            } catch (PDOException $e) {
                $message = "Error updating password.";
                $msg_type = "danger";
            }
        } else {
            $message = "Incorrect current password!";
            $msg_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .profile-img { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 3px solid #0d6efd; }
    </style>
</head>
<body>

<div class="container py-5" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-person-circle text-primary"></i> My Profile</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ACCOUNT INFO CARD -->
        <div class="col-md-4 text-center">
            <div class="card shadow-sm border-0 p-4">
                <div class="mb-3">
                    <img src="../<?= !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'assets/no-image.jpg'; ?>" class="profile-img shadow-sm" alt="Profile Picture">
                </div>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['username']); ?></h5>
                <p class="text-muted small text-uppercase mb-3"><span class="badge bg-primary"><?= htmlspecialchars($user['role'] ?? 'User'); ?></span></p>
                <hr>
                <div class="text-start small">
                    <p class="mb-1"><strong>Account Status:</strong> <span class="text-success fw-bold">Active</span></p>
                    <p class="mb-0"><strong>Account ID:</strong> #<?= $user['id']; ?></p>
                </div>
            </div>
        </div>

        <!-- EDIT PROFILE & CHANGE PASSWORD FORMS -->
        <div class="col-md-8">
            <!-- Edit Profile -->
            <div class="card shadow-sm border-0 p-4 mb-4">
                <h5 class="fw-bold mb-3 text-primary"><i class="bi bi-pencil-square"></i> Edit Profile Information</h5>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Profile Picture (Optional)</label>
                        <input type="file" name="profile_image" class="form-control" accept=".jpg, .jpeg, .png, .webp">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary w-100">Save Changes</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card shadow-sm border-0 p-4">
                <h5 class="fw-bold mb-3 text-danger"><i class="bi bi-key"></i> Change Password</h5>
                <form action="" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-danger w-100">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>