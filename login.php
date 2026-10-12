<?php
session_start();
require_once __DIR__ . '/config/db.php';

$error = "";

// FUNCTION PARA SA AUDIT TRAIL LOGGING
function logAction($conn, $user_id, $username, $role, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_id = (int)$user_id;
    $username = mysqli_real_escape_string($conn, $username);
    $role = mysqli_real_escape_string($conn, $role);
    $action = mysqli_real_escape_string($conn, $action);
    $details = mysqli_real_escape_string($conn, $details);

    $sql = "INSERT INTO audit_log (user_id, username, role, action, details, ip_address) 
            VALUES ($user_id, '$username', '$role', '$action', '$details', '$ip')";
    mysqli_query($conn, $sql);
}

if (isset($_POST['login'])) {

    // Clean input
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password']);

    // Get user by username
    $query = "SELECT * FROM users WHERE username = '$username' AND status = 'Active' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) == 1) {

        $user = mysqli_fetch_assoc($result);

        // VERIFY HASH PASSWORD
        if (password_verify($password, $user['password'])) {

            // Prevent session fixation
            session_regenerate_id(true);

            // SESSION DATA
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // >>> DITO NATIN TINAWAG ANG AUDIT TRAIL PAHINA PAGKALOGIN <<<
            $details = "User successfully logged into the system";
            logAction($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], "Login", $details);

            // ROUTING BASED ON ROLE
            if ($user['role'] == 'owner') {
                header("Location: owner/dashboard.php");
                exit();
            } elseif ($user['role'] == 'admin') {
                header("Location: admin/dashboard.php");
                exit();
            } elseif ($user['role'] == 'cashier') {
                header("Location: cashier/dashboard.php");
                exit();
            } else {
                header("Location: dashboard.php");
                exit();
            }

        } else {
            $error = "Invalid username or password";
        }

    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
    body {
        background-image: url('assets/R.png'); 
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        padding: 20px;
    }

    .card {
        background-color: rgba(199, 209, 229, 0.95) !important;
        border-radius: 20px;
        border: none;
        width: 100%;
        max-width: 450px;
        padding: 40px !important;
        margin: auto;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2) !important;
    }

    .form-control {
        padding: 12px 15px;
        font-size: 16px;
    }

    /* Pagtugma ng background ng eye button sa textfield */
    .input-group .btn-outline-secondary {
        background-color: #fff;
        border-color: #ced4da;
        color: #6c757d;
    }
    
    .input-group .btn-outline-secondary:hover {
        background-color: #e9ecef;
        border-color: #ced4da;
        color: #495057;
    }

    .btn {
        padding: 12px;
        font-size: 18px;
        font-weight: 600;
    }
</style>
</head>
<body>
    <div class="container">
        <div class="card shadow p-4">
            <div class="text-center mb-3">
                <img src="assets/logo.jpg"
                     style="width:70px; height:70px; border-radius:50%; object-fit:cover; border:2px solid #1f1b38;">
                <h4 class="mt-3 mb-1">R. Taipan Hardware</h4>
                <small class="text-muted">Please login to continue</small>
            </div>

            <h3 class="text-center mb-3">Login</h3>

            <?php if ($error != "") { ?>
                <div class="alert alert-danger text-center">
                    <?php echo $error; ?>
                </div>
            <?php } ?>

            <form method="POST" action="login.php">
                <div class="mb-2">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="mb-3">
                    <div class="input-group">
                        <input type="password" name="password" id="passwordField" class="form-control" placeholder="Password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>
                <button class="btn w-100 text-white" name="login" style="background:#051c3c;">
                    Login
                </button>
                <div class="forgot-password mt-2">
                    <a href="forgotPassword.php">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const passwordField = document.getElementById('passwordField');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');

        if (togglePasswordBtn && passwordField && togglePasswordIcon) {
            togglePasswordBtn.addEventListener('click', function () {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                togglePasswordIcon.classList.toggle('bi-eye');
                togglePasswordIcon.classList.toggle('bi-eye-slash');
            });
        }
    </script>
</body>
</html>