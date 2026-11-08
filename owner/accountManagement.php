<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// OWNER ACCESS ONLY
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}

$owner_name = $_SESSION['username'] ?? 'owner';
$currentOwnerId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['owner_id'] ?? 0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$success = '';
$error = '';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// HANDLE ACTIONS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_account') {
            $username = trim($_POST['username'] ?? '');
            $fullname = trim($_POST['fullname'] ?? '');
            $phone_number = trim($_POST['phone_number'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $role = strtolower(trim($_POST['role'] ?? ''));
            $allowedRoles = ['admin', 'cashier', 'owner'];

            if ($username === '' || $fullname === '' || $phone_number === '' || $email === '' || $password === '' || $role === '') {
                $error = "Please fill in all required fields.";
            } elseif (mb_strlen($username) > 100) {
                $error = "Username must not exceed 100 characters.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } elseif (!in_array($role, $allowedRoles, true)) {
                $error = "Invalid account role.";
            } elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters long.";
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $check->execute([$username, $email]);
                $exists = $check->fetch();

                if ($exists) {
                    $error = "Username or email already exists.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (username, fullname, phone_number, email, password, role, status)
                         VALUES (?, ?, ?, ?, ?, ?, 'Active')"
                    );
                    
                    if ($stmt->execute([$username, $fullname, $phone_number, $email, $hashedPassword, $role])) {
                        $success = "Account successfully created.";

                        // ─── AUDIT TRAIL LOG FOR ADD ACCOUNT ───
                        $auditAction = "Add Account";
                        $details = "Owner created a new account: {$username} ({$role})";
                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

                        $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, username, role, action, details, ip_address) VALUES (?, ?, 'owner', ?, ?, ?)");
                        if ($auditStmt) {
                            $auditStmt->execute([$currentOwnerId > 0 ? $currentOwnerId : null, $owner_name, $auditAction, $details, $ipAddress]);
                        }
                    } else {
                        $error = "Failed to create account. Please try again.";
                    }
                }
            }
        } elseif ($action === 'reset_password') {
            $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);

            if (!$userId || $userId <= 0) {
                $error = "Invalid account selected.";
            } else {
                $check = $pdo->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
                $check->execute([$userId]);
                $account = $check->fetch(PDO::FETCH_ASSOC);

                if (!$account) {
                    $error = "Account not found.";
                } else {
                    $targetUsername = $account['username'];
                    $defaultPasswordHash = password_hash('password', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    
                    if ($stmt->execute([$defaultPasswordHash, $userId])) {
                        $success = "Password for account '{$targetUsername}' has been successfully reset to 'password'.";

                        // ─── AUDIT TRAIL LOG FOR RESET PASSWORD ───
                        $auditAction = "Reset Password";
                        $details = "Owner reset password to default for account: {$targetUsername} (ID: {$userId})";
                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

                        $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, username, role, action, details, ip_address) VALUES (?, ?, 'owner', ?, ?, ?)");
                        if ($auditStmt) {
                            $auditStmt->execute([$currentOwnerId > 0 ? $currentOwnerId : null, $owner_name, $auditAction, $details, $ipAddress]);
                        }
                    } else {
                        $error = "Failed to reset password.";
                    }
                }
            }
        } elseif ($action === 'archive_account') {
            $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);

            if (!$userId || $userId <= 0) {
                $error = "Invalid account selected.";
            } elseif ($currentOwnerId > 0 && $userId === $currentOwnerId) {
                $error = "You cannot archive your own account.";
            } else {
                $check = $pdo->prepare("SELECT id, username, status FROM users WHERE id = ? LIMIT 1");
                $check->execute([$userId]);
                $account = $check->fetch(PDO::FETCH_ASSOC);

                if (!$account) {
                    $error = "Account not found.";
                } elseif ($account['status'] === 'Archived') {
                    $error = "This account is already archived.";
                } else {
                    $targetUsername = $account['username'];
                    $stmt = $pdo->prepare("UPDATE users SET status = 'Archived' WHERE id = ?");
                    
                    if ($stmt->execute([$userId])) {
                        $success = "Account successfully archived.";

                        // ─── AUDIT TRAIL LOG FOR ARCHIVE ACCOUNT ───
                        $auditAction = "Archive Account";
                        $details = "Owner archived account: {$targetUsername} (ID: {$userId})";
                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

                        $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, username, role, action, details, ip_address) VALUES (?, ?, 'owner', ?, ?, ?)");
                        if ($auditStmt) {
                            $auditStmt->execute([$currentOwnerId > 0 ? $currentOwnerId : null, $owner_name, $auditAction, $details, $ipAddress]);
                        }
                    } else {
                        $error = "Failed to archive account.";
                    }
                }
            }
        } elseif ($action === 'unarchive_account') {
            $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);

            if (!$userId || $userId <= 0) {
                $error = "Invalid account selected.";
            } else {
                $check = $pdo->prepare("SELECT id, username, status FROM users WHERE id = ? LIMIT 1");
                $check->execute([$userId]);
                $account = $check->fetch(PDO::FETCH_ASSOC);

                if (!$account) {
                    $error = "Account not found.";
                } elseif ($account['status'] === 'Active') {
                    $error = "This account is already active.";
                } else {
                    $targetUsername = $account['username'];
                    $stmt = $pdo->prepare("UPDATE users SET status = 'Active' WHERE id = ?");
                    
                    if ($stmt->execute([$userId])) {
                        $success = "Account successfully unarchived and restored to active status.";

                        // ─── AUDIT TRAIL LOG FOR UNARCHIVE ACCOUNT ───
                        $auditAction = "Unarchive Account";
                        $details = "Owner unarchived account: {$targetUsername} (ID: {$userId})";
                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

                        $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, username, role, action, details, ip_address) VALUES (?, ?, 'owner', ?, ?, ?)");
                        if ($auditStmt) {
                            $auditStmt->execute([$currentOwnerId > 0 ? $currentOwnerId : null, $owner_name, $auditAction, $details, $ipAddress]);
                        }
                    } else {
                        $error = "Failed to unarchive account.";
                    }
                }
            }
        } else {
            $error = "Invalid action.";
        }
    }
}

// ACTIVE / ARCHIVED FILTER
$tab = $_GET['tab'] ?? 'active';
if (!in_array($tab, ['active', 'archived'], true)) {
    $tab = 'active';
}
$statusFilter = $tab === 'archived' ? 'Archived' : 'Active';
$search = trim($_GET['search'] ?? '');

// HANDLE AJAX LIVE SEARCH REQUEST
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if ($search !== '') {
        $searchTerm = '%' . $search . '%';
        $stmt = $pdo->prepare(
            "SELECT id, username, email, role, status
             FROM users
             WHERE status = ? AND (username LIKE ? OR email LIKE ? OR role LIKE ?)
             ORDER BY id DESC"
        );
        $stmt->execute([$statusFilter, $searchTerm, $searchTerm, $searchTerm]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, username, email, role, status
             FROM users WHERE status = ? ORDER BY id DESC"
        );
        $stmt->execute([$statusFilter]);
    }
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($accounts) > 0):
        foreach ($accounts as $account):
            ?>
            <tr>
                <td><?= (int)$account['id'] ?></td>
                <td><strong><?= e($account['username']) ?></strong></td>
                <td><?= e($account['email'] ?? '') ?></td>
                <td><span class="role-badge"><?= e($account['role']) ?></span></td>
                <td>
                    <?php if ($account['status'] === 'Active'): ?>
                        <span class="status-badge status-active">Active</span>
                    <?php else: ?>
                        <span class="status-badge status-archived">Archived</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button type="button" class="btn btn-warning-custom btn-sm fw-bold"
                            data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                            data-id="<?= (int)$account['id'] ?>"
                            data-username="<?= e($account['username']) ?>">
                        <i class="bi bi-key me-1"></i>Reset Password
                    </button>
                </td>
                <td>
                    <?php if ($tab === 'active'): ?>
                        <?php if ($currentOwnerId <= 0 || (int)$account['id'] !== $currentOwnerId): ?>
                            <button type="button" class="btn btn-archive"
                                    data-bs-toggle="modal" data-bs-target="#archiveModal"
                                    data-id="<?= (int)$account['id'] ?>"
                                    data-username="<?= e($account['username']) ?>">
                                <i class="bi bi-archive me-1"></i>Archive
                            </button>
                        <?php else: ?>
                            <span class="text-muted small">Current Account</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <button type="button" class="btn btn-unarchive"
                                data-bs-toggle="modal" data-bs-target="#unarchiveModal"
                                data-id="<?= (int)$account['id'] ?>"
                                data-username="<?= e($account['username']) ?>">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Unarchive
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        endforeach;
    else:
        ?>
        <tr>
            <td colspan="7">
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h6>No accounts found</h6>
                    <p class="mb-0">There are no <?= e($tab) ?> accounts to display.</p>
                </div>
            </td>
        </tr>
        <?php
    endif;
    exit(); 
}

// INITIAL PAGE LOAD QUERY
if ($search !== '') {
    $searchTerm = '%' . $search . '%';
    $stmt = $pdo->prepare(
        "SELECT id, username, email, role, status
         FROM users
         WHERE status = ? AND (username LIKE ? OR email LIKE ? OR role LIKE ?)
         ORDER BY id DESC"
    );
    $stmt->execute([$statusFilter, $searchTerm, $searchTerm, $searchTerm]);
} else {
    $stmt = $pdo->prepare(
        "SELECT id, username, email, role, status
         FROM users WHERE status = ? ORDER BY id DESC"
    );
    $stmt->execute([$statusFilter]);
}
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countActiveResult = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE status = 'Active'");
$countArchivedResult = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE status = 'Archived'");
$countActive = (int)($countActiveResult->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$countArchived = (int)($countArchivedResult->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management | R. Taipan Owner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            margin: 0;
            overflow-x: hidden;
            background: #f8f9fa;
            font-family: Arial, sans-serif;
        }

        /* NAVBAR */
        .navbar {
            height: 56px;
            z-index: 1100;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            width: 260px;
            height: calc(100vh - 56px);
            background: #001f4d;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 15px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
            z-index: 1040;
        }
        .sidebar-link {
            color: #adb5bd;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 500;
            display: block;
            transition: 0.3s;
        }
        .sidebar-link i { margin-right: 8px; }
        .sidebar-link:hover { background: #0d6efd; color: #fff; padding-left: 20px; }
        .sidebar-link.active { background: #0d6efd; color: #fff; }
        .logout-section {
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #0d6efd; border-radius: 10px; }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            margin-top: 56px;
            padding: 25px;
            min-height: calc(100vh - 56px);
            transition: margin 0.3s ease;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .page-header h2 { font-size: 26px; font-weight: 700; color: #051c3c; margin: 0; }
        .page-header p { color: #6c757d; margin: 6px 0 0; font-size: 14px; }
        .btn-primary-custom {
            background: #051c3c;
            color: #fff;
            border: none;
            padding: 11px 18px;
            border-radius: 7px;
            font-weight: 600;
        }
        .btn-primary-custom:hover { background: #0b315f; color: #fff; }
        .btn-warning-custom {
            background: #ffc107;
            color: #000;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
        }
        .btn-warning-custom:hover { background: #e0a800; color: #000; }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
            border: 1px solid #e9ecef;
        }
        .stat-card h6 { color: #6c757d; font-size: 14px; margin-bottom: 10px; }
        .stat-card h3 { color: #051c3c; font-weight: 700; margin: 0; font-size: 28px; }
        .content-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        .tabs {
            display: flex;
            gap: 25px;
            padding: 18px 22px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .tabs a {
            text-decoration: none;
            color: #6c757d;
            padding-bottom: 14px;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }
        .tabs a.active { color: #051c3c; border-bottom-color: #051c3c; }
        .table-toolbar {
            padding: 20px 22px;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }
        .search-box { display: flex; gap: 8px; width: 100%; max-width: 430px; }
        .search-box input { border-radius: 7px; min-width: 0; }
        .table-responsive { width: 100%; }
        .table { margin: 0; min-width: 860px; }
        .table thead th {
            background: #f8f9fa;
            color: #495057;
            font-size: 13px;
            padding: 15px 18px;
            white-space: nowrap;
        }
        .table tbody td { padding: 16px 18px; vertical-align: middle; font-size: 14px; }
        .role-badge {
            display: inline-block; padding: 6px 11px; border-radius: 20px;
            background: #e7eef8; color: #051c3c; font-size: 12px;
            font-weight: 700; text-transform: capitalize;
        }
        .status-badge { display: inline-block; padding: 6px 11px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .status-active { background: #d1e7dd; color: #0f5132; }
        .status-archived { background: #e2e3e5; color: #41464b; }
        .btn-archive {
            background: #fff0f0; color: #b02a37; border: 1px solid #f1c2c7;
            padding: 7px 12px; border-radius: 6px; font-size: 13px; font-weight: 600;
        }
        .btn-archive:hover { background: #b02a37; color: #fff; }
        .btn-unarchive {
            background: #e7f1ff; color: #0d6efd; border: 1px solid #b6d4fe;
            padding: 7px 12px; border-radius: 6px; font-size: 13px; font-weight: 600;
        }
        .btn-unarchive:hover { background: #0d6efd; color: #fff; }
        .empty-state { padding: 45px 20px; text-align: center; color: #6c757d; }
        .empty-state i { font-size: 40px; display: block; margin-bottom: 12px; }
        .modal-header { background: #051c3c; color: #fff; border-bottom: none; }
        .modal-header .btn-close { filter: invert(1); }
        .form-label { font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 6px; }
        .form-control, .form-select { padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; }
        .form-control:focus, .form-select:focus { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15); }
        .alert { border-radius: 8px; }

        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 18px 15px; }
        }
        @media (max-width: 768px) {
            .page-header { align-items: stretch; }
            .page-header h2 { font-size: 22px; }
            .page-header .btn-primary-custom { width: 100%; }
            .stats-container { gap: 10px; }
            .stat-card { padding: 15px; }
            .stat-card h3 { font-size: 23px; }
            .tabs { gap: 18px; padding: 16px 15px 0; overflow-x: auto; }
            .table-toolbar { padding: 15px; }
            .search-box { max-width: 100%; }
            .table { min-width: 860px; }
        }
        @media (max-width: 400px) {
            .stats-container { grid-template-columns: 1fr; }
            .main-content { padding: 15px 10px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-dark px-3" style="background-color:#051c3c;">
    <div class="d-flex align-items-center w-100 justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <span class="navbar-brand fw-bold mb-0">Owner Panel</span>
        </div>
        <div class="d-flex align-items-center text-white gap-3">
            <span>
                Welcome, <strong><?= e($owner_name); ?></strong>
            </span>
            <button class="btn btn-outline-light btn-sm d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="bi bi-list fs-5"></i>
            </button>
        </div>
    </div>
</nav>

<!-- SIDEBAR -->
<div class="sidebar" id="appSidebar">
    <a href="dashboard.php" class="sidebar-link">
        <i class="bi bi-graph-up-arrow"></i> Dashboard
    </a>
    <a href="sales.php" class="sidebar-link">
        <i class="bi bi-cash-stack"></i> Sales
    </a>
    <a href="inventory.php" class="sidebar-link">
        <i class="bi bi-box-seam"></i> Inventory
    </a>
    <a href="orders.php" class="sidebar-link">
        <i class="bi bi-clipboard-check"></i> Order Mgt. Monitoring
    </a>
    <a href="history.php" class="sidebar-link">
        <i class="bi bi-clock-history"></i> History
    </a>
    <a href="auditTrail.php" class="sidebar-link">
        <i class="bi bi-shield-lock"></i> Audit Trail
    </a>
    <a href="accountManagement.php" class="sidebar-link active">
        <i class="bi bi-person-circle"></i> Account Management
    </a>

    <div class="logout-section">
         <a href="../logout.php" class="sidebar-link text-danger" onclick="return confirm('Are you sure you want to log out?')">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<!-- MAIN CONTENT -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h2><i class="bi bi-person-circle me-2"></i>Account Management</h2>
            <p>Manage staff accounts, roles, and account status.</p>
        </div>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="bi bi-plus-lg me-2"></i>Add Account
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="stats-container">
        <div class="stat-card">
            <h6><i class="bi bi-person-check me-2"></i>Active Accounts</h6>
            <h3><?= $countActive ?></h3>
        </div>
        <div class="stat-card">
            <h6><i class="bi bi-archive me-2"></i>Archived Accounts</h6>
            <h3><?= $countArchived ?></h3>
        </div>
    </div>

    <div class="content-card">
        <div class="tabs">
            <a href="?tab=active" class="<?= $tab === 'active' ? 'active' : '' ?>">
                <i class="bi bi-people me-1"></i>Active Accounts (<?= $countActive ?>)
            </a>
            <a href="?tab=archived" class="<?= $tab === 'archived' ? 'active' : '' ?>">
                <i class="bi bi-archive me-1"></i>Archived (<?= $countArchived ?>)
            </a>
        </div>

        <div class="table-toolbar">
            <div class="search-box">
                <input type="text" id="searchInput" class="form-control"
                       placeholder="Search username, email, or role..."
                       value="<?= e($search) ?>" autocomplete="off">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Reset Password</th><th>Action</th>
                    </tr>
                </thead>
                <tbody id="accountTableBody">
                <?php if (count($accounts) > 0): ?>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><?= (int)$account['id'] ?></td>
                            <td><strong><?= e($account['username']) ?></strong></td>
                            <td><?= e($account['email'] ?? '') ?></td>
                            <td><span class="role-badge"><?= e($account['role']) ?></span></td>
                            <td>
                                <?php if ($account['status'] === 'Active'): ?>
                                    <span class="status-badge status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-archived">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-warning-custom btn-sm fw-bold"
                                        data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                        data-id="<?= (int)$account['id'] ?>"
                                        data-username="<?= e($account['username']) ?>">
                                    <i class="bi bi-key me-1"></i>Reset Password
                                </button>
                            </td>
                            <td>
                                <?php if ($tab === 'active'): ?>
                                    <?php if ($currentOwnerId <= 0 || (int)$account['id'] !== $currentOwnerId): ?>
                                        <button type="button" class="btn btn-archive"
                                                data-bs-toggle="modal" data-bs-target="#archiveModal"
                                                data-id="<?= (int)$account['id'] ?>"
                                                data-username="<?= e($account['username']) ?>">
                                            <i class="bi bi-archive me-1"></i>Archive
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">Current Account</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="button" class="btn btn-unarchive"
                                            data-bs-toggle="modal" data-bs-target="#unarchiveModal"
                                            data-id="<?= (int)$account['id'] ?>"
                                            data-username="<?= e($account['username']) ?>">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Unarchive
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h6>No accounts found</h6>
                                <p class="mb-0">There are no <?= e($tab) ?> accounts to display.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header px-4 py-3">
                <h5 class="modal-title fw-bold fs-5"><i class="bi bi-person-plus me-2"></i>Add New Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="add_account">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="e.g. juandc" maxlength="100" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" class="form-control" placeholder="e.g. Juan Dela Cruz" maxlength="100" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                name="phone_number" 
                                class="form-control" 
                                placeholder="09XXXXXXXXX"
                                maxlength="11"
                                minlength="11"
                                pattern="09[0-9]{9}"
                                inputmode="numeric"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="juan@example.com" maxlength="255" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="" disabled selected>Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="cashier">Cashier</option>
                                <option value="owner">Owner</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Default Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="passwordField" class="form-control"
                                       placeholder="At least 8 characters" minlength="8" autocomplete="new-password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info small mt-4 mb-0 d-flex align-items-center">
                        <i class="bi bi-info-circle fs-5 me-2"></i>
                        <div>
                            The password will be securely hashed before being saved into the database.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-top-0">
                    <button type="button" class="btn btn-outline-secondary px-3 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom px-4 fw-bold">
                        <i class="bi bi-check-lg me-1"></i>Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Confirmation Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to reset the password for this account?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Account: <strong id="resetUsername"></strong>
                        <br>
                        <small>The password will be reset to: <strong>password</strong></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold">
                        <i class="bi bi-key me-1"></i>Confirm Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Archive Confirmation Modal -->
<div class="modal fade" id="archiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-archive me-2"></i>Archive Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="archive_account">
                <input type="hidden" name="user_id" id="archiveUserId">
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to archive this account?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Account: <strong id="archiveUsername"></strong>
                        <br>
                        <small>The archived account will be blocked from accessing/logging into the system.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-archive me-1"></i>Confirm Archive
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Unarchive Confirmation Modal -->
<div class="modal fade" id="unarchiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-counterclockwise me-2"></i>Unarchive Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="unarchive_account">
                <input type="hidden" name="user_id" id="unarchiveUserId">
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to restore/unarchive this account?</p>
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle me-2"></i>
                        Account: <strong id="unarchiveUsername"></strong>
                        <br>
                        <small>This account will return to active status and will be allowed to log in again.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Confirm Unarchive
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
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

    // Reset Password Modal Filler
    const resetPasswordModal = document.getElementById('resetPasswordModal');
    if (resetPasswordModal) {
        resetPasswordModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('resetUserId').value = button.getAttribute('data-id') || '';
            document.getElementById('resetUsername').textContent = button.getAttribute('data-username') || '';
        });
    }

    // Archive Modal Filler
    const archiveModal = document.getElementById('archiveModal');
    if (archiveModal) {
        archiveModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('archiveUserId').value = button.getAttribute('data-id') || '';
            document.getElementById('archiveUsername').textContent = button.getAttribute('data-username') || '';
        });
    }

    // Unarchive Modal Filler
    const unarchiveModal = document.getElementById('unarchiveModal');
    if (unarchiveModal) {
        unarchiveModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('unarchiveUserId').value = button.getAttribute('data-id') || '';
            document.getElementById('unarchiveUsername').textContent = button.getAttribute('data-username') || '';
        });
    }

    // LIVE SEARCH IMPLEMENTATION
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('accountTableBody');
    const currentTab = "<?= $tab ?>";
    let debounceTimer;

    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            debounceTimer = setTimeout(() => {
                fetch(`accountManagement.php?ajax=1&tab=${currentTab}&search=${encodeURIComponent(query)}`)
                    .then(response => response.text())
                    .then(html => {
                        tableBody.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Error fetching live search results:', error);
                    });
            }, 150);
        });
    }
});

// Password Hide/Unhide Toggle
const togglePasswordBtn = document.getElementById('togglePasswordBtn');
const passwordField = document.getElementById('passwordField');
const togglePasswordIcon = document.getElementById('togglePasswordIcon');

if (togglePasswordBtn && passwordField && togglePasswordIcon) {
    togglePasswordBtn.addEventListener('click', function () {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);

        togglePasswordIcon.classList.toggle('bi-eye-slash');
        togglePasswordIcon.classList.toggle('bi-eye');
    });
}
</script>
</body>
</html>