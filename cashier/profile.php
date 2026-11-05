<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier' || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$msg_type = "";

// Kunin ang detalye ng user mula sa database
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

// UPDATE PROFILE LOGIC
if (isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $profile_image = $user['profile_image'] ?? ''; 

    // Logic para sa pag-remove ng Profile Picture
    if (isset($_POST['remove_profile_image']) && $_POST['remove_profile_image'] == '1') {
        $profile_image = ''; // Gagawing empty para bumalik sa default
    } 
    // Logic para sa pag-upload ng bagong picture (Base64 Encoding para manatili sa DB)
    elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['profile_image']['tmp_name'];
        if (!empty($tmp) && file_exists($tmp)) {
            $check_image = getimagesize($tmp);
            if ($check_image !== false) {
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($ext, $allowed)) {
                    $mime_type = mime_content_type($tmp);
                    $file_data = file_get_contents($tmp);
                    // I-save bilang Base64 Data URI o local path fallback
                    $profile_image = 'data:' . $mime_type . ';base64,' . base64_encode($file_data);
                }
            }
        }
    }

    try {
        $update_stmt = $pdo->prepare("UPDATE users SET username = :username, fullname = :fullname, email = :email, phone_number = :phone_number, profile_image = :image WHERE id = :id");
        $update_stmt->execute([
            ':username' => $new_username,
            ':fullname' => $fullname,
            ':email' => $email,
            ':phone_number' => $phone_number,
            ':image' => $profile_image,
            ':id' => $user_id
        ]);

        $_SESSION['username'] = $new_username; 
        if (!empty($fullname)) {
            $_SESSION['fullname'] = $fullname;
        }
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

// Built-in Default Avatar (SVG Data URI - Hindi na kailangan ng physical file)
$default_avatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='%23001f4d'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z'/></svg>";

$user_display_photo = $default_avatar;

if (!empty($user['profile_image'])) {
    // Kung Base64
    if (stripos($user['profile_image'], 'data:image/') === 0) {
        $user_display_photo = $user['profile_image'];
    } 
    // Kung local file path at umiiral pa sa server
    else {
        $photo_path = __DIR__ . '/../' . $user['profile_image'];
        if (file_exists($photo_path)) {
            $user_display_photo = '../' . htmlspecialchars($user['profile_image']);
        }
    }
}

// Name to display on top
$display_name = !empty($user['fullname']) ? $user['fullname'] : $user['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Cashier Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f4f6f9;
            margin: 0;
            overflow-x: hidden;
        }

        /* MAIN CONTENT */
        .main-content {
            padding: 25px;
            min-height: 100vh;
        }

        .top-bar {
            width: 100%;
            max-width: none;
            margin: 0 0 18px;
        }

        /* PROFILE CONTAINER */
        .profile-container {
            background: #001f4d;
            color: #fff;
            border-radius: 12px;
            padding: 35px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            width: 100%;
        }

        .profile-img-lg {
            width: 75px;
            height: 75px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #0d6efd;
            background-color: #fff;
        }

        /* Input fields container */
        .input-group-custom {
            position: relative;
        }

        .input-group-custom input {
            background-color: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            border-radius: 8px;
            padding-right: 95px;
            height: 48px;
        }

        .input-group-custom input:focus {
            background-color: rgba(255, 255, 255, 0.12);
            color: #fff;
            border-color: #0d6efd;
            box-shadow: none;
        }

        .edit-icon-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #ffadb0;
            cursor: pointer;
            z-index: 10;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .edit-icon-btn:hover {
            color: #fff;
        }

        @media (max-width: 991.98px) {
            .main-content {
                padding: 15px;
            }
            .profile-container {
                padding: 22px;
            }
        }

        @media (max-width: 575.98px) {
            .profile-container {
                padding: 18px;
            }
            .profile-img-lg {
                width: 62px;
                height: 62px;
            }
        }
    </style>
</head>

<body>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-person-badge me-2"></i>My Profile</h4>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="bi bi-arrow-left me-1"></i> Back to POS
            </a>
        </div>
        <div class="container-fluid px-0">

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show mb-4" role="alert">
                    <?= htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                
                <!-- Header Section -->
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-light border-opacity-25">
                    <img src="<?= $user_display_photo; ?>" class="profile-img-lg shadow-sm" alt="Profile Picture">
                    <div>
                        <h4 class="fw-bold mb-1 text-white"><?= htmlspecialchars($display_name); ?></h4>
                        <p class="text-white-50 small mb-1">Manage your personal information and account security.</p>
                        <span class="badge bg-success text-white fw-bold px-2 py-1" style="font-size: 0.75rem;">Active Account</span>
                    </div>
                </div>

                <form action="" method="POST" enctype="multipart/form-data">
                    <h5 class="fw-bold mb-3 fs-6 text-uppercase text-white-50">Personal Information</h5>

                    <div class="row">
                        <!-- Username -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-white-50">Username</label>
                            <div class="input-group-custom">
                                <input type="text" name="username" id="inputUsername" class="form-control" value="<?= htmlspecialchars($user['username']); ?>" readonly required>
                                <button type="button" class="edit-icon-btn" onclick="enableField('inputUsername')">
                                    [edit]
                                </button>
                            </div>
                        </div>

                        <!-- Full Name -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-white-50">Full Name</label>
                            <div class="input-group-custom">
                                <input type="text" name="fullname" id="inputFullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? ''); ?>" placeholder="Enter your full name" readonly>
                                <button type="button" class="edit-icon-btn" onclick="enableField('inputFullname')">
                                    [edit]
                                </button>
                            </div>
                        </div>

                        <!-- Email Address -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-white-50">Email Address</label>
                            <div class="input-group-custom">
                                <input type="email" name="email" id="inputEmail" class="form-control" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Enter your email address" readonly>
                                <button type="button" class="edit-icon-btn" onclick="enableField('inputEmail')">
                                    [edit]
                                </button>
                            </div>
                        </div>

                        <!-- Contact Number -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-white-50">Contact Number</label>
                            <div class="input-group-custom">
                                <input type="text" name="phone_number" id="inputPhone" class="form-control" value="<?= htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="09XXXXXXXXX" readonly>
                                <button type="button" class="edit-icon-btn" onclick="enableField('inputPhone')">
                                    [edit]
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Change Profile Picture and Remove Option -->
                    <div class="mb-4">
                        <label class="form-label small text-white-50">Change Profile Picture</label>
                        <input type="file" name="profile_image" class="form-control text-white border-light border-opacity-25 mb-2" accept=".jpg, .jpeg, .png, .webp" style="height: auto; padding: 10px; background-color: rgba(255,255,255,0.08);">
                        
                        <!-- Checkbox para sa pag-remove ng profile picture papuntang default photo -->
                        <?php if (!empty($user['profile_image'])): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_profile_image" value="1" id="removePhotoCheck">
                                <label class="form-check-label small text-danger fw-bold" for="removePhotoCheck">
                                    Remove profile picture (revert to default)
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Account Role & Status Info (Read-only) -->
                    <div class="row g-2 mb-4 p-3 rounded border border-light border-opacity-25 text-white-50 small" style="background-color: rgba(0,0,0,0.2);">
                        <div class="col-6">
                            <strong>Account Role:</strong> <span class="text-info text-uppercase"><?= htmlspecialchars($user['role'] ?? 'Cashier'); ?></span>
                        </div>
                        <div class="col-6">
                            <strong>Account ID:</strong> <span class="text-light">#<?= $user['id']; ?></span>
                        </div>
                    </div>

                    <!-- Save Changes Button -->
                    <button type="submit" name="update_profile" class="btn btn-light w-100 fw-bold py-2 shadow-sm">Save Changes</button>
                </form>

            </div>
        </div>
    </div>

    <script>
        function enableField(fieldId) {
            const field = document.getElementById(fieldId);
            field.removeAttribute('readonly');
            field.focus();
        }


    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>