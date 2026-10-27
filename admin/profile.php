<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f4f6f9;
            margin: 0;
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: #001f4d;
            padding: 20px;
            overflow-y: auto;
            z-index: 1040;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }

        .sidebar h2 {
            color: white;
            text-align: center;
            font-weight: bold;
            margin-bottom: 40px;
            line-height: 1.3;
            font-size: 1.5rem;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: 0.3s;
            font-size: 15px;
            display: block;
            font-weight: 500;
        }

        .sidebar a i {
            margin-right: 8px;
        }

        .sidebar a:hover {
            background: #0d6efd;
            padding-left: 20px;
        }

        .sidebar .active {
            background: #0d6efd;
        }

        .logout-section {
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            padding: 25px;
            min-height: 100vh;
            transition: margin 0.3s ease;
        }

        /* PROFILE CONTAINER - Ginawa nang kapareho ng kulay ng sidebar (#001f4d) */
        .profile-container {
            background: #001f4d;
            color: #fff;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .profile-img-lg {
            width: 75px;
            height: 75px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #0d6efd;
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
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark bg-dark d-lg-none px-3">
        <span class="navbar-brand fw-bold fs-6"><i class="bi bi-person-badge"></i> My Profile</span>
        <button class="btn btn-outline-light btn-sm" type="button" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="appSidebar">
        <h2>Admin<br>Dashboard</h2>
        <a href="dashboard.php"><i class="bi bi-grid"></i> Dashboard</a>
        <a href="products.php"><i class="bi bi-box-seam"></i> Inventory</a>
        <a href="orders.php"><i class="bi bi-receipt"></i> Orders</a>
        <a href="ItemsRequest.php"><i class="bi bi-arrow-return-left"></i> Customer Service request</a>
        <a href="damageItems.php"><i class="bi bi-exclamation-triangle"></i> Damaged Items</a>
        <a href="accountManagement.php"><i class="bi bi-person-circle"></i> Account Management</a>
        <a href="profile.php" class="active"><i class="bi bi-person-badge"></i> My Profile</a>
        <div class="logout-section">
            <a href="../logout.php" class="text-danger" onclick="return confirm('Are you sure you want to logout?')">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="container-fluid" style="max-width: 800px;">

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show mb-4" role="alert">
                    <?= htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                
                <!-- Header Section -->
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-light border-opacity-25">
                    <img src="../<?= !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'assets/no-image.jpg'; ?>" class="profile-img-lg shadow-sm" alt="Profile">
                    <div>
                        <h4 class="fw-bold mb-1 text-white">My Profile</h4>
                        <p class="text-white-50 small mb-1">Manage your personal information and account security.</p>
                        <span class="badge bg-success text-white fw-bold px-2 py-1" style="font-size: 0.75rem;">Active Account</span>
                    </div>
                </div>

                <form action="" method="POST" enctype="multipart/form-data">
                    <h5 class="fw-bold mb-3 fs-6 text-uppercase text-white-50">Personal Information</h5>

                    <!-- Username -->
                    <div class="mb-3">
                        <label class="form-label small text-white-50">Username</label>
                        <div class="input-group-custom">
                            <input type="text" name="username" id="inputUsername" class="form-control" value="<?= htmlspecialchars($user['username']); ?>" readonly required>
                            <button type="button" class="edit-icon-btn" onclick="enableField('inputUsername')">
                               [edit]
                            </button>
                        </div>
                    </div>

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label class="form-label small text-white-50">Full Name</label>
                        <div class="input-group-custom">
                            <input type="text" name="fullname" id="inputFullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? ''); ?>" placeholder="Enter your full name" readonly>
                            <button type="button" class="edit-icon-btn" onclick="enableField('inputFullname')">
                                [edit]
                            </button>
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="mb-3">
                        <label class="form-label small text-white-50">Email Address</label>
                        <div class="input-group-custom">
                            <input type="email" name="email" id="inputEmail" class="form-control" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Enter your email address" readonly>
                            <button type="button" class="edit-icon-btn" onclick="enableField('inputEmail')">
                                [edit]
                            </button>
                        </div>
                    </div>

                    <!-- Contact Number -->
                    <div class="mb-3">
                        <label class="form-label small text-white-50">Contact Number</label>
                        <div class="input-group-custom">
                            <input type="text" name="phone_number" id="inputPhone" class="form-control" value="<?= htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="09XXXXXXXXX" readonly>
                            <button type="button" class="edit-icon-btn" onclick="enableField('inputPhone')">
                                [edit]
                            </button>
                        </div>
                    </div>

                    <!-- Profile Picture Upload (Optional) -->
                    <div class="mb-4">
                        <label class="form-label small text-white-50">Change Profile Picture</label>
                        <input type="file" name="profile_image" class="form-control text-white border-light border-opacity-25" accept=".jpg, .jpeg, .png, .webp" style="height: auto; padding: 10px; background-color: rgba(255,255,255,0.08);">
                    </div>

                    <!-- Account Role & Status Info (Read-only) -->
                    <div class="row g-2 mb-4 p-3 rounded border border-light border-opacity-25 text-white-50 small" style="background-color: rgba(0,0,0,0.2);">
                        <div class="col-6">
                            <strong>Account Role:</strong> <span class="text-info text-uppercase"><?= htmlspecialchars($user['role'] ?? 'Admin'); ?></span>
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

    <!-- Scripts para sa Sidebar Toggle at pag-enable ng input fields kapag pinindot ang edit logo -->
    <script>
        function enableField(fieldId) {
            const field = document.getElementById(fieldId);
            field.removeAttribute('readonly');
            field.focus();
        }

        document.addEventListener("DOMContentLoaded", function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const appSidebar = document.getElementById('appSidebar');
            if (sidebarToggle && appSidebar) {
                sidebarToggle.addEventListener('click', function () {
                    appSidebar.classList.toggle('show');
                });

                document.addEventListener('click', function (event) {
                    if (window.innerWidth < 992) {
                        if (!appSidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                            appSidebar.classList.remove('show');
                        }
                    }
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>