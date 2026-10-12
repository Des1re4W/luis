<?php
// Kunin ang pangalan ng kasalukuyang file para sa highlight logic
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Bootstrap Icons (ilagay ito sa header kung wala pa) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    :root {
        --sidebar-width: 250px;
        --primary-dark: #051c3c;
        --accent-blue: #0d6efd;
        --text-gray: #b8c7ce;
    }

    body {
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .sidebar {
        height: 100vh;
        width: var(--sidebar-width);
        position: fixed;
        top: 0;
        left: 0;
        background-color: var(--primary-dark);
        color: white;
        padding-top: 20px;
        z-index: 1000;
    }

    .sidebar-header {
        padding: 0 20px 20px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 10px;
        text-align: center;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu li {
        padding: 2px 15px;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: var(--text-gray);
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.2s;
    }

    .sidebar-menu a i {
        margin-right: 12px;
        font-size: 1.1rem;
    }

    .sidebar-menu a:hover {
        background-color: rgba(255,255,255,0.05);
        color: white;
    }

    .sidebar-menu a.active {
        background-color: var(--accent-blue);
        color: white;
        box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
    }

    /* Gamitin ito sa iyong main pages (sales.php, dashboard.php) */
    .main-wrapper {
        margin-left: var(--sidebar-width);
        padding: 25px;
        min-height: 100vh;
        background-color: #f4f6f9;
    }

    .logout-section {
        position: absolute;
        bottom: 20px;
        width: 100%;
        padding: 0 15px;
    }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <h4 class="m-0">R. TAIPAN</h4>
        <small style="color: #888;">ADMIN PANEL</small>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="sales.php" class="<?php echo ($current_page == 'sales.php') ? 'active' : ''; ?>">
                <i class="bi bi-graph-up-arrow"></i> Sales
            </a>
        </li>
        <li>
            <a href="products.php" class="<?php echo ($current_page == 'products.php') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> Inventory
            </a>
        </li>
        <li>
            <a href="orders.php" class="<?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
                <i class="bi bi-cart-check"></i> Online Orders
            </a>
        </li>
        <li>
            <a href="history.php" class="<?php echo ($current_page == 'history.php') ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i> History
            </a>
        </li>
    </ul>

    <div class="logout-section">
        <a href="../logout.php" class="btn btn-danger w-100" style="text-decoration: none; display: block; text-align: center; padding: 10px; border-radius: 5px; color: white; background: #dc3545;">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>
```

### Paano gamitin sa Sales.php o Dashboard.php:
Upang maayos ang layout, siguraduhin na balot ng `<div class="main-wrapper">` ang iyong content:

```php
<?php include "sidebar.php"; ?>

<div class="main-wrapper">
    <!-- DITO ILAGAY ANG LAHAT NG CARDS, TABLES, ATBP. -->
    <h2>Sales Page</h2>
</div>